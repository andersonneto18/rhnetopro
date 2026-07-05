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
    if ($clientId <= 0) {
        $s = $pdo->prepare('SELECT client_id FROM employees WHERE id = ? LIMIT 1');
        $s->execute([$employeeId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        $clientId = (int)($r['client_id'] ?? 0);
        if ($clientId > 0) $_SESSION['client_id'] = $clientId;
    }

    $checkTable = $pdo->query("SHOW TABLES LIKE 'turno_swap_requests'");
    if ($checkTable->rowCount() === 0) {
        echo json_encode(['success' => true, 'incoming' => [], 'outgoing' => [], 'hash' => '']);
        exit;
    }

    // Pedidos recebidos: sou o target, status pendente_colega
    $stmtIn = $pdo->prepare(
        "SELECT r.id, r.status, r.requested_date, r.reason,
                er.name AS requester_name,
                rt.turno_tipo AS requester_turno_tipo,
                rt.horario_inicio AS requester_horario_inicio,
                rt.horario_fim AS requester_horario_fim,
                tt.turno_tipo AS target_turno_tipo,
                tt.horario_inicio AS target_horario_inicio,
                tt.horario_fim AS target_horario_fim
         FROM turno_swap_requests r
         INNER JOIN employees er ON er.id = r.requester_employee_id
         INNER JOIN turnos rt ON rt.id = r.requester_turno_id
         INNER JOIN turnos tt ON tt.id = r.target_turno_id
         WHERE r.client_id = ? AND r.target_employee_id = ? AND r.status = 'pendente_colega'
         ORDER BY r.requested_at DESC"
    );
    $stmtIn->execute([$clientId, $employeeId]);
    $incoming = $stmtIn->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Pedidos enviados: sou o requester, pendentes ou dos últimos 30 dias
    $stmtOut = $pdo->prepare(
        "SELECT r.id, r.status, r.requested_date, r.reason, r.review_note,
                et.name AS target_name,
                rt.turno_tipo AS requester_turno_tipo,
                rt.horario_inicio AS requester_horario_inicio,
                rt.horario_fim AS requester_horario_fim,
                tt.turno_tipo AS target_turno_tipo,
                tt.horario_inicio AS target_horario_inicio,
                tt.horario_fim AS target_horario_fim
         FROM turno_swap_requests r
         INNER JOIN employees et ON et.id = r.target_employee_id
         INNER JOIN turnos rt ON rt.id = r.requester_turno_id
         INNER JOIN turnos tt ON tt.id = r.target_turno_id
         WHERE r.client_id = ? AND r.requester_employee_id = ?
           AND (r.status IN ('pendente_colega','pendente_admin')
                OR r.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
         ORDER BY r.updated_at DESC LIMIT 20"
    );
    $stmtOut->execute([$clientId, $employeeId]);
    $outgoing = $stmtOut->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $hash = md5(json_encode(['in' => $incoming, 'out' => $outgoing]));

    echo json_encode([
        'success'  => true,
        'incoming' => $incoming,
        'outgoing' => $outgoing,
        'hash'     => $hash,
    ]);
} catch (PDOException $e) {
    error_log('get_trocas_turno erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor.']);
}
