<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

require_once '../config/db_connection.php';

$employeeId = (int)$_SESSION['employee_id'];
$clientId   = (int)($_SESSION['client_id'] ?? 0);

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$id = isset($payload['id']) ? (int)$payload['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

try {
    // Auto-migration: adicionar colunas se não existirem ainda
    $colCheck = $pdo->query("SHOW COLUMNS FROM notificacoes LIKE 'requer_confirmacao'");
    if ($colCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE notificacoes ADD COLUMN requer_confirmacao TINYINT(1) NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE notificacoes ADD COLUMN confirmado_em DATETIME DEFAULT NULL");
    }

    $stmt = $pdo->prepare(
        "UPDATE notificacoes SET confirmado_em = NOW()
         WHERE id = ? AND funcionario_id = ? AND client_id = ?
           AND requer_confirmacao = 1 AND confirmado_em IS NULL"
    );
    $stmt->execute([$id, $employeeId, $clientId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Mensagem não encontrada ou já confirmada.']);
    } else {
        echo json_encode(['success' => true]);
    }
} catch (PDOException $e) {
    error_log('confirmar_recepcao.php erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor.']);
}
