<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

load_env_fallback(dirname(__DIR__) . '/.env');

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

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/planos/create-checkout-session.php')), '/');
$baseUrl = $scheme . '://' . $host . $scriptDir;

$resolvedPriceId = getenv('STRIPE_PRICE_ID');
if (!$resolvedPriceId && isset($_ENV['STRIPE_PRICE_ID'])) {
    $resolvedPriceId = $_ENV['STRIPE_PRICE_ID'];
}
if (!$resolvedPriceId && isset($_SERVER['STRIPE_PRICE_ID'])) {
    $resolvedPriceId = $_SERVER['STRIPE_PRICE_ID'];
}

if (!$resolvedPriceId) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Variavel de ambiente STRIPE_PRICE_ID nao definida.',
    ]);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$clientId = (int)($_SESSION['client_id'] ?? 0);

try {
    $stripeClass = '\\Stripe\\Stripe';
    $checkoutSessionClass = '\\Stripe\\Checkout\\Session';

    $stripeClass::setApiKey($secretKey);

    $session = $checkoutSessionClass::create([
        'mode' => 'subscription',
        'client_reference_id' => $userId > 0 ? (string)$userId : null,
        'metadata' => [
            'user_id' => $userId > 0 ? (string)$userId : '',
            'client_id' => $clientId > 0 ? (string)$clientId : '',
        ],
        'line_items' => [[
            'price' => $resolvedPriceId,
            'quantity' => 1,
        ]],
        'success_url' => $baseUrl . '/sucesso.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $baseUrl . '/cancelado.php',
    ]);

    header('Location: ' . $session->url, true, 303);
    exit();
} catch (\Throwable $e) {
    error_log('create-checkout-session erro: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao criar sessão de pagamento. Tente novamente.',
    ]);
    exit();
}
