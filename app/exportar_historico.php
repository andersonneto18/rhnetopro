<?php
session_start();
require_once '../config/db_connection.php';

if (empty($_SESSION['employee_id']) || empty($_SESSION['client_id'])) {
    header('Location: employee_login.php');
    exit;
}

$employee_id   = (int)$_SESSION['employee_id'];
$client_id     = (int)$_SESSION['client_id'];
$mes           = $_GET['mes'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $mes) || $mes > date('Y-m')) {
    $mes = date('Y-m');
}

$mesesPt = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$mesFmt  = $mesesPt[(int)date('m', strtotime($mes . '-01')) - 1] . ' ' . date('Y', strtotime($mes . '-01'));

$employee = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND client_id = ?");
$employee->execute([$employee_id, $client_id]);
$emp = $employee->fetch(PDO::FETCH_ASSOC) ?: [];
$empName = htmlspecialchars($emp['name'] ?? $_SESSION['employee_name'] ?? 'Funcionário');
$empCargo = htmlspecialchars($emp['position'] ?? '');

// $diasPorData — um registo agregado por dia de calendário
$diasPorData = [];
$totalHoras  = 0.0;
$diasTrab    = 0;
$atrasos     = 0;
$turnoInicio = null;

try {
    $stmtT = $pdo->prepare("SELECT horario_inicio FROM turnos WHERE funcionario_id = ? AND LOWER(COALESCE(status,'ativo')) IN ('ativo','active') ORDER BY id DESC LIMIT 1");
    $stmtT->execute([$employee_id]);
    $tRow = $stmtT->fetch(PDO::FETCH_ASSOC);
    if ($tRow) $turnoInicio = substr((string)($tRow['horario_inicio'] ?? ''), 0, 5);

    $checkTable = $pdo->query("SHOW TABLES LIKE 'registros_ponto'");
    if ($checkTable->rowCount() > 0) {
        $cols    = $pdo->query("SHOW COLUMNS FROM registros_ponto")->fetchAll(PDO::FETCH_COLUMN);
        $dateCol = in_array('data_registro', $cols) ? 'data_registro' : (in_array('data', $cols) ? 'data' : null);
        if ($dateCol) {
            // Sem LIMIT — buscar todos os registos do mês
            $stmt = $pdo->prepare("SELECT * FROM registros_ponto WHERE funcionario_id = ? AND DATE_FORMAT({$dateCol},'%Y-%m') = ? ORDER BY {$dateCol} ASC, id ASC");
            $stmt->execute([$employee_id, $mes]);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar por data
            $byDate = [];
            foreach ($rawRows as $r) {
                $dk = substr((string)($r[$dateCol] ?? ''), 0, 10);
                if ($dk) $byDate[$dk][] = $r;
            }

            // Agregar cada dia
            foreach ($byDate as $dk => $dayRecs) {
                $lastRec = end($dayRecs);

                $hEnt = '';
                foreach ($dayRecs as $dr) {
                    if (!empty($dr['hora_entrada'])) { $hEnt = substr((string)$dr['hora_entrada'], 0, 5); break; }
                }
                $hSai = '';
                foreach (array_reverse($dayRecs) as $dr) {
                    if (!empty($dr['hora_saida'])) { $hSai = substr((string)$dr['hora_saida'], 0, 5); break; }
                }

                $totalSecs = 0;
                foreach ($dayRecs as $dr) {
                    $e = (string)($dr['hora_entrada'] ?? '');
                    $s = (string)($dr['hora_saida']   ?? '');
                    if ($e && $s) {
                        $d = strtotime('today ' . $s) - strtotime('today ' . $e);
                        if ($d > 0) $totalSecs += $d;
                    }
                }

                $obs = implode(' | ', array_filter(array_map(fn($dr) => trim((string)($dr['observacao'] ?? '')), $dayRecs)));

                if ($totalSecs > 0) { $totalHoras += $totalSecs / 3600; $diasTrab++; }
                if ($turnoInicio && $hEnt) {
                    $diff = (strtotime('today ' . $hEnt) - strtotime('today ' . $turnoInicio)) / 60;
                    if ($diff > 15) $atrasos++;
                }

                $diasPorData[$dk] = [
                    'data_raw'   => $dk,
                    'entrada'    => $hEnt,
                    'saida'      => $hSai,
                    'total_secs' => $totalSecs,
                    'n_periodos' => count($dayRecs),
                    'status'     => (string)($lastRec['status_confirmacao'] ?? 'pendente'),
                    'obs'        => $obs,
                ];
            }
        }
    }
} catch (PDOException $e) {}

$totalHorasFmt = sprintf('%dh%02dm', floor($totalHoras), round(fmod($totalHoras, 1) * 60));
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Histórico de Presenças — <?= $mesFmt ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: Arial, sans-serif; font-size: 12px; color: #1e293b; background:#fff; padding:20px; }
    .header { border-bottom: 2px solid #2563eb; padding-bottom:12px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:flex-end; }
    .header h1 { font-size:18px; color:#2563eb; }
    .header p  { font-size:11px; color:#64748b; margin-top:2px; }
    .meta { display:flex; gap:24px; margin-bottom:16px; background:#f8fafc; padding:10px 14px; border-radius:8px; }
    .meta-item { display:flex; flex-direction:column; gap:2px; }
    .meta-item span:first-child { font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
    .meta-item span:last-child  { font-size:15px; font-weight:700; color:#1e293b; }
    table { width:100%; border-collapse:collapse; margin-bottom:16px; }
    th { background:#2563eb; color:#fff; padding:7px 10px; text-align:left; font-size:11px; }
    td { padding:6px 10px; border-bottom:1px solid #e2e8f0; font-size:11px; }
    tr:nth-child(even) td { background:#f8fafc; }
    .badge { display:inline-block; padding:2px 7px; border-radius:99px; font-size:10px; font-weight:700; }
    .ok   { background:#d1fae5; color:#059669; }
    .late { background:#fee2e2; color:#dc2626; }
    .early{ background:#dbeafe; color:#1d4ed8; }
    .pend { background:#fef3c7; color:#b45309; }
    .conf { background:#d1fae5; color:#059669; }
    .inv  { background:#fee2e2; color:#dc2626; }
    .footer { margin-top:20px; padding-top:10px; border-top:1px solid #e2e8f0; font-size:10px; color:#94a3b8; display:flex; justify-content:space-between; }
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
        <h1>Histórico de Presenças</h1>
        <p><?= $empName ?><?= $empCargo ? " — $empCargo" : '' ?></p>
    </div>
    <div style="text-align:right">
        <p style="font-weight:700;font-size:14px"><?= $mesFmt ?></p>
        <p style="color:#64748b;font-size:10px">Gerado em <?= date('d/m/Y H:i') ?></p>
    </div>
</div>

<div class="meta">
    <div class="meta-item"><span>Horas trabalhadas</span><span><?= $totalHorasFmt ?></span></div>
    <div class="meta-item"><span>Dias trabalhados</span><span><?= $diasTrab ?></span></div>
    <div class="meta-item"><span>Atrasos</span><span><?= $atrasos ?></span></div>
    <?php if ($turnoInicio): ?>
    <div class="meta-item"><span>Turno (início)</span><span><?= htmlspecialchars($turnoInicio) ?></span></div>
    <?php endif; ?>
</div>

<table>
    <thead>
        <tr>
            <th>Data</th>
            <th>1.ª Entrada</th>
            <th>Última Saída</th>
            <th>Total</th>
            <th>Pontualidade</th>
            <th>Estado</th>
            <th>Períodos</th>
            <th>Observação</th>
        </tr>
    </thead>
    
    <tbody>
    <?php if (empty($diasPorData)): ?>
        <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:20px">Sem registos para este período.</td></tr>
    <?php else: foreach ($diasPorData as $dia):
        $hEnt  = $dia['entrada'];
        $hSai  = $dia['saida'];
        $dataF = date('d/m/Y', strtotime($dia['data_raw']));
        $obs   = htmlspecialchars($dia['obs']);

        $totalH = '';
        if ($dia['total_secs'] > 0)
            $totalH = sprintf('%dh%02dm', floor($dia['total_secs'] / 3600), floor(($dia['total_secs'] % 3600) / 60));

        $compLabel = ''; $compCls = '';
        if ($turnoInicio && $hEnt) {
            $diff = (int)round((strtotime('today ' . $hEnt) - strtotime('today ' . $turnoInicio)) / 60);
            if ($diff > 15)     { $compLabel = "Atraso {$diff}min"; $compCls = 'late'; }
            elseif ($diff < -5) { $compLabel = 'Antecipado';        $compCls = 'early'; }
            else                { $compLabel = 'A tempo';            $compCls = 'ok'; }
        }

        $status = $dia['status'];
        $stCls  = $status === 'confirmado' ? 'conf' : ($status === 'invalidado' ? 'inv' : 'pend');
        $stLbl  = ucfirst($status);
        $nP     = (int)$dia['n_periodos'];
    ?>
        <tr>
            <td><?= $dataF ?></td>
            <td><?= $hEnt ?: '--:--' ?></td>
            <td><?= $hSai ?: '--:--' ?></td>
            <td><?= $totalH ?: '—' ?></td>
            <td><?= $compLabel ? "<span class='badge $compCls'>$compLabel</span>" : '—' ?></td>
            <td><span class="badge <?= $stCls ?>"><?= htmlspecialchars($stLbl) ?></span></td>
            <td style="text-align:center"><?= $nP ?><?= $nP > 1 ? ' <span class="badge pend" title="Inclui pausa para refeição">c/ pausa</span>' : '' ?></td>
            <td><?= $obs ?: '—' ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

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
