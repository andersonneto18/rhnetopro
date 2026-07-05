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
$detail     = $_GET['detail'] ?? null; // "YYYY-M" for single recibo detail

try {
    if ($clientId <= 0) {
        $s = $pdo->prepare('SELECT client_id FROM employees WHERE id = ? LIMIT 1');
        $s->execute([$employeeId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        $clientId = (int)($r['client_id'] ?? 0);
        if ($clientId > 0) $_SESSION['client_id'] = $clientId;
    }

    // Check tables exist
    $hasFolha = $pdo->query("SHOW TABLES LIKE 'folha_pagamento'")->rowCount() > 0;
    if (!$hasFolha) {
        echo json_encode(['success' => true, 'recibos' => []]);
        exit;
    }

    $hasVarTable = $pdo->query("SHOW TABLES LIKE 'folha_variaveis_mensais'")->rowCount() > 0;

    // Introspect folha_pagamento columns
    $fpCols = $pdo->query("SHOW COLUMNS FROM folha_pagamento")->fetchAll(PDO::FETCH_COLUMN);
    $col = fn(string $c) => in_array($c, $fpCols, true);

    $selectFp = 'fp.employee_id, fp.client_id, fp.fiscal_year, fp.fiscal_month';
    if ($col('salario_base'))   $selectFp .= ', fp.salario_base';
    if ($col('salario_bruto'))  $selectFp .= ', fp.salario_bruto';
    if ($col('salario_liquido'))$selectFp .= ', fp.salario_liquido';
    if ($col('gorjetas'))       $selectFp .= ', fp.gorjetas';
    if ($col('subsidio_alimentacao')) $selectFp .= ', fp.subsidio_alimentacao';
    if ($col('status_pagamento'))     $selectFp .= ', fp.status_pagamento';
    if ($col('data_pagamento'))       $selectFp .= ', fp.data_pagamento';
    if ($col('updated_at'))           $selectFp .= ', fp.updated_at';

    $joinVar = '';
    $selectVar = '';
    if ($hasVarTable) {
        $fvCols = $pdo->query("SHOW COLUMNS FROM folha_variaveis_mensais")->fetchAll(PDO::FETCH_COLUMN);
        $colV = fn(string $c) => in_array($c, $fvCols, true);
        $joinVar = 'LEFT JOIN folha_variaveis_mensais fvm
                    ON fvm.client_id = fp.client_id
                   AND fvm.employee_id = fp.employee_id
                   AND fvm.fiscal_year = fp.fiscal_year
                   AND fvm.fiscal_month = fp.fiscal_month';
        if ($colV('horas_extra'))       $selectVar .= ', COALESCE(fvm.horas_extra, 0) AS horas_extra';
        if ($colV('bonus'))             $selectVar .= ', COALESCE(fvm.bonus, 0) AS bonus';
        if ($colV('subsidios_extra'))   $selectVar .= ', COALESCE(fvm.subsidios_extra, 0) AS subsidios_extra';
        if ($colV('faltas_dias'))       $selectVar .= ', COALESCE(fvm.faltas_dias, 0) AS faltas_dias';
        if ($colV('outros_descontos'))  $selectVar .= ', COALESCE(fvm.outros_descontos, 0) AS outros_descontos';
        if ($colV('gorjeta_manual'))    $selectVar .= ', COALESCE(fvm.gorjeta_manual, 0) AS gorjeta_manual';
    }

    if ($detail) {
        // Single recibo detail: detail = "YYYY-M"
        [$dYear, $dMonth] = array_map('intval', explode('-', $detail . '-0'));
        if ($dYear < 2020 || $dMonth < 1 || $dMonth > 12) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Período inválido.']);
            exit;
        }
        $sql = "SELECT $selectFp $selectVar
                FROM folha_pagamento fp $joinVar
                WHERE fp.employee_id = ? AND fp.client_id = ?
                  AND fp.fiscal_year = ? AND fp.fiscal_month = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employeeId, $clientId, $dYear, $dMonth]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Recibo não encontrado.']);
            exit;
        }
        echo json_encode(['success' => true, 'recibo' => _formatRecibo($row)]);
        exit;
    }

    // List: last 12 months
    $sql = "SELECT $selectFp $selectVar
            FROM folha_pagamento fp $joinVar
            WHERE fp.employee_id = ? AND fp.client_id = ?
            ORDER BY fp.fiscal_year DESC, fp.fiscal_month DESC
            LIMIT 12";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employeeId, $clientId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode(['success' => true, 'recibos' => array_map('_formatRecibo', $rows)]);
} catch (Throwable $e) {
    error_log('get_recibos_employee erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor.']);
}

function _formatRecibo(array $r): array {
    $mesesPt = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    $m = (int)($r['fiscal_month'] ?? 0);
    $y = (int)($r['fiscal_year']  ?? 0);
    return [
        'periodo_key'   => "$y-$m",
        'periodo_label' => ($mesesPt[$m] ?? '') . ' ' . $y,
        'ano'           => $y,
        'mes'           => $m,
        'salario_base'  => (float)($r['salario_base']  ?? 0),
        'salario_bruto' => (float)($r['salario_bruto'] ?? 0),
        'salario_liquido' => (float)($r['salario_liquido'] ?? 0),
        'gorjetas'      => (float)($r['gorjeta_manual'] ?? $r['gorjetas'] ?? 0),
        'subsidio_alimentacao' => (float)($r['subsidio_alimentacao'] ?? 0),
        'horas_extra'   => (float)($r['horas_extra']   ?? 0),
        'bonus'         => (float)($r['bonus']          ?? 0),
        'subsidios_extra' => (float)($r['subsidios_extra'] ?? 0),
        'faltas_dias'   => (float)($r['faltas_dias']   ?? 0),
        'outros_descontos' => (float)($r['outros_descontos'] ?? 0),
        'status'        => $r['status_pagamento'] ?? 'pendente',
        'data_pagamento'=> $r['data_pagamento'] ?? null,
    ];
}
