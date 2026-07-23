<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db_connection.php';
date_default_timezone_set('Europe/Lisbon');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

if (!isset($_SESSION['client_id'])) {
    try {
        $stmtClient = $pdo->prepare("SELECT client_id FROM usuarios WHERE id = ? LIMIT 1");
        $stmtClient->execute([(int)$_SESSION['user_id']]);
        $userRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
        if (!empty($userRow['client_id'])) {
            $_SESSION['client_id'] = (int)$userRow['client_id'];
        }
    } catch (Throwable $e) {
        error_log('bulk_set_ferias client_id lookup warning: ' . $e->getMessage());
    }
}

if (!isset($_SESSION['client_id']) || (int)$_SESSION['client_id'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sessão inválida: client_id ausente']);
    exit;
}

$clientId = (int)$_SESSION['client_id'];
$employeeId = (int)($_POST['employee_id'] ?? 0);
$dataInicio = trim((string)($_POST['data_inicio'] ?? ''));
$dataFim = trim((string)($_POST['data_fim'] ?? ''));
$motivo = trim((string)($_POST['motivo'] ?? ''));

if ($employeeId <= 0
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)
    || $dataInicio > $dataFim
) {
    echo json_encode(['success' => false, 'message' => 'Datas de férias inválidas']);
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

    $stmtEmp = $pdo->prepare('SELECT id, vacation_days FROM employees WHERE id = ? AND client_id = ? LIMIT 1');
    $stmtEmp->execute([$employeeId, $clientId]);
    $employeeRow = $stmtEmp->fetch(PDO::FETCH_ASSOC);
    if (!$employeeRow) {
        echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado']);
        exit;
    }

    $feriasCols = array_map('strtolower', $pdo->query('SHOW COLUMNS FROM ferias')->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $feriasEmployeeCol = in_array('funcionario_id', $feriasCols, true)
        ? 'funcionario_id'
        : (in_array('employee_id', $feriasCols, true) ? 'employee_id' : 'funcionario_id');
    $feriasHasClientCol = in_array('client_id', $feriasCols, true);
    $feriasHasMotivoCol = in_array('motivo', $feriasCols, true);

    // Saldo: dias pedidos não podem exceder o saldo anual disponível do funcionário.
    $ano = substr($dataInicio, 0, 4);
    $diasPedidos = (int)((strtotime($dataFim) - strtotime($dataInicio)) / 86400) + 1;
    $saldoTotal = max(0, (int)($employeeRow['vacation_days'] ?? 22));

    $usadosSql = "SELECT COALESCE(SUM(DATEDIFF(LEAST(data_fim, '$ano-12-31'), GREATEST(data_inicio, '$ano-01-01')) + 1), 0) AS total
        FROM ferias
        WHERE {$feriasEmployeeCol} = ?
          AND LOWER(COALESCE(status, '')) IN ('aprovada', 'aprovado')
          AND data_fim >= '$ano-01-01' AND data_inicio <= '$ano-12-31'";
    $usadosParams = [$employeeId];
    if ($feriasHasClientCol) {
        $usadosSql .= ' AND client_id = ?';
        $usadosParams[] = $clientId;
    }
    $stmtUsados = $pdo->prepare($usadosSql);
    $stmtUsados->execute($usadosParams);
    $diasUsados = (int)$stmtUsados->fetchColumn();

    if ($diasPedidos > max(0, $saldoTotal - $diasUsados)) {
        echo json_encode(['success' => false, 'message' => 'Saldo de férias insuficiente para o período informado']);
        exit;
    }

    // Evita sobrepor outro período já aprovado do mesmo funcionário.
    $overlapSql = "SELECT f.id
        FROM ferias f
        INNER JOIN employees e ON e.id = f.{$feriasEmployeeCol}
        WHERE f.{$feriasEmployeeCol} = ? AND e.client_id = ?
        AND LOWER(COALESCE(f.status, '')) IN ('aprovada','aprovado')
        AND COALESCE(f.data_inicio, '0000-00-00') <= ?
        AND COALESCE(f.data_fim, '0000-00-00') >= ?";
    $overlapParams = [$employeeId, $clientId, $dataFim, $dataInicio];
    if ($feriasHasClientCol) {
        $overlapSql .= ' AND f.client_id = ?';
        $overlapParams[] = $clientId;
    }
    $overlapSql .= ' LIMIT 1';
    $stmtOverlap = $pdo->prepare($overlapSql);
    $stmtOverlap->execute($overlapParams);
    if ($stmtOverlap->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'Já existe um período de férias aprovado que se sobrepõe a estas datas']);
        exit;
    }

    $insertCols = [$feriasEmployeeCol, 'data_inicio', 'data_fim', 'status'];
    $insertVals = [$employeeId, $dataInicio, $dataFim, 'aprovada'];
    if ($feriasHasMotivoCol) {
        $insertCols[] = 'motivo';
        $insertVals[] = $motivo;
    }
    if ($feriasHasClientCol) {
        $insertCols[] = 'client_id';
        $insertVals[] = $clientId;
    }

    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $stmtInsert = $pdo->prepare('INSERT INTO ferias (' . implode(',', $insertCols) . ') VALUES (' . $placeholders . ')');
    $stmtInsert->execute($insertVals);

    // Só marca o funcionário como "ferias" se o período já estiver em curso hoje.
    $todayIso = date('Y-m-d');
    if ($dataInicio <= $todayIso && $dataFim >= $todayIso) {
        $pdo->prepare('UPDATE employees SET status = ? WHERE id = ? AND client_id = ?')
            ->execute(['ferias', $employeeId, $clientId]);
    }

    echo json_encode(['success' => true, 'message' => 'Férias registadas com sucesso']);
} catch (Throwable $e) {
    error_log('bulk_set_ferias.php erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor. Tente novamente.']);
}
