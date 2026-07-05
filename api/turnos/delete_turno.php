<?php
session_start(); // Adiciona início da sessão
require_once '../../config/db_connection.php';
header('Content-Type: application/json; charset=utf-8');

// Verifica se usuário está logado
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

try {
    // delete apenas se o turno pertence a um funcionário do client_id atual
    $stmt = $pdo->prepare("
        DELETE t FROM turnos t
        INNER JOIN employees e ON t.funcionario_id = e.id
        WHERE t.id = ? AND e.client_id = ?
    ");
    $ok = $stmt->execute([$id, $_SESSION['client_id']]);

    if ($ok && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Turno excluído com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Turno não encontrado ou não pertence ao cliente']);
    }
} catch (Exception $e) {
    error_log('delete_turno erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir turno.']);
}
