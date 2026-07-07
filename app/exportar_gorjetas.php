<?php
session_start();
require_once '../config/db_connection.php';
date_default_timezone_set('Europe/Lisbon');

if (empty($_SESSION['employee_id']) || empty($_SESSION['client_id'])) {
    header('Location: employee_login.php');
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];
$client_id   = (int)$_SESSION['client_id'];
$mes         = $_GET['mes'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $mes) || $mes > date('Y-m')) {
    $mes = date('Y-m');
}

$mesesPt = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$mesFmt  = $mesesPt[(int)date('m', strtotime($mes . '-01')) - 1] . ' ' . date('Y', strtotime($mes . '-01'));

$empStmt = $pdo->prepare("SELECT name, position FROM employees WHERE id = ? AND client_id = ?");
$empStmt->execute([$employee_id, $client_id]);
$emp     = $empStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$empName = htmlspecialchars($emp['name'] ?? ($_SESSION['employee_name'] ?? 'Funcionário'));
$empCargo= htmlspecialchars($emp['position'] ?? '');

$gorjetas   = [];
$totalValor = 0.0;
$countByStatus = ['pendente' => 0, 'pago' => 0, 'cancelada' => 0, 'rejeitado' => 0];

try {
    $checkGT = $pdo->query("SHOW TABLES LIKE 'gorjetas'");
    if ($checkGT->rowCount() > 0) {
        $cols = $pdo->query("SHOW COLUMNS FROM gorjetas")->fetchAll(PDO::FETCH_COLUMN);

        $dateCol = in_array('data', $cols) ? 'data' : (in_array('data_registo', $cols) ? 'data_registo' : (in_array('created_at', $cols) ? 'created_at' : null));
        if ($dateCol) {
            $stmt = $pdo->prepare("
                SELECT * FROM gorjetas
                WHERE funcionario_id = ? AND client_id = ?
                  AND DATE_FORMAT({$dateCol}, '%Y-%m') = ?
                ORDER BY {$dateCol} ASC, id ASC
            ");
            $stmt->execute([$employee_id, $client_id, $mes]);
            $gorjetas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($gorjetas as $g) {
                $gs = strtolower(trim((string)($g['status'] ?? 'pendente')));
                $totalValor += (float)($g['valor'] ?? 0);
                $key = array_key_exists($gs, $countByStatus) ? $gs : 'pendente';
                $countByStatus[$key]++;
            }
        }
    }
} catch (PDOException $e) {
    error_log('exportar_gorjetas erro: ' . $e->getMessage());
}

$totalFmt = number_format($totalValor, 2, ',', '.') . ' €';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Gorjetas — <?= $mesFmt ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: Arial, sans-serif; font-size: 12px; color: #1e293b; background:#fff; padding:20px; }
    .header { border-bottom: 2px solid #2563eb; padding-bottom:12px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:flex-end; }
    .header h1 { font-size:18px; color:#2563eb; }
    .header p  { font-size:11px; color:#64748b; margin-top:2px; }
    .meta { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:16px; background:#f8fafc; padding:10px 14px; border-radius:8px; }
    .meta-item { display:flex; flex-direction:column; gap:2px; }
    .meta-item span:first-child { font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
    .meta-item span:last-child  { font-size:15px; font-weight:700; color:#1e293b; }
    table { width:100%; border-collapse:collapse; margin-bottom:16px; }
    th { background:#2563eb; color:#fff; padding:7px 10px; text-align:left; font-size:11px; }
    td { padding:6px 10px; border-bottom:1px solid #e2e8f0; font-size:11px; }
    tr:nth-child(even) td { background:#f8fafc; }
    .badge { display:inline-block; padding:2px 7px; border-radius:99px; font-size:10px; font-weight:700; }
    .pago      { background:#d1fae5; color:#059669; }
    .pendente  { background:#fef3c7; color:#b45309; }
    .cancelada { background:#f1f5f9; color:#64748b; }
    .rejeitado { background:#fee2e2; color:#dc2626; }
    .total-row td { font-weight:700; border-top:2px solid #334155; background:#f0fdf4; }
    .footer { margin-top:20px; padding-top:10px; border-top:1px solid #e2e8f0; font-size:10px; color:#94a3b8; display:flex; justify-content:space-between; }
    .empty-msg { text-align:center; padding:30px; color:#94a3b8; font-size:13px; }
    @media print {
        body { padding:10px; }
        .no-print { display:none; }
        @page { margin:1cm; }
    }
</style>
</head>
<body>

<div class="header">
    <div>
        <h1>Registo de Gorjetas</h1>
        <p><?= $empName ?><?= $empCargo ? " — $empCargo" : '' ?></p>
    </div>
    <div style="text-align:right">
        <p style="font-weight:700;font-size:14px"><?= $mesFmt ?></p>
        <p style="color:#64748b;font-size:10px">Gerado em <?= date('d/m/Y H:i') ?></p>
    </div>
</div>

<div class="meta">
    <div class="meta-item"><span>Total do mês</span><span><?= $totalFmt ?></span></div>
    <div class="meta-item"><span>Registos</span><span><?= count($gorjetas) ?></span></div>
    <div class="meta-item"><span>Pagas</span><span><?= $countByStatus['pago'] ?></span></div>
    <div class="meta-item"><span>Pendentes</span><span><?= $countByStatus['pendente'] ?></span></div>
    <?php if ($countByStatus['rejeitado'] > 0): ?>
    <div class="meta-item"><span>Rejeitadas</span><span><?= $countByStatus['rejeitado'] ?></span></div>
    <?php endif; ?>
    <?php if ($countByStatus['cancelada'] > 0): ?>
    <div class="meta-item"><span>Canceladas</span><span><?= $countByStatus['cancelada'] ?></span></div>
    <?php endif; ?>
</div>

<?php if (empty($gorjetas)): ?>
    <div class="empty-msg">Sem gorjetas registadas para este período.</div>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Data</th>
            <th>Valor</th>
            <th>F. Pagamento</th>
            <th>Origem</th>
            <th>Estado</th>
            <th>Observações</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($gorjetas as $g):
        $gs     = strtolower(trim((string)($g['status'] ?? 'pendente')));
        $gCls   = $gs === 'pago' ? 'pago' : ($gs === 'pendente' ? 'pendente' : ($gs === 'rejeitado' ? 'rejeitado' : 'cancelada'));
        $gLbl   = $gs === 'pago' ? 'Pago' : ($gs === 'pendente' ? 'Pendente' : ($gs === 'rejeitado' ? 'Rejeitado' : ucfirst($gs)));
        $gData  = substr((string)($g['data'] ?? $g['data_registo'] ?? $g['created_at'] ?? ''), 0, 10);
        $gDataF = $gData ? date('d/m/Y', strtotime($gData)) : '—';
        $gValor = number_format((float)($g['valor'] ?? 0), 2, ',', '.') . ' €';
        $gFP    = htmlspecialchars($g['forma_pagamento'] ?? '—');
        $gOrig  = htmlspecialchars($g['origem'] ?? '—');
        $gObs   = htmlspecialchars($g['observacoes'] ?? $g['observacao'] ?? '');
    ?>
        <tr>
            <td><?= $gDataF ?></td>
            <td style="font-weight:600"><?= $gValor ?></td>
            <td><?= $gFP ?></td>
            <td><?= $gOrig ?></td>
            <td><span class="badge <?= $gCls ?>"><?= $gLbl ?></span></td>
            <td><?= $gObs ?: '—' ?></td>
        </tr>
    <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="1">Total</td>
            <td><?= $totalFmt ?></td>
            <td colspan="4"></td>
        </tr>
    </tbody>
</table>
<?php endif; ?>

<div class="footer">
    <span>RHNeto Pro — Portal do Funcionário</span>
    <span>Documento gerado automaticamente em <?= date('d/m/Y \à\s H:i') ?></span>
</div>

<div class="no-print" style="margin-top:20px;text-align:center">
    <button onclick="window.print()" style="padding:10px 24px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600">
        🖨️ Imprimir / Guardar PDF
    </button>
</div>

</body>
</html>
