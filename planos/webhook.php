<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido. Use POST.']);
    exit();
}

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Stripe SDK nao encontrada. Execute: composer require stripe/stripe-php',
    ]);
    exit();
}

require_once $autoloadPath;
require_once dirname(__DIR__) . '/config/db_connection.php';

function load_env_fallback(string $envPath): void
{
    if (!file_exists($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0 || strpos($trimmed, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $trimmed, 2));
        if ($key === '') {
            continue;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
        if (!isset($_SERVER[$key])) {
            $_SERVER[$key] = $value;
        }
    }
}

load_env_fallback(dirname(__DIR__) . '/.env');

function env_value(string $name): ?string
{
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
        return (string)$_ENV[$name];
    }
    if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return (string)$_SERVER[$name];
    }
    return null;
}

function has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensure_stripe_columns(PDO $pdo): void
{
    foreach (['usuarios', 'clients'] as $table) {
        if (!has_column($pdo, $table, 'stripe_customer_id')) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN stripe_customer_id VARCHAR(255) NULL");
        }
        if (!has_column($pdo, $table, 'stripe_subscription_id')) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN stripe_subscription_id VARCHAR(255) NULL");
        }
    }
}

function apply_status_for_invoice(PDO $pdo, ?string $customerId, ?string $subscriptionId, string $status): void
{
    $customerId = trim((string)$customerId);
    $subscriptionId = trim((string)$subscriptionId);

    if ($customerId === '' && $subscriptionId === '') {
        return;
    }

    $whereParts = [];
    $params = [];

    if ($customerId !== '') {
        $whereParts[] = 'stripe_customer_id = ?';
        $params[] = $customerId;
    }

    if ($subscriptionId !== '') {
        $whereParts[] = 'stripe_subscription_id = ?';
        $params[] = $subscriptionId;
    }

    $whereSql = implode(' OR ', $whereParts);

    $sqlUsers = "UPDATE usuarios SET subscription_status = ? WHERE {$whereSql}";
    $stmtUsers = $pdo->prepare($sqlUsers);
    $stmtUsers->execute(array_merge([$status], $params));

    $sqlClients = "UPDATE clients SET subscription_status = ? WHERE {$whereSql}";
    $stmtClients = $pdo->prepare($sqlClients);
    $stmtClients->execute(array_merge([$status], $params));
}

function handle_subscription_updated(PDO $pdo, object $subscription): void
{
    $customerId     = trim((string)($subscription->customer ?? ''));
    $subscriptionId = trim((string)($subscription->id ?? ''));
    $stripeStatus   = trim((string)($subscription->status ?? ''));

    if ($customerId === '' && $subscriptionId === '') {
        return;
    }

    $statusMap = [
        'active'            => 'active',
        'trialing'          => 'active',
        'past_due'          => 'blocked',
        'unpaid'            => 'blocked',
        'canceled'          => 'inactive',
        'incomplete'        => 'blocked',
        'incomplete_expired'=> 'inactive',
        'paused'            => 'blocked',
    ];

    $internalStatus = $statusMap[$stripeStatus] ?? 'blocked';
    apply_status_for_invoice($pdo, $customerId, $subscriptionId, $internalStatus);
}

function handle_subscription_deleted(PDO $pdo, object $subscription): void
{
    $customerId     = trim((string)($subscription->customer ?? ''));
    $subscriptionId = trim((string)($subscription->id ?? ''));
    apply_status_for_invoice($pdo, $customerId, $subscriptionId, 'inactive');
}

function handle_checkout_completed(PDO $pdo, object $session): void
{
    $customerId = trim((string)($session->customer ?? ''));
    $subscriptionId = trim((string)($session->subscription ?? ''));

    if ($customerId === '' || $subscriptionId === '') {
        return;
    }

    $metadata = (array)($session->metadata ?? []);
    $userId = (int)($metadata['user_id'] ?? 0);
    $clientId = (int)($metadata['client_id'] ?? 0);

    if ($userId <= 0 && !empty($session->client_reference_id)) {
        $userId = (int)$session->client_reference_id;
    }

    $pdo->beginTransaction();

    try {
        if ($userId > 0) {
            $stmtUser = $pdo->prepare(
                'UPDATE usuarios
                 SET stripe_customer_id = ?, stripe_subscription_id = ?, subscription_status = ?
                 WHERE id = ?'
            );
            $stmtUser->execute([$customerId, $subscriptionId, 'active', $userId]);

            if ($stmtUser->rowCount() > 0) {
                $stmtClientFromUser = $pdo->prepare(
                    'UPDATE clients c
                     INNER JOIN usuarios u ON u.client_id = c.id
                     SET c.stripe_customer_id = ?, c.stripe_subscription_id = ?, c.subscription_status = ?
                     WHERE u.id = ?'
                );
                $stmtClientFromUser->execute([$customerId, $subscriptionId, 'active', $userId]);
                $pdo->commit();
                return;
            }
        }

        if ($clientId > 0) {
            $stmtClient = $pdo->prepare(
                'UPDATE clients
                 SET stripe_customer_id = ?, stripe_subscription_id = ?, subscription_status = ?
                 WHERE id = ?'
            );
            $stmtClient->execute([$customerId, $subscriptionId, 'active', $clientId]);

            if ($stmtClient->rowCount() > 0) {
                $stmtUsers = $pdo->prepare(
                    'UPDATE usuarios
                     SET stripe_customer_id = ?, stripe_subscription_id = ?, subscription_status = ?
                     WHERE client_id = ?'
                );
                $stmtUsers->execute([$customerId, $subscriptionId, 'active', $clientId]);
                $pdo->commit();
                return;
            }
        }

        $email = trim((string)($session->customer_details->email ?? ''));
        if ($email !== '') {
            $stmtByEmail = $pdo->prepare(
                'UPDATE usuarios
                 SET stripe_customer_id = ?, stripe_subscription_id = ?, subscription_status = ?
                 WHERE email = ?'
            );
            $stmtByEmail->execute([$customerId, $subscriptionId, 'active', $email]);

            if ($stmtByEmail->rowCount() > 0) {
                $stmtClientsByEmail = $pdo->prepare(
                    'UPDATE clients c
                     INNER JOIN usuarios u ON u.client_id = c.id
                     SET c.stripe_customer_id = ?, c.stripe_subscription_id = ?, c.subscription_status = ?
                     WHERE u.email = ?'
                );
                $stmtClientsByEmail->execute([$customerId, $subscriptionId, 'active', $email]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$webhookSecret = env_value('STRIPE_WEBHOOK_SECRET');
if ($webhookSecret === null) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Variavel de ambiente STRIPE_WEBHOOK_SECRET nao definida.',
    ]);
    exit();
}

$payload = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($signature === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Assinatura Stripe ausente.']);
    exit();
}

$webhookClass = '\\Stripe\\Webhook';

try {
    ensure_stripe_columns($pdo);

    $event = $webhookClass::constructEvent($payload, $signature, $webhookSecret);
    $eventType = (string)($event->type ?? '');
    $eventObject = $event->data->object ?? null;

    if ($eventType === 'checkout.session.completed' && is_object($eventObject)) {
        handle_checkout_completed($pdo, $eventObject);
    } elseif ($eventType === 'invoice.paid' && is_object($eventObject)) {
        apply_status_for_invoice(
            $pdo,
            (string)($eventObject->customer ?? ''),
            (string)($eventObject->subscription ?? ''),
            'active'
        );
    } elseif ($eventType === 'invoice.payment_failed' && is_object($eventObject)) {
        apply_status_for_invoice(
            $pdo,
            (string)($eventObject->customer ?? ''),
            (string)($eventObject->subscription ?? ''),
            'blocked'
        );
    } elseif ($eventType === 'customer.subscription.updated' && is_object($eventObject)) {
        handle_subscription_updated($pdo, $eventObject);
    } elseif ($eventType === 'customer.subscription.deleted' && is_object($eventObject)) {
        handle_subscription_deleted($pdo, $eventObject);
    }

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['received' => true]);
    exit();
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Payload invalido.']);
    exit();
} catch (Throwable $e) {
    if (stripos(get_class($e), 'SignatureVerificationException') !== false) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Assinatura invalida.']);
        exit();
    }

    error_log('Erro no webhook Stripe: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Erro interno no webhook.']);
    exit();
}
