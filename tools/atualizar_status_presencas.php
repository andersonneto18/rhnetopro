<?php
// Persiste 'falta' na tabela presencas para funcionarios que nao compareceram no dia,
// respeitando o turno de cada um (dia da semana + vigencia) e o registo real de ponto.
// Pensado para correr uma vez por dia, ao final do dia, via Tarefas Agendadas do Windows
// (nao depende de sessao/login — cobre todos os clientes numa so passada).

require_once __DIR__ . '/../config/db_connection.php';

date_default_timezone_set('Europe/Lisbon');

function normalizeTurnoDayTokenCli(string $value): string
{
    $token = mb_strtolower(trim($value));
    $token = strtr($token, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ç' => 'c',
    ]);
    $token = str_replace('-feira', '', $token);

    $map = [
        'segunda' => 'seg', 'seg' => 'seg',
        'terca' => 'ter', 'ter' => 'ter',
        'quarta' => 'qua', 'qua' => 'qua',
        'quinta' => 'qui', 'qui' => 'qui',
        'sexta' => 'sex', 'sex' => 'sex',
        'sabado' => 'sab', 'sab' => 'sab',
        'domingo' => 'dom', 'dom' => 'dom',
    ];

    return $map[$token] ?? $token;
}

function parseTurnoDaysCli(string $diasSemana): array
{
    $parts = preg_split('/\s*,\s*/', $diasSemana) ?: [];
    $normalized = [];
    foreach ($parts as $part) {
        $day = normalizeTurnoDayTokenCli((string)$part);
        if ($day === '') {
            continue;
        }
        $normalized[$day] = true;
    }
    return array_keys($normalized);
}

$dataHoje = date('Y-m-d');
$weekdayMap = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];
$weekdayToken = $weekdayMap[(int)date('w')];

// Todos os funcionarios ativos com turno ativo, de todos os clientes (script corre sem sessao).
$stmtEmployees = $pdo->prepare("
    SELECT e.id, e.client_id, t.horario_inicio, t.horario_fim, t.dias_semana, t.data_inicio, t.data_fim
    FROM employees e
    INNER JOIN turnos t ON t.funcionario_id = e.id AND LOWER(COALESCE(t.status, '')) IN ('ativo', 'active')
    WHERE LOWER(COALESCE(e.status, '')) = 'active'
");
$stmtEmployees->execute();
$funcionarios = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);

$totalMarcados = 0;

foreach ($funcionarios as $func) {
    $employeeId = (int)$func['id'];
    $clientId = (int)$func['client_id'];

    // 1) O turno tem de estar previsto para hoje (dia da semana + vigencia) — caso contrario e um dia de folga.
    $diasRaw = trim((string)($func['dias_semana'] ?? ''));
    $dias = $diasRaw !== '' ? parseTurnoDaysCli($diasRaw) : [];
    $diaCorreto = empty($dias) || in_array($weekdayToken, $dias, true);

    $inicioVigencia = trim((string)($func['data_inicio'] ?? ''));
    $fimVigencia = trim((string)($func['data_fim'] ?? ''));
    $dentroVigencia = ($inicioVigencia === '' || $inicioVigencia === '0000-00-00' || $inicioVigencia <= $dataHoje)
        && ($fimVigencia === '' || $fimVigencia === '0000-00-00' || $fimVigencia >= $dataHoje);

    if (!$diaCorreto || !$dentroVigencia) {
        continue;
    }

    $horaEntrada = substr((string)$func['horario_inicio'], 0, 5);
    $horaFim = substr((string)$func['horario_fim'], 0, 5);
    if ($horaEntrada === '' || $horaFim === '') {
        continue;
    }

    // 2) So decide falta depois de o TURNO TERMINAR — nunca so por passar a tolerancia
    // (um atraso de horas ainda pode virar presenca se a pessoa aparecer antes do fim do turno).
    $entradaTs = strtotime($dataHoje . ' ' . $horaEntrada);
    $fimTs = strtotime($dataHoje . ' ' . $horaFim);
    if ($entradaTs !== false && $fimTs !== false && $fimTs <= $entradaTs) {
        $fimTs += 24 * 60 * 60; // suporte a turno noturno
    }
    $agoraTs = strtotime($dataHoje . ' ' . date('H:i'));

    if ($entradaTs === false || $fimTs === false || $agoraTs <= $fimTs) {
        continue;
    }

    // 3) Se ja bateu ponto (entrada) hoje, esteve presente — nunca sobrescrever com falta.
    $stmtPonto = $pdo->prepare("
        SELECT id FROM registros_ponto
        WHERE funcionario_id = ? AND data_registro = ? AND hora_entrada IS NOT NULL
        LIMIT 1
    ");
    $stmtPonto->execute([$employeeId, $dataHoje]);
    if ($stmtPonto->fetch(PDO::FETCH_ASSOC)) {
        continue;
    }

    // 4) Se ja existe presenca gravada hoje (auto-confirmacao do funcionario), respeita-a.
    $stmtPresenca = $pdo->prepare("
        SELECT id FROM presencas WHERE funcionario_id = ? AND DATE(data_registro) = ?
        LIMIT 1
    ");
    $stmtPresenca->execute([$employeeId, $dataHoje]);
    if ($stmtPresenca->fetch(PDO::FETCH_ASSOC)) {
        continue;
    }

    // 5) Sem ponto, sem presenca e o turno ja terminou: grava falta definitiva do dia.
    $stmtInsert = $pdo->prepare("
        INSERT INTO presencas (funcionario_id, client_id, status, data_registro)
        VALUES (?, ?, 'falta', ?)
    ");
    $stmtInsert->execute([$employeeId, $clientId, $dataHoje]);
    $totalMarcados++;
}

echo "[{$dataHoje}] Status de presencas atualizado: {$totalMarcados} falta(s) gravada(s).\n";
