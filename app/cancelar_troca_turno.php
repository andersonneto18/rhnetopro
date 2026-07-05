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

$payload   = json_decode(file_get_contents('php://input'), true);
$requestId = (int)($payload['request_id'] ?? 0);

if ($requestId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

try {
    if ($clientId <= 0) {
        $s = $pdo->prepare('SELECT client_id FROM employees WHERE id = ? LIMIT 1');
        $s->execute([$employeeId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        $clientId = (int)($row['client_id'] ?? 0);
        if ($clientId > 0) $_SESSION['client_id'] = $clientId;
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM turno_swap_requests
         WHERE id = ? AND client_id = ? AND requester_employee_id = ? AND status = 'pendente_colega'
         LIMIT 1"
    );
    $stmt->execute([$requestId, $clientId, $employeeId]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado ou não pode ser cancelado.']);
        exit;
    }

    $pdo->prepare(
        "UPDATE turno_swap_requests SET status = 'cancelada', updated_at = NOW()
         WHERE id = ? AND client_id = ?"
    )->execute([$requestId, $clientId]);

    echo json_encode(['success' => true, 'message' => 'Pedido cancelado com sucesso.']);
} catch (Throwable $e) {
    error_log('cancelar_troca_turno erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao cancelar pedido.']);
}
