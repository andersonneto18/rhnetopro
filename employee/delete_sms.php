<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessao invalida.']);
    exit;
}

require_once '../config/db_connection.php';

$employeeId = (int) $_SESSION['employee_id'];
$clientId = isset($_SESSION['client_id']) ? (int) $_SESSION['client_id'] : 0;

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !isset($payload['ids']) || !is_array($payload['ids'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados invalidos.']);
    exit;
}

$ids = array_values(array_filter(array_map('intval', $payload['ids']), function ($id) {
    return $id > 0;
}));

if (count($ids) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhuma SMS selecionada.']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "DELETE FROM notificacoes WHERE id IN ($placeholders) AND funcionario_id = ?";
    $params = $ids;
    $params[] = $employeeId;

    if ($clientId > 0) {
        $sql .= " AND client_id = ?";
        $params[] = $clientId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $deleted = (int) $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => $deleted > 0
            ? "${deleted} SMS eliminada(s) com sucesso."
            : 'Nenhuma SMS foi eliminada.'
    ]);
} catch (PDOException $e) {
    error_log('employee/delete_sms.php erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao eliminar SMS.']);
}
