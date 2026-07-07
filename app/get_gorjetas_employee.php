<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

require_once '../config/db_connection.php';
date_default_timezone_set('Europe/Lisbon');

$employeeId = (int)$_SESSION['employee_id'];
$clientId   = (int)($_SESSION['client_id'] ?? 0);
$mes        = isset($_GET['mes']) ? preg_replace('/[^0-9\-]/', '', $_GET['mes']) : date('Y-m');
$status     = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$offset     = max(0, (int)($_GET['offset'] ?? 0));
$limit      = 10;

if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');
if (!in_array($status, ['all', 'pendente', 'pago', 'rejeitado'], true)) $status = 'all';

try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'gorjetas'");
    if ($checkTable->rowCount() === 0) {
        echo json_encode(['success' => true, 'gorjetas' => [], 'total' => 0, 'totaisPorPagamento' => []]);
        exit;
    }

    $columns    = $pdo->query("SHOW COLUMNS FROM gorjetas")->fetchAll(PDO::FETCH_COLUMN);
    $dateColumn = in_array('data', $columns) ? 'data' : (in_array('data_registro', $columns) ? 'data_registro' : null);

    if (!$dateColumn) {
        echo json_encode(['success' => true, 'gorjetas' => [], 'total' => 0, 'totaisPorPagamento' => []]);
        exit;
    }

    $whereParts = ["funcionario_id = ?", "client_id = ?", "DATE_FORMAT($dateColumn, '%Y-%m') = ?"];
    $params     = [$employeeId, $clientId, $mes];

    if ($status !== 'all') {
        $whereParts[] = "LOWER(COALESCE(status,'')) = ?";
        $params[]     = $status;
    }

    $where = implode(' AND ', $whereParts);

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM gorjetas WHERE $where");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $stmtG = $pdo->prepare(
        "SELECT * FROM gorjetas WHERE $where ORDER BY $dateColumn DESC, created_at DESC LIMIT ? OFFSET ?"
    );
    $stmtG->execute([...$params, $limit, $offset]);
    $gorjetas = $stmtG->fetchAll(PDO::FETCH_ASSOC);

    // Totais por forma de pagamento (todo o mês, sem filtro de status)
    $stmtT = $pdo->prepare(
        "SELECT COALESCE(NULLIF(TRIM(forma_pagamento),''),'Outro') AS fp, SUM(valor) AS total
         FROM gorjetas WHERE funcionario_id = ? AND client_id = ? AND DATE_FORMAT($dateColumn, '%Y-%m') = ?
         GROUP BY fp"
    );
    $stmtT->execute([$employeeId, $clientId, $mes]);
    $totaisPorPagamento = [];
    foreach ($stmtT->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $totaisPorPagamento[$r['fp']] = (float)$r['total'];
    }

    // Total monetário do mês (todos os registos aprovados/pagos + pendentes)
    $stmtSum = $pdo->prepare(
        "SELECT COALESCE(SUM(valor),0) FROM gorjetas
         WHERE funcionario_id = ? AND client_id = ? AND DATE_FORMAT($dateColumn,'%Y-%m') = ?"
    );
    $stmtSum->execute([$employeeId, $clientId, $mes]);
    $totalValor = (float)$stmtSum->fetchColumn();

    $hash = md5(json_encode($gorjetas) . json_encode($totaisPorPagamento));

    echo json_encode([
        'success'            => true,
        'gorjetas'           => $gorjetas,
        'total'              => $total,
        'totalValor'         => $totalValor,
        'totaisPorPagamento' => $totaisPorPagamento,
        'mes'                => $mes,
        'offset'             => $offset,
        'hash'               => $hash,
    ]);
} catch (PDOException $e) {
    error_log('get_gorjetas_employee erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor.']);
}
