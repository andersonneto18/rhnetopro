<?php
// Secção "Turnos" — incluída a partir de admin/dashboard.php (depende de $pdo, $employees, $loggedInClientId, $csrfToken, etc. já definidos lá).
?>
        <section id="turnos-section" class="content-section">
            <style>
                @keyframes turnoLivePulse {
                    0% { box-shadow: 0 0 0 0 rgba(59,130,246,.55); }
                    70% { box-shadow: 0 0 0 6px rgba(59,130,246,0); }
                    100% { box-shadow: 0 0 0 0 rgba(59,130,246,0); }
                }
                .turno-status-chip {
                    display: inline-flex;
                    align-items: center;
                    gap: .35rem;
                    margin-top: .3rem;
                    padding: .18rem .5rem;
                    border-radius: 999px;
                    font-size: .7rem;
                    font-weight: 700;
                    width: fit-content;
                }
                .turno-status-chip--andamento {
                    background: rgba(59,130,246,.16);
                    color: #93c5fd;
                }
                .turno-status-chip--concluido {
                    background: rgba(34,197,94,.16);
                    color: #86efac;
                }
                .turno-status-chip--falta {
                    background: rgba(239,68,68,.16);
                    color: #fca5a5;
                }
                .turno-status-dot {
                    width: 7px;
                    height: 7px;
                    border-radius: 50%;
                    background: #3b82f6;
                    animation: turnoLivePulse 1.6s infinite;
                }
            </style>
            <?php $swapFeedback = (string)($_GET['swap'] ?? ''); ?>
            <?php if ($swapFeedback === 'created'): ?>
            <div class="alert-success"
                style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;padding:.85rem 1rem;border-radius:10px;background:linear-gradient(90deg,#14532d,#166534);color:#ecfdf5;">
                <span><i class="fas fa-check-circle" style="margin-right:.35rem;"></i>Solicitação de troca enviada para aprovação.</span>
                <a href="dashboard.php?section=solicitacoes&solicitacao_card=trocas_turno" class="btn btn-secondary"
                    style="background:#ecfdf5;color:#14532d;border:none;font-weight:700;white-space:nowrap;">
                    <i class="fas fa-arrow-right" style="margin-right:.25rem;"></i>Ver em Solicitações
                </a>
            </div>
            <?php endif; ?>

            <?php
            $turnosStatsTotal = 0; $turnosStatsAtivos = 0; $turnosStatsInativos = 0; $turnosFuncionariosSet = [];
            try {
                $stmtTurnosStats = $pdo->prepare("SELECT t.status, t.funcionario_id FROM turnos t INNER JOIN employees e ON e.id = t.funcionario_id WHERE e.client_id = ?");
                $stmtTurnosStats->execute([(int)$loggedInClientId]);
                foreach ($stmtTurnosStats->fetchAll(PDO::FETCH_ASSOC) as $tsRow) {
                    $turnosStatsTotal++;
                    if (mb_strtolower(trim((string)($tsRow['status'] ?? ''))) === 'ativo') $turnosStatsAtivos++;
                    else $turnosStatsInativos++;
                    $turnosFuncionariosSet[(int)$tsRow['funcionario_id']] = true;
                }
            } catch (Throwable $e) { error_log('Turnos stats: ' . $e->getMessage()); }
            $turnosFuncionariosCount = count($turnosFuncionariosSet);
            ?>

            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon" style="background:linear-gradient(135deg,#0f766e,#0d9488);box-shadow:0 4px 14px rgba(13,148,136,.35);"><i class="fas fa-business-time"></i></div>
                    <div>
                        <h2 class="frhd-title">Turnos</h2>
                        <p class="frhd-sub"><?php echo (int)$turnosStatsTotal; ?> turnos &middot; <?php echo (int)$turnosFuncionariosCount; ?> funcionário<?php echo $turnosFuncionariosCount !== 1 ? 's' : ''; ?> com escala</p>
                    </div>
                </div>
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                    <button type="button" id="btnOpenTurnoSwapModal" class="frhd-add-btn" style="background:linear-gradient(135deg,#0f766e,#0d9488);box-shadow:0 4px 12px rgba(13,148,136,.3);">
                        <i class="fas fa-exchange-alt"></i> Solicitar Troca
                    </button>
                    <button type="button" id="btnOpenBulkTurnoModal" class="frhd-add-btn" style="background:linear-gradient(135deg,#334155,#475569);box-shadow:0 4px 12px rgba(51,65,85,.3);">
                        <i class="fas fa-layer-group"></i> Criação em Massa
                    </button>
                    <button type="button" id="btnAddTurno" class="frhd-add-btn">
                        <i class="fas fa-plus"></i> Novo Turno
                    </button>
                </div>
            </div>

            <div class="fr-kpi-strip" style="grid-template-columns:repeat(4,1fr);">
                <div class="fr-kpi fr-kpi-total">
                    <div class="fr-kpi-icon"><i class="fas fa-business-time"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)$turnosStatsTotal; ?></span>
                        <span class="fr-kpi-lbl">Total de Turnos</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-active">
                    <div class="fr-kpi-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)$turnosStatsAtivos; ?></span>
                        <span class="fr-kpi-lbl">Ativos</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-inactive">
                    <div class="fr-kpi-icon"><i class="fas fa-pause-circle"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)$turnosStatsInativos; ?></span>
                        <span class="fr-kpi-lbl">Inativos</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-ferias">
                    <div class="fr-kpi-icon"><i class="fas fa-users"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)$turnosFuncionariosCount; ?></span>
                        <span class="fr-kpi-lbl">Funcionários c/ Escala</span>
                    </div>
                </div>
            </div>

            <div class="data-table fr-table-wrap" style="margin-top:.25rem;">
                <div class="fr-toolbar">
                    <!-- Row 1: search + view switch + filter + export -->
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" placeholder="Pesquisar turnos…" id="searchTurnos" class="fr-search">
                        </div>
                        <div class="fr-toolbar-right">
                            <div class="turnos-view-switch" role="group" style="display:flex;gap:.25rem;">
                                <button type="button" id="btnTurnosViewTable" class="fr-filter-toggle pa-filter-open" data-view="table" title="Tabela"><i class="fas fa-table"></i></button>
                                <button type="button" id="btnTurnosViewWeek"  class="fr-filter-toggle" data-view="week"  title="Semana"><i class="fas fa-calendar-week"></i></button>
                                <button type="button" id="btnTurnosViewMonth" class="fr-filter-toggle" data-view="month" title="Mês"><i class="fas fa-calendar-alt"></i></button>
                            </div>
                            <button type="button" class="fr-filter-toggle" id="turnosFilterToggle"
                                onclick="document.getElementById('turnosAdvFilters').classList.toggle('fr-adv-open'); this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                                <span class="fr-filter-badge" id="turnosFilterBadge" style="display:none"></span>
                            </button>
                            <div style="position:relative;">
                                <button class="fr-export-btn" onclick="toggleExportTurnosDropdown()">
                                    <i class="fas fa-arrow-up-from-bracket"></i> Exportar <i class="fas fa-chevron-down" style="font-size:.7em;margin-left:2px;"></i>
                                </button>
                                <div id="exportTurnosDropdown" class="fr-export-menu" style="display:none;">
                                    <a href="#" onclick="exportTurnosPDF(); return false;" class="fr-export-item"><i class="fas fa-file-pdf" style="color:#e74c3c;"></i> PDF</a>
                                    <a href="#" onclick="exportTurnosExcel(); return false;" class="fr-export-item"><i class="fas fa-file-excel" style="color:#27ae60;"></i> Excel</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: advanced filters -->
                    <div class="fr-adv-filters" id="turnosAdvFilters">
                        <select id="turnosFilterTipo" class="fr-select" style="width:160px;">
                            <option value="">Todos os turnos</option>
                        </select>
                        <select id="turnosFilterEscala" class="fr-select" style="width:160px;">
                            <option value="">Todas as escalas</option>
                        </select>
                        <select id="turnosFilterStatus" class="fr-select" style="width:150px;">
                            <option value="">Todos os status</option>
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                        <select id="turnosFilterDepartment" class="fr-select" style="width:220px;">
                            <option value="">Todas as equipas/departamentos</option>
                        </select>
                        <button type="button" id="clearTurnosAdvancedFilters" class="fr-clear-btn" style="display:none;">
                            <i class="fas fa-times"></i> Limpar
                        </button>
                    </div>

                    <!-- Row 3: publication panel -->
                    <div id="turnosPublicationBox" style="display:flex;gap:.45rem;align-items:center;flex-wrap:wrap;padding:.6rem .75rem;border-radius:10px;background:rgba(13,148,136,.07);border:1px solid rgba(13,148,136,.18);">
                        <span style="font-size:.75rem;font-weight:700;color:#2dd4bf;text-transform:uppercase;letter-spacing:.06em;margin-right:.25rem;"><i class="fas fa-paper-plane" style="margin-right:.3rem;"></i>Oficialização</span>
                        <input type="date" id="turnosPublicationStart" class="fr-select" style="width:155px;">
                        <input type="date" id="turnosPublicationEnd" class="fr-select" style="width:155px;">
                        <button type="button" id="btnCheckTurnosPublication" class="fr-filter-toggle"><i class="fas fa-search"></i> Ver Estado</button>
                        <button type="button" id="btnPublishTurnosPeriod" class="fr-filter-toggle" style="border-color:rgba(13,148,136,.4);color:#2dd4bf;"><i class="fas fa-paper-plane"></i> Publicar</button>
                        <button type="button" id="btnCloseTurnosPeriod" class="fr-filter-toggle" style="border-color:rgba(100,116,139,.3);color:#94a3b8;"><i class="fas fa-lock"></i> Fechar</button>
                        <button type="button" id="btnReopenTurnosPeriod" class="fr-filter-toggle" style="border-color:rgba(251,191,36,.3);color:#fbbf24;"><i class="fas fa-lock-open"></i> Reabrir</button>
                        <span id="turnosPublicationBadge" style="display:inline-flex;align-items:center;border-radius:999px;padding:.2rem .65rem;font-size:.72rem;font-weight:700;background:rgba(100,116,139,.15);color:#94a3b8;">Sem período</span>
                        <small id="turnosPublicationMeta" style="color:#64748b;font-weight:600;font-size:.72rem;"></small>
                    </div>
                </div>

                <select id="turnosAllEmployeesCatalog" style="display:none;">
                    <?php foreach ($employees as $emp):
                        $catalogStatus = mb_strtolower(trim((string)($emp['status'] ?? '')));
                        if (in_array($catalogStatus, ['inactive', 'inativo', 'ferias', 'férias'], true)) continue;
                        $catalogDepartment = trim((string)($emp['department'] ?? ''));
                        $catalogRole = trim((string)($emp['role'] ?? ($emp['position'] ?? '')));
                        $catalogTeam = $catalogDepartment !== '' ? $catalogDepartment : $catalogRole;
                    ?>
                    <option value="<?php echo (int)$emp['id']; ?>"
                        data-name="<?php echo htmlspecialchars((string)$emp['name'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-team="<?php echo htmlspecialchars($catalogTeam, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string)$emp['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <table id="turnosTable" class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-emp">Funcionário</th>
                            <th>Turno</th>
                            <th>Horário</th>
                            <th>Dias / Vigência</th>
                            <th>Escala</th>
                            <th class="fr-th-status">Status</th>
                            <th class="fr-th-acts">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare("
                    SELECT t.*, e.name as funcionario_nome, e.profile_picture as funcionario_profile_picture
                    FROM turnos t
                    INNER JOIN employees e ON t.funcionario_id = e.id
                    WHERE e.client_id = ?
                    ORDER BY e.name ASC
                ");
                        $stmt->execute([$loggedInClientId]);
                        $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                                $stmtClosedPeriods = $pdo->prepare(
                                                        "SELECT period_start, period_end
                                                         FROM turnos_publicacoes
                                                         WHERE client_id = ?
                                                             AND LOWER(COALESCE(status, '')) = 'fechado'"
                                                );
                                                $stmtClosedPeriods->execute([$loggedInClientId]);
                                                $closedPeriods = $stmtClosedPeriods->fetchAll(PDO::FETCH_ASSOC) ?: [];

                        $turnoTeamByEmployeeId = [];
                        foreach ($employees as $empTeam) {
                            $teamDepartment = trim((string)($empTeam['department'] ?? ''));
                            $teamRole = trim((string)($empTeam['role'] ?? ($empTeam['position'] ?? '')));
                            $turnoTeamByEmployeeId[(int)($empTeam['id'] ?? 0)] = $teamDepartment !== '' ? $teamDepartment : $teamRole;
                        }

                        foreach ($turnos as $turno):
                            $horario = date('H:i', strtotime($turno['horario_inicio'])) . ' - ' .
                                date('H:i', strtotime($turno['horario_fim']));
                            $vigenciaLabel = formatTurnoDateRange($turno['data_inicio'] ?? null, $turno['data_fim'] ?? null);
                            $turnoTeam = (string)($turnoTeamByEmployeeId[(int)($turno['funcionario_id'] ?? 0)] ?? '');
                            $turnoStart = normalizeTurnoDate($turno['data_inicio'] ?? null);
                            $turnoEnd = normalizeTurnoDate($turno['data_fim'] ?? null);
                            $isTurnoLocked = false;
                            foreach ($closedPeriods as $closedPeriod) {
                                $closedStart = normalizeTurnoDate((string)($closedPeriod['period_start'] ?? ''));
                                $closedEnd = normalizeTurnoDate((string)($closedPeriod['period_end'] ?? ''));
                                if ($closedStart && $closedEnd && turnoDateRangesOverlap($turnoStart, $turnoEnd, $closedStart, $closedEnd)) {
                                    $isTurnoLocked = true;
                                    break;
                                }
                            }
                        ?>
                        <?php
                            $turnoInitials = strtoupper(mb_substr((string)$turno['funcionario_nome'], 0, 2));
                        ?>
                        <tr class="fr-row"
                            data-funcionario="<?php echo htmlspecialchars((string)$turno['funcionario_nome'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-turno-employee-id="<?php echo (int)($turno['funcionario_id'] ?? 0); ?>"
                            data-turno-tipo="<?php echo htmlspecialchars((string)$turno['turno_tipo'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-turno-dias="<?php echo htmlspecialchars((string)$turno['dias_semana'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-turno-date-start="<?php echo htmlspecialchars((string)($turno['data_inicio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            data-turno-date-end="<?php echo htmlspecialchars((string)($turno['data_fim'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            data-turno-team="<?php echo htmlspecialchars($turnoTeam, ENT_QUOTES, 'UTF-8'); ?>"
                            data-turno-escala="<?php echo htmlspecialchars((string)$turno['escala'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-turno-status="<?php echo htmlspecialchars((string)$turno['status'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-turno-locked="<?php echo $isTurnoLocked ? '1' : '0'; ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#0f766e,#0d9488);border-radius:12px;">
                                        <?php if (!empty($turno['funcionario_profile_picture'])): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars((string)$turno['funcionario_profile_picture']); ?>"
                                            alt="" onerror="this.parentElement.textContent='<?php echo $turnoInitials; ?>'; this.remove();">
                                        <?php else: echo $turnoInitials; endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo htmlspecialchars($turno['funcionario_nome']); ?></span>
                                        <span class="fr-emp-email"><?php echo htmlspecialchars($turnoTeam ?: '—'); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="fr-td-role">
                                <span class="fr-role-pos"><?php echo htmlspecialchars($turno['turno_tipo']); ?></span>
                            </td>
                            <td class="fr-td-role"><?php echo $horario; ?></td>
                            <td class="fr-td-role">
                                <span class="fr-role-pos"><?php echo htmlspecialchars($turno['dias_semana']); ?></span>
                                <?php if ($vigenciaLabel !== ''): ?>
                                <span class="fr-role-dept"><?php echo htmlspecialchars($vigenciaLabel); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($turno['escala']); ?></td>
                            <td class="fr-td-status">
                                <span class="status-badge <?php echo $turno['status'] === 'ativo' ? 'status-presente' : 'status-falta'; ?>">
                                    <?php echo ucfirst($turno['status']); ?>
                                </span>
                                <?php if ($isTurnoLocked): ?>
                                <span style="display:inline-flex;align-items:center;gap:3px;margin-top:3px;font-size:.67rem;color:#94a3b8;"><i class="fas fa-lock"></i> Fechado</span>
                                <?php endif; ?>
                            </td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <button type="button"
                                        class="fr-btn fr-btn-edit btn-edit employee-action-btn<?php echo $isTurnoLocked ? ' fr-btn-off' : ''; ?>"
                                        data-id="<?php echo htmlspecialchars($turno['id']); ?>"
                                        title="<?php echo $isTurnoLocked ? 'Período fechado' : 'Editar turno'; ?>"
                                        <?php echo $isTurnoLocked ? 'disabled' : ''; ?>>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button"
                                        class="fr-btn fr-btn-deact btn-delete employee-action-btn<?php echo $isTurnoLocked ? ' fr-btn-off' : ''; ?>"
                                        data-id="<?php echo htmlspecialchars($turno['id']); ?>"
                                        title="<?php echo $isTurnoLocked ? 'Período fechado' : 'Excluir turno'; ?>"
                                        <?php echo $isTurnoLocked ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div id="turnosCalendarWrapper" class="turnos-calendar-wrapper" style="display:none;">
                    <div class="turnos-calendar-header">
                        <div class="turnos-calendar-nav">
                            <button type="button" id="turnosCalendarPrev" class="btn btn-secondary" style="white-space:nowrap;">
                                <i class="fas fa-chevron-left"></i>
                                <span>Anterior</span>
                            </button>
                            <button type="button" id="turnosCalendarToday" class="btn btn-primary" style="white-space:nowrap;">
                                <i class="fas fa-dot-circle"></i>
                                <span>Hoje</span>
                            </button>
                            <button type="button" id="turnosCalendarNext" class="btn btn-secondary" style="white-space:nowrap;">
                                <span>Próximo</span>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <h4 id="turnosCalendarRangeLabel" class="turnos-calendar-range-label">Calendário</h4>
                    </div>
                    <div id="turnosCalendarGrid" class="turnos-calendar-grid"></div>
                </div>
            </div>


            <!-- Modal criar/editar turno -->
            <div id="turnoModal" class="modal" style="display:none;">
                <div class="am-sheet">

                    <button class="am-close" type="button" id="closeTurnoModal"
                        onclick="document.getElementById('turnoModal').style.display='none'"
                        aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#0f766e,#0d9488);box-shadow:0 6px 16px rgba(13,148,136,.35);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div>
                            <h2 class="am-title" id="turnoModalTitle">Novo Turno</h2>
                            <p class="am-subtitle">Configure o horário e escala do colaborador</p>
                        </div>
                    </div>

                    <div id="turnoInlineError" class="am-error" style="display:none;"></div>

                    <form id="turnoForm">
                        <input type="hidden" name="id" id="turnoId">

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-user"></i> Funcionário</div>
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="turnoFuncionario">Funcionário *</label>
                                    <select class="am-inp am-sel" id="turnoFuncionario" name="funcionario_id" required>
                                        <option value="">Selecione o funcionário…</option>
                                        <?php foreach ($employees as $emp):
                                            $empStatus = mb_strtolower(trim((string)($emp['status'] ?? '')));
                                            if (in_array($empStatus, ['inactive', 'inativo', 'ferias', 'férias'], true)) continue;
                                            $empDepartment = trim((string)($emp['department'] ?? ''));
                                            $empRole = trim((string)($emp['role'] ?? ($emp['position'] ?? '')));
                                            $empTeam = $empDepartment !== '' ? $empDepartment : $empRole;
                                        ?>
                                        <option value="<?php echo (int)$emp['id']; ?>"
                                            data-team="<?php echo htmlspecialchars($empTeam, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($emp['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-clock"></i> Horário</div>
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="turnoTipo">Tipo de Turno *</label>
                                    <select class="am-inp am-sel" id="turnoTipo" name="turno_tipo" required>
                                        <option value="">Selecione o turno…</option>
                                        <option value="Manhã">Manhã</option>
                                        <option value="Tarde">Tarde</option>
                                        <option value="Noturno">Noturno</option>
                                        <option value="Intermitente">Intermitente</option>
                                    </select>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="turnoInicio">Início *</label>
                                    <input class="am-inp" type="time" id="turnoInicio" name="horario_inicio" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="turnoFim">Fim *</label>
                                    <input class="am-inp" type="time" id="turnoFim" name="horario_fim" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="turnoDataInicio">Data de Início <span class="am-opt">(vigência)</span></label>
                                    <input class="am-inp" type="date" id="turnoDataInicio" name="data_inicio">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="turnoDataFim">Data de Fim <span class="am-opt">(vigência)</span></label>
                                    <input class="am-inp" type="date" id="turnoDataFim" name="data_fim">
                                </div>
                            </div>
                            <span class="am-hint" style="margin-top:.25rem;">Defina a vigência para que a escala apareça em datas reais. Se deixar vazio, o turno é recorrente por dia da semana.</span>
                        </div>

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-calendar-week"></i> Dias da Semana</div>
                            <div class="am-f am-f-full">
                                <label class="am-lbl" for="turnoDiasToggle">Dias activos</label>
                                <div id="turnoDiasPicker" style="position:relative;">
                                    <button type="button" id="turnoDiasToggle"
                                        class="am-inp" style="text-align:left;display:flex;justify-content:space-between;align-items:center;cursor:pointer;background:var(--input-bg,#1e293b);">
                                        <span id="turnoDiasLabel" style="color:var(--text-secondary);">Selecione os dias…</span>
                                        <i class="fas fa-chevron-down" style="font-size:.75rem;opacity:.6;"></i>
                                    </button>
                                    <div id="turnoDiasMenu"
                                        style="display:none;position:absolute;z-index:1200;top:calc(100% + 4px);left:0;right:0;background:var(--card-bg,#1e293b);border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:8px;box-shadow:0 12px 28px rgba(0,0,0,.35);display:grid;grid-template-columns:repeat(4,1fr);gap:.3rem;">
                                        <?php foreach (['Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo'] as $dia): ?>
                                        <label style="display:flex;align-items:center;gap:6px;padding:7px 8px;border-radius:8px;cursor:pointer;border:1px solid rgba(148,163,184,.15);background:rgba(255,255,255,.03);font-size:.82rem;transition:background .15s;">
                                            <input type="checkbox" class="turno-dia-option" value="<?php echo $dia; ?>" style="accent-color:#0d9488;"> <?php echo $dia; ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" id="turnoDiasInput" name="dias_semana">
                                </div>
                            </div>
                        </div>

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-layer-group"></i> Escala &amp; Estado</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="turnoEscala">Escala *</label>
                                    <select class="am-inp am-sel" id="turnoEscala" name="escala" required>
                                        <option value="">Selecione a escala…</option>
                                        <option value="Fixa semanal">Fixa semanal</option>
                                        <option value="Rotativa">Rotativa</option>
                                        <option value="Flexível">Flexível</option>
                                        <option value="12x36">12x36</option>
                                    </select>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="turnoStatus">Estado</label>
                                    <select class="am-inp am-sel" id="turnoStatus" name="status" required>
                                        <option value="ativo">Ativo</option>
                                        <option value="inativo">Inativo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel"
                                onclick="document.getElementById('turnoModal').style.display='none'">Cancelar</button>
                            <button type="submit" class="am-btn-submit" id="saveTurnoBtn"
                                style="background:linear-gradient(135deg,#0f766e,#0d9488);box-shadow:0 4px 14px rgba(13,148,136,.28);">
                                <i class="fas fa-floppy-disk"></i> Guardar Turno
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal criação de turnos em massa -->
            <div id="bulkTurnoModal" class="modal" style="display:none;">
                <div class="am-sheet">

                    <button class="am-close" type="button" id="closeBulkTurnoModal"
                        onclick="document.getElementById('bulkTurnoModal').style.display='none'"
                        aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#334155,#475569);box-shadow:0 6px 16px rgba(51,65,85,.35);">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Criação em Massa</h2>
                            <p class="am-subtitle">Atribua o mesmo turno a vários colaboradores de uma vez</p>
                        </div>
                    </div>

                    <div id="bulkTurnoInlineError" class="am-error" style="display:none;"></div>

                    <form id="bulkTurnoForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-clock"></i> Horário</div>
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="bulkTurnoTipo">Tipo de Turno *</label>
                                    <select class="am-inp am-sel" id="bulkTurnoTipo" name="turno_tipo" required>
                                        <option value="">Selecione o turno…</option>
                                        <option value="Manhã">Manhã</option>
                                        <option value="Tarde">Tarde</option>
                                        <option value="Noturno">Noturno</option>
                                        <option value="Intermitente">Intermitente</option>
                                    </select>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="bulkTurnoInicio">Início *</label>
                                    <input class="am-inp" type="time" id="bulkTurnoInicio" name="horario_inicio" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="bulkTurnoFim">Fim *</label>
                                    <input class="am-inp" type="time" id="bulkTurnoFim" name="horario_fim" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="bulkTurnoDataInicio">Data de Início <span class="am-opt">(vigência)</span></label>
                                    <input class="am-inp" type="date" id="bulkTurnoDataInicio" name="data_inicio">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="bulkTurnoDataFim">Data de Fim <span class="am-opt">(vigência)</span></label>
                                    <input class="am-inp" type="date" id="bulkTurnoDataFim" name="data_fim">
                                </div>
                            </div>
                        </div>

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-calendar-week"></i> Dias da Semana</div>
                            <div class="am-f am-f-full">
                                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.3rem;padding:.65rem;background:var(--bg-tertiary,rgba(15,23,42,.08));border:1px solid rgba(148,163,184,.2);border-radius:12px;">
                                    <?php foreach (['Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo'] as $dia): ?>
                                    <label style="display:flex;align-items:center;gap:6px;padding:7px 8px;border-radius:8px;cursor:pointer;border:1px solid rgba(148,163,184,.15);background:rgba(255,255,255,.03);font-size:.82rem;transition:background .15s;">
                                        <input type="checkbox" class="bulk-turno-dia-option" value="<?php echo $dia; ?>" style="accent-color:#475569;"> <?php echo $dia; ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-layer-group"></i> Escala &amp; Estado</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="bulkTurnoEscala">Escala *</label>
                                    <select class="am-inp am-sel" id="bulkTurnoEscala" name="escala" required>
                                        <option value="">Selecione a escala…</option>
                                        <option value="Fixa semanal">Fixa semanal</option>
                                        <option value="Rotativa">Rotativa</option>
                                        <option value="Flexível">Flexível</option>
                                        <option value="12x36">12x36</option>
                                    </select>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="bulkTurnoStatus">Estado</label>
                                    <select class="am-inp am-sel" id="bulkTurnoStatus" name="status" required>
                                        <option value="ativo">Ativo</option>
                                        <option value="inativo">Inativo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-users"></i> Funcionários</div>
                            <div class="am-f am-f-full">
                                <label class="am-lbl">Selecione um ou mais colaboradores</label>
                                <div id="bulkTurnoEmployeesList"
                                    style="max-height:220px;overflow-y:auto;display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:.35rem;padding:.55rem;background:var(--bg-tertiary,rgba(15,23,42,.08));border:1px solid rgba(148,163,184,.2);border-radius:12px;">
                                    <?php foreach ($employees as $emp):
                                        $bulkEmpStatus = mb_strtolower(trim((string)($emp['status'] ?? '')));
                                        if (in_array($bulkEmpStatus, ['inactive', 'inativo', 'ferias', 'férias'], true)) continue;
                                    ?>
                                    <label style="display:flex;align-items:center;gap:8px;padding:7px 8px;border:1px solid rgba(148,163,184,.18);border-radius:8px;cursor:pointer;background:rgba(255,255,255,.04);font-size:.83rem;transition:background .15s;">
                                        <input type="checkbox" class="bulk-turno-employee-option" name="employee_ids[]"
                                            value="<?php echo (int)$emp['id']; ?>" style="accent-color:#475569;">
                                        <span><?php echo htmlspecialchars((string)$emp['name']); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel"
                                onclick="document.getElementById('bulkTurnoModal').style.display='none'">Cancelar</button>
                            <button type="submit" class="am-btn-submit" id="saveBulkTurnoBtn"
                                style="background:linear-gradient(135deg,#334155,#475569);box-shadow:0 4px 14px rgba(51,65,85,.28);">
                                <i class="fas fa-layer-group"></i> Criar Turnos em Massa
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal solicitar troca de turno -->
            <div id="turnoSwapModal" class="modal" style="display:none;">
                <div class="am-sheet">

                    <button class="am-close" type="button" id="closeTurnoSwapModal"
                        onclick="document.getElementById('turnoSwapModal').style.display='none'"
                        aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#d97706,#f59e0b);box-shadow:0 6px 16px rgba(217,119,6,.35);">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Solicitar Troca de Turno</h2>
                            <p class="am-subtitle">Proposta de troca de horário entre colegas</p>
                        </div>
                    </div>

                    <div id="turnoSwapInlineError" class="am-error" style="display:none;"></div>

                    <form id="turnoSwapForm" method="POST" action="dashboard.php?section=turnos">
                        <input type="hidden" name="action" value="create_turno_swap_request">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-arrows-alt-h"></i> Turnos a Trocar</div>
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="swapRequesterTurno">Turno do Solicitante *</label>
                                    <select class="am-inp am-sel" id="swapRequesterTurno" name="requester_turno_id" required>
                                        <option value="">Selecione o turno…</option>
                                        <?php foreach ($turnos as $swapTurno):
                                            $swapLabel = (string)($swapTurno['funcionario_nome'] ?? 'Funcionário')
                                                . ' — ' . (string)($swapTurno['turno_tipo'] ?? '-')
                                                . ' ' . substr((string)($swapTurno['horario_inicio'] ?? ''), 0, 5)
                                                . '–' . substr((string)($swapTurno['horario_fim'] ?? ''), 0, 5)
                                                . ' (' . (string)($swapTurno['dias_semana'] ?? '-') . ')';
                                        ?>
                                        <option value="<?php echo (int)$swapTurno['id']; ?>"
                                            data-employee-id="<?php echo (int)$swapTurno['funcionario_id']; ?>">
                                            <?php echo htmlspecialchars($swapLabel); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="swapTargetTurno">Turno do Colega *</label>
                                    <select class="am-inp am-sel" id="swapTargetTurno" name="target_turno_id" required>
                                        <option value="">Selecione o turno para troca…</option>
                                        <?php foreach ($turnos as $swapTurno):
                                            $swapLabel = (string)($swapTurno['funcionario_nome'] ?? 'Funcionário')
                                                . ' — ' . (string)($swapTurno['turno_tipo'] ?? '-')
                                                . ' ' . substr((string)($swapTurno['horario_inicio'] ?? ''), 0, 5)
                                                . '–' . substr((string)($swapTurno['horario_fim'] ?? ''), 0, 5)
                                                . ' (' . (string)($swapTurno['dias_semana'] ?? '-') . ')';
                                        ?>
                                        <option value="<?php echo (int)$swapTurno['id']; ?>"
                                            data-employee-id="<?php echo (int)$swapTurno['funcionario_id']; ?>">
                                            <?php echo htmlspecialchars($swapLabel); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-info-circle"></i> Detalhes do Pedido</div>
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="swapRequestedDate">Data de Referência <span class="am-opt">(opcional)</span></label>
                                    <input class="am-inp" type="date" id="swapRequestedDate" name="requested_date">
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="swapReason">Motivo</label>
                                    <textarea class="am-inp" id="swapReason" name="reason" rows="3" maxlength="500"
                                        placeholder="Descreva o motivo da troca…" style="resize:vertical;"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel"
                                onclick="document.getElementById('turnoSwapModal').style.display='none'">Cancelar</button>
                            <button type="submit" class="am-btn-submit"
                                style="background:linear-gradient(135deg,#d97706,#f59e0b);box-shadow:0 4px 14px rgba(217,119,6,.28);">
                                <i class="fas fa-paper-plane"></i> Enviar Solicitação
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </section>
