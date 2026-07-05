<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

require_once '../config/db_connection.php';

$employeeId = (int)$_SESSION['employee_id'];
$clientId   = (int)($_SESSION['client_id'] ?? 0);
$payload    = json_decode(file_get_contents('php://input'), true);

$currentPin = trim($payload['current_pin'] ?? '');
$newPin     = trim($payload['new_pin'] ?? '');
$confirmPin = trim($payload['confirm_pin'] ?? '');

if ($currentPin === '' || $newPin === '' || $confirmPin === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
    exit;
}
if (strlen($newPin) < 4) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'O novo PIN deve ter pelo menos 4 caracteres.']);
    exit;
}
if ($newPin !== $confirmPin) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Os PINs não coincidem.']);
    exit;
}

try {
    if ($clientId <= 0) {
        $s = $pdo->prepare('SELECT client_id FROM employees WHERE id = ? LIMIT 1');
        $s->execute([$employeeId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        $clientId = (int)($r['client_id'] ?? 0);
        if ($clientId > 0) $_SESSION['client_id'] = $clientId;
    }

    $stmt = $pdo->prepare('SELECT pin_hash, pin FROM employees WHERE id = ? AND client_id = ? LIMIT 1');
    $stmt->execute([$employeeId, $clientId]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado.']);
        exit;
    }

    // Verify current PIN against hash (or legacy plain pin column)
    $valid = false;
    if (!empty($emp['pin_hash'])) {
        $valid = password_verify($currentPin, $emp['pin_hash']);
    } elseif (!empty($emp['pin'])) {
        $valid = ($currentPin === (string)$emp['pin']);
    }

    if (!$valid) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'PIN atual incorreto.']);
        exit;
    }

    $newHash = password_hash($newPin, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE employees SET pin_hash = ?, pin = NULL WHERE id = ? AND client_id = ?')
        ->execute([$newHash, $employeeId, $clientId]);

    echo json_encode(['success' => true, 'message' => 'PIN alterado com sucesso.']);
} catch (Throwable $e) {
    error_log('change_pin erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao alterar PIN.']);
}
