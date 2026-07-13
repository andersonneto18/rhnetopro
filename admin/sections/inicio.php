<?php
// Secção "Início" — incluída a partir de admin/dashboard.php (depende de $ADMIN_DIR, $pdo, $loggedInClientId,
// $username, $turnosRelatorio, $estHorario, parseTurnoDays(), etc. já definidos lá).

// Pendências: contagens leves de coisas que precisam de ação do admin, para dar ao Início uma
// função de "o que precisa da minha atenção hoje" além dos KPIs estáticos.
$pendJustificativas = 0;
try {
    $stmtPendJust = $pdo->prepare("SELECT COUNT(*) FROM justificativas_presenca WHERE client_id = ? AND LOWER(status) = 'pendente'");
    $stmtPendJust->execute([$loggedInClientId]);
    $pendJustificativas = (int) $stmtPendJust->fetchColumn();
} catch (Throwable $e) {
    $pendJustificativas = 0;
}

$pendAlteracoes = 0;
try {
    $stmtPendAlt = $pdo->prepare("SELECT COUNT(*) FROM employee_change_requests WHERE client_id = ? AND status = 'pendente'");
    $stmtPendAlt->execute([$loggedInClientId]);
    $pendAlteracoes = (int) $stmtPendAlt->fetchColumn();
} catch (Throwable $e) {
    $pendAlteracoes = 0;
}

$pendConfirmacoes = 0;
try {
    $stmtPendConf = $pdo->prepare("SELECT COUNT(*) FROM registros_ponto WHERE client_id = ? AND LOWER(COALESCE(status_confirmacao, '')) <> 'confirmado'");
    $stmtPendConf->execute([$loggedInClientId]);
    $pendConfirmacoes = (int) $stmtPendConf->fetchColumn();
} catch (Throwable $e) {
    $pendConfirmacoes = 0;
}

// Tendência de 7 dias: Presentes (com entrada real) vs Faltas (turno já terminado sem entrada,
// mesma lógica de correspondência dia da semana + vigência usada no resto da app).
$tendInicio = date('Y-m-d', strtotime('-6 days'));
$tendFim = date('Y-m-d');
$tendLabels = [];
$tendPresentes = array_fill(0, 7, 0);
$tendFaltas = array_fill(0, 7, 0);

try {
    $diasComEntradaTend = [];
    $stmtTendPres = $pdo->prepare("
        SELECT funcionario_id, DATE(data_registro) AS dia
        FROM registros_ponto
        WHERE client_id = ? AND DATE(data_registro) BETWEEN ? AND ?
          AND hora_entrada IS NOT NULL AND hora_entrada <> ''
    ");
    $stmtTendPres->execute([$loggedInClientId, $tendInicio, $tendFim]);
    foreach ($stmtTendPres->fetchAll(PDO::FETCH_ASSOC) ?: [] as $rowTend) {
        $diasComEntradaTend[$rowTend['funcionario_id'] . '_' . $rowTend['dia']] = true;
    }

    $weekdayMapTend = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];
    $agoraTend = time();

    for ($i = 0; $i < 7; $i++) {
        $diaTs = strtotime($tendInicio . " +{$i} days");
        $diaIso = date('Y-m-d', $diaTs);
        $tendLabels[$i] = date('d/m', $diaTs);
        $weekdayTokTend = $weekdayMapTend[(int) date('w', $diaTs)];

        $presentesDia = 0;
        $faltasDia = 0;

        foreach ($turnosRelatorio as $tCand) {
            if (!in_array(mb_strtolower(trim((string) ($tCand['status'] ?? ''))), ['ativo', 'active'], true)) {
                continue;
            }
            $tDiasTend = parseTurnoDays((string) ($tCand['dias_semana'] ?? ''));
            if (!empty($tDiasTend) && !in_array($weekdayTokTend, $tDiasTend, true)) {
                continue;
            }
            $tIniTend = trim((string) ($tCand['data_inicio'] ?? ''));
            $tFimTend = trim((string) ($tCand['data_fim'] ?? ''));
            if ($tIniTend !== '' && $tIniTend !== '0000-00-00' && $tIniTend > $diaIso) {
                continue;
            }
            if ($tFimTend !== '' && $tFimTend !== '0000-00-00' && $tFimTend < $diaIso) {
                continue;
            }

            $empIdTend = (int) ($tCand['funcionario_id'] ?? 0);
            $keyTend = $empIdTend . '_' . $diaIso;

            if (isset($diasComEntradaTend[$keyTend])) {
                $presentesDia++;
                continue;
            }

            $horaFimTend = substr((string) ($tCand['horario_fim'] ?? ''), 0, 5);
            $horaIniTend = substr((string) ($tCand['horario_inicio'] ?? ''), 0, 5);
            if ($horaFimTend === '' || $horaIniTend === '') {
                continue;
            }
            $fimTsTend = strtotime($diaIso . ' ' . $horaFimTend);
            $iniTsTend = strtotime($diaIso . ' ' . $horaIniTend);
            if ($fimTsTend !== false && $iniTsTend !== false && $fimTsTend <= $iniTsTend) {
                $fimTsTend += 24 * 60 * 60;
            }
            if ($fimTsTend === false || $agoraTend <= $fimTsTend) {
                continue; // turno ainda em curso ou por vir: ainda não é falta definitiva
            }
            $faltasDia++;
        }

        $tendPresentes[$i] = $presentesDia;
        $tendFaltas[$i] = $faltasDia;
    }
} catch (Throwable $e) {
    error_log('Erro ao montar tendência de 7 dias no Início: ' . $e->getMessage());
}
?>
        <section id="inicio-section" class="content-section active">

            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon"><i class="fas fa-house-chimney"></i></div>
                    <div>
                        <h2 class="frhd-title"><?php echo htmlspecialchars($username); ?></h2>
                        <p class="frhd-sub"><?php
                            $diasPT = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
                            $mesesPT = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
                            echo $diasPT[(int)date('w')] . ', ' . date('d') . ' de ' . $mesesPT[(int)date('n')-1] . ' de ' . date('Y');
                        ?> &middot; <?php echo (int)($activeEmployeesCount ?? 0); ?> funcionários ativos</p>
                    </div>
                </div>
                <button type="button" class="frhd-add-btn" onclick="showSection('funcionarios')">
                    <i class="fas fa-user-plus"></i> Novo Funcionário
                </button>
            </div>

            <div class="fr-kpi-strip" style="grid-template-columns:repeat(4,1fr);">
                <div class="fr-kpi fr-kpi-total">
                    <div class="fr-kpi-icon"><i class="fas fa-users"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)($activeEmployeesCount ?? 0); ?></span>
                        <span class="fr-kpi-lbl">Funcionários</span>
                        <span class="fr-kpi-pct">total ativo</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-active">
                    <div class="fr-kpi-icon"><i class="fas fa-user-check"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)($presentCount ?? 0); ?></span>
                        <span class="fr-kpi-lbl">Presentes</span>
                        <span class="fr-kpi-pct">hoje</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-inactive">
                    <div class="fr-kpi-icon"><i class="fas fa-user-times"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)($faltasHoje ?? 0); ?></span>
                        <span class="fr-kpi-lbl">Faltas</span>
                        <span class="fr-kpi-pct">hoje</span>
                    </div>
                </div>
                <div class="fr-kpi" style="position:relative;">
                    <div class="fr-kpi-icon" style="background:rgba(167,139,250,.12);color:#a78bfa;"><i class="fas fa-euro-sign"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val" style="font-size:1.25rem;">€ <?php echo number_format((float)($folhaResumo['custo_total'] ?? 0), 2, ',', '.'); ?></span>
                        <span class="fr-kpi-lbl">Custos Salariais</span>
                        <span class="fr-kpi-pct">este mês</span>
                    </div>
                </div>
            </div>

            <?php if ($pendJustificativas > 0 || $pendAlteracoes > 0 || $pendConfirmacoes > 0): ?>
            <div class="fr-kpi-strip" style="grid-template-columns:repeat(<?php echo (int)($pendJustificativas > 0) + (int)($pendAlteracoes > 0) + (int)($pendConfirmacoes > 0); ?>,1fr);margin-top:1rem;">
                <?php if ($pendJustificativas > 0): ?>
                <div class="fr-kpi" style="cursor:pointer;border-color:rgba(251,191,36,.3);" onclick="showSection('solicitacoes')">
                    <div class="fr-kpi-icon" style="background:rgba(251,191,36,.14);color:#fbbf24;"><i class="fas fa-file-signature"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo $pendJustificativas; ?></span>
                        <span class="fr-kpi-lbl">Justificativa<?php echo $pendJustificativas !== 1 ? 's' : ''; ?> por Aprovar</span>
                        <span class="fr-kpi-pct">clique para rever</span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($pendAlteracoes > 0): ?>
                <div class="fr-kpi" style="cursor:pointer;border-color:rgba(251,191,36,.3);" onclick="showSection('funcionarios')">
                    <div class="fr-kpi-icon" style="background:rgba(251,191,36,.14);color:#fbbf24;"><i class="fas fa-user-edit"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo $pendAlteracoes; ?></span>
                        <span class="fr-kpi-lbl">Alteraç<?php echo $pendAlteracoes !== 1 ? 'ões' : 'ão'; ?> de Dados por Aprovar</span>
                        <span class="fr-kpi-pct">clique para rever</span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($pendConfirmacoes > 0): ?>
                <div class="fr-kpi" style="cursor:pointer;border-color:rgba(251,191,36,.3);" onclick="showSection('assiduidade')">
                    <div class="fr-kpi-icon" style="background:rgba(251,191,36,.14);color:#fbbf24;"><i class="fas fa-clipboard-check"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo $pendConfirmacoes; ?></span>
                        <span class="fr-kpi-lbl">Registo<?php echo $pendConfirmacoes !== 1 ? 's' : ''; ?> de Ponto por Confirmar</span>
                        <span class="fr-kpi-pct">clique para rever</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;margin-top:1rem;height:260px;">
                <canvas id="chartInicioTendencia" style="max-height:230px;"></canvas>
            </div>

            <div class="data-table fr-table-wrap" style="margin-top:.25rem;">
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#2563eb);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;flex-shrink:0;box-shadow:0 3px 10px rgba(59,130,246,.3);">
                                <i class="fas fa-stream"></i>
                            </div>
                            <div>
                                <div style="font-size:.95rem;font-weight:700;color:var(--text-primary,#f1f5f9);line-height:1.1;">Atividade Recente</div>
                                <div style="font-size:.72rem;color:#64748b;margin-top:1px;">Histórico de eventos</div>
                            </div>
                        </div>
                    </div>
                </div>

                <table class="table fr-table" style="margin:0;">
                    <thead>
                        <tr class="fr-thead-row">
                            <th style="width:44px;"></th>
                            <th>Evento</th>
                            <th style="width:120px;">Estado</th>
                            <th style="width:90px;text-align:right;">Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // 1. Verificamos se a variável existe e se não está vazia
                    if (!empty($recentActivities) && is_array($recentActivities)):
                        foreach ($recentActivities as $activity):
                            // 2. Verificamos se cada item individual tem os dados necessários
                            if (isset($activity['title'], $activity['timestamp'])):
                    ?>
                    <?php
                                // Tentar obter avatar relacionado (se existir)
                                $avatarUrl = null;
                                $avatarAlt = '';

                                if (!empty($activity['employee_profile_picture'] ?? null)) {
                                    $avatarUrl = $activity['employee_profile_picture'];
                                }
                                if (!empty($activity['employee_name'] ?? null)) {
                                    $avatarAlt = $activity['employee_name'];
                                }

                                // Se ainda não houver dados suficientes, tenta buscar pelo employee_id
                                if (!$avatarAlt && !empty($activity['employee_id'] ?? null)) {
                                    try {
                                        $stmtAvatar = $pdo->prepare("SELECT profile_picture, name FROM employees WHERE client_id = ? AND id = ? LIMIT 1");
                                        $stmtAvatar->execute([$loggedInClientId, $activity['employee_id']]);
                                        $rowA = $stmtAvatar->fetch(PDO::FETCH_ASSOC);
                                        if ($rowA) {
                                            $avatarAlt = $rowA['name'] ?? '';
                                            if (!empty($rowA['profile_picture'])) {
                                                $avatarUrl = $rowA['profile_picture'];
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        // ignore lookup errors
                                    }
                                }

                                // fallback: quando criamos uma atividade tipo 'Novo funcionário: Nome' ou 'Nome marcou presença/falta' tentamos buscar pelo nome
                                if (!$avatarUrl) {
                                    $nomeBuscado = '';

                                    if (preg_match('/Novo funcionário:\s*(.+)/i', $activity['title'], $m)) {
                                        $nomeBuscado = trim($m[1]);
                                    } elseif (preg_match('/^(.+?)\s+marcou\s+(?:presen[cç]a|falta)/i', $activity['title'], $m)) {
                                        $nomeBuscado = trim($m[1]);
                                    } elseif (preg_match('/^(?:Presen[cç]a|Falta):\s*(.+)$/i', $activity['title'], $m)) {
                                        $nomeBuscado = trim($m[1]);
                                    } else {
                                        // tentativa mais genérica: texto após ':'
                                        if (strpos($activity['title'], ':') !== false) {
                                            $parts = explode(':', $activity['title'], 2);
                                            $possible = trim($parts[1]);
                                            if (strlen($possible) > 0 && strlen($possible) < 80) {
                                                $nomeBuscado = $possible;
                                            }
                                        }
                                    }

                                    if ($nomeBuscado !== '') {
                                        try {
                                            // tolerância estendida: normaliza espaços e pontuação
                                            $nomeNormalized = preg_replace('/\s+/', ' ', trim($nomeBuscado));

                                            // 1) tentativa de correspondência exata (como antes)
                                            $stmtAvatar = $pdo->prepare("SELECT profile_picture, name FROM employees WHERE client_id = ? AND name = ? LIMIT 1");
                                            $stmtAvatar->execute([$loggedInClientId, $nomeNormalized]);
                                            $rowA = $stmtAvatar->fetch(PDO::FETCH_ASSOC);

                                            // 2) variants mais tolerantes
                                            if (!$rowA) {
                                                // tentar variações de verbo (marcou presença / marcou ponto / registrou presença)
                                                $patterns = [
                                                    $nomeNormalized,
                                                    "%$nomeNormalized%",
                                                ];
                                                $stmtAvatar = $pdo->prepare("SELECT profile_picture, name FROM employees WHERE client_id = ? AND (name = ? OR name LIKE ?) LIMIT 1");
                                                $stmtAvatar->execute([$loggedInClientId, $patterns[0], $patterns[1]]);
                                                $rowA = $stmtAvatar->fetch(PDO::FETCH_ASSOC);
                                            }

                                            // 3) procurar por primeiro/último nome
                                            if (!$rowA) {
                                                $parts = preg_split('/\s+/', $nomeNormalized);
                                                if (count($parts) > 1) {
                                                    $first = $parts[0];
                                                    $last = end($parts);
                                                    $stmtAvatar = $pdo->prepare("SELECT profile_picture, name FROM employees WHERE client_id = ? AND (name LIKE ? OR name LIKE ?) LIMIT 1");
                                                    $stmtAvatar->execute([$loggedInClientId, "%$first%", "%$last%"]);
                                                    $rowA = $stmtAvatar->fetch(PDO::FETCH_ASSOC);
                                                }
                                            }

                                            if ($rowA) {
                                                $avatarAlt = $rowA['name'] ?? $nomeBuscado;
                                                if (!empty($rowA['profile_picture'])) {
                                                    $candidate = '../' . ltrim($rowA['profile_picture'], '/');
                                                    if (@file_exists($ADMIN_DIR . '/' . $candidate) || @file_exists($ADMIN_DIR . '/../' . $rowA['profile_picture'])) {
                                                        $avatarUrl = $rowA['profile_picture'];
                                                    }
                                                }
                                            }
                                        } catch (PDOException $e) {
                                            // ignore
                                        }
                                    }
                                }
                                ?>

                    <?php
                                $avatarSrc = null;
                                if (!empty($avatarUrl)) {
                                    $avatarSrc = preg_match('#^https?://#i', $avatarUrl) ? $avatarUrl : '../' . ltrim($avatarUrl, '/');
                                }
                                $actParts = preg_split('/\s+/', $avatarAlt);
                                $actInitials = '';
                                foreach ($actParts as $p) {
                                    if (trim($p) !== '' && mb_strlen($actInitials) < 2) $actInitials .= mb_strtoupper(mb_substr($p, 0, 1));
                                }
                                $displayStatus = '';
                                if (!empty($activity['status'])) {
                                    $displayStatus = $activity['status'];
                                } else {
                                    $t = mb_strtolower($activity['title'] ?? '');
                                    if (strpos($t, 'marcou presença') !== false || strpos($t, 'presente') !== false) $displayStatus = 'Presente';
                                    elseif (strpos($t, 'marcou falta') !== false || strpos($t, 'falta') !== false) $displayStatus = 'Falta';
                                    elseif (strpos($t, 'novo funcionário') !== false || strpos($t, 'novo') !== false) $displayStatus = 'Novo';
                                }
                                $badgeClass = $displayStatus ? 'status-' . preg_replace('/[^a-z0-9\-]/u', '-', mb_strtolower($displayStatus)) : '';
                                $actTime = !empty($activity['timestamp']) ? strtotime($activity['timestamp']) : 0;
                                ?>
                    <tr class="fr-row">
                        <td style="padding:.75rem .5rem .75rem 1rem;width:44px;vertical-align:middle;">
                            <?php if (!empty($avatarSrc)): ?>
                            <div class="fr-av" style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:50%;">
                                <img class="fr-av-img" style="border-radius:50%;" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="">
                            </div>
                            <?php elseif ($actInitials !== ''): ?>
                            <div class="fr-av" style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:50%;font-size:.8rem;"><?php echo htmlspecialchars($actInitials); ?></div>
                            <?php else:
                                $actIcon = strpos($activity['title'], 'Novo funcionário') !== false ? 'fa-user-plus' : (strpos($activity['title'], 'presente') !== false ? 'fa-check-circle' : 'fa-info-circle');
                            ?>
                            <div class="fr-av" style="background:rgba(59,130,246,.12);color:#3b82f6;border-radius:10px;font-size:.85rem;"><i class="fas <?php echo $actIcon; ?>"></i></div>
                            <?php endif; ?>
                        </td>
                        <td class="fr-td-emp" style="vertical-align:middle;">
                            <span class="fr-emp-name"><?php echo htmlspecialchars($activity['title']); ?></span>
                        </td>
                        <td class="fr-td-status" style="vertical-align:middle;">
                            <?php if ($badgeClass): ?><span class="status-badge <?php echo htmlspecialchars($badgeClass); ?>"><?php echo htmlspecialchars($displayStatus); ?></span><?php endif; ?>
                        </td>
                        <td style="vertical-align:middle;text-align:right;padding-right:1rem;">
                            <span style="font-size:.75rem;color:#64748b;white-space:nowrap;"><?php echo $actTime ? date('H:i', $actTime) : '--:--'; ?></span>
                        </td>
                    </tr>
                    <?php
                            endif;
                        endforeach;
                    else:
                        ?>
                    <tr>
                        <td colspan="4" style="text-align:center;padding:3rem 1rem;color:#475569;">
                            <i class="fas fa-stream" style="font-size:2rem;opacity:.2;display:block;margin-bottom:.75rem;"></i>
                            Nenhuma atividade registada hoje.
                        </td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($ativTotalPages > 1): ?>
                <div style="display:flex; justify-content:center; align-items:center; gap:.75rem; padding:1rem 0; flex-wrap:wrap;">
                    <?php if ($ativPage > 1): ?>
                    <button type="button" class="btn btn-secondary" onclick="goToAtividadesPage(<?php echo $ativPage - 1; ?>)"
                        style="padding:.5rem 1rem;">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </button>
                    <?php endif; ?>
                    <span style="color:var(--text-secondary); font-size:.9rem; background:var(--bg-tertiary); border:1px solid var(--border-primary); border-radius:8px; padding:.4rem .8rem;">
                        Página <?php echo $ativPage; ?> de <?php echo $ativTotalPages; ?>
                        &nbsp;&middot;&nbsp;<?php echo number_format($ativTotalRows); ?> evento(s)
                    </span>
                    <?php if ($ativPage < $ativTotalPages): ?>
                    <button type="button" class="btn btn-secondary" onclick="goToAtividadesPage(<?php echo $ativPage + 1; ?>)"
                        style="padding:.5rem 1rem;">
                        Próxima <i class="fas fa-chevron-right"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

        </section>

        <script>
            (function() {
                if (typeof Chart === 'undefined') return;
                var canvas = document.getElementById('chartInicioTendencia');
                if (!canvas) return;
                var labels = <?php echo json_encode($tendLabels); ?>;
                var presentesData = <?php echo json_encode($tendPresentes); ?>;
                var faltasData = <?php echo json_encode($tendFaltas); ?>;
                new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Presentes',
                                data: presentesData,
                                borderColor: '#43e97b',
                                backgroundColor: 'rgba(67,233,123,.15)',
                                tension: .3,
                                fill: true
                            },
                            {
                                label: 'Faltas',
                                data: faltasData,
                                borderColor: '#fa709a',
                                backgroundColor: 'rgba(250,112,154,.15)',
                                tension: .3,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#e2e8f0' } },
                            title: { display: true, text: 'Presenças vs Faltas (últimos 7 dias)', color: '#e2e8f0' }
                        },
                        scales: {
                            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#94a3b8', precision: 0 }, grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true }
                        }
                    }
                });
            })();
        </script>
