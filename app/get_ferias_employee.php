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

    $stmtVac = $pdo->prepare('SELECT vacation_days FROM employees WHERE id = ? LIMIT 1');
    $stmtVac->execute([$employeeId]);
    $vacationDays = max(0, (int)($stmtVac->fetchColumn() ?: 22));

    $checkTable = $pdo->query("SHOW TABLES LIKE 'ferias'");
    if ($checkTable->rowCount() === 0) {
        echo json_encode(['success' => true, 'ferias' => [], 'hash' => '', 'diasUsados' => 0, 'vacationDays' => $vacationDays, 'diasDisponiveis' => $vacationDays]);
        exit;
    }

    $cols = $pdo->query("SHOW COLUMNS FROM ferias")->fetchAll(PDO::FETCH_COLUMN);
    $hasMotivoRej = in_array('motivo_rejeicao', $cols, true);
    $selectExtra  = $hasMotivoRej ? ', motivo_rejeicao' : '';

    $stmt = $pdo->prepare("SELECT id, data_inicio, data_fim, status, motivo$selectExtra, created_at
        FROM ferias WHERE funcionario_id = ? AND client_id = ?
        ORDER BY id DESC LIMIT 10");
    $stmt->execute([$employeeId, $clientId]);
    $ferias = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Dias aprovados no ano corrente
    $ano = date('Y');
    $stmtD = $pdo->prepare(
        "SELECT SUM(DATEDIFF(LEAST(data_fim, '$ano-12-31'), GREATEST(data_inicio, '$ano-01-01')) + 1) AS total
         FROM ferias
         WHERE funcionario_id = ? AND client_id = ?
           AND LOWER(COALESCE(status,'')) IN ('aprovada','aprovado')
           AND data_fim >= '$ano-01-01' AND data_inicio <= '$ano-12-31'"
    );
    $stmtD->execute([$employeeId, $clientId]);
    $diasUsados = (int)$stmtD->fetchColumn();
    $diasDisponiveis = max(0, $vacationDays - $diasUsados);

    $hash = md5(json_encode($ferias) . $diasUsados);
    echo json_encode([
        'success' => true,
        'ferias' => $ferias,
        'hash' => $hash,
        'diasUsados' => $diasUsados,
        'vacationDays' => $vacationDays,
        'diasDisponiveis' => $diasDisponiveis,
    ]);
} catch (PDOException $e) {
    error_log('get_ferias_employee erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor.']);
}
