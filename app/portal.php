<?php
session_start();
if (empty($_SESSION['employee_id'])) {
    header('Location: employee_login.php');
    exit;
}

require_once '../config/db_connection.php';

$employee_id = (int)$_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Funcionário';
$client_id = $_SESSION['client_id'] ?? 1;

// Buscar informações do funcionário
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND client_id = ?");
$stmt->execute([$employee_id, $client_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    session_destroy();
    header('Location: employee_login.php');
    exit;
}

// Inicializar variáveis
$today = date('Y-m-d');
$lastPonto = null;
$pontosHoje = [];
$presencaHoje = null;
$historicoPresencas = [];
$pontoDateColumn = null;
$turnos = [];
$turnosHistorico = [];
$turnosColegasAtivos = [];
$trocasTurnoRecebidas = [];
$trocasTurnoEnviadas = [];
$turnosTrocadosIds = [];
$gorjetas = [];
$totalGorjetas = 0;
$feriasPedidos = [];

try {
    // Verificar se tabela registros_ponto existe e buscar último registro
    $checkTable = $pdo->query("SHOW TABLES LIKE 'registros_ponto'");
    if ($checkTable->rowCount() > 0) {
        // Verificar quais colunas existem
        $columns = $pdo->query("SHOW COLUMNS FROM registros_ponto")->fetchAll(PDO::FETCH_COLUMN);
        $dateColumn = in_array('data', $columns) ? 'data' : (in_array('data_registro', $columns) ? 'data_registro' : null);
        $pontoDateColumn = $dateColumn;
        
        if ($dateColumn) {
            // Todos os períodos de hoje (suporte a múltiplos períodos: pausa de almoço, etc.)
            $stmtPontoHoje = $pdo->prepare("
                SELECT * FROM registros_ponto
                WHERE funcionario_id = ? AND $dateColumn = ?
                ORDER BY id ASC
            ");
            $stmtPontoHoje->execute([$employee_id, $today]);
            $pontosHoje = $stmtPontoHoje->fetchAll(PDO::FETCH_ASSOC);
            $lastPonto  = !empty($pontosHoje) ? $pontosHoje[count($pontosHoje) - 1] : null;

            // Histórico do mês corrente (todos os registos, para agregar por dia)
            $stmtHistorico = $pdo->prepare("
                SELECT * FROM registros_ponto
                WHERE funcionario_id = ?
                  AND DATE_FORMAT($dateColumn, '%Y-%m') = ?
                ORDER BY $dateColumn ASC, id ASC
            ");
            $stmtHistorico->execute([$employee_id, date('Y-m')]);
            $historicoPresencas = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar registros de ponto: " . $e->getMessage());
}

try {
    // Verificar se tabela presencas existe
    $checkTable = $pdo->query("SHOW TABLES LIKE 'presencas'");
    if ($checkTable->rowCount() > 0) {
        $columns = $pdo->query("SHOW COLUMNS FROM presencas")->fetchAll(PDO::FETCH_COLUMN);
        $dateColumn = in_array('data', $columns) ? 'data' : (in_array('data_registro', $columns) ? 'data_registro' : null);
        
        if ($dateColumn) {
            $stmtPresenca = $pdo->prepare("
                SELECT * FROM presencas 
                WHERE funcionario_id = ? AND $dateColumn = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmtPresenca->execute([$employee_id, $today]);
            $presencaHoje = $stmtPresenca->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar presença: " . $e->getMessage());
}

try {
    // Buscar turnos do funcionário
    $checkTable = $pdo->query("SHOW TABLES LIKE 'turnos'");
    $checkPublicationTable = $pdo->query("SHOW TABLES LIKE 'turnos_publicacoes'");
    if ($checkTable->rowCount() > 0 && $checkPublicationTable->rowCount() > 0) {
        $stmtTurnos = $pdo->prepare("
            SELECT DISTINCT t.*
            FROM turnos t
            INNER JOIN turnos_publicacoes tp ON tp.client_id = ?
            WHERE t.funcionario_id = ?
              AND LOWER(COALESCE(t.status, 'ativo')) IN ('ativo', 'active')
                            AND LOWER(COALESCE(tp.status, 'publicado')) IN ('publicado', 'fechado')
              AND COALESCE(t.data_inicio, '1000-01-01') <= tp.period_end
              AND COALESCE(t.data_fim, '9999-12-31') >= tp.period_start
            ORDER BY t.id DESC
        ");
        $stmtTurnos->execute([$client_id, $employee_id]);
        $turnos = $stmtTurnos->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $turnos = [];
    }

    // Fallback: se a query principal não retornou turnos (publicações em falta ou tabela inexistente),
    // buscar directamente o turno activo do funcionário
    if (empty($turnos) && $checkTable->rowCount() > 0) {
        $stmtTurnoFallback = $pdo->prepare("
            SELECT t.* FROM turnos t
            INNER JOIN employees e ON e.id = t.funcionario_id
            WHERE t.funcionario_id = ? AND e.client_id = ?
              AND LOWER(COALESCE(t.status, 'ativo')) IN ('ativo', 'active')
            ORDER BY t.id DESC LIMIT 5
        ");
        $stmtTurnoFallback->execute([$employee_id, $client_id]);
        $turnos = $stmtTurnoFallback->fetchAll(PDO::FETCH_ASSOC);
    }
    // Histórico: turnos inactivos do funcionário
    try {
        if ($checkTable->rowCount() > 0) {
            $stmtHist = $pdo->prepare("
                SELECT t.* FROM turnos t
                INNER JOIN employees e ON e.id = t.funcionario_id
                WHERE t.funcionario_id = ? AND e.client_id = ?
                  AND LOWER(COALESCE(t.status,'')) NOT IN ('ativo','active','')
                ORDER BY COALESCE(t.data_fim, t.updated_at, t.created_at) DESC LIMIT 10
            ");
            $stmtHist->execute([$employee_id, $client_id]);
            $turnosHistorico = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('Erro ao buscar histórico de turnos: ' . $e->getMessage());
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar turnos: " . $e->getMessage());
}

try {
        $checkTableSwap = $pdo->query("SHOW TABLES LIKE 'turno_swap_requests'");
        if ($checkTableSwap->rowCount() > 0) {
                $stmtTurnosColegas = $pdo->prepare(
                        "SELECT t.id, t.funcionario_id, t.turno_tipo, t.horario_inicio, t.horario_fim, t.dias_semana,
                                        e.name AS funcionario_nome
                         FROM turnos t
                         INNER JOIN employees e ON e.id = t.funcionario_id
                         WHERE e.client_id = ?
                             AND e.id <> ?
                             AND LOWER(COALESCE(t.status, 'ativo')) IN ('ativo', 'active')
                         ORDER BY e.name ASC, t.id DESC"
                );
                $stmtTurnosColegas->execute([$client_id, $employee_id]);
                $turnosColegasAtivos = $stmtTurnosColegas->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $stmtRecebidas = $pdo->prepare(
                        "SELECT r.id, r.requested_date, r.reason, r.requested_at,
                                        er.name AS requester_name,
                                        rt.turno_tipo AS requester_turno_tipo, rt.horario_inicio AS requester_horario_inicio, rt.horario_fim AS requester_horario_fim, rt.dias_semana AS requester_dias,
                                        tt.turno_tipo AS target_turno_tipo, tt.horario_inicio AS target_horario_inicio, tt.horario_fim AS target_horario_fim, tt.dias_semana AS target_dias
                         FROM turno_swap_requests r
                         INNER JOIN employees er ON er.id = r.requester_employee_id
                         INNER JOIN turnos rt ON rt.id = r.requester_turno_id
                         INNER JOIN turnos tt ON tt.id = r.target_turno_id
                         WHERE r.client_id = ?
                             AND r.target_employee_id = ?
                             AND LOWER(COALESCE(r.status, 'pendente_colega')) IN ('pendente_colega')
                         ORDER BY r.requested_at DESC, r.id DESC"
                );
                $stmtRecebidas->execute([$client_id, $employee_id]);
                $trocasTurnoRecebidas = $stmtRecebidas->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $stmtEnviadas = $pdo->prepare(
                        "SELECT r.id, r.requested_date, r.reason, r.requested_at, r.status,
                                        et.name AS target_name,
                                        rt.turno_tipo AS requester_turno_tipo, rt.horario_inicio AS requester_horario_inicio, rt.horario_fim AS requester_horario_fim,
                                        tt.turno_tipo AS target_turno_tipo, tt.horario_inicio AS target_horario_inicio, tt.horario_fim AS target_horario_fim
                         FROM turno_swap_requests r
                         INNER JOIN employees et ON et.id = r.target_employee_id
                         INNER JOIN turnos rt ON rt.id = r.requester_turno_id
                         INNER JOIN turnos tt ON tt.id = r.target_turno_id
                         WHERE r.client_id = ?
                             AND r.requester_employee_id = ?
                         ORDER BY r.requested_at DESC, r.id DESC
                         LIMIT 10"
                );
                $stmtEnviadas->execute([$client_id, $employee_id]);
                $trocasTurnoEnviadas = $stmtEnviadas->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $stmtTurnosTrocados = $pdo->prepare(
                    "SELECT requester_turno_id, target_turno_id
                     FROM turno_swap_requests
                     WHERE client_id = ?
                         AND (requester_employee_id = ? OR target_employee_id = ?)
                         AND LOWER(COALESCE(status, '')) IN ('aprovada', 'approved')"
                );
                $stmtTurnosTrocados->execute([$client_id, $employee_id, $employee_id]);
                $turnosTrocadosRows = $stmtTurnosTrocados->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($turnosTrocadosRows as $swapRow) {
                    $requesterTurnoId = (int)($swapRow['requester_turno_id'] ?? 0);
                    $targetTurnoId = (int)($swapRow['target_turno_id'] ?? 0);
                    if ($requesterTurnoId > 0) {
                        $turnosTrocadosIds[$requesterTurnoId] = true;
                    }
                    if ($targetTurnoId > 0) {
                        $turnosTrocadosIds[$targetTurnoId] = true;
                    }
                }
        }
} catch (PDOException $e) {
        error_log("Erro ao buscar trocas de turno no app: " . $e->getMessage());
}

try {
    // Buscar gorjetas do mês atual
    $currentMonth = date('Y-m');
    $checkTable = $pdo->query("SHOW TABLES LIKE 'gorjetas'");
    if ($checkTable->rowCount() > 0) {
        $columns = $pdo->query("SHOW COLUMNS FROM gorjetas")->fetchAll(PDO::FETCH_COLUMN);
        $dateColumn = in_array('data', $columns) ? 'data' : (in_array('data_registro', $columns) ? 'data_registro' : null);
        
        if ($dateColumn) {
            $stmtGorjetas = $pdo->prepare("
                SELECT * FROM gorjetas 
                WHERE funcionario_id = ? AND DATE_FORMAT($dateColumn, '%Y-%m') = ?
                ORDER BY $dateColumn DESC
            ");
            $stmtGorjetas->execute([$employee_id, $currentMonth]);
            $gorjetas = $stmtGorjetas->fetchAll(PDO::FETCH_ASSOC);
            $totalGorjetas = array_sum(array_column($gorjetas, 'valor'));
            $totaisPorPagamento = [];
            foreach ($gorjetas as $_g) {
                $_fp = trim((string)($_g['forma_pagamento'] ?? ''));
                if ($_fp === '') $_fp = 'Outro';
                $totaisPorPagamento[$_fp] = ($totaisPorPagamento[$_fp] ?? 0.0) + (float)$_g['valor'];
            }

$_ptMeses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$gorjetaMesesOpcoes = [];
for ($i = 0; $i < 12; $i++) {
    $ts  = mktime(0, 0, 0, (int)date('n') - $i, 1, (int)date('Y'));
    $val = date('Y-m', $ts);
    $gorjetaMesesOpcoes[] = ['value' => $val, 'label' => $_ptMeses[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts)];
}
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar gorjetas: " . $e->getMessage());
}

$feriasHasMotivoRej = false;
$diasUsadosAno      = 0;

try {
    $checkTableFerias = $pdo->query("SHOW TABLES LIKE 'ferias'");
    if ($checkTableFerias->rowCount() > 0) {
        $_feriasCols        = $pdo->query("SHOW COLUMNS FROM ferias")->fetchAll(PDO::FETCH_COLUMN);
        $feriasHasMotivoRej = in_array('motivo_rejeicao', $_feriasCols, true);
        $_feriasExtra       = $feriasHasMotivoRej ? ', motivo_rejeicao' : '';

        $stmtFerias = $pdo->prepare("SELECT id, data_inicio, data_fim, status, motivo$_feriasExtra, created_at
            FROM ferias
            WHERE funcionario_id = ? AND client_id = ?
            ORDER BY id DESC LIMIT 10");
        $stmtFerias->execute([$employee_id, $client_id]);
        $feriasPedidos = $stmtFerias->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $_fAno = date('Y');
        $stmtDias = $pdo->prepare(
            "SELECT COALESCE(SUM(DATEDIFF(LEAST(data_fim,'$_fAno-12-31'),GREATEST(data_inicio,'$_fAno-01-01'))+1),0) AS total
             FROM ferias
             WHERE funcionario_id = ? AND client_id = ?
               AND LOWER(COALESCE(status,'')) IN ('aprovada','aprovado')
               AND data_fim >= '$_fAno-01-01' AND data_inicio <= '$_fAno-12-31'"
        );
        $stmtDias->execute([$employee_id, $client_id]);
        $diasUsadosAno = (int)$stmtDias->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar férias: " . $e->getMessage());
}






// ... suas outras variáveis (totalGorjetas, etc)

$mensagens = []; // IMPORTANTE: Inicializa como array vazio para não dar erro de count()
$mensagensSource = null;

try {
    // Fonte primária: notificacoes (usada pelo fluxo de SMS do app)
    $checkTableNotif = $pdo->query("SHOW TABLES LIKE 'notificacoes'");
    if ($checkTableNotif->rowCount() > 0) {
        $_colConfirm = $pdo->query("SHOW COLUMNS FROM notificacoes LIKE 'requer_confirmacao'")->rowCount() > 0;
        $_notifCols  = $_colConfirm ? 'id, mensagem, data_envio, lida, requer_confirmacao, confirmado_em' : 'id, mensagem, data_envio, lida';
        $stmtMsg = $pdo->prepare("SELECT $_notifCols FROM notificacoes WHERE funcionario_id = ? AND client_id = ? ORDER BY data_envio DESC LIMIT 20");
        $stmtMsg->execute([$employee_id, $client_id]);
        $mensagens = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($mensagens)) {
            $mensagensSource = 'notificacoes';
        }
    }

    // Fallback: atividades_recentes (compatível com notificações antigas)
    if (empty($mensagens)) {
        $stmtMsgFallback = $pdo->prepare("SELECT id, title AS mensagem, timestamp AS data_envio FROM atividades_recentes WHERE client_id = ? AND employee_id = ? ORDER BY timestamp DESC LIMIT 20");
        $stmtMsgFallback->execute([$client_id, $employee_id]);
        $mensagens = $stmtMsgFallback->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($mensagens)) {
            $mensagensSource = 'atividades_recentes';
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar notificações: " . $e->getMessage());
    $mensagens = []; // Garante que continua sendo um array mesmo se o banco falhar
    $mensagensSource = null;
}

$unreadCount = $mensagensSource === 'notificacoes'
    ? count(array_filter($mensagens, fn($m) => empty($m['lida'])))
    : 0;

// Horas e dias trabalhados no mês corrente
$totalHorasMes = 0.0;
$totalDiasTrabalhados = 0;
try {
    if ($pontoDateColumn) {
        // Incluir DATE() para contar dias únicos mesmo com múltiplos períodos por dia
        $stmtHoras = $pdo->prepare("
            SELECT hora_entrada, hora_saida, DATE({$pontoDateColumn}) AS data_reg
            FROM registros_ponto
            WHERE funcionario_id = ?
              AND DATE_FORMAT({$pontoDateColumn}, '%Y-%m') = ?
              AND hora_entrada IS NOT NULL AND hora_entrada != ''
              AND hora_saida IS NOT NULL AND hora_saida != ''
        ");
        $stmtHoras->execute([$employee_id, date('Y-m')]);
        $datesWorked = [];
        foreach ($stmtHoras->fetchAll(PDO::FETCH_ASSOC) as $reg) {
            $t1 = strtotime('today ' . $reg['hora_entrada']);
            $t2 = strtotime('today ' . $reg['hora_saida']);
            if ($t2 > $t1) {
                $totalHorasMes += ($t2 - $t1) / 3600;
                $datesWorked[$reg['data_reg']] = true;
            }
        }
        $totalDiasTrabalhados = count($datesWorked);
    }
} catch (PDOException $e) {
    error_log("Erro ao calcular horas do mês: " . $e->getMessage());
}

$salarioBaseRaw = $employee['salary_base'] ?? $employee['base_salary'] ?? $employee['salario_base'] ?? null;
$salarioBaseFormatted = is_numeric($salarioBaseRaw) ? number_format((float)$salarioBaseRaw, 2, ',', '.') . '€' : 'N/D';
$gorjetasMesFormatted = number_format((float)$totalGorjetas, 2, ',', '.') . '€';
$estimativaTotalFormatted = is_numeric($salarioBaseRaw)
    ? number_format((float)$salarioBaseRaw + (float)$totalGorjetas, 2, ',', '.') . '€'
    : 'N/D';

// Estado dos botões de ponto — baseado no último registo de hoje
$pontoAberto = $lastPonto && !empty($lastPonto['hora_entrada']) && empty($lastPonto['hora_saida']);

// Observação do último registo — distingue Pausa / Regresso / Saída final
$_ultimaObs     = mb_strtolower(trim((string)($lastPonto['observacao'] ?? '')));
$_isPausaUlt    = !$pontoAberto && str_contains($_ultimaObs, 'pausa');
$_isRegressoUlt = $pontoAberto  && str_contains($_ultimaObs, 'regresso');

// Em pausa = saiu com observação "Pausa" (ainda vai regressar)
$emPausa  = $_isPausaUlt;
$semPonto = empty($pontosHoje);

// Ícone / label / hora para o bloco ponto-status-display
if ($semPonto || !$lastPonto) {
    $_pStatusIcon = 'fa-clock'; $_pStatusLabel = 'Sem ponto hoje'; $_pStatusClass = 'ponto-status--none';
    $_pStatusHora = 'Registe a sua entrada';
} elseif ($_isRegressoUlt) {
    $_pStatusIcon = 'fa-undo-alt'; $_pStatusLabel = 'Regresso registado'; $_pStatusClass = 'ponto-status--in';
    $_pStatusHora = substr((string)($lastPonto['hora_entrada'] ?? ''), 0, 5);
} elseif ($pontoAberto) {
    $_pStatusIcon = 'fa-sign-in-alt'; $_pStatusLabel = 'Entrada registada'; $_pStatusClass = 'ponto-status--in';
    $_pStatusHora = substr((string)($lastPonto['hora_entrada'] ?? ''), 0, 5);
} elseif ($_isPausaUlt) {
    $_obsDisplay  = trim((string)($lastPonto['observacao'] ?? ''));
    $_pStatusIcon = 'fa-pause-circle';
    $_pStatusLabel = ($_obsDisplay !== '' ? $_obsDisplay : 'Pausa') . ' registada';
    $_pStatusClass = 'ponto-status--out';
    $_pStatusHora = substr((string)($lastPonto['hora_saida'] ?? ''), 0, 5);
} else {
    $_pStatusIcon = 'fa-sign-out-alt'; $_pStatusLabel = 'Saída registada'; $_pStatusClass = 'ponto-status--out';
    $_pStatusHora = substr((string)($lastPonto['hora_saida'] ?? $lastPonto['hora_entrada'] ?? ''), 0, 5);
}

// Dados do dia para o card "Presença Hoje" — agrega todos os períodos
$entradaHoje = '';
foreach ($pontosHoje as $_p) {
    if (!empty($_p['hora_entrada'])) { $entradaHoje = substr((string)$_p['hora_entrada'], 0, 5); break; }
}
$saidaHoje = '';
foreach (array_reverse($pontosHoje) as $_p) {
    if (!empty($_p['hora_saida'])) { $saidaHoje = substr((string)$_p['hora_saida'], 0, 5); break; }
}
// Total de horas hoje = soma de todos os pares completos
$_totalSegsHoje = 0;
foreach ($pontosHoje as $_p) {
    $_e = (string)($_p['hora_entrada'] ?? '');
    $_s = (string)($_p['hora_saida']   ?? '');
    if ($_e && $_s) {
        $_d = strtotime('today ' . $_s) - strtotime('today ' . $_e);
        if ($_d > 0) $_totalSegsHoje += $_d;
    }
}
$horasHojeLabel = $_totalSegsHoje > 0
    ? sprintf('%dh%02dm', floor($_totalSegsHoje / 3600), floor(($_totalSegsHoje % 3600) / 60))
    : '';
// Entrada do período actual (para o live timer — pode ser diferente da primeira entrada do dia)
$entradaPeriodoAtual = $pontoAberto ? substr((string)($lastPonto['hora_entrada'] ?? ''), 0, 5) : '';

// Estado da presença: Não registada | Em serviço | Em pausa | Concluída | Ausente
$_hasCompletePeriod = false;
foreach ($pontosHoje as $_p) {
    if (!empty($_p['hora_entrada']) && !empty($_p['hora_saida'])) { $_hasCompletePeriod = true; break; }
}
if ($presencaHoje && mb_strtolower(trim((string)($presencaHoje['status'] ?? ''))) === 'ausente') {
    $estadoPresencaLabel = 'Ausente';
    $estadoPresencaBadge = 'badge-danger';
} elseif (!$lastPonto) {
    $estadoPresencaLabel = 'Não registada';
    $estadoPresencaBadge = 'badge-warning';
} elseif ($pontoAberto) {
    $estadoPresencaLabel = 'Em serviço';
    $estadoPresencaBadge = 'badge-success';
} elseif ($emPausa) {
    $estadoPresencaLabel = 'Em pausa';
    $estadoPresencaBadge = 'badge-warning';
} elseif ($_hasCompletePeriod) {
    $confirmacao = mb_strtolower(trim((string)($lastPonto['status_confirmacao'] ?? '')));
    if ($confirmacao === 'confirmado') {
        $estadoPresencaLabel = 'Concluída';
        $estadoPresencaBadge = 'badge-success';
    } else {
        $estadoPresencaLabel = 'Concluído';
        $estadoPresencaBadge = 'badge-secondary';
    }
} else {
    $estadoPresencaLabel = 'Não registada';
    $estadoPresencaBadge = 'badge-warning';
}

// Roteiro do dia — lista cronológica de eventos (Entrada, Pausa, Regresso, Saída)
$_timeline      = [];
$_lastSaiTime   = null;
$_pontosTotal   = count($pontosHoje);
foreach ($pontosHoje as $_ti => $_tp) {
    $hEnt    = substr((string)($_tp['hora_entrada'] ?? ''), 0, 5);
    $hSai    = substr((string)($_tp['hora_saida']   ?? ''), 0, 5);
    $obs     = trim((string)($_tp['observacao'] ?? ''));
    $obsLow  = mb_strtolower($obs);

    // Entrada ou Regresso
    if ($hEnt) {
        $pausaDur = '';
        if ($_lastSaiTime) {
            $d = strtotime('today ' . $hEnt) - strtotime('today ' . $_lastSaiTime);
            if ($d > 0) $pausaDur = $d >= 3600 ? sprintf('%dh%02dm', intdiv($d,3600), intdiv($d%3600,60)) : sprintf('%dmin', round($d/60));
        }
        if ($_ti === 0) {
            $_timeline[] = ['hora' => $hEnt, 'tipo' => 'entrada',  'label' => 'Entrada',               'icon' => 'fa-sign-in-alt', 'cls' => 'tl-entrada'];
        } else {
            $_timeline[] = ['hora' => $hEnt, 'tipo' => 'regresso', 'label' => 'Regresso ao trabalho',  'icon' => 'fa-undo-alt',    'cls' => 'tl-regresso', 'pausa_dur' => $pausaDur];
        }
    }

    // Pausa ou Saída
    if ($hSai) {
        $workDur = '';
        if ($hEnt) {
            $d = strtotime('today ' . $hSai) - strtotime('today ' . $hEnt);
            if ($d > 0) $workDur = $d >= 3600 ? sprintf('%dh%02dm', intdiv($d,3600), intdiv($d%3600,60)) : sprintf('%dmin', round($d/60));
        }
        if (str_contains($obsLow, 'pausa')) {
            if      (str_contains($obsLow, 'almo')) { $icon = 'fa-utensils';    $lbl = 'Pausa Almoço';  }
            elseif  (str_contains($obsLow, 'cigar')){ $icon = 'fa-smoking';     $lbl = 'Pausa Cigarro'; }
            else                                     { $icon = 'fa-pause-circle';$lbl = 'Pausa';         }
            $_timeline[] = ['hora' => $hSai, 'tipo' => 'pausa', 'label' => $lbl, 'icon' => $icon, 'cls' => 'tl-pausa', 'work_dur' => $workDur];
        } else {
            $_timeline[] = ['hora' => $hSai, 'tipo' => 'saida', 'label' => 'Saída', 'icon' => 'fa-sign-out-alt', 'cls' => 'tl-saida', 'work_dur' => $workDur];
        }
        $_lastSaiTime = $hSai;
    } elseif ($_ti === $_pontosTotal - 1) {
        $_timeline[] = ['hora' => null, 'tipo' => 'ativo', 'label' => 'Em serviço', 'icon' => 'fa-circle', 'cls' => 'tl-ativo'];
    }
}

// Turno de referência para comparações
$turnoRef          = $turnos[0] ?? null;
$turnoHorarioInicio = $turnoRef ? (string)($turnoRef['horario_inicio'] ?? '') : '';
$turnoHorarioFim    = $turnoRef ? (string)($turnoRef['horario_fim']    ?? '') : '';
$toleranciaMin      = 15; // minutos de tolerância padrão

// Estatísticas do mês corrente — agrupadas por dia (múltiplos períodos por dia)
$totalAtrasos     = 0;
$totalIncompletos = 0;
$mesCorrente      = date('Y-m');
$_statsByDate = [];
foreach ($historicoPresencas as $reg) {
    $regDataRaw = $pontoDateColumn ? (string)($reg[$pontoDateColumn] ?? '') : (string)($reg['data_registro'] ?? $reg['data'] ?? '');
    $dk = substr($regDataRaw, 0, 10);
    if ($dk && substr($regDataRaw, 0, 7) === $mesCorrente) {
        $_statsByDate[$dk][] = $reg;
    }
}
foreach ($_statsByDate as $_dk => $_dayRecs) {
    // Primeira entrada do dia
    $_firstHEnt = '';
    foreach ($_dayRecs as $_dr) {
        if (!empty($_dr['hora_entrada'])) { $_firstHEnt = (string)$_dr['hora_entrada']; break; }
    }
    // Última saída do dia
    $_lastHSai = '';
    foreach (array_reverse($_dayRecs) as $_dr) {
        if (!empty($_dr['hora_saida'])) { $_lastHSai = (string)$_dr['hora_saida']; break; }
    }
    // Incompleto: dia passado com entrada em aberto (sem saída no último registo)
    if ($_firstHEnt && !$_lastHSai && $_dk !== $today) {
        $totalIncompletos++;
    }
    // Atraso: primeira entrada do dia depois do início do turno + tolerância
    if ($turnoHorarioInicio && $_firstHEnt) {
        $diffS = strtotime('today ' . substr($_firstHEnt, 0, 5)) - strtotime('today ' . $turnoHorarioInicio);
        if ($diffS > $toleranciaMin * 60) $totalAtrasos++;
    }
}

// Alerta de atraso actual (funcionário ainda não entrou mas devia ter)
$alertaAtraso = null;
if (!$pontoAberto && !$lastPonto && $turnoHorarioInicio) {
    $agoraTs    = time();
    $inicioTs   = strtotime('today ' . $turnoHorarioInicio);
    $diffAtMin  = (int)round(($agoraTs - $inicioTs) / 60);
    if ($diffAtMin > $toleranciaMin && $diffAtMin < 240) {
        $alertaAtraso = ['inicio' => substr($turnoHorarioInicio, 0, 5), 'minutos' => $diffAtMin];
    }
}

// Justificativas já enviadas (tabela partilhada com o admin)
$justificativas = [];
try {
    $checkJust = $pdo->query("SHOW TABLES LIKE 'justificativas_presenca'");
    if ($checkJust->rowCount() > 0) {
        $stmtJust = $pdo->prepare("
            SELECT id, data_ocorrencia AS data_ausencia, tipo, motivo, anexo_path AS documento,
                   status, admin_observacao, created_at
            FROM justificativas_presenca
            WHERE employee_id = ? AND client_id = ?
            ORDER BY data_ocorrencia DESC
            LIMIT 10
        ");
        $stmtJust->execute([$employee_id, $client_id]);
        $justificativas = $stmtJust->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar justificativas: " . $e->getMessage());
}

// ── Parser de dias da semana ──────────────────────────────────────────
function _parseDiasSemana(string $s): array {
    $map = [
        'dom' => 0, 'sun' => 0, 'domingo' => 0,
        'seg' => 1, 'mon' => 1, 'segunda' => 1,
        'ter' => 2, 'tue' => 2, 'terca' => 2, 'terça' => 2,
        'qua' => 3, 'wed' => 3, 'quarta' => 3,
        'qui' => 4, 'thu' => 4, 'quinta' => 4,
        'sex' => 5, 'fri' => 5, 'sexta' => 5,
        'sab' => 6, 'sat' => 6, 'sabado' => 6, 'sábado' => 6,
    ];
    $out = [];
    foreach (preg_split('/[,;\s\/|]+/', mb_strtolower($s)) as $p) {
        $p = trim(preg_replace('/[-_].*/', '', $p)); // remove "-feira"
        if (isset($map[$p])) $out[] = $map[$p];
        elseif (is_numeric($p) && $p >= 0 && $p <= 6) $out[] = (int)$p;
    }
    return array_unique($out);
}

// ── Grelha de presença (dias úteis do mês + registos + faltas) ───────
$workingDayNums = !empty($turnoRef['dias_semana'])
    ? _parseDiasSemana((string)$turnoRef['dias_semana'])
    : [];

// Índice dos registos por data — agrupa múltiplos períodos do mesmo dia
$recordsByDate = [];
foreach ($historicoPresencas as $r) {
    $dk = substr($pontoDateColumn
        ? (string)($r[$pontoDateColumn] ?? '')
        : (string)($r['data_registro'] ?? $r['data'] ?? ''), 0, 10);
    if ($dk) $recordsByDate[$dk][] = $r;
}

// Índice das justificativas por data
$justByDate = [];
foreach ($justificativas as $j) {
    $jdk = substr((string)($j['data_ausencia'] ?? ''), 0, 10);
    if ($jdk) $justByDate[$jdk] = $j;
}

$attendanceGrid = [];
$diasSemPt = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$mesDt     = new DateTime(date('Y-m') . '-01');
$fimDt     = new DateTime(date('Y-m-t'));
$hojeDt    = new DateTime($today);
$cur       = clone $mesDt;

while ($cur <= $fimDt && $cur <= $hojeDt) {
    $dateStr   = $cur->format('Y-m-d');
    $dow       = (int)$cur->format('w');
    $isWorkday = empty($workingDayNums) || in_array($dow, $workingDayNums);
    $cur->modify('+1 day');
    if (!$isWorkday) continue;

    $dayRecords = $recordsByDate[$dateStr] ?? [];
    $_lastRec   = !empty($dayRecords) ? end($dayRecords) : null;

    // Primeira entrada e última saída do dia
    $hEnt = '';
    foreach ($dayRecords as $_dr) {
        if (!empty($_dr['hora_entrada'])) { $hEnt = substr((string)$_dr['hora_entrada'], 0, 5); break; }
    }
    $hSai = '';
    foreach (array_reverse($dayRecords) as $_dr) {
        if (!empty($_dr['hora_saida'])) { $hSai = substr((string)$_dr['hora_saida'], 0, 5); break; }
    }
    // Total de horas = soma de todos os pares completos do dia
    $_totalSegs = 0;
    foreach ($dayRecords as $_dr) {
        $_e = (string)($_dr['hora_entrada'] ?? '');
        $_s = (string)($_dr['hora_saida']   ?? '');
        if ($_e && $_s) {
            $_d = strtotime('today ' . $_s) - strtotime('today ' . $_e);
            if ($_d > 0) $_totalSegs += $_d;
        }
    }
    $horas = $_totalSegs > 0 ? sprintf('%dh%02dm', floor($_totalSegs / 3600), floor(($_totalSegs % 3600) / 60)) : '';

    // Status do dia
    $_lastIsOpen = $_lastRec && !empty($_lastRec['hora_entrada']) && empty($_lastRec['hora_saida']);
    if (empty($dayRecords))                            $gridStatus = 'falta';
    elseif ($_lastIsOpen && $dateStr !== $today)       $gridStatus = 'incompleto';
    else                                               $gridStatus = 'presente';

    $cLbl = ''; $cCls = '';
    if ($turnoHorarioInicio && $hEnt) {
        $dm = (int)round((strtotime('today ' . $hEnt) - strtotime('today ' . $turnoHorarioInicio)) / 60);
        if ($dm > $toleranciaMin)   { $cLbl = "Atraso {$dm}min"; $cCls = 'comp-badge--late'; }
        elseif ($dm < -5)           { $cLbl = 'Antecipado';      $cCls = 'comp-badge--early'; }
        else                        { $cLbl = 'A tempo';          $cCls = 'comp-badge--ok'; }
    }

    $just     = $justByDate[$dateStr] ?? null;
    $obsDay   = implode(' | ', array_filter(array_map(fn($_dr) => trim((string)($_dr['observacao'] ?? '')), $dayRecords)));
    $confDay  = $_lastRec ? (string)($_lastRec['status_confirmacao'] ?? 'pendente') : '';

    $_periodos = [];
    foreach ($dayRecords as $_dr) {
        $_pe = substr((string)($_dr['hora_entrada'] ?? ''), 0, 5);
        if ($_pe) $_periodos[] = [
            'entrada' => $_pe,
            'saida'   => substr((string)($_dr['hora_saida'] ?? ''), 0, 5),
            'obs'     => trim((string)($_dr['observacao'] ?? '')),
        ];
    }

    $attendanceGrid[] = [
        'date'        => $dateStr,
        'date_fmt'    => date('d/m/Y', strtotime($dateStr)),
        'weekday'     => $diasSemPt[$dow],
        'status'      => $gridStatus,
        'entrada'     => $hEnt,
        'saida'       => $hSai,
        'horas'       => $horas,
        'comp_lbl'    => $cLbl,
        'comp_cls'    => $cCls,
        'obs'         => $obsDay,
        'confirm'     => $confDay,
        'periodos'    => $_periodos,
        'just_status'  => $just ? (string)($just['status']             ?? 'pendente') : '',
        'just_tipo'    => $just ? (string)($just['tipo']              ?? '') : '',
        'just_motivo'  => $just ? (string)($just['motivo']            ?? '') : '',
        'just_obs'     => $just ? (string)($just['admin_observacao']  ?? '') : '',
        'just_doc'     => $just ? (string)($just['anexo_path']        ?? '') : '',
        'just_at'      => $just ? substr((string)($just['created_at'] ?? ''), 0, 16) : '',
        'n_periodos'   => count($dayRecords),
    ];
}
$attendanceGrid = array_reverse($attendanceGrid); // mais recente primeiro
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Funcionário - RH Neto</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="portal.css">
    <style>
        /* ── Roteiro do Dia ── */
        .roteiro-dia {
            margin: 0.9rem 0 0.6rem;
            padding: 0 0.25rem;
        }
        .roteiro-titulo {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--neutral-400);
            margin-bottom: 0.6rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .roteiro-evento {
            display: grid;
            grid-template-columns: 3rem 1.25rem 1fr;
            align-items: flex-start;
            gap: 0 0.5rem;
            position: relative;
        }
        .roteiro-hora {
            font-size: 0.8rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: var(--neutral-300);
            text-align: right;
            padding-top: 0.05rem;
            line-height: 1.4rem;
        }
        .roteiro-dot-col {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .roteiro-dot {
            width: 0.75rem;
            height: 0.75rem;
            border-radius: 50%;
            border: 2px solid currentColor;
            background: var(--neutral-900);
            flex-shrink: 0;
            margin-top: 0.3rem;
        }
        .roteiro-line {
            width: 2px;
            flex: 1;
            min-height: 1.6rem;
            background: rgba(255,255,255,0.1);
            margin-bottom: -2px;
        }
        .roteiro-info {
            padding-bottom: 1.1rem;
        }
        .roteiro-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--neutral-200);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
            line-height: 1.4rem;
        }
        .roteiro-label .fas { font-size: 0.78rem; }
        .roteiro-dur {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.1rem 0.45rem;
            border-radius: 99px;
            background: rgba(255,255,255,0.08);
            color: var(--neutral-400);
        }
        .roteiro-dur--pausa { background: rgba(245,158,11,0.15); color: #f59e0b; }
        /* Colors per event type */
        .tl-entrada  .roteiro-dot, .tl-entrada  .roteiro-label { color: #22c55e; }
        .tl-regresso .roteiro-dot, .tl-regresso .roteiro-label { color: #4ade80; }
        .tl-pausa    .roteiro-dot, .tl-pausa    .roteiro-label { color: #f59e0b; }
        .tl-saida    .roteiro-dot, .tl-saida    .roteiro-label { color: #f87171; }
        .tl-ativo    .roteiro-dot, .tl-ativo    .roteiro-label { color: #38bdf8; }
        .tl-entrada  .roteiro-dot { background: #22c55e; }
        .tl-regresso .roteiro-dot { background: #4ade80; }
        .tl-pausa    .roteiro-dot { background: #f59e0b; }
        .tl-saida    .roteiro-dot { background: #f87171; }
        .tl-ativo    .roteiro-dot { background: #38bdf8; }
        /* ── Paginação da tabela de presenças ── */
        .pg-hidden { display: none !important; }
        .pg-bar-wrap { padding: .75rem 0 .25rem; display: flex; justify-content: center; }
        .pg-bar { display: flex; align-items: center; gap: .3rem; flex-wrap: wrap; justify-content: center; }
        .pg-btn {
            min-width: 2rem; height: 2rem; padding: 0 .5rem;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 6px;
            background: transparent;
            color: var(--neutral-300);
            font-size: .8rem; font-weight: 600; cursor: pointer;
            transition: background .15s, color .15s;
        }
        .pg-btn:hover:not(:disabled) { background: rgba(255,255,255,.08); color: #fff; }
        .pg-btn:disabled { opacity: .35; cursor: default; }
        .pg-btn.pg-active { background: var(--primary-600); color: #fff; border-color: var(--primary-600); }
        .pg-ellipsis { color: var(--neutral-500); font-size: .8rem; padding: 0 .2rem; line-height: 2rem; }
        .pg-info { font-size: .72rem; color: var(--neutral-500); margin-left: .5rem; white-space: nowrap; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
        @keyframes pulse-dot { 0%,100%{box-shadow:0 0 0 0 rgba(56,189,248,0.5)} 50%{box-shadow:0 0 0 5px rgba(56,189,248,0)} }
        .tl-ativo .roteiro-dot { animation: pulse-dot 1.8s ease-in-out infinite; }
        .tl-ativo .roteiro-hora { color: #38bdf8; }
        .tl-ativo-blink { animation: blink 1.4s ease-in-out infinite; font-size: 0.72rem; }
        .blink-dot { animation: blink 1.4s ease-in-out infinite; font-size: 0.55rem; vertical-align: middle; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="user-info">
                <div class="avatar" id="navAvatar">
                    <?php if (!empty($employee['profile_picture'])): ?>
                        <img src="../<?php echo htmlspecialchars($employee['profile_picture']); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else:
                        $initials = implode('', array_map(function($n) { return strtoupper($n[0]); }, explode(' ', $employee_name)));
                        echo htmlspecialchars(substr($initials, 0, 2));
                    endif; ?>
                </div>
                <div>
                    <h2 style="margin-bottom: 0.25rem;">Olá, <?php echo htmlspecialchars(explode(' ', $employee_name)[0]); ?>!</h2>
                    <p style="opacity: 0.9; font-size: 0.9rem;"><?php echo htmlspecialchars($employee['position'] ?? 'Funcionário'); ?></p>
                </div>
            </div>
            
            <div style="display:flex;align-items:center;gap:.5rem">
                <button type="button" class="header-bell-btn" id="header-bell-btn" title="Notificações" aria-label="Notificações">
                    <i class="fas fa-bell"></i>
                    <span class="header-bell-badge" id="header-bell-badge"<?= $unreadCount === 0 ? ' style="display:none"' : '' ?>><?= $unreadCount ?></span>
                </button>
                <button type="button" class="portal-menu-btn" id="portal-menu-btn" aria-label="Abrir menu" aria-controls="portal-nav" aria-expanded="false">
                    <i id="portal-menu-icon" class="fas fa-bars"></i>
                    Menu
                </button>
            </div>
        </div>
    </div>

    <div class="container">
        <nav class="portal-nav" id="portal-nav" aria-label="Navegação do portal">
            <button type="button" class="nav-btn active" data-section="home-section">
                <i class="fas fa-home"></i>
                Início
            </button>
            <button type="button" class="nav-btn" data-section="presenca-section">
                <i class="fas fa-user-check"></i>
                Presença & Ponto
            </button>
            <button type="button" class="nav-btn" data-section="gorjeta-section">
                <i class="fas fa-coins"></i>
                Gorjeta & Salário
            </button>
            <button type="button" class="nav-btn" data-section="recibos-section">
                <i class="fas fa-file-invoice-dollar"></i>
                Recibos
            </button>
            <button type="button" class="nav-btn" data-section="turnos-section">
                <i class="fas fa-business-time"></i>
                Turnos
            </button>
            <button type="button" class="nav-btn" data-section="ferias-section">
                <i class="fas fa-umbrella-beach"></i>
                Férias
            </button>
            <button type="button" class="nav-btn" data-section="definicoes-section">
                <i class="fas fa-cog"></i>
                Definições
            </button>
        </nav>

        <!-- ===== HOME / DASHBOARD ===== -->
        <section id="home-section" class="portal-section active">
            <h3 class="section-title"><i class="fas fa-home"></i> Início</h3>

            <!-- Hero: estado do dia -->
            <div class="card dashboard-hero-card">
                <div class="dashboard-hero-row">
                    <div class="dashboard-hero-date">
                        <span class="dashboard-hero-day"><?php echo date('d'); ?></span>
                        <div>
                            <div class="dashboard-hero-weekday"><?php
                                $diasSemana = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
                                echo $diasSemana[(int)date('w')];
                            ?></div>
                            <div class="dashboard-hero-month"><?php
                                $mesesAno = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
                                echo $mesesAno[(int)date('n') - 1] . ' ' . date('Y');
                            ?></div>
                        </div>
                    </div>
                    <div class="dashboard-hero-status">
                        <div class="ponto-status <?= $_pStatusClass ?>" id="ponto-status-display">
                            <i class="fas <?= $_pStatusIcon ?>"></i>
                            <div>
                                <span><?= htmlspecialchars($_pStatusLabel) ?></span>
                                <strong><?= htmlspecialchars($_pStatusHora) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dashboard-quick-btns">
                    <?php if ($semPonto): ?>
                        <button class="btn btn-success btn-ponto-action" onclick="registrarPonto('entrada')">
                            <i class="fas fa-sign-in-alt"></i> Entrada
                        </button>
                    <?php elseif ($pontoAberto && !$_hasCompletePeriod): ?>
                        <button class="btn btn-warning btn-ponto-action" onclick="registrarPausa()">
                            <i class="fas fa-pause-circle"></i> Pausa
                        </button>
                        <button class="btn btn-danger btn-ponto-action" onclick="registrarPonto('saida')">
                            <i class="fas fa-sign-out-alt"></i> Saída
                        </button>
                    <?php elseif ($pontoAberto && $_hasCompletePeriod): ?>
                        <button class="btn btn-warning btn-ponto-action" onclick="registrarPausa()">
                            <i class="fas fa-pause-circle"></i> Pausa
                        </button>
                        <button class="btn btn-danger btn-ponto-action" onclick="registrarPonto('saida')">
                            <i class="fas fa-sign-out-alt"></i> Saída
                        </button>
                    <?php elseif ($emPausa): ?>
                        <button class="btn btn-success btn-ponto-action" onclick="registrarRegresso()">
                            <i class="fas fa-undo-alt"></i> Regresso
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-primary" onclick="openGorjetaModal()">
                        <i class="fas fa-coins"></i> Gorjeta
                    </button>
                </div>
            </div>

            <!-- KPIs -->
            <div class="kpi-grid">
                <div class="kpi-card kpi-blue">
                    <div class="kpi-icon"><i class="fas fa-clock"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-value"><?php echo number_format($totalHorasMes, 1); ?>h</span>
                        <span class="kpi-label">Horas este mês</span>
                        <span class="kpi-sub"><?php echo $totalDiasTrabalhados; ?> dias trabalhados</span>
                    </div>
                </div>
                <div class="kpi-card kpi-green">
                    <div class="kpi-icon"><i class="fas fa-coins"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-value"><?php echo $gorjetasMesFormatted; ?></span>
                        <span class="kpi-label">Gorjetas este mês</span>
                        <span class="kpi-sub"><?php echo count($gorjetas); ?> <?php echo count($gorjetas) === 1 ? 'registo' : 'registos'; ?></span>
                    </div>
                </div>
                <div class="kpi-card kpi-purple">
                    <div class="kpi-icon"><i class="fas fa-wallet"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-value"><?php echo $estimativaTotalFormatted; ?></span>
                        <span class="kpi-label">Estimativa mensal</span>
                        <span class="kpi-sub">Base + gorjetas</span>
                    </div>
                </div>
                <?php $nTrocasPendentes = count($trocasTurnoRecebidas); ?>
                <div class="kpi-card kpi-orange<?php echo $nTrocasPendentes > 0 ? ' kpi-alert' : ''; ?>">
                    <div class="kpi-icon"><i class="fas fa-exchange-alt"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-value"><?php echo $nTrocasPendentes; ?></span>
                        <span class="kpi-label">Trocas pendentes</span>
                        <span class="kpi-sub">Para a sua aprovação</span>
                    </div>
                </div>
                <?php
                $diasFeriasTotal = max(22, (int)($employee['vacation_days'] ?? 22));
                $diasFeriasDisp  = max(0, $diasFeriasTotal - $diasUsadosAno);
                $feriasAlerta    = $diasUsadosAno >= $diasFeriasTotal;
                ?>
                <div class="kpi-card kpi-teal<?php echo $feriasAlerta ? ' kpi-alert' : ''; ?>">
                    <div class="kpi-icon"><i class="fas fa-umbrella-beach"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-value" id="kpi-ferias-disp"><?php echo $diasFeriasDisp; ?></span>
                        <span class="kpi-label">Dias de férias disponíveis</span>
                        <span class="kpi-sub" id="kpi-ferias-sub"><?php echo $diasUsadosAno; ?>/<?php echo $diasFeriasTotal; ?> usados</span>
                    </div>
                </div>
            </div>

            <!-- Turno activo -->
            <?php if (!empty($turnos)): $turnoAtivo = $turnos[0]; ?>
            <div class="card dashboard-turno">
                <div class="card-header">
                    <i class="fas fa-business-time"></i>
                    <h3>Turno Activo</h3>
                </div>
                <div class="dashboard-turno-row">
                    <span class="dashboard-turno-tipo"><?php echo htmlspecialchars($turnoAtivo['turno_tipo']); ?></span>
                    <span class="dashboard-turno-horario">
                        <i class="fas fa-clock"></i>
                        <?php echo htmlspecialchars(substr($turnoAtivo['horario_inicio'], 0, 5) . ' — ' . substr($turnoAtivo['horario_fim'], 0, 5)); ?>
                    </span>
                    <span class="dashboard-turno-dias">
                        <i class="fas fa-calendar"></i>
                        <?php echo htmlspecialchars($turnoAtivo['dias_semana']); ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Atalhos para todas as secções -->
            <div class="shortcuts-grid">
                <button class="shortcut-card" data-section="presenca-section">
                    <i class="fas fa-user-check shortcut-icon sc-blue"></i>
                    <span class="shortcut-label">Presença</span>
                </button>
                <button class="shortcut-card" data-section="turnos-section">
                    <i class="fas fa-business-time shortcut-icon sc-purple"></i>
                    <span class="shortcut-label">Turnos</span>
                </button>
                <button class="shortcut-card" data-section="ferias-section">
                    <i class="fas fa-umbrella-beach shortcut-icon sc-teal"></i>
                    <span class="shortcut-label">Férias</span>
                </button>
                <button class="shortcut-card" data-section="gorjeta-section">
                    <i class="fas fa-coins shortcut-icon sc-green"></i>
                    <span class="shortcut-label">Gorjetas</span>
                </button>
                <button class="shortcut-card" data-section="sms-section">
                    <i class="fas fa-sms shortcut-icon sc-orange"></i>
                    <span class="shortcut-label">Mensagens</span>
                </button>
                <button class="shortcut-card" data-section="recibos-section">
                    <i class="fas fa-file-invoice-dollar shortcut-icon sc-indigo"></i>
                    <span class="shortcut-label">Recibos</span>
                </button>
                <button class="shortcut-card" data-section="definicoes-section">
                    <i class="fas fa-cog shortcut-icon sc-gray"></i>
                    <span class="shortcut-label">Definições</span>
                </button>
            </div>
        </section>

        <section id="presenca-section" class="portal-section">
            <h3 class="section-title"><i class="fas fa-user-check"></i> Presença &amp; Ponto</h3>

            <?php if ($alertaAtraso): ?>
            <div class="presenca-alert-banner">
                <i class="fas fa-exclamation-triangle"></i>
                O seu turno começou às <strong><?= htmlspecialchars($alertaAtraso['inicio']) ?></strong>.
                Está com <strong><?= $alertaAtraso['minutos'] ?> minutos de atraso</strong> — registe a entrada agora.
            </div>
            <?php endif; ?>

            <div class="presenca-stats-row">
                <div class="stat-pill">
                    <span class="stat-pill-value"><?= number_format($totalHorasMes, 1) ?>h</span>
                    <span class="stat-pill-label">Horas mês</span>
                </div>
                <div class="stat-pill">
                    <span class="stat-pill-value"><?= $totalDiasTrabalhados ?></span>
                    <span class="stat-pill-label">Dias trabalhados</span>
                </div>
                <div class="stat-pill <?= $totalAtrasos > 0 ? 'stat-pill--alert' : '' ?>">
                    <span class="stat-pill-value"><?= $totalAtrasos ?></span>
                    <span class="stat-pill-label">Atrasos</span>
                </div>
                <div class="stat-pill <?= $totalIncompletos > 0 ? 'stat-pill--alert' : '' ?>">
                    <span class="stat-pill-value"><?= $totalIncompletos ?></span>
                    <span class="stat-pill-label">Incompletos</span>
                </div>
            </div>

            <div class="grid">
                <!-- Card: Registo de Ponto -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock"></i>
                        <h3>Registo de Ponto</h3>
                    </div>

                    <?php if ($lastPonto): ?>
                        <div class="info-row">
                            <span class="info-label">Última marcação</span>
                            <span class="info-value">
                                <?= htmlspecialchars($_pStatusLabel . ' às ' . $_pStatusHora) ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Estado actual</span>
                            <?php if ($pontoAberto): ?>
                                <span class="status-badge badge-success">Em serviço</span>
                            <?php elseif ($emPausa): ?>
                                <span class="status-badge badge-warning">Em pausa</span>
                            <?php elseif (!empty($lastPonto)): ?>
                                <span class="status-badge badge-secondary">Dia concluído</span>
                            <?php else: ?>
                                <span class="status-badge badge-secondary">Sem registo</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-hint">Nenhum ponto registado hoje.</p>
                    <?php endif; ?>

                    <div class="btn-grid">
                        <?php if ($semPonto): ?>
                            <button class="btn btn-success btn-ponto-action" onclick="registrarPonto('entrada')">
                                <i class="fas fa-sign-in-alt"></i> Entrada
                            </button>
                        <?php elseif ($pontoAberto && !$_hasCompletePeriod): ?>
                            <button class="btn btn-warning btn-ponto-action" onclick="registrarPausa()">
                                <i class="fas fa-pause-circle"></i> Pausa
                            </button>
                            <button class="btn btn-danger btn-ponto-action" onclick="registrarPonto('saida')">
                                <i class="fas fa-sign-out-alt"></i> Saída
                            </button>
                        <?php elseif ($pontoAberto && $_hasCompletePeriod): ?>
                            <button class="btn btn-warning btn-ponto-action" onclick="registrarPausa()">
                                <i class="fas fa-pause-circle"></i> Pausa
                            </button>
                            <button class="btn btn-danger btn-ponto-action" onclick="registrarPonto('saida')">
                                <i class="fas fa-sign-out-alt"></i> Saída
                            </button>
                        <?php elseif ($emPausa): ?>
                            <button class="btn btn-success btn-ponto-action" onclick="registrarRegresso()">
                                <i class="fas fa-undo-alt"></i> Regresso
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Card: Presença Hoje -->
                <div class="card" id="card-presenca-hoje">
                    <div class="card-header">
                        <i class="fas fa-calendar-check"></i>
                        <h3>Presença Hoje</h3>
                        <span class="status-badge <?= $estadoPresencaBadge ?>" id="presenca-estado-badge"><?= htmlspecialchars($estadoPresencaLabel) ?></span>
                    </div>

                    <!-- Campo oculto para o live timer usar a entrada do período actual -->
                    <input type="hidden" id="periodo-entrada-atual" value="<?= htmlspecialchars($entradaPeriodoAtual) ?>">

                    <!-- Roteiro cronológico do dia -->
                    <?php if (!empty($_timeline)): ?>
                    <div class="roteiro-dia">
                        <div class="roteiro-titulo"><i class="fas fa-route"></i> Roteiro do dia</div>
                        <?php foreach ($_timeline as $_ei => $_ev):
                            $_isLast = ($_ei === count($_timeline) - 1);
                        ?>
                        <div class="roteiro-evento <?= htmlspecialchars($_ev['cls']) ?>">
                            <div class="roteiro-hora">
                                <?php if ($_ev['hora']): ?>
                                    <?= htmlspecialchars($_ev['hora']) ?>
                                <?php else: ?>
                                    <span class="tl-ativo-blink" style="font-size:0.65rem">agora</span>
                                <?php endif; ?>
                            </div>
                            <div class="roteiro-dot-col">
                                <div class="roteiro-dot"></div>
                                <?php if (!$_isLast): ?><div class="roteiro-line"></div><?php endif; ?>
                            </div>
                            <div class="roteiro-info">
                                <div class="roteiro-label">
                                    <i class="fas <?= htmlspecialchars($_ev['icon']) ?>"></i>
                                    <?= htmlspecialchars($_ev['label']) ?>
                                    <?php if (!empty($_ev['work_dur'])): ?>
                                        <span class="roteiro-dur"><?= htmlspecialchars($_ev['work_dur']) ?> trabalhado</span>
                                    <?php endif; ?>
                                    <?php if (!empty($_ev['pausa_dur'])): ?>
                                        <span class="roteiro-dur roteiro-dur--pausa"><?= htmlspecialchars($_ev['pausa_dur']) ?> de pausa</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="info-row">
                        <span class="info-label">Funcionário</span>
                        <span class="info-value"><?= htmlspecialchars($employee_name) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Data</span>
                        <span class="info-value"><?= date('d/m/Y') ?></span>
                    </div>
                    <!-- Quando pontoAberto, o live timer já mostra o total combinado → esconder a row estática -->
                    <div class="info-row" id="presenca-horas-row" <?= (!$horasHojeLabel || $pontoAberto) ? 'style="display:none"' : '' ?>>
                        <span class="info-label">Total hoje</span>
                        <span class="info-value presenca-horas-valor" id="presenca-horas-valor" style="color:var(--accent-500);font-weight:700"><?= htmlspecialchars($horasHojeLabel) ?></span>
                    </div>
                    <div class="info-row live-timer-row" id="live-timer-row" <?= !$pontoAberto ? 'style="display:none"' : '' ?>>
                        <span class="info-label"><i class="fas fa-circle live-dot"></i> Total hoje</span>
                        <span class="info-value live-timer" id="live-timer">--h --min</span>
                    </div>
                    <!-- Campos ocultos para o JS -->
                    <input type="hidden" id="horas-base-hoje" value="<?= (int)$_totalSegsHoje ?>">
                    <span id="presenca-entrada-valor" style="display:none"><?= htmlspecialchars($entradaHoje) ?></span>
                    <span id="presenca-saida-valor"   style="display:none"><?= htmlspecialchars($saidaHoje) ?></span>
                </div>
            </div>

            <div class="card attendance-history-card">
                <div class="card-header" style="flex-wrap:wrap;gap:0.5rem">
                    <i class="fas fa-history"></i>
                    <h3>Histórico de Presenças</h3>
                    <div class="history-header-actions">
                        <div class="month-nav">
                            <button class="month-nav-btn" id="btn-mes-anterior" title="Mês anterior"><i class="fas fa-chevron-left"></i></button>
                            <span class="month-nav-label" id="history-mes-label"><?= date('F Y') ?></span>
                            <button class="month-nav-btn" id="btn-mes-seguinte" title="Mês seguinte" disabled><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <a href="exportar_historico.php?mes=<?= date('Y-m') ?>" class="btn btn-primary btn-export" target="_blank" rel="noopener"><i class="fas fa-download"></i> Exportar</a>
                        <button type="button" id="btn-cal-toggle" class="btn btn-primary btn-cal-toggle" onclick="_toggleCalView()"><i class="fas fa-calendar-alt"></i> Calendário</button>
                        <button type="button" class="btn btn-primary btn-history-toggle" onclick="toggleAttendanceHistory(this)">
                            <i class="fas fa-eye"></i> Ver
                        </button>
                    </div>
                </div>

                <!-- Pesquisa rápida por data -->
                <div class="history-filters" id="history-filters" style="display:none">
                    
                    <div class="history-period-btns">
                        <button class="period-btn active" data-period="all">Tudo</button>
                        <button class="period-btn" data-period="present">Presentes</button>
                        <button class="period-btn" data-period="falta">Faltas</button>
                        <button class="period-btn" data-period="incomplete">Incompletos</button>
                    </div>
                </div>

                <div id="attendanceHistoryPanel" class="attendance-history-panel">
                    <div id="attendance-table-wrap">
                    <?php if (!empty($attendanceGrid)): ?>
                        <table class="presence-table" id="presence-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Estado</th>
                                    <th>Horas</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($attendanceGrid as $row):
                                $pStatus   = $row['status'];      // presente | falta | incompleto
                                $jStatus   = $row['just_status']; // '' | pendente | aprovada | rejeitada

                                // Estado composto (presença + justificativa)
                                if ($pStatus === 'presente') {
                                    $badgeLbl = 'Presente';  $badgeCls = 'badge-success'; $displayStatus = 'presente';
                                } elseif ($pStatus === 'incompleto') {
                                    $badgeLbl = 'Incompleto'; $badgeCls = 'badge-warning'; $displayStatus = 'incompleto';
                                } elseif ($jStatus === 'aprovada') {
                                    $badgeLbl = 'Aprovada';  $badgeCls = 'badge-success'; $displayStatus = 'just-aprovada';
                                } elseif ($jStatus === 'rejeitada') {
                                    $badgeLbl = 'Rejeitada'; $badgeCls = 'badge-danger';  $displayStatus = 'just-rejeitada';
                                } elseif ($jStatus) {
                                    $badgeLbl = 'Pendente';  $badgeCls = 'badge-warning'; $displayStatus = 'just-pendente';
                                } else {
                                    $badgeLbl = 'Falta';     $badgeCls = 'badge-danger';  $displayStatus = 'falta';
                                }

                                $rowDataJson = htmlspecialchars(json_encode([
                                    'date'     => $row['date'],
                                    'date_fmt' => $row['date_fmt'],
                                    'weekday'  => $row['weekday'],
                                    'entrada'  => $row['entrada'],
                                    'saida'    => $row['saida'],
                                    'horas'    => $row['horas'],
                                    'comp_lbl' => $row['comp_lbl'],
                                    'comp_cls' => $row['comp_cls'],
                                    'obs'      => $row['obs'],
                                    'confirm'  => $row['confirm'],
                                    'periodos' => $row['periodos'],
                                ]), ENT_QUOTES);
                            ?>
                                <?php
                                    $_justJson = htmlspecialchars(json_encode([
                                        'data_fmt'   => $row['date_fmt'],
                                        'tipo'       => $row['just_tipo'],
                                        'motivo'     => $row['just_motivo'],
                                        'status'     => $row['just_status'],
                                        'obs'        => $row['just_obs'],
                                        'doc'        => $row['just_doc'],
                                        'enviado_em' => $row['just_at'],
                                    ]), ENT_QUOTES);
                                ?>
                                <tr class="presence-row" data-status="<?= $displayStatus ?>" data-date="<?= $row['date'] ?>">
                                    <td class="presence-date">
                                        <span class="presence-date-day"><?= $row['date_fmt'] ?></span>
                                        <span class="presence-date-week"><?= $row['weekday'] ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $badgeCls ?>"><?= $badgeLbl ?></span>
                                    </td>
                                    <td class="presence-horas">
                                        <?php if ($row['horas']): ?>
                                            <span style="font-weight:700;color:var(--accent-500)"><?= htmlspecialchars($row['horas']) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--neutral-500)">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="presence-action">
                                        <?php if ($pStatus === 'presente' || $pStatus === 'incompleto'): ?>
                                            <button class="btn-action btn-ver" data-row='<?= $rowDataJson ?>' onclick="verDetalhePresenca(this)">
                                                <i class="fas fa-eye"></i> Ver
                                            </button>
                                        <?php elseif ($displayStatus === 'falta'): ?>
                                            <button class="btn-action btn-justificar" data-date="<?= $row['date'] ?>" data-fmt="<?= $row['date_fmt'] ?>" onclick="justificarFalta(this)">
                                                <i class="fas fa-file-alt"></i> Justificar
                                            </button>
                                        <?php elseif ($displayStatus === 'just-rejeitada'): ?>
                                            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                                                <button class="btn-action btn-justificar" data-date="<?= $row['date'] ?>" data-fmt="<?= $row['date_fmt'] ?>" onclick="justificarFalta(this)">
                                                    <i class="fas fa-redo"></i> Re-enviar
                                                </button>
                                                <button class="btn-action btn-ver-just" data-just='<?= $_justJson ?>' onclick="verJustificacao(this)">
                                                    <i class="fas fa-eye"></i> Ver
                                                </button>
                                            </div>
                                        <?php elseif ($displayStatus === 'just-pendente'): ?>
                                            <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
                                                <span class="btn-action btn-just-sent">
                                                    <i class="fas fa-clock"></i> Aguarda
                                                </span>
                                                <button class="btn-action btn-ver-just" data-just='<?= $_justJson ?>' onclick="verJustificacao(this)">
                                                    <i class="fas fa-eye"></i> Ver
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
                                                <span class="btn-action btn-just-ok">
                                                    <i class="fas fa-check-circle"></i> Aprovada
                                                </span>
                                                <button class="btn-action btn-ver-just" data-just='<?= $_justJson ?>' onclick="verJustificacao(this)">
                                                    <i class="fas fa-eye"></i> Ver
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <p>Sem dias úteis registados este mês.</p>
                        </div>
                    <?php endif; ?>
                    </div>
                    <div id="history-ajax-list" style="display:none"></div>
                    <div id="attendance-calendar-wrap" style="display:none;padding:1rem 0"></div>
                    <div id="presence-pagination" class="pg-bar-wrap"></div>
                </div>
            </div>

            <!-- Secção Justificativas de Ausência -->
            <?php
            $tiposJust = [
                'doenca'                  => 'Doença',
                'consulta_medica'         => 'Consulta Médica',
                'assistencia_familiar'    => 'Assistência a Familiar',
                'falecimento_familiar'    => 'Falecimento de Familiar',
                'casamento'               => 'Casamento',
                'maternidade_paternidade' => 'Maternidade / Paternidade',
                'formacao_profissional'   => 'Formação Profissional',
                'convocacao_judicial'     => 'Convocação Judicial',
                'acidente'                => 'Acidente',
                'transporte'              => 'Problema de Transporte',
                'motivo_pessoal'          => 'Motivo Pessoal',
                'outro'                   => 'Outro',
            ];
            ?>
            
        </section>

        <section id="sms-section" class="portal-section">
            <h3 class="section-title"><i class="fas fa-bell"></i> Notificações</h3>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-inbox"></i>
                    <h3>Últimas Mensagens</h3>
                    <button id="sms-notif-perm-btn" class="btn-sms-notify-perm" onclick="requestSMSNotificationPermission()" title="Ativar notificações do browser">
                        <i class="fas fa-bell-slash"></i> Ativar notificações
                    </button>
                </div>
                <div style="padding:.75rem 1rem .25rem">
                    <input type="search" id="sms-search-input"
                           placeholder="Pesquisar mensagens…"
                           oninput="filterSMSMessages(this.value)"
                           autocomplete="off"
                           style="width:100%;padding:.45rem .75rem;border-radius:8px;border:1px solid var(--neutral-700,#334155);background:var(--neutral-800,#1e293b);color:inherit;font-size:.875rem;outline:none">
                </div>
                <div class="sms-filter-tabs">
                    <button class="sms-tab active" data-filter="all" onclick="setSMSStateFilter('all')">Todas</button>
                    <button class="sms-tab" data-filter="unread" onclick="setSMSStateFilter('unread')">Não lidas</button>
                    <button class="sms-tab" data-filter="read" onclick="setSMSStateFilter('read')">Lidas</button>
                </div>
                <?php if (count($mensagens) > 0 && $mensagensSource === 'notificacoes'): ?>
                    <div class="sms-actions">
                        <label class="sms-select-all">
                            <input type="checkbox" id="smsSelectAll" data-role="sms-select-all" onchange="toggleAllSMS(this)">
                            Selecionar todas
                        </label>
                        <button type="button" class="btn btn-secondary" onclick="markAllSMSRead()">
                            <i class="fas fa-check-double"></i>
                            Marcar todas lidas
                        </button>
                        <button type="button" class="btn btn-danger btn-sms-delete" id="deleteSelectedSmsBtn" onclick="deleteSelectedSMS()" disabled>
                            <i class="fas fa-trash-alt"></i>
                            Eliminar selecionadas
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="deleteAllSMS()">
                            <i class="fas fa-trash"></i>
                            Limpar tudo
                        </button>
                    </div>
                <?php endif; ?>
                <div class="message-list">
                    <?php if (count($mensagens) > 0): ?>
                        <?php foreach ($mensagens as $m):
                            $isError   = (strpos(strtoupper($m['mensagem']), 'ERRO') !== false);
                            $isUnread  = $mensagensSource === 'notificacoes' && empty($m['lida']);
                        ?>
                            <div class="message-item<?= $isUnread ? ' message-item--unread' : '' ?>">
                                <div class="message-check-wrap">
                                    <?php if ($mensagensSource === 'notificacoes'): ?>
                                        <input
                                            type="checkbox"
                                            class="sms-checkbox"
                                            value="<?php echo (int)($m['id'] ?? 0); ?>"
                                            onchange="onSMSItemCheckboxChange()"
                                            aria-label="Selecionar SMS"
                                        >
                                    <?php endif; ?>
                                </div>
                                <div class="message-meta">
                                    <?php
                                    $msg = $m['mensagem'] ?? '';
                                    $isSistema = (
                                        stripos($msg, 'troca') !== false ||
                                        stripos($msg, 'férias') !== false ||
                                        stripos($msg, 'ferias') !== false ||
                                        stripos($msg, 'gorjeta') !== false ||
                                        stripos($msg, 'turno') !== false ||
                                        stripos($msg, 'ausência') !== false ||
                                        stripos($msg, 'justif') !== false
                                    );
                                    ?>
                                    <span class="status-badge <?php echo $isError ? 'badge-error' : ($isSistema ? 'badge-info-notif' : 'badge-success'); ?>">
                                        <?php echo $isError ? 'ALERTA' : ($isSistema ? 'SISTEMA' : 'MENSAGEM'); ?>
                                    </span>
                                    <small><?php echo $m['data_envio'] ? date('d/m H:i', strtotime($m['data_envio'])) : '--'; ?></small>
                                    <?php if ($mensagensSource === 'notificacoes'): ?>
                                        <?php if (!$isUnread): ?>
                                            <button class="btn-sms-unread" onclick="markSMSUnread(<?= (int)($m['id'] ?? 0) ?>, this)" title="Marcar como não lido" aria-label="Marcar como não lido"><i class="fas fa-envelope"></i></button>
                                        <?php endif; ?>
                                        <button class="btn-sms-item-del" onclick="deleteSingleSMS(<?= (int)($m['id'] ?? 0) ?>, this)" title="Eliminar mensagem" aria-label="Eliminar"><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                </div>
                                <?php
                                    $_msgId   = (int)($m['id'] ?? 0);
                                    $_msgText = $m['mensagem'];
                                    $_trunc   = mb_strlen($_msgText) > 150;
                                ?>
                                <?php if ($_trunc): ?>
                                    <p class="message-text"><?= htmlspecialchars(mb_substr($_msgText, 0, 150)) ?>… <button class="msg-expand-btn" data-full="<?= htmlspecialchars($_msgText, ENT_QUOTES) ?>" onclick="expandSMSMessage(this)">Ver mais</button></p>
                                <?php else: ?>
                                    <p class="message-text"><?= htmlspecialchars($_msgText) ?></p>
                                <?php endif; ?>
                                <?php if ($mensagensSource === 'notificacoes' && !empty($m['requer_confirmacao'])): ?>
                                    <div class="sms-confirm-wrap">
                                        <?php if (empty($m['confirmado_em'])): ?>
                                            <button class="btn-sms-confirm" onclick="confirmRecepcao(<?= $_msgId ?>, this)"><i class="fas fa-check"></i> Confirmar leitura</button>
                                        <?php else: ?>
                                            <span class="sms-confirmed-badge"><i class="fas fa-check-circle"></i> Confirmado</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-state">Nenhuma mensagem nova.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="gorjeta-section" class="portal-section">
            <h3 class="section-title"><i class="fas fa-coins"></i> Gorjeta e Salário</h3>
            <div class="grid">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-money-bill-wave"></i>
                        <h3>Gorjetas</h3>
                    </div>
                    <button class="btn btn-success" onclick="openGorjetaModal()" style="width: 100%;">
                        <i class="fas fa-plus"></i>
                        Registrar Gorjeta
                    </button>
                    <div class="total-gorjetas">
                        <h4>Total de Gorjetas no Mês</h4>
                        <div class="amount"><?php echo $gorjetasMesFormatted; ?></div>
                        <?php if (!empty($totaisPorPagamento)): ?>
                            <div class="gorjeta-payment-breakdown">
                                <?php foreach ($totaisPorPagamento as $_fp => $_fv): ?>
                                    <span><?php echo number_format($_fv, 2, ',', '.'); ?>€ <?php echo htmlspecialchars($_fp); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-wallet"></i>
                        <h3>Resumo Salarial</h3>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Salário Base</span>
                        <span class="info-value"><?php echo $salarioBaseFormatted; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Gorjetas do Mês</span>
                        <span class="info-value" id="resumo-gorjetas-mes"><?php echo $gorjetasMesFormatted; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Estimativa Total</span>
                        <span class="info-value" id="resumo-estimativa-total"><?php echo $estimativaTotalFormatted; ?></span>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 1.5rem;">
                <div class="card-header">
                    <i class="fas fa-history"></i>
                    <h3>Histórico de Gorjetas</h3>
                    <select id="gorjeta-mes-select" class="gorjeta-mes-select" onchange="setGorjetaMes(this.value)">
                        <?php foreach ($gorjetaMesesOpcoes as $_opt): ?>
                            <option value="<?= htmlspecialchars($_opt['value']) ?>"<?= $_opt['value'] === date('Y-m') ? ' selected' : '' ?>><?= htmlspecialchars($_opt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <a id="gorjeta-export-btn" href="exportar_gorjetas.php?mes=<?= date('Y-m') ?>" class="btn btn-primary btn-export-gorjeta" target="_blank" rel="noopener"><i class="fas fa-download"></i> Exportar</a>
                </div>
                <div class="gorjeta-filter-tabs">
                    <button class="gorjeta-tab active" data-filter="all"       onclick="setGorjetaStatusFilter('all')">Todas</button>
                    <button class="gorjeta-tab"        data-filter="pendente"  onclick="setGorjetaStatusFilter('pendente')">Pendentes</button>
                    <button class="gorjeta-tab"        data-filter="pago"      onclick="setGorjetaStatusFilter('pago')">Pagas</button>
                    <button class="gorjeta-tab"        data-filter="rejeitado" onclick="setGorjetaStatusFilter('rejeitado')">Rejeitadas</button>
                    <button class="gorjeta-tab"        data-filter="cancelada" onclick="setGorjetaStatusFilter('cancelada')">Canceladas</button>
                </div>

                <div id="gorjeta-list" data-total="<?= count($gorjetas) ?>" data-offset="<?= min(count($gorjetas), 10) ?>">
                <?php if (count($gorjetas) > 0): ?>
                    <?php foreach (array_slice($gorjetas, 0, 10) as $gorjeta):
                        $dataGorjeta = isset($gorjeta['data']) ? $gorjeta['data'] : ($gorjeta['data_registro'] ?? date('Y-m-d'));
                    ?>
                        <?php
                            $_gs      = strtolower(trim((string)($gorjeta['status'] ?? '')));
                            $_gCls    = $_gs === 'pago' ? 'badge-success' : ($_gs === 'pendente' ? 'badge-warning' : 'badge-danger');
                            $_gLbl    = $_gs === 'pago' ? 'Pago' : ($_gs === 'pendente' ? 'Pendente' : ucfirst($_gs));
                            $_gFP     = trim((string)($gorjeta['forma_pagamento'] ?? ''));
                            $_gOrig   = trim((string)($gorjeta['origem'] ?? ''));
                            $_gObs    = trim((string)($gorjeta['observacoes'] ?? $gorjeta['observacao'] ?? ''));
                            $_gMotivo = trim((string)($gorjeta['motivo_rejeicao'] ?? ''));
                        ?>
                        <div class="gorjeta-item" data-status="<?= htmlspecialchars($_gs) ?>" data-id="<?= (int)$gorjeta['id'] ?>">
                            <div style="flex:1;min-width:0">
                                <div class="gorjeta-valor"><?php echo number_format($gorjeta['valor'], 2, ',', '.'); ?>€</div>
                                <div class="gorjeta-data">
                                    <?php echo date('d/m/Y', strtotime($dataGorjeta)); ?>
                                    <?php if ($gorjeta['turno'] ?? ''): ?> • <?php echo htmlspecialchars($gorjeta['turno']); ?><?php endif; ?>
                                    <?php if ($_gFP): ?> • <?php echo htmlspecialchars($_gFP); ?><?php endif; ?>
                                </div>
                                <?php if ($_gOrig): ?>
                                    <div class="gorjeta-detail"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($_gOrig); ?></div>
                                <?php endif; ?>
                                <?php if ($_gObs): ?>
                                    <div class="gorjeta-detail"><i class="fas fa-comment"></i> <?php echo htmlspecialchars($_gObs); ?></div>
                                <?php endif; ?>
                                <?php if ($_gs === 'rejeitado' && $_gMotivo): ?>
                                    <div class="gorjeta-motivo"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($_gMotivo); ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;flex-shrink:0">
                                <span class="status-badge <?= $_gCls ?>">
                                    <?= htmlspecialchars($_gLbl) ?>
                                </span>
                                <?php if ($_gs === 'pendente'): ?>
                                <button class="btn-cancel-gorjeta" data-id="<?= (int)$gorjeta['id'] ?>" title="Cancelar gorjeta">
                                    <i class="fas fa-times"></i> Cancelar
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <p>Sem registros de gorjeta neste mês.</p>
                    </div>
                <?php endif; ?>
                </div><!-- /gorjeta-list -->
                <div id="gorjeta-load-more-wrap" style="text-align:center;padding:.6rem 1rem .9rem;<?= count($gorjetas) <= 10 ? 'display:none' : '' ?>">
                    <button id="gorjeta-load-more-btn" class="btn btn-secondary" onclick="loadMoreGorjetas()">Ver mais</button>
                </div>
            </div>
        </section>

        <section id="turnos-section" class="portal-section">
            <h3 class="section-title"><i class="fas fa-business-time"></i> Meus Turnos</h3>
            <div class="grid">
                
            
            
            
            <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>Escala de Turnos</h3>
                    </div>

                    <?php if (count($turnos) > 0): ?>
                        <?php foreach ($turnos as $turno): ?>
                            <?php $turnoFoiTrocado = !empty($turnosTrocadosIds[(int)($turno['id'] ?? 0)]); ?>
                            <div class="turno-item">
                                <div class="turno-topo">
                                    <strong><?php echo htmlspecialchars($turno['turno_tipo']); ?></strong>
                                    <span class="status-badge <?php echo $turnoFoiTrocado ? 'badge-warning' : 'badge-success'; ?>">
                                        <?php echo $turnoFoiTrocado ? 'Trocado' : 'Ativo'; ?>
                                    </span>
                                </div>
                                <div class="info-row turno-row">
                                    <span class="info-label"><i class="fas fa-clock"></i> Horário:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($turno['horario_inicio'] . ' - ' . $turno['horario_fim']); ?></span>
                                </div>
                                <div class="info-row turno-row">
                                    <span class="info-label"><i class="fas fa-calendar"></i> Dias:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($turno['dias_semana']); ?></span>
                                </div>
                                <div class="info-row turno-row">
                                    <span class="info-label"><i class="fas fa-sync"></i> Escala:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($turno['escala']); ?></span>
                                </div>
                                <?php
                                    $_diasOn = _parseDiasSemana((string)($turno['dias_semana'] ?? ''));
                                    $_calLabels = ['D','S','T','Q','Q','S','S'];
                                ?>
                                <div class="turno-calendar">
                                    <?php foreach ($_calLabels as $_ci => $_cl): ?>
                                    <div class="turno-cal-day<?= in_array($_ci, $_diasOn) ? ' turno-cal-day--on' : '' ?>">
                                        <?= $_cl ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>Nenhum turno atribuído ainda.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-exchange-alt"></i>
                        <h3>Pedir Troca de Turno</h3>
                    </div>
                    <form id="turnoSwapRequestForm">
                        <div class="form-group">
                            <label for="swap_requester_turno"><i class="fas fa-user"></i> Meu turno</label>
                            <select id="swap_requester_turno" name="requester_turno_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($turnos as $turno): ?>
                                <option value="<?php echo (int)$turno['id']; ?>">
                                    <?php echo htmlspecialchars((string)$turno['turno_tipo'] . ' | ' . substr((string)$turno['horario_inicio'], 0, 5) . '-' . substr((string)$turno['horario_fim'], 0, 5) . ' | ' . (string)$turno['dias_semana']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="swap_target_turno"><i class="fas fa-user-friends"></i> Turno do colega</label>
                            <select id="swap_target_turno" name="target_turno_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($turnosColegasAtivos as $turnoCol): ?>
                                <option value="<?php echo (int)$turnoCol['id']; ?>">
                                    <?php echo htmlspecialchars((string)$turnoCol['funcionario_nome'] . ' | ' . (string)$turnoCol['turno_tipo'] . ' | ' . substr((string)$turnoCol['horario_inicio'], 0, 5) . '-' . substr((string)$turnoCol['horario_fim'], 0, 5) . ' | ' . (string)$turnoCol['dias_semana']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="swap_requested_date"><i class="fas fa-calendar-day"></i> Data (opcional)</label>
                            <input type="date" id="swap_requested_date" name="requested_date">
                        </div>

                        <div class="form-group">
                            <label for="swap_reason"><i class="fas fa-comment-dots"></i> Motivo</label>
                            <textarea id="swap_reason" name="reason" rows="3" maxlength="500" placeholder="Ex.: consulta médica no período do meu turno"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;">
                            <i class="fas fa-paper-plane"></i>
                            Enviar pedido ao colega
                        </button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-check"></i>
                        <h3>Pedidos para Minha Aprovação</h3>
                    </div>

                    <div id="swapIncomingList" class="message-list">
                        <?php if (count($trocasTurnoRecebidas) > 0): ?>
                            <?php foreach ($trocasTurnoRecebidas as $swapReq): ?>
                                <div class="message-item">
                                    <div class="message-meta" style="margin-bottom:.35rem;">
                                        <span class="status-badge badge-warning">Pendente colega</span>
                                        <small><?php echo htmlspecialchars((string)($swapReq['requester_name'] ?? 'Funcionário')); ?></small>
                                    </div>
                                    <p class="message-text" style="margin:0 0 .45rem 0;">
                                        <strong>Turno solicitante:</strong>
                                        <?php echo htmlspecialchars((string)($swapReq['requester_turno_tipo'] ?? '-') . ' ' . substr((string)($swapReq['requester_horario_inicio'] ?? ''), 0, 5) . '-' . substr((string)($swapReq['requester_horario_fim'] ?? ''), 0, 5)); ?>
                                        <br>
                                        <strong>Seu turno:</strong>
                                        <?php echo htmlspecialchars((string)($swapReq['target_turno_tipo'] ?? '-') . ' ' . substr((string)($swapReq['target_horario_inicio'] ?? ''), 0, 5) . '-' . substr((string)($swapReq['target_horario_fim'] ?? ''), 0, 5)); ?>
                                    </p>
                                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                                        <button type="button" class="btn btn-success btn-turno-swap-decision" data-id="<?php echo (int)$swapReq['id']; ?>" data-decision="accept">
                                            <i class="fas fa-check"></i> Aceitar
                                        </button>
                                        <button type="button" class="btn btn-danger btn-turno-swap-decision" data-id="<?php echo (int)$swapReq['id']; ?>" data-decision="reject">
                                            <i class="fas fa-times"></i> Rejeitar
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-state" id="swapIncomingEmptyState">Nenhum pedido pendente para você aprovar.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <h3>Meus Pedidos de Troca</h3>
                    </div>

                    <div id="swapSentList" class="message-list">
                        <?php if (count($trocasTurnoEnviadas) > 0): ?>
                            <?php foreach ($trocasTurnoEnviadas as $swapSent):
                                $swapStatus = mb_strtolower(trim((string)($swapSent['status'] ?? 'pendente_colega')));
                                $swapBadgeClass = 'badge-warning';
                                $swapLabel = 'Pendente colega';
                                if ($swapStatus === 'pendente_admin') {
                                    $swapLabel = 'Pendente admin';
                                    $swapBadgeClass = 'badge-warning';
                                } elseif (in_array($swapStatus, ['aprovada', 'aprovado'], true)) {
                                    $swapLabel = 'Aprovada';
                                    $swapBadgeClass = 'badge-success';
                                } elseif (in_array($swapStatus, ['rejeitada', 'rejeitado', 'rejeitada_colega', 'rejeitado_colega'], true)) {
                                    $swapLabel = 'Rejeitada';
                                    $swapBadgeClass = 'badge-danger';
                                }
                            ?>
                                <div class="message-item">
                                    <div class="message-meta" style="margin-bottom:.35rem;">
                                        <span class="status-badge <?php echo htmlspecialchars($swapBadgeClass); ?>"><?php echo htmlspecialchars($swapLabel); ?></span>
                                        <small><?php echo htmlspecialchars((string)($swapSent['target_name'] ?? 'Colega')); ?></small>
                                    </div>
                                    <p class="message-text" style="margin:0;">
                                        <?php echo htmlspecialchars((string)($swapSent['requester_turno_tipo'] ?? '-') . ' ' . substr((string)($swapSent['requester_horario_inicio'] ?? ''), 0, 5) . '-' . substr((string)($swapSent['requester_horario_fim'] ?? ''), 0, 5)); ?>
                                        ↔
                                        <?php echo htmlspecialchars((string)($swapSent['target_turno_tipo'] ?? '-') . ' ' . substr((string)($swapSent['target_horario_inicio'] ?? ''), 0, 5) . '-' . substr((string)($swapSent['target_horario_fim'] ?? ''), 0, 5)); ?>
                                    </p>
                                    <?php if ($swapStatus === 'pendente_colega'): ?>
                                    <button type="button" class="btn btn-danger btn-cancel-swap"
                                            data-id="<?= (int)$swapSent['id'] ?>"
                                            style="margin-top:.5rem;font-size:.8rem;padding:.35rem .75rem;">
                                        <i class="fas fa-times"></i> Cancelar pedido
                                    </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-state" id="swapSentEmptyState">Você ainda não enviou pedidos de troca.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($turnosHistorico)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <h3>Histórico de Turnos</h3>
                    </div>
                    <div class="turno-historico-list">
                        <?php foreach ($turnosHistorico as $th): ?>
                        <div class="turno-item turno-item--inactive">
                            <div class="turno-topo">
                                <strong><?= htmlspecialchars($th['turno_tipo']) ?></strong>
                                <span class="status-badge badge-neutral"><?= htmlspecialchars(ucfirst((string)($th['status'] ?? 'Inativo'))) ?></span>
                            </div>
                            <div class="info-row turno-row">
                                <span class="info-label"><i class="fas fa-clock"></i> Horário:</span>
                                <span class="info-value"><?= htmlspecialchars(substr((string)$th['horario_inicio'], 0, 5) . ' – ' . substr((string)$th['horario_fim'], 0, 5)) ?></span>
                            </div>
                            <div class="info-row turno-row">
                                <span class="info-label"><i class="fas fa-calendar"></i> Dias:</span>
                                <span class="info-value"><?= htmlspecialchars($th['dias_semana']) ?></span>
                            </div>
                            <?php if (!empty($th['data_inicio']) || !empty($th['data_fim'])): ?>
                            <div class="info-row turno-row">
                                <span class="info-label"><i class="fas fa-calendar-check"></i> Período:</span>
                                <span class="info-value">
                                    <?= !empty($th['data_inicio']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$th['data_inicio']))) : '–' ?>
                                    →
                                    <?= !empty($th['data_fim']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$th['data_fim']))) : '–' ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="ferias-section" class="portal-section">
            <h3 class="section-title"><i class="fas fa-umbrella-beach"></i> Férias</h3>
            <div class="grid">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-paper-plane"></i>
                        <h3>Novo Pedido de Férias</h3>
                    </div>

                    <form id="feriasForm">
                        <div class="form-group">
                            <label for="ferias_data_inicio"><i class="fas fa-calendar-day"></i> Data de Início</label>
                            <input type="date" id="ferias_data_inicio" name="data_inicio" required
                                   min="<?= date('Y-m-d') ?>" onchange="onFeriasDatesChange()">
                        </div>

                        <div class="form-group">
                            <label for="ferias_data_fim"><i class="fas fa-calendar-check"></i> Data de Término</label>
                            <input type="date" id="ferias_data_fim" name="data_fim" required
                                   min="<?= date('Y-m-d') ?>" onchange="onFeriasDatesChange()">
                        </div>

                        <div id="ferias-dias-counter" class="ferias-dias-counter" style="display:none;"></div>

                        <div class="form-group">
                            <label for="ferias_motivo"><i class="fas fa-comment-dots"></i> Motivo (opcional)</label>
                            <textarea id="ferias_motivo" name="motivo" rows="3" maxlength="500" placeholder="Ex.: férias anuais"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;">
                            <i class="fas fa-send"></i>
                            Enviar Pedido
                        </button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <h3>Meus Pedidos Recentes</h3>
                    </div>

                    <div class="ferias-saldo-bar">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?= $diasUsadosAno ?> dias aprovados em <?= date('Y') ?></span>
                    </div>

                    <div id="feriasList" class="message-list">
                        <?php if (count($feriasPedidos) > 0): ?>
                            <?php foreach ($feriasPedidos as $fPedido):
                                $fStatusRaw = mb_strtolower(trim((string)($fPedido['status'] ?? 'pendente')));
                                if ($fStatusRaw === 'aprovado') $fStatusRaw = 'aprovada';
                                if (in_array($fStatusRaw, ['rejeitado', 'recusado', 'recusada'], true)) $fStatusRaw = 'rejeitada';

                                $fInicioIso   = (string)($fPedido['data_inicio'] ?? '');
                                $fFimIso      = (string)($fPedido['data_fim'] ?? '');
                                $todayIso     = date('Y-m-d');
                                $fStatusLabel = $fStatusRaw === 'aprovada' ? 'Em curso' : ($fStatusRaw === 'rejeitada' ? 'Rejeitada' : ($fStatusRaw === 'cancelada' ? 'Cancelada' : 'Pendente'));
                                $fBadgeClass  = $fStatusRaw === 'aprovada' ? 'badge-success' : ($fStatusRaw === 'rejeitada' ? 'badge-danger' : ($fStatusRaw === 'cancelada' ? 'badge-neutral' : 'badge-warning'));
                                if ($fStatusRaw === 'aprovada' && $fInicioIso !== '' && $todayIso < $fInicioIso) {
                                    $fStatusLabel = 'Agendada'; $fBadgeClass = 'badge-warning';
                                } elseif ($fStatusRaw === 'aprovada' && $fFimIso !== '' && $todayIso > $fFimIso) {
                                    $fStatusLabel = 'Terminada'; $fBadgeClass = 'badge-neutral';
                                }
                                $fInicioFmt    = !empty($fPedido['data_inicio']) ? date('d/m/Y', strtotime((string)$fPedido['data_inicio'])) : 'N/D';
                                $fFimFmt       = !empty($fPedido['data_fim'])    ? date('d/m/Y', strtotime((string)$fPedido['data_fim']))    : 'N/D';
                                $fMotivo       = trim((string)($fPedido['motivo'] ?? ''));
                                $fMotivoRej    = $feriasHasMotivoRej ? trim((string)($fPedido['motivo_rejeicao'] ?? '')) : '';
                                $fDias         = (!empty($fPedido['data_inicio']) && !empty($fPedido['data_fim']))
                                                 ? (int)(strtotime($fPedido['data_fim']) - strtotime($fPedido['data_inicio'])) / 86400 + 1
                                                 : 0;
                            ?>
                                <div class="message-item ferias-item-row" data-ferias-id="<?= (int)$fPedido['id'] ?>" data-ferias-status="<?= htmlspecialchars($fStatusRaw) ?>">
                                    <div style="flex:1;">
                                        <div class="message-meta" style="margin-bottom:.3rem;">
                                            <span class="status-badge <?php echo $fBadgeClass; ?>"><?php echo htmlspecialchars($fStatusLabel); ?></span>
                                            <small><?php echo htmlspecialchars($fInicioFmt . ' – ' . $fFimFmt); ?><?= $fDias > 0 ? " ($fDias dias)" : '' ?></small>
                                        </div>
                                        <p class="message-text" style="margin:0 0 .3rem;"><?php echo htmlspecialchars($fMotivo !== '' ? $fMotivo : 'Sem motivo informado.'); ?></p>
                                        <?php if ($fMotivoRej !== ''): ?>
                                        <p class="ferias-motivo-rej"><i class="fas fa-comment-slash"></i> <?= htmlspecialchars($fMotivoRej) ?></p>
                                        <?php endif; ?>
                                        <?php if ($fStatusRaw === 'pendente'): ?>
                                        <button type="button" class="btn btn-danger btn-cancel-ferias"
                                                data-id="<?= (int)$fPedido['id'] ?>"
                                                style="margin-top:.4rem;font-size:.8rem;padding:.3rem .7rem;">
                                            <i class="fas fa-times"></i> Cancelar pedido
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-state" id="feriasEmptyState">Sem pedidos de férias no momento.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section id="recibos-section" class="portal-section">
            <h3 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Recibos de Vencimento</h3>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i>
                    <h3>Histórico de Recibos</h3>
                </div>
                <div id="recibos-list">
                    <div class="empty-state" id="recibos-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>A carregar recibos…</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal Recibo de Vencimento -->
        <div id="reciboModal" class="modal" style="display:none">
            <div class="modal-content recibo-modal-content">
                <div class="recibo-modal-header">
                    <div>
                        <div class="recibo-modal-suptitle">Recibo de Vencimento</div>
                        <h3 class="recibo-modal-title" id="reciboModalPeriodo"></h3>
                    </div>
                    <div style="display:flex;align-items:center;gap:.75rem">
                        <span class="status-badge" id="reciboModalStatus"></span>
                        <span class="close-modal" onclick="closeReciboModal()" style="font-size:1.5rem;cursor:pointer">&times;</span>
                    </div>
                </div>
                <div class="recibo-modal-body" id="reciboModalBody"></div>
                <div class="recibo-modal-footer">
                    <button type="button" class="btn btn-primary" onclick="imprimirRecibo()">
                        <i class="fas fa-print"></i> Imprimir / PDF
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeReciboModal()">Fechar</button>
                </div>
            </div>
        </div>

        <section id="definicoes-section" class="portal-section">
            <h3 class="section-title"><i class="fas fa-cog"></i> Definições da Conta</h3>
            <?php
            $defAvatar = $employee['profile_picture'] ?? '';
            $defName   = htmlspecialchars($employee['name'] ?? $employee_name);
            $defInitials = strtoupper(mb_substr($employee['name'] ?? $employee_name, 0, 1));
            if (preg_match('/\s+(\S)/', $employee['name'] ?? $employee_name, $_m)) {
                $defInitials .= strtoupper($_m[1]);
            }
            $defPhone  = htmlspecialchars($employee['phone'] ?? '');
            ?>
            <div class="def-profile-header">
                <div class="def-avatar-wrap">
                    <?php if ($defAvatar): ?>
                        <img id="defAvatarImg" src="<?= htmlspecialchars($defAvatar) ?>?v=<?= time() ?>" alt="Avatar" class="def-avatar-img">
                    <?php else: ?>
                        <div id="defAvatarInitials" class="def-avatar-initials"><?= $defInitials ?></div>
                    <?php endif; ?>
                    <label class="def-avatar-overlay" title="Alterar foto" for="defAvatarInput">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="defAvatarInput" accept="image/jpeg,image/png,image/webp" style="display:none">
                </div>
                <div class="def-profile-name">
                    <span class="def-name"><?= $defName ?></span>
                    <span class="def-position"><?= htmlspecialchars($employee['position'] ?? 'Funcionário') ?></span>
                </div>
            </div>

            <div class="grid">
                <!-- Informações -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-id-badge"></i>
                        <h3>Informações da Conta</h3>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Nome</span>
                        <span class="info-value"><?= $defName ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Cargo</span>
                        <span class="info-value"><?= htmlspecialchars($employee['position'] ?? 'Funcionário') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($employee['email'] ?? 'N/D') ?></span>
                    </div>
                    <div class="info-row" style="align-items:center;gap:.5rem;flex-wrap:wrap">
                        <span class="info-label">Telefone</span>
                        <span class="info-value" id="defPhoneDisplay"><?= $defPhone ?: '<em style="opacity:.5">Não definido</em>' ?></span>
                        <button type="button" class="btn-icon-sm" id="defPhoneEditBtn" title="Editar telefone"><i class="fas fa-pen"></i></button>
                    </div>
                    <div id="defPhoneForm" style="display:none;margin-top:.75rem">
                        <div style="display:flex;gap:.5rem">
                            <input type="tel" id="defPhoneInput" class="form-control form-control-sm"
                                   placeholder="Ex: +351 912 345 678" maxlength="20"
                                   value="<?= htmlspecialchars($employee['phone'] ?? '') ?>">
                            <button type="button" class="btn btn-primary btn-sm" id="defPhoneSaveBtn">Guardar</button>
                            <button type="button" class="btn btn-secondary btn-sm" id="defPhoneCancelBtn">Cancelar</button>
                        </div>
                    </div>
                </div>

                <!-- Alterar PIN -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-key"></i>
                        <h3>Alterar PIN</h3>
                    </div>
                    <form id="defPinForm" autocomplete="off">
                        <div class="form-group" style="margin-bottom:.75rem">
                            <label class="form-label">PIN atual</label>
                            <input type="password" id="defPinCurrent" class="form-control" placeholder="••••" maxlength="20" inputmode="numeric">
                        </div>
                        <div class="form-group" style="margin-bottom:.75rem">
                            <label class="form-label">Novo PIN</label>
                            <input type="password" id="defPinNew" class="form-control" placeholder="mín. 4 caracteres" maxlength="20" inputmode="numeric">
                        </div>
                        <div class="form-group" style="margin-bottom:1rem">
                            <label class="form-label">Confirmar novo PIN</label>
                            <input type="password" id="defPinConfirm" class="form-control" placeholder="repetir PIN" maxlength="20" inputmode="numeric">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%">
                            <i class="fas fa-save"></i> Guardar PIN
                        </button>
                    </form>
                </div>

                <!-- Dados Laborais -->
                <?php
                $defStartDate     = !empty($employee['startDate'])     ? date('d/m/Y', strtotime($employee['startDate']))     : null;
                $defContractType  = !empty($employee['contractType'])  ? htmlspecialchars($employee['contractType'])          : null;
                $defNif           = !empty($employee['nif'])           ? htmlspecialchars($employee['nif'])                   : null;
                $defNiss          = !empty($employee['niss'])          ? htmlspecialchars($employee['niss'])                  : null;
                $defDept          = !empty($employee['department'])    ? htmlspecialchars($employee['department'])            : null;
                $hasLaborData = $defStartDate || $defContractType || $defNif || $defNiss || $defDept;
                ?>
                <?php if ($hasLaborData): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-briefcase"></i>
                        <h3>Dados Laborais</h3>
                    </div>
                    <?php if ($defDept): ?>
                    <div class="info-row">
                        <span class="info-label">Departamento</span>
                        <span class="info-value"><?= $defDept ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($defStartDate): ?>
                    <div class="info-row">
                        <span class="info-label">Data de entrada</span>
                        <span class="info-value"><?= $defStartDate ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($defContractType): ?>
                    <div class="info-row">
                        <span class="info-label">Tipo de contrato</span>
                        <span class="info-value"><?= $defContractType ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($defNif): ?>
                    <div class="info-row">
                        <span class="info-label">NIF</span>
                        <span class="info-value"><?= $defNif ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($defNiss): ?>
                    <div class="info-row">
                        <span class="info-label">NISS</span>
                        <span class="info-value"><?= $defNiss ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Notificações do Browser -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bell"></i>
                        <h3>Notificações</h3>
                    </div>
                    <div class="info-row" style="align-items:center;gap:.75rem;flex-wrap:wrap">
                        <div style="flex:1;min-width:0">
                            <span style="font-size:.875rem;font-weight:600;color:var(--text-primary,#e2e8f0)">Notificações do Browser</span>
                            <p style="font-size:.75rem;color:var(--neutral-500,#64748b);margin-top:.2rem">Receber alertas mesmo quando o portal está em segundo plano.</p>
                        </div>
                        <button type="button" id="def-notif-perm-btn"
                                class="btn btn-primary"
                                onclick="requestSMSNotificationPermission()"
                                style="white-space:nowrap;font-size:.8rem;padding:.35rem .85rem">
                            <i class="fas fa-bell-slash"></i> Ativar
                        </button>
                    </div>
                </div>

                <!-- Ações -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-sliders-h"></i>
                        <h3>Ações Rápidas</h3>
                    </div>
                    <div class="settings-actions">
                        <a href="employee_logout.php" class="btn btn-danger settings-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            Terminar Sessão
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Modal Justificação de Ausência -->
    <div id="justificativaModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeJustificativaModal()">&times;</span>
            <h3 style="margin-bottom:.5rem;color:var(--dark)">
                <i class="fas fa-file-medical-alt"></i> Justificação de Ausência
            </h3>
            <p id="just_modal_date_label" style="font-size:.95rem;font-weight:700;color:var(--accent-500);margin-bottom:1.25rem"></p>
            <form id="justificativaModalForm" enctype="multipart/form-data">
                <input type="hidden" name="data_ausencia" id="just_modal_data">

                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Tipo de Justificação *</label>
                    <select name="tipo" id="just_modal_tipo" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($tiposJust as $val => $lbl): ?>
                        <option value="<?= $val ?>"><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-comment-alt"></i> Motivo / Descrição *</label>
                    <textarea name="motivo" id="just_modal_motivo" rows="3" required maxlength="500" placeholder="Descreva o motivo da ausência…"></textarea>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-paperclip"></i> Documento de suporte <span style="color:var(--neutral-500)">(opcional)</span></label>
                    <label class="file-upload-area" id="just_file_area">
                        <input type="file" name="documento" id="just_modal_doc"
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                               onchange="onJustFileChange(this)">
                        <div class="file-upload-placeholder" id="just_file_placeholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Clique ou arraste um ficheiro</span>
                            <small>PDF, JPG, PNG ou DOC — máx. 5 MB</small>
                        </div>
                        <div class="file-upload-preview" id="just_file_preview" style="display:none">
                            <i class="fas fa-file-check"></i>
                            <span id="just_file_name"></span>
                            <button type="button" class="just-remove-file" onclick="removeJustFile(event)" title="Remover">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </label>
                </div>

                <div style="display:flex;gap:.75rem;margin-top:1.5rem">
                    <button type="submit" class="btn btn-primary" style="flex:1" id="just_submit_btn">
                        <i class="fas fa-paper-plane"></i> Enviar Justificação
                    </button>
                    <button type="button" class="btn btn-danger" onclick="closeJustificativaModal()" style="flex:0 0 auto">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Registrar Gorjeta -->
    <div id="gorjetaModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeGorjetaModal()">&times;</span>
            <h3 style="margin-bottom: 1.5rem; color: var(--dark);">
                <i class="fas fa-money-bill-wave"></i> Registrar Gorjeta
            </h3>
            
            <form id="gorjetaForm">
                <div class="form-group">
                    <label><i class="fas fa-calendar-day"></i> Data *</label>
                    <input type="date" name="data_gorjeta" id="gorjeta_data" required
                           value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d', strtotime('-90 days')) ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-euro-sign"></i> Valor (€) *</label>
                    <input type="number" step="0.01" name="valor" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Turno</label>
                    <?php if (!empty($turnos)): ?>
                        <?php $_t0 = $turnos[0]; ?>
                        <input type="hidden" name="turno" value="<?= htmlspecialchars($_t0['turno_tipo']) ?>">
                        <div style="padding:.45rem .75rem;border-radius:8px;border:1px solid var(--neutral-700,#334155);background:var(--neutral-800,#1e293b);font-size:.875rem;color:#94a3b8">
                            <i class="fas fa-lock" style="opacity:.5;margin-right:.35rem"></i>
                            <?= htmlspecialchars($_t0['turno_tipo']) ?>
                            (<?= substr((string)$_t0['horario_inicio'], 0, 5) ?>–<?= substr((string)$_t0['horario_fim'], 0, 5) ?>)
                        </div>
                    <?php else: ?>
                        <select name="turno" required>
                            <option value="">Selecione...</option>
                            <option value="Manhã">Manhã</option>
                            <option value="Tarde">Tarde</option>
                            <option value="Noturno">Noturno</option>
                        </select>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Forma de Pagamento *</label>
                    <select name="forma_pagamento" required>
                        <option value="">Selecione...</option>
                        <option value="Dinheiro">Dinheiro</option>
                        <option value="Cartão">Cartão</option>
                        <option value="MB Way">MB Way</option>
                        <option value="Transferência">Transferência</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Origem</label>
                    <input type="text" name="origem" placeholder="Ex: Mesa 5, Balcão...">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Observações</label>
                    <textarea name="observacoes" rows="3" placeholder="Observações opcionais..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">
                        <i class="fas fa-save"></i> Registrar
                    </button>
                    <button type="button" onclick="closeGorjetaModal()" class="btn btn-danger" style="flex: 1;">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        var _attendanceData  = <?= json_encode(array_reverse($attendanceGrid), JSON_UNESCAPED_UNICODE) ?>;
        var _attendanceMes   = '<?= date('Y-m') ?>';
        var _diasFeriasDisp  = <?= (int)$diasFeriasDisp ?>;
        var _diasFeriasTotal = <?= (int)$diasFeriasTotal ?>;
        var _salarioBase     = <?= is_numeric($salarioBaseRaw) ? (float)$salarioBaseRaw : 'null' ?>;
    </script>
    <script src="portal.js"></script>
  
</body>
</html>
