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
$gorjetaId  = (int)($payload['gorjeta_id'] ?? 0);

if ($gorjetaId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
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

    // Verify the gorjeta belongs to this employee, this client, and is still pending
    $stmt = $pdo->prepare(
        "SELECT id FROM gorjetas
         WHERE id = ? AND funcionario_id = ? AND client_id = ?
           AND LOWER(COALESCE(status,'')) = 'pendente'
         LIMIT 1"
    );
    $stmt->execute([$gorjetaId, $employeeId, $clientId]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Gorjeta não encontrada ou já processada.']);
        exit;
    }

    $pdo->prepare("UPDATE gorjetas SET status = 'cancelada' WHERE id = ? AND client_id = ?")
        ->execute([$gorjetaId, $clientId]);

    echo json_encode(['success' => true, 'message' => 'Gorjeta cancelada.']);
} catch (Throwable $e) {
    error_log('cancelar_gorjeta erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao cancelar gorjeta.']);
}
