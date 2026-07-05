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

$requesterTurnoId = (int)($payload['requester_turno_id'] ?? 0);
$targetTurnoId = (int)($payload['target_turno_id'] ?? 0);
$requestedDate = trim((string)($payload['requested_date'] ?? ''));
$reason = trim((string)($payload['reason'] ?? ''));

if ($requesterTurnoId <= 0 || $targetTurnoId <= 0 || $requesterTurnoId === $targetTurnoId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Selecione turnos válidos e diferentes.']);
    exit;
}

if ($requestedDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Data inválida.']);
    exit;
}

if (mb_strlen($reason) > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Motivo deve ter no máximo 500 caracteres.']);
    exit;
}

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS turno_swap_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            requester_employee_id INT NOT NULL,
            target_employee_id INT NOT NULL,
            requester_turno_id INT NOT NULL,
            target_turno_id INT NOT NULL,
            requested_date DATE NULL,
            reason TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendente_colega',
            review_note TEXT NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            requested_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_turno_swap_client_status (client_id, status),
            KEY idx_turno_swap_requester (requester_employee_id),
            KEY idx_turno_swap_target (target_employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

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

    $stmtTurnos = $pdo->prepare(
        "SELECT t.id, t.funcionario_id
         FROM turnos t
         INNER JOIN employees e ON e.id = t.funcionario_id
         WHERE t.id IN (?, ?) AND e.client_id = ?"
    );
    $stmtTurnos->execute([$requesterTurnoId, $targetTurnoId, $clientId]);
    $turnosRows = $stmtTurnos->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (count($turnosRows) !== 2) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Turnos não encontrados para esta empresa.']);
        exit;
    }

    $byId = [];
    foreach ($turnosRows as $row) {
        $byId[(int)$row['id']] = (int)$row['funcionario_id'];
    }

    $requesterEmployeeId = (int)($byId[$requesterTurnoId] ?? 0);
    $targetEmployeeId = (int)($byId[$targetTurnoId] ?? 0);

    if ($requesterEmployeeId !== $employeeId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Você só pode solicitar troca usando um dos seus turnos.']);
        exit;
    }

    if ($targetEmployeeId <= 0 || $targetEmployeeId === $requesterEmployeeId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Escolha um turno válido de outro colega.']);
        exit;
    }

    $stmtDup = $pdo->prepare(
        "SELECT id
         FROM turno_swap_requests
         WHERE client_id = ?
           AND status IN ('pendente_colega', 'pendente_admin', 'pendente')
           AND ((requester_turno_id = ? AND target_turno_id = ?) OR (requester_turno_id = ? AND target_turno_id = ?))
         LIMIT 1"
    );
    $stmtDup->execute([$clientId, $requesterTurnoId, $targetTurnoId, $targetTurnoId, $requesterTurnoId]);

    if ($stmtDup->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Já existe solicitação pendente para essa troca.']);
        exit;
    }

    $stmtInsert = $pdo->prepare(
        'INSERT INTO turno_swap_requests (client_id, requester_employee_id, target_employee_id, requester_turno_id, target_turno_id, requested_date, reason, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmtInsert->execute([
        $clientId,
        $requesterEmployeeId,
        $targetEmployeeId,
        $requesterTurnoId,
        $targetTurnoId,
        $requestedDate !== '' ? $requestedDate : null,
        $reason !== '' ? $reason : null,
        'pendente_colega'
    ]);

    // Notificar o colega-alvo que recebeu um pedido de troca
    try {
        $notifCheck = $pdo->query("SHOW TABLES LIKE 'notificacoes'");
        if ($notifCheck->rowCount() > 0) {
            $stmtInfo = $pdo->prepare(
                "SELECT e.name AS req_name, t.turno_tipo
                 FROM employees e
                 INNER JOIN turnos t ON t.id = ?
                 WHERE e.id = ? LIMIT 1"
            );
            $stmtInfo->execute([$requesterTurnoId, $requesterEmployeeId]);
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            $reqName = $info['req_name'] ?? 'Um colega';
            $reqTipo = $info['turno_tipo'] ?? 'turno';
            $pdo->prepare(
                "INSERT INTO notificacoes (funcionario_id, client_id, mensagem, data_envio, lida) VALUES (?, ?, ?, NOW(), 0)"
            )->execute([
                $targetEmployeeId,
                $clientId,
                "$reqName pediu troca do seu turno ($reqTipo). Aceda à secção Turnos para aceitar ou rejeitar."
            ]);
        }
    } catch (Throwable $eNotif) {
        error_log('solicitar_troca notif erro: ' . $eNotif->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Pedido enviado. O colega precisa aceitar antes de seguir para o administrador.',
        'request' => [
            'id' => (int)$pdo->lastInsertId(),
            'status' => 'pendente_colega'
        ]
    ]);
} catch (Throwable $e) {
    error_log('Erro ao solicitar troca de turno (app): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao solicitar troca de turno.']);
}
