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

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = isset($payload['ids']) && is_array($payload['ids'])
        ? array_values(array_filter(array_map('intval', $payload['ids']), fn($id) => $id > 0))
        : [];
    $lida = (isset($payload['lida']) && $payload['lida'] === 0) ? 0 : 1;

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = [$lida, ...$ids, $employeeId, $clientId];
        $stmt = $pdo->prepare(
            "UPDATE notificacoes SET lida = ?
             WHERE id IN ($placeholders) AND funcionario_id = ? AND client_id = ?"
        );
        $stmt->execute($params);
    } else {
        // sem IDs: marcar todas como lidas (comportamento legado, lida=0 sem IDs não faz sentido)
        $stmt = $pdo->prepare(
            'UPDATE notificacoes SET lida = 1
             WHERE funcionario_id = ? AND client_id = ? AND lida = 0'
        );
        $stmt->execute([$employeeId, $clientId]);
    }

    echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
} catch (PDOException $e) {
    error_log('marcar_lida.php erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false]);
}
