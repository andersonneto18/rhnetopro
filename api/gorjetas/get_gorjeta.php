<?php
session_start();
require_once '../../config/db_connection.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

try {
    // Busca gorjeta apenas do cliente logado
    $stmt = $pdo->prepare("
        SELECT g.*, e.name as funcionario_nome
        FROM gorjetas g
        INNER JOIN employees e ON g.funcionario_id = e.id
        WHERE g.id = ? AND g.client_id = ?
        LIMIT 1
    ");
    
    $stmt->execute([$id, $_SESSION['client_id']]);
    $gorjeta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$gorjeta) {
        echo json_encode(['success' => false, 'message' => 'Gorjeta não encontrada']);
        exit;
    }
    
    echo json_encode($gorjeta);
    
} catch (Exception $e) {
    error_log('Erro get_gorjeta.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar gorjeta']);
}

