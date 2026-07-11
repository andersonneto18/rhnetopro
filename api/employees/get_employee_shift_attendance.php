<?php
// API para buscar Turno Atual e Último Registro de Ponto de um Funcionário

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('Europe/Lisbon');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Obtém ID do funcionário
$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if (!$employee_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do funcionário não fornecido']);
    exit;
}

$anchorDate = trim((string)($_GET['anchor_date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchorDate)) {
    $anchorDate = date('Y-m-d');
}

try {
    // Inclui conexão com BD
    require_once '../../config/db_connection.php';
    
    // 1. BUSCAR TURNO ATUAL DO FUNCIONÁRIO
    // Busca o turno mais recente para o funcionário, sem filtrar o status
    // (caso a coluna contenha valores diferentes como 'Ativo' ou 'active').
    $stmtTurno = $pdo->prepare("
        SELECT
            t.id,
            t.turno_tipo,
            t.horario_inicio,
            t.horario_fim,
            t.dias_semana,
            t.data_inicio,
            t.data_fim,
            t.status
        FROM turnos t
        WHERE t.funcionario_id = ?
        ORDER BY t.created_at DESC
        LIMIT 1
    ");
    $stmtTurno->execute([$employee_id]);
    $turno = $stmtTurno->fetch(PDO::FETCH_ASSOC);
    if ($turno && isset($turno['status'])) {
        $turno['status'] = mb_strtolower(trim((string)$turno['status']));
    }
    
    // 2. BUSCAR ÚLTIMO REGISTRO DE PONTO DO FUNCIONÁRIO
    // Precisamos detectar se a coluna de data chama-se 'data' ou 'data_registro'
    $dateColumn = 'data_registro';
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM presencas")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('data', $cols) && !in_array('data_registro', $cols)) {
            $dateColumn = 'data';
        }
    } catch (Exception $e) {
        // Se der erro, mantém data_registro
    }

    // identificar colunas de entrada/saída (alguns esquemas diferentes; a tabela `presencas`
    // deste esquema nem sequer tem entrada/saída — usa NULL nesse caso em vez de assumir
    // 'hora_entrada'/'hora_saida', o que fazia esta query falhar sempre com "Column not found").
    $entryColumn = null;
    $exitColumn = null;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM presencas")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('hora_entrada', $cols, true)) {
            $entryColumn = 'hora_entrada';
        } elseif (in_array('entrada', $cols, true)) {
            $entryColumn = 'entrada';
        }
        if (in_array('hora_saida', $cols, true)) {
            $exitColumn = 'hora_saida';
        } elseif (in_array('saida', $cols, true)) {
            $exitColumn = 'saida';
        }
    } catch (Exception $e) {
        // ignore
    }

    $entrySelect = $entryColumn ? "p.{$entryColumn}" : 'NULL';
    $exitSelect = $exitColumn ? "p.{$exitColumn}" : 'NULL';
    $entryOrderBy = $entryColumn ? "p.{$entryColumn} DESC" : 'p.id DESC';

    $sqlPonto = "
        SELECT
            p.id,
            p.{$dateColumn} as data_marcacao,
            {$entrySelect} as hora_entrada,
            {$exitSelect} as hora_saida,
            p.status as tipo_registro,
            p.status
        FROM presencas p
        WHERE p.funcionario_id = ?
        ORDER BY p.{$dateColumn} DESC, {$entryOrderBy}
        LIMIT 1
    ";
    $stmtPonto = $pdo->prepare($sqlPonto);
    $stmtPonto->execute([$employee_id]);
    $ultimoPonto = $stmtPonto->fetch(PDO::FETCH_ASSOC);
    
    // Preparar resposta
    $response = [
        'success' => true,
        'turno' => null,
        'ultimo_ponto' => null
    ];
    
    // Processar dados do turno
    if ($turno) {
        $response['turno'] = [
            'id' => (int)$turno['id'],
            'tipo' => $turno['turno_tipo'],
            'horario_inicio' => $turno['horario_inicio'],
            'horario_fim' => $turno['horario_fim'],
            'dias_semana' => $turno['dias_semana'],
            'status' => $turno['status'],
            'horario_formatado' => $turno['horario_inicio'] . ' - ' . $turno['horario_fim']
        ];
    }

    // 3. RESUMO DO PERIODO (mes da data de referencia)
    $summaryYear = (int)substr($anchorDate, 0, 4);
    $summaryMonth = (int)substr($anchorDate, 5, 2);

    $pontoDateColumn = 'data_registro';
    try {
        $pontoCols = $pdo->query('SHOW COLUMNS FROM registros_ponto')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (!in_array('data_registro', $pontoCols, true) && in_array('data', $pontoCols, true)) {
            $pontoDateColumn = 'data';
        }
    } catch (Throwable $e) {
        // Mantem data_registro como fallback.
    }

    $expectedShiftMinutes = null;
    if ($turno && !empty($turno['horario_inicio']) && !empty($turno['horario_fim'])) {
        $startTs = strtotime('1970-01-01 ' . (string)$turno['horario_inicio']);
        $endTs = strtotime('1970-01-01 ' . (string)$turno['horario_fim']);
        if ($startTs !== false && $endTs !== false) {
            if ($endTs < $startTs) {
                $endTs += 24 * 60 * 60;
            }
            $expectedShiftMinutes = max(0, (int)floor(($endTs - $startTs) / 60));
        }
    }

    $stmtSummary = $pdo->prepare(
        "SELECT DATE({$pontoDateColumn}) AS data_ref, status, tipo_dia, hora_entrada, hora_saida
         FROM registros_ponto
         WHERE funcionario_id = ? AND YEAR({$pontoDateColumn}) = ? AND MONTH({$pontoDateColumn}) = ?
         ORDER BY {$pontoDateColumn} DESC, id DESC"
    );
    $stmtSummary->execute([$employee_id, $summaryYear, $summaryMonth]);
    $summaryRows = $stmtSummary->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $seenDays = [];
    $workedDays = 0;
    $absences = 0;
    $overtimeMinutes = 0;

    foreach ($summaryRows as $summaryRow) {
        $dayKey = (string)($summaryRow['data_ref'] ?? '');
        if ($dayKey === '' || isset($seenDays[$dayKey])) {
            continue;
        }
        $seenDays[$dayKey] = true;

        $statusRow = mb_strtolower(trim((string)($summaryRow['status'] ?? '')));
        $tipoDiaRow = mb_strtolower(trim((string)($summaryRow['tipo_dia'] ?? 'normal')));
        $isAbsence = in_array($statusRow, ['falta', 'ausente'], true) || $tipoDiaRow === 'falta';

        if ($isAbsence) {
            $absences++;
            continue;
        }

        $entradaRaw = trim((string)($summaryRow['hora_entrada'] ?? ''));
        $saidaRaw = trim((string)($summaryRow['hora_saida'] ?? ''));
        if ($entradaRaw === '' || $saidaRaw === '') {
            continue;
        }

        $entradaTs = strtotime('1970-01-01 ' . $entradaRaw);
        $saidaTs = strtotime('1970-01-01 ' . $saidaRaw);
        if ($entradaTs === false || $saidaTs === false) {
            continue;
        }

        if ($saidaTs < $entradaTs) {
            $saidaTs += 24 * 60 * 60;
        }

        $workedMinutes = max(0, (int)floor(($saidaTs - $entradaTs) / 60));
        if ($workedMinutes <= 0) {
            continue;
        }

        $workedDays++;

        if (is_int($expectedShiftMinutes) && $expectedShiftMinutes > 0) {
            $delta = $workedMinutes - $expectedShiftMinutes;
            if ($delta > 0) {
                $overtimeMinutes += $delta;
            }
        }
    }

    // Faltas ainda não persistidas: o script noturno que grava 'falta' em `presencas` só corre
    // uma vez por dia — se não tiver corrido nessa noite, o dia fica sem qualquer registo (nem
    // em registros_ponto nem em presencas) e o loop acima não o conta. Recalcula ao vivo, dia a
    // dia dentro do mês do resumo (até hoje), os dias com turno previsto já terminado sem
    // entrada e sem justificativa aprovada.
    try {
        $turnoStatusNorm = mb_strtolower(trim((string)($turno['status'] ?? '')));
        if ($turno && in_array($turnoStatusNorm, ['ativo', 'active'], true)
            && !empty($turno['horario_inicio']) && !empty($turno['horario_fim'])) {
            $diasRawResumo = trim((string)($turno['dias_semana'] ?? ''));
            $diasResumo = [];
            if ($diasRawResumo !== '') {
                $mapResumo = [
                    'domingo' => 'dom', 'dom' => 'dom', 'segunda' => 'seg', 'seg' => 'seg',
                    'terca' => 'ter', 'terça' => 'ter', 'ter' => 'ter', 'quarta' => 'qua', 'qua' => 'qua',
                    'quinta' => 'qui', 'qui' => 'qui', 'sexta' => 'sex', 'sex' => 'sex',
                    'sabado' => 'sab', 'sábado' => 'sab', 'sab' => 'sab',
                ];
                foreach (preg_split('/\s*,\s*/', mb_strtolower($diasRawResumo)) as $pResumo) {
                    if (isset($mapResumo[$pResumo])) $diasResumo[] = $mapResumo[$pResumo];
                }
            }

            $horaInicioResumo = substr((string)$turno['horario_inicio'], 0, 5);
            $horaFimResumo = substr((string)$turno['horario_fim'], 0, 5);

            $periodoInicioResumo = sprintf('%04d-%02d-01', $summaryYear, $summaryMonth);
            $periodoFimMesResumo = date('Y-m-t', strtotime($periodoInicioResumo));
            $hojeResumo = date('Y-m-d');
            $periodoFimResumo = ($periodoFimMesResumo < $hojeResumo) ? $periodoFimMesResumo : $hojeResumo;

            $inicioVigResumo = trim((string)($turno['data_inicio'] ?? ''));
            $fimVigResumo = trim((string)($turno['data_fim'] ?? ''));
            $rangeInicioResumo = ($inicioVigResumo !== '' && $inicioVigResumo !== '0000-00-00' && $inicioVigResumo > $periodoInicioResumo)
                ? $inicioVigResumo : $periodoInicioResumo;
            $rangeFimResumo = ($fimVigResumo !== '' && $fimVigResumo !== '0000-00-00' && $fimVigResumo < $periodoFimResumo)
                ? $fimVigResumo : $periodoFimResumo;

            if ($horaInicioResumo !== '' && $horaFimResumo !== '' && $rangeInicioResumo <= $rangeFimResumo) {
                // Dias com justificativa aprovada no mês — não contam como falta.
                $diasJustificadosResumo = [];
                $stmtJustResumo = $pdo->prepare("
                    SELECT data FROM justificativas_presenca
                    WHERE funcionario_id = ? AND client_id = ?
                      AND data BETWEEN ? AND ?
                      AND LOWER(status) = 'aprovada'
                ");
                $stmtJustResumo->execute([$employee_id, (int)$_SESSION['client_id'], $rangeInicioResumo, $rangeFimResumo]);
                foreach ($stmtJustResumo->fetchAll(PDO::FETCH_ASSOC) ?: [] as $rowJustResumo) {
                    $diasJustificadosResumo[(string)$rowJustResumo['data']] = true;
                }

                $agoraTsResumo = time();
                $cursorResumo = new DateTime($rangeInicioResumo);
                $fimDtResumo = new DateTime($rangeFimResumo);
                $weekdayMapResumo = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];

                while ($cursorResumo <= $fimDtResumo) {
                    $diaStrResumo = $cursorResumo->format('Y-m-d');
                    $weekdayTokenResumo = $weekdayMapResumo[(int)$cursorResumo->format('w')];
                    $cursorResumo->modify('+1 day');

                    // Já contabilizado (trabalhado ou falta) pelo loop de registros_ponto acima.
                    if (isset($seenDays[$diaStrResumo])) {
                        continue;
                    }

                    $diaCorretoResumo = empty($diasResumo) || in_array($weekdayTokenResumo, $diasResumo, true);
                    if (!$diaCorretoResumo || isset($diasJustificadosResumo[$diaStrResumo])) {
                        continue;
                    }

                    $inicioTsResumo = strtotime($diaStrResumo . ' ' . $horaInicioResumo);
                    $fimTsResumo = strtotime($diaStrResumo . ' ' . $horaFimResumo);
                    if ($inicioTsResumo !== false && $fimTsResumo !== false && $fimTsResumo <= $inicioTsResumo) {
                        $fimTsResumo += 24 * 60 * 60; // turno noturno
                    }
                    if ($fimTsResumo === false || $agoraTsResumo <= $fimTsResumo) {
                        continue; // turno ainda em curso ou ainda por vir
                    }

                    $seenDays[$diaStrResumo] = true;
                    $absences++;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Erro ao somar faltas ao vivo em get_employee_shift_attendance.php: ' . $e->getMessage());
    }

    $overtimeHours = floor($overtimeMinutes / 60);
    $overtimeRemainder = $overtimeMinutes % 60;

    $response['resumo_periodo'] = [
        'anchor_date' => $anchorDate,
        'ano' => $summaryYear,
        'mes' => $summaryMonth,
        'dias_trabalhados' => $workedDays,
        'faltas' => $absences,
        'horas_extras_minutos' => $overtimeMinutes,
        'horas_extras_formatadas' => sprintf('%02d:%02d', (int)$overtimeHours, (int)$overtimeRemainder)
    ];
    
    // Processar dados do último ponto
    if ($ultimoPonto) {
        $response['ultimo_ponto'] = [
            'id' => (int)$ultimoPonto['id'],
            'data' => $ultimoPonto['data_marcacao'],
            'data_formatada' => date('d/m/Y', strtotime($ultimoPonto['data_marcacao'])),
            'hora_entrada' => $ultimoPonto['hora_entrada'],
            'hora_saida' => $ultimoPonto['hora_saida'],
            'tipo' => $ultimoPonto['tipo_registro'],
            'status' => $ultimoPonto['status']
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    $msg = $e->getMessage();
    error_log('Erro em get_employee_shift_attendance.php: ' . $msg);
    // retornamos também no JSON para facilitar debug (pode ser removido em produção)
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar dados', 'error' => $msg]);
}
