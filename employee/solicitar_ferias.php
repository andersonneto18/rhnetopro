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
date_default_timezone_set('Europe/Lisbon');

$employeeId = (int)$_SESSION['employee_id'];
$clientIdSession = (int)($_SESSION['client_id'] ?? 0);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$dataInicio = trim((string)($data['data_inicio'] ?? ''));
$dataFim = trim((string)($data['data_fim'] ?? ''));
$motivo = trim((string)($data['motivo'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Datas inválidas.']);
    exit;
}

if ($dataInicio > $dataFim) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A data de término deve ser maior ou igual à data de início.']);
    exit;
}

if (mb_strlen($motivo) > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'O motivo deve ter no máximo 500 caracteres.']);
    exit;
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ferias (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        funcionario_id INT NOT NULL,
        data_inicio DATE NOT NULL,
        data_fim DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pendente',
        motivo TEXT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_ferias_client_employee (client_id, funcionario_id),
        KEY idx_ferias_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmtEmployee = $pdo->prepare('SELECT id, client_id, vacation_days, position FROM employees WHERE id = ? LIMIT 1');
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

    $feriasCols = $pdo->query('SHOW COLUMNS FROM ferias')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $feriasCols = array_map(static fn($c) => mb_strtolower((string)$c), $feriasCols);

    $employeeCol = in_array('funcionario_id', $feriasCols, true) ? 'funcionario_id' : (in_array('employee_id', $feriasCols, true) ? 'employee_id' : 'funcionario_id');
    $hasClientCol = in_array('client_id', $feriasCols, true);

    // Validação de saldo: não permite pedir mais dias do que o saldo anual disponível.
    $anoPedido = substr($dataInicio, 0, 4);
    $diasPedidos = (int)((strtotime($dataFim) - strtotime($dataInicio)) / 86400) + 1;
    $saldoTotal = max(0, (int)($employeeRow['vacation_days'] ?? 22));

    $usadosSql = "SELECT COALESCE(SUM(DATEDIFF(LEAST(data_fim, '$anoPedido-12-31'), GREATEST(data_inicio, '$anoPedido-01-01')) + 1), 0) AS total
        FROM ferias
        WHERE {$employeeCol} = ?
          AND LOWER(COALESCE(status, '')) IN ('aprovada', 'aprovado')
          AND data_fim >= '$anoPedido-01-01' AND data_inicio <= '$anoPedido-12-31'";
    $usadosParams = [$employeeId];
    if ($hasClientCol) {
        $usadosSql .= ' AND client_id = ?';
        $usadosParams[] = $clientId;
    }
    $stmtUsados = $pdo->prepare($usadosSql);
    $stmtUsados->execute($usadosParams);
    $diasUsados = (int)$stmtUsados->fetchColumn();
    $diasDisponiveis = max(0, $saldoTotal - $diasUsados);

    if ($diasPedidos > $diasDisponiveis) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Saldo insuficiente: pediu $diasPedidos dia(s) mas tem apenas $diasDisponiveis disponível(eis) em $anoPedido."]);
        exit;
    }

        $overlapSql = "SELECT id
                FROM ferias
                WHERE {$employeeCol} = ?
                    AND COALESCE(LOWER(TRIM(status)), 'pendente') IN ('pendente', 'pending', 'aprovada', 'aprovado')
                    AND data_inicio <= ?
                    AND data_fim >= ?";
        $overlapParams = [$employeeId, $dataFim, $dataInicio];
        if ($hasClientCol) {
                $overlapSql .= " AND client_id = ?";
                $overlapParams[] = $clientId;
        }
        $overlapSql .= " LIMIT 1";

        $stmtOverlap = $pdo->prepare($overlapSql);
        $stmtOverlap->execute($overlapParams);

    if ($stmtOverlap->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Já existe um pedido de férias para esse período.']);
        exit;
    }

    $insertCols = [$employeeCol, 'data_inicio', 'data_fim', 'status', 'motivo'];
    $insertVals = ['?', '?', '?', "'pendente'", '?'];
    $insertParams = [$employeeId, $dataInicio, $dataFim, $motivo !== '' ? $motivo : null];

    if ($hasClientCol) {
        array_unshift($insertCols, 'client_id');
        array_unshift($insertVals, '?');
        array_unshift($insertParams, $clientId);
    }

    $stmtInsert = $pdo->prepare('INSERT INTO ferias (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')');
    $stmtInsert->execute($insertParams);

    // Aviso de conflito de equipa (informativo, não bloqueia o pedido): colegas da mesma
    // função com férias aprovadas/pendentes a sobrepor-se a este período.
    $avisoConflito = null;
    $position = trim((string)($employeeRow['position'] ?? ''));
    if ($position !== '') {
        $conflitoSql = "SELECT COUNT(*) FROM ferias f
            INNER JOIN employees e ON e.id = f.{$employeeCol}
            WHERE e.client_id = ? AND e.position = ? AND f.{$employeeCol} != ?
              AND LOWER(COALESCE(f.status, 'pendente')) IN ('pendente', 'pending', 'aprovada', 'aprovado')
              AND f.data_inicio <= ? AND f.data_fim >= ?";
        $stmtConflito = $pdo->prepare($conflitoSql);
        $stmtConflito->execute([$clientId, $position, $employeeId, $dataFim, $dataInicio]);
        $nConflito = (int)$stmtConflito->fetchColumn();
        if ($nConflito > 0) {
            $avisoConflito = "Atenção: $nConflito colega(s) da função \"$position\" já tem(êm) férias aprovadas ou pendentes neste período.";
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Pedido de férias enviado para aprovação do administrador.',
        'aviso_conflito' => $avisoConflito,
        'request' => [
            'id' => (int)$pdo->lastInsertId(),
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'status' => 'pendente',
            'motivo' => $motivo,
        ]
    ]);
} catch (Throwable $e) {
    error_log('Erro ao solicitar férias: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao enviar pedido de férias.']);
}
