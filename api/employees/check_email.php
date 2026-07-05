<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$email = trim($_GET['email'] ?? '');
$excludeId = (int)($_GET['exclude_id'] ?? 0);
$clientId = (int)$_SESSION['client_id'];

if ($email === '') {
    echo json_encode(['success' => false, 'message' => 'Email não informado']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => true, 'valid' => false, 'duplicate' => false, 'message' => 'Formato de email inválido']);
    exit;
}

try {
    $sql = 'SELECT COUNT(*) FROM employees WHERE email = ? AND client_id = ?';
    $params = [$email, $clientId];

    if ($excludeId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'valid' => true,
        'duplicate' => $count > 0,
        'message' => $count > 0 ? 'Este email já está cadastrado.' : 'Email disponível.'
    ]);
} catch (Throwable $e) {
    error_log('check_email error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao validar email']);
}
