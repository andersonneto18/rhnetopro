<?php
session_start();
require_once '../../config/db_connection.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

try {
    // Exclui apenas se a gorjeta pertence ao cliente logado
    $stmt = $pdo->prepare("
        DELETE FROM gorjetas 
        WHERE id = ? AND client_id = ?
    ");
    
    $ok = $stmt->execute([$id, $_SESSION['client_id']]);
    
    if ($ok && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Gorjeta excluída com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gorjeta não encontrada ou não pertence ao cliente']);
    }
    
} catch (Exception $e) {
    error_log('delete_gorjeta error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir gorjeta: ' . $e->getMessage()]);
}

