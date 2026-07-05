<?php
session_start();
require_once '../../config/db_connection.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$funcionario_id = isset($_GET['funcionario_id']) ? (int)$_GET['funcionario_id'] : 0;
if (!$funcionario_id) {
    echo json_encode(['turno_id' => null]);
    exit;
}

try {
    // Busca o turno ativo mais recente do funcionário
    $stmt = $pdo->prepare("
        SELECT t.id 
        FROM turnos t
        WHERE t.funcionario_id = ? 
        AND t.status = 'ativo'
        ORDER BY t.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$funcionario_id]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'turno_id' => $turno ? (int)$turno['id'] : null,
        'success' => true
    ]);
} catch (Exception $e) {
    error_log('Erro em get_turno_funcionario.php: ' . $e->getMessage());
    echo json_encode(['turno_id' => null, 'success' => false]);
}
