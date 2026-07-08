<?php
session_start();
require_once '../../config/db_connection.php';
date_default_timezone_set('Europe/Lisbon');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

function statusPeriodoNormalizeDayToken(string $value): string
{
    $token = mb_strtolower(trim($value), 'UTF-8');
    $token = strtr($token, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'ê' => 'e',
        'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u',
        'ç' => 'c',
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

function statusPeriodoParseTurnoDays(string $diasSemana): array
{
    $parts = preg_split('/\s*,\s*/', $diasSemana) ?: [];
    $normalized = [];
    foreach ($parts as $part) {
        $day = statusPeriodoNormalizeDayToken((string)$part);
        if ($day === '') {
            continue;
        }
        $normalized[$day] = true;
    }
    return array_keys($normalized);
}

$dataInicio = isset($_GET['data_inicio']) ? trim((string)$_GET['data_inicio']) : '';
$dataFim = isset($_GET['data_fim']) ? trim((string)$_GET['data_fim']) : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
    echo json_encode(['success' => false, 'message' => 'Datas inválidas']);
    exit;
}

$inicioTs = strtotime($dataInicio);
$fimTs = strtotime($dataFim);
if ($inicioTs === false || $fimTs === false || $fimTs < $inicioTs) {
    echo json_encode(['success' => false, 'message' => 'Intervalo de datas inválido']);
    exit;
}

// Limita o intervalo para evitar varreduras enormes (cobre a vista de mês com folga).
$diffDays = (int)round(($fimTs - $inicioTs) / 86400);
if ($diffDays > 60) {
    echo json_encode(['success' => false, 'message' => 'Intervalo de datas demasiado longo']);
    exit;
}

$clientId = (int)$_SESSION['client_id'];
$weekdayMap = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];

try {
    $stmtTurnos = $pdo->prepare(
        "SELECT t.funcionario_id, t.horario_inicio, t.horario_fim, t.dias_semana, t.data_inicio, t.data_fim
         FROM turnos t
         INNER JOIN employees e ON e.id = t.funcionario_id
         WHERE e.client_id = ?"
    );
    $stmtTurnos->execute([$clientId]);
    $turnos = $stmtTurnos->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Monta a lista de ocorrências (funcionario+data+horario) dentro do intervalo pedido.
    $ocorrencias = [];
    $funcionarioIds = [];
    for ($ts = $inicioTs; $ts <= $fimTs; $ts += 86400) {
        $dataAtual = date('Y-m-d', $ts);
        $weekdayToken = $weekdayMap[(int)date('w', $ts)];

        foreach ($turnos as $turno) {
            $funcionarioId = (int)($turno['funcionario_id'] ?? 0);
            if ($funcionarioId <= 0) {
                continue;
            }

            $diasRaw = trim((string)($turno['dias_semana'] ?? ''));
            $dias = $diasRaw !== '' ? statusPeriodoParseTurnoDays($diasRaw) : [];
            $diaCorreto = empty($dias) || in_array($weekdayToken, $dias, true);
            if (!$diaCorreto) {
                continue;
            }

            $vigenciaInicio = trim((string)($turno['data_inicio'] ?? ''));
            $vigenciaFim = trim((string)($turno['data_fim'] ?? ''));
            $dentroVigencia = ($vigenciaInicio === '' || $vigenciaInicio === '0000-00-00' || $vigenciaInicio <= $dataAtual)
                && ($vigenciaFim === '' || $vigenciaFim === '0000-00-00' || $vigenciaFim >= $dataAtual);
            if (!$dentroVigencia) {
                continue;
            }

            $ocorrencias[] = [
                'funcionario_id' => $funcionarioId,
                'data' => $dataAtual,
                'horario_inicio' => (string)($turno['horario_inicio'] ?? ''),
                'horario_fim' => (string)($turno['horario_fim'] ?? ''),
            ];
            $funcionarioIds[$funcionarioId] = true;
        }
    }

    // Busca todos os registos de ponto relevantes numa única query.
    $pontosByKey = [];
    if (!empty($ocorrencias)) {
        $placeholders = implode(',', array_fill(0, count($funcionarioIds), '?'));
        $stmtPontos = $pdo->prepare(
            "SELECT funcionario_id, data_registro, hora_entrada, hora_saida
             FROM registros_ponto
             WHERE funcionario_id IN ($placeholders)
               AND data_registro BETWEEN ? AND ?
               AND LOWER(COALESCE(status, '')) <> 'invalidado'
             ORDER BY id ASC"
        );
        $stmtPontos->execute(array_merge(array_keys($funcionarioIds), [$dataInicio, $dataFim]));
        $pontoRows = $stmtPontos->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($pontoRows as $pontoRow) {
            $key = (int)$pontoRow['funcionario_id'] . '_' . (string)$pontoRow['data_registro'];
            if (!isset($pontosByKey[$key])) {
                $pontosByKey[$key] = [];
            }
            $pontosByKey[$key][] = $pontoRow;
        }
    }

    $agora = time();
    $resultado = [];

    foreach ($ocorrencias as $ocorrencia) {
        $key = $ocorrencia['funcionario_id'] . '_' . $ocorrencia['data'];
        if (isset($resultado[$key])) {
            // Já processado (funcionário pode ter mais de um turno na mesma data; mantém o primeiro).
            continue;
        }

        $inicioTurnoTs = strtotime($ocorrencia['data'] . ' ' . $ocorrencia['horario_inicio']);
        $fimTurnoTs = strtotime($ocorrencia['data'] . ' ' . $ocorrencia['horario_fim']);
        if ($inicioTurnoTs !== false && $fimTurnoTs !== false && $fimTurnoTs <= $inicioTurnoTs) {
            $fimTurnoTs += 86400; // turno noturno
        }

        $rows = $pontosByKey[$key] ?? [];
        $temEntrada = false;
        $emAberto = false;
        foreach ($rows as $idx => $row) {
            $hasEntrada = !empty($row['hora_entrada']) && $row['hora_entrada'] !== '00:00:00';
            if ($hasEntrada) {
                $temEntrada = true;
            }
            $isLast = $idx === count($rows) - 1;
            if ($isLast) {
                $hasSaida = !empty($row['hora_saida']) && $row['hora_saida'] !== '00:00:00';
                $emAberto = $hasEntrada && !$hasSaida;
            }
        }

        if ($temEntrada && $emAberto) {
            $status = 'em_andamento';
        } elseif ($temEntrada && !$emAberto) {
            $status = 'concluido';
        } elseif ($fimTurnoTs !== false && $agora > $fimTurnoTs) {
            $status = 'falta';
        } else {
            $status = 'agendado';
        }

        $resultado[$key] = $status;
    }

    echo json_encode(['success' => true, 'status' => $resultado]);
    exit;
} catch (Throwable $e) {
    error_log('Erro status_periodo.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao calcular status dos turnos']);
    exit;
}
