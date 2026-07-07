<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../admin/views/login.php?error=sessao_invalida');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Metodo nao permitido. Use POST.',
    ]);
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

function load_env_fallback_portal(string $envPath): void
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

        $hasDoubleQuotes = strlen($value) >= 2 && substr($value, 0, 1) === '"' && substr($value, -1) === '"';
        $hasSingleQuotes = strlen($value) >= 2 && substr($value, 0, 1) === "'" && substr($value, -1) === "'";
        if ($hasDoubleQuotes || $hasSingleQuotes) {
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

load_env_fallback_portal(dirname(__DIR__) . '/.env');

$secretKey = getenv('STRIPE_SECRET_KEY');
if (!$secretKey && isset($_ENV['STRIPE_SECRET_KEY'])) {
    $secretKey = $_ENV['STRIPE_SECRET_KEY'];
}
if (!$secretKey && isset($_SERVER['STRIPE_SECRET_KEY'])) {
    $secretKey = $_SERVER['STRIPE_SECRET_KEY'];
}

if (!$secretKey) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Variavel de ambiente STRIPE_SECRET_KEY nao definida.',
    ]);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$clientId = (int)($_SESSION['client_id'] ?? 0);

$stripeCustomerId = '';
$stmt = $pdo->prepare('SELECT stripe_customer_id FROM usuarios WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$stripeCustomerId = trim((string)($stmt->fetchColumn() ?: ''));

if ($stripeCustomerId === '' && $clientId > 0) {
    $stmt = $pdo->prepare('SELECT stripe_customer_id FROM clients WHERE id = ? LIMIT 1');
    $stmt->execute([$clientId]);
    $stripeCustomerId = trim((string)($stmt->fetchColumn() ?: ''));
}

if ($stripeCustomerId === '') {
    header('Location: index.php?sem_assinatura=1');
    exit();
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/planos/create-portal-session.php')), '/');
$projectBase = rtrim(str_replace('\\', '/', dirname($scriptDir)), '/');
$returnUrl = $scheme . '://' . $host . $projectBase . '/admin/dashboard.php?section=definicoes';

try {
    $stripeClass = '\\Stripe\\Stripe';
    $portalSessionClass = '\\Stripe\\BillingPortal\\Session';

    $stripeClass::setApiKey($secretKey);

    $portalSession = $portalSessionClass::create([
        'customer' => $stripeCustomerId,
        'return_url' => $returnUrl,
    ]);

    header('Location: ' . $portalSession->url, true, 303);
    exit();
} catch (\Throwable $e) {
    error_log('create-portal-session erro: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao abrir o portal de gestao da assinatura. Tente novamente.',
    ]);
    exit();
}
