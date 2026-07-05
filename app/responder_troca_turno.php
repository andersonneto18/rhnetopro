<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida. Faça login novamente.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

require_once '../config/db_connection.php';

$employeeId = (int)$_SESSION['employee_id'];
$clientIdSession = (int)($_SESSION['client_id'] ?? 0);

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido.']);
    exit;
}

$requestId = (int)($payload['request_id'] ?? 0);
$decision = mb_strtolower(trim((string)($payload['decision'] ?? '')));

if ($requestId <= 0 || !in_array($decision, ['accept', 'reject'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos para resposta.']);
    exit;
}

try {
    $stmtEmployee = $pdo->prepare('SELECT id, client_id FROM employees WHERE id = ? LIMIT 1');
    $stmtEmployee->execute([$employeeId]);
    $employeeRow = $stmtEmployee->fetch(PDO::FETCH_ASSOC);
    if (!$employeeRow) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Funcionário não autorizado.']);
        exit;
    }

    $clientId = (int)($employeeRow['client_id'] ?? 0);
    if ($clientId <= 0) {
        $clientId = $clientIdSession;
    }
    if ($clientId <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cliente inválido para o funcionário.']);
        exit;
    }

    $_SESSION['client_id'] = $clientId;

    $stmtReq = $pdo->prepare(
        "SELECT id, status, target_employee_id, requester_employee_id, requester_turno_id, target_turno_id
         FROM turno_swap_requests
         WHERE id = ? AND client_id = ?
         LIMIT 1"
    );
    $stmtReq->execute([$requestId, $clientId]);
    $reqRow = $stmtReq->fetch(PDO::FETCH_ASSOC);

    if (!$reqRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Solicitação não encontrada.']);
        exit;
    }

    if ((int)($reqRow['target_employee_id'] ?? 0) !== $employeeId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para responder esta solicitação.']);
        exit;
    }

    $statusAtual = mb_strtolower(trim((string)($reqRow['status'] ?? '')));
    if (!in_array($statusAtual, ['pendente_colega'], true)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Esta solicitação não está mais pendente do colega.']);
        exit;
    }

    $requesterEmployeeId = (int)($reqRow['requester_employee_id'] ?? 0);

    if ($decision === 'accept') {
        $stmtUp = $pdo->prepare('UPDATE turno_swap_requests SET status = ?, updated_at = NOW() WHERE id = ? AND client_id = ?');
        $stmtUp->execute(['pendente_admin', $requestId, $clientId]);

        // Notificar solicitante que o colega aceitou
        try {
            $notifCheck = $pdo->query("SHOW TABLES LIKE 'notificacoes'");
            if ($notifCheck->rowCount() > 0 && $requesterEmployeeId > 0) {
                $stmtInfo = $pdo->prepare(
                    "SELECT e.name AS col_name, t.turno_tipo
                     FROM employees e INNER JOIN turnos t ON t.id = ?
                     WHERE e.id = ? LIMIT 1"
                );
                $stmtInfo->execute([(int)($reqRow['target_turno_id'] ?? 0), $employeeId]);
                $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                $colName = $info['col_name'] ?? 'O colega';
                $colTipo = $info['turno_tipo'] ?? 'turno';
                $pdo->prepare(
                    "INSERT INTO notificacoes (funcionario_id, client_id, mensagem, data_envio, lida) VALUES (?, ?, ?, NOW(), 0)"
                )->execute([
                    $requesterEmployeeId,
                    $clientId,
                    "$colName aceitou a sua proposta de troca ($colTipo). O pedido aguarda aprovação do administrador."
                ]);
            }
        } catch (Throwable $eNotif) {
            error_log('responder_troca accept notif erro: ' . $eNotif->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Aceite registrado. Agora o pedido foi enviado ao administrador para aprovação final.',
            'status' => 'pendente_admin'
        ]);
        exit;
    }

    $stmtUp = $pdo->prepare('UPDATE turno_swap_requests SET status = ?, updated_at = NOW() WHERE id = ? AND client_id = ?');
    $stmtUp->execute(['rejeitada_colega', $requestId, $clientId]);

    // Notificar solicitante que o colega rejeitou
    try {
        $notifCheck = $pdo->query("SHOW TABLES LIKE 'notificacoes'");
        if ($notifCheck->rowCount() > 0 && $requesterEmployeeId > 0) {
            $stmtInfo = $pdo->prepare(
                "SELECT e.name AS col_name, t.turno_tipo
                 FROM employees e INNER JOIN turnos t ON t.id = ?
                 WHERE e.id = ? LIMIT 1"
            );
            $stmtInfo->execute([(int)($reqRow['target_turno_id'] ?? 0), $employeeId]);
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            $colName = $info['col_name'] ?? 'O colega';
            $colTipo = $info['turno_tipo'] ?? 'turno';
            $pdo->prepare(
                "INSERT INTO notificacoes (funcionario_id, client_id, mensagem, data_envio, lida) VALUES (?, ?, ?, NOW(), 0)"
            )->execute([
                $requesterEmployeeId,
                $clientId,
                "$colName recusou a sua proposta de troca ($colTipo). O pedido foi encerrado."
            ]);
        }
    } catch (Throwable $eNotif) {
        error_log('responder_troca reject notif erro: ' . $eNotif->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Solicitação rejeitada. A troca não seguirá para aprovação do administrador.',
        'status' => 'rejeitada_colega'
    ]);
} catch (Throwable $e) {
    error_log('Erro ao responder troca de turno (app): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao responder solicitação de troca.']);
}
