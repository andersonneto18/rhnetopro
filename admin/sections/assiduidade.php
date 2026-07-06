<?php
// Secção "Assiduidade" — incluída a partir de admin/dashboard.php (depende de $pdo, $employees, $loggedInClientId, $estHorario, etc. já definidos lá). Define $allJustificativas, $pontoDateColumn, $justificativasPendentes, etc. usados pela secção Solicitações logo a seguir.
?>
        <section id="assiduidade-section" class="content-section">

            <style>
            /* ── togglePresencaHistoryBtn ── */
            #togglePresencaHistoryBtn {
                transition: transform .16s ease, box-shadow .2s ease, background .2s ease, border-color .2s ease;
            }
            #togglePresencaHistoryBtn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15,23,42,.16); }
            #togglePresencaHistoryBtn:active { transform: translateY(0); box-shadow: 0 2px 6px rgba(15,23,42,.2); }
            #togglePresencaHistoryBtn:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,.25); }
            #togglePresencaHistoryBtn.history-active {
                background: linear-gradient(135deg,#1d4ed8,#2563eb);
                color:#fff; border:1px solid #1e40af; box-shadow:0 6px 18px rgba(37,99,235,.35);
            }
            #togglePresencaHistoryBtn.history-active:hover { box-shadow:0 8px 22px rgba(37,99,235,.42); }
            #togglePresencaHistoryBtn.history-active i { color:#fff; }

            /* ── pa-* Presença section design system ── */
            .pa-hdr { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:1.5rem; background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.07)); border-radius:14px; padding:.875rem 1.25rem; width:100%; box-sizing:border-box; }
            .pa-hdr-icon {
                width:46px; height:46px; border-radius:13px; flex-shrink:0;
                background:linear-gradient(135deg,#3b82f6,#1d4ed8);
                display:flex; align-items:center; justify-content:center;
                color:#fff; font-size:1.15rem;
                box-shadow:0 4px 14px rgba(59,130,246,.35);
            }
            .pa-hdr-title { font-size:1.25rem; font-weight:700; color:var(--text-primary); margin:0; }
            .pa-hdr-sub  { font-size:.78rem; color:var(--text-secondary); margin:0; }

            /* KPI strip */
            .pa-kpi-strip {
                display:grid;
                grid-template-columns:repeat(6,1fr);
                gap:.65rem; margin-bottom:1.25rem;
            }
            @media(max-width:1100px){ .pa-kpi-strip{ grid-template-columns:repeat(3,1fr); } }
            @media(max-width:640px) { .pa-kpi-strip{ grid-template-columns:repeat(2,1fr); } }
            .pa-kpi-card {
                background:var(--bg-secondary); border:1px solid var(--border-primary);
                border-radius:12px; padding:.8rem 1rem;
                cursor:pointer; text-align:left;
                transition:transform .15s, box-shadow .15s, border-color .15s;
                position:relative; overflow:hidden;
            }
            .pa-kpi-card::before {
                content:''; position:absolute; top:0; left:0; right:0; height:3px;
                border-radius:12px 12px 0 0;
                background:var(--pa-accent,#3b82f6); opacity:0; transition:opacity .15s;
            }
            .pa-kpi-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.22); border-color:var(--pa-accent,#3b82f6); }
            .pa-kpi-card:hover::before, .pa-kpi-active::before { opacity:1; }
            .pa-kpi-active { border-color:var(--pa-accent,#3b82f6); box-shadow:0 0 0 2px rgba(59,130,246,.2); }
            .pa-kpi-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:.45rem; }
            .pa-kpi-num { font-size:1.75rem; font-weight:800; color:var(--text-primary); letter-spacing:-.03em; line-height:1; }
            .pa-kpi-ico {
                width:32px; height:32px; border-radius:8px; flex-shrink:0;
                background:rgba(255,255,255,.07);
                display:flex; align-items:center; justify-content:center;
                color:var(--pa-accent,#3b82f6); font-size:.88rem;
            }
            .pa-kpi-lbl { font-size:.72rem; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.04em; }

            /* Toolbar */
            .pa-toolbar {
                background:var(--bg-secondary); border:1px solid var(--border-primary);
                border-radius:12px 12px 0 0; border-bottom:none;
                padding:1rem 1.1rem .8rem;
            }
            .pa-toolbar-row1 { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; margin-bottom:.6rem; }
            .pa-toolbar-row2 { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; }
            .pa-tbar-title { font-size:1rem; font-weight:700; color:var(--text-primary); white-space:nowrap; flex-shrink:0; }
            .pa-inp {
                background:var(--bg-tertiary); border:1px solid var(--border-primary);
                color:var(--text-primary); border-radius:8px;
                padding:.48rem .75rem; font-size:.875rem; outline:none;
                transition:border-color .15s;
            }
            .pa-inp:focus { border-color:#3b82f6; }
            .pa-inp::placeholder { color:var(--text-secondary); }
            .pa-search { flex:1; min-width:200px; }
            .pa-chip {
                font-weight:600; color:var(--text-primary); font-size:.85rem;
                white-space:nowrap; background:var(--bg-tertiary);
                border:1px solid var(--border-primary); border-radius:8px;
                padding:.42rem .7rem; flex-shrink:0;
            }
            .pa-spacer { flex:1; }

            /* Table employee cell */
            .pa-emp-cell { display:flex; align-items:center; gap:.55rem; }
            .pa-emp-av {
                width:32px; height:32px; border-radius:50%; overflow:hidden; flex-shrink:0;
                background:linear-gradient(135deg,#475569,#334155);
                color:#fff; display:flex; align-items:center; justify-content:center;
                font-size:.7rem; font-weight:700;
            }
            .pa-emp-av img { width:100%; height:100%; object-fit:cover; }
            .pa-emp-name { font-weight:600; font-size:.875rem; }

            /* Action buttons */
            .pa-acts { display:flex; gap:.35rem; align-items:center; justify-content:center; }
            .pa-btn {
                width:30px; height:30px; border-radius:7px; border:none;
                display:inline-flex; align-items:center; justify-content:center;
                font-size:.78rem; cursor:pointer;
                transition:transform .12s, box-shadow .12s;
                flex-shrink:0;
            }
            .pa-btn:hover { transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.25); }
            .pa-btn:disabled { opacity:.45; cursor:not-allowed; transform:none; box-shadow:none; }
            .pa-btn-view   { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; }
            .pa-btn-edit   { background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; }
            .pa-btn-assign { background:linear-gradient(135deg,#64748b,#475569); color:#fff; }

            /* Filter toggle active state */
            #paFilterToggle.pa-filter-open {
                border-color:#3b82f6; color:#60a5fa;
                background:rgba(59,130,246,.08);
            }

            /* Attendance modals */
            #modalVerPresenca,
            #modalEditarPresenca { overflow-y:auto; padding:24px 16px 48px; }
            </style>


            <div class="pa-hdr">
                <div class="pa-hdr-icon"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <h2 class="pa-hdr-title">Marcação de Presença</h2>
                    <p class="pa-hdr-sub">Registos diários, ponto e assiduidade</p>
                </div>
            </div>

            <div id="presencaHistoryPanel"
                style="max-height: 0; overflow: hidden; opacity: 0; transition: max-height 0.35s ease, opacity 0.25s ease, margin-top 0.25s ease; margin-top: 0;">
                <div class="data-table" style="margin-top: 1.25rem;">
                    <div class="table-header"
                        style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <h3
                            style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary); white-space: nowrap;">
                            Histórico de Presença
                        </h3>

                        <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-left:auto;">
                            <input type="text" id="searchHistoryPresenca" placeholder="Pesquisar no histórico..."
                                class="search-input" style="width: 260px; min-width: 220px;">

                            <input type="date" id="filterHistoryPresencaStart" class="search-input"
                                style="min-width: 160px;" title="Data inicial do histórico"
                                value="<?php echo htmlspecialchars($historyServerStart); ?>">

                            <input type="date" id="filterHistoryPresencaEnd" class="search-input"
                                style="min-width: 160px;" title="Data final do histórico"
                                value="<?php echo htmlspecialchars($historyServerEnd); ?>">

                            <select id="filterHistoryPresencaStatus" class="search-input" style="min-width: 180px;">
                                <option value="">Todos os status</option>
                                <option value="presente">Presente</option>
                                <option value="falta">Falta</option>
                                <option value="em-aberto">Em aberto</option>
                                <option value="invalidado">Invalidado</option>
                            </select>

                            <button id="clearHistoryPresenca" class="btn btn-secondary"
                                style="padding: 0.6rem 1rem; display: none; white-space: nowrap; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-primary);">
                                <i class="fas fa-times"></i> Limpar
                            </button>

                            <button type="button" id="applyHistoryPresencaServer" class="btn btn-primary"
                                onclick="applyHistoryPresencaServerFilter()"
                                style="padding: 0.6rem 1rem; white-space: nowrap;">
                                <i class="fas fa-database"></i> Aplicar
                            </button>

                            <button type="button" id="clearHistoryPresencaServer" class="btn btn-secondary"
                                onclick="clearHistoryPresencaServerFilter()"
                                style="padding: 0.6rem 1rem; white-space: nowrap; background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-primary);">
                                <i class="fas fa-eraser"></i> Limpar
                            </button>

                            <span id="resultCountHistoryPresenca"
                                style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem; white-space: nowrap; background: var(--bg-tertiary); border: 1px solid var(--border-primary); border-radius: 8px; padding: 0.45rem 0.75rem;"></span>

                            <div class="dropdown" style="position: relative; display: inline-block;">
                                <button class="btn btn-accent" style="white-space: nowrap;"
                                    onclick="toggleExportHistoryPresencaDropdown()">
                                    <i class="fas fa-download"></i>
                                    <span>Exportar Histórico</span>
                                    <i class="fas fa-chevron-down" style="margin-left: 5px; font-size: 0.8em;"></i>
                                </button>
                                <div id="exportHistoryPresencaDropdown" class="dropdown-content"
                                    style="display: none; position: absolute; right: 0; background-color: white; min-width: 210px; box-shadow: 0 8px 16px rgba(0,0,0,0.2); border-radius: 8px; z-index: 1; margin-top: 5px;">
                                    <a href="#" onclick="exportHistoryPresencaPDF(); return false;"
                                        style="color: #1f2937; padding: 12px 16px; text-decoration: none; display: block; border-bottom: 1px solid #e5e7eb;">
                                        <i class="fas fa-file-pdf" style="color: #e74c3c; margin-right: 8px;"></i>
                                        Exportar PDF
                                    </a>
                                    <a href="#" onclick="exportHistoryPresencaCSV(); return false;"
                                        style="color: #1f2937; padding: 12px 16px; text-decoration: none; display: block;">
                                        <i class="fas fa-file-csv" style="color: #27ae60; margin-right: 8px;"></i>
                                        Exportar CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table class="table" id="historyPresencaTable">
                        <thead>
                            <tr>
                                <th>Funcionário</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Tipo de Dia</th>
                                <th>Entrada</th>
                                <th>Saída</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $historicoPresenca = [];
                            $histPresencaDateColumn = 'data_registro';
                            $histPontoDateColumn = 'data_registro';

                            try {
                                $histColsPresenca = $pdo->query("SHOW COLUMNS FROM presencas")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                                if (!in_array('data_registro', $histColsPresenca, true) && in_array('data', $histColsPresenca, true)) {
                                    $histPresencaDateColumn = 'data';
                                }
                            } catch (Exception $e) {
                                // Mantém padrão data_registro
                            }

                            try {
                                $histColsPonto = $pdo->query("SHOW COLUMNS FROM registros_ponto")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                                if (!in_array('data_registro', $histColsPonto, true) && in_array('data', $histColsPonto, true)) {
                                    $histPontoDateColumn = 'data';
                                }
                            } catch (Exception $e) {
                                // Mantém padrão data_registro
                            }

                            $historyDateFiltersSql = '';
                            $historyParamsBase = [(int)$loggedInClientId];

                            if ($historyServerStart !== '') {
                                $historyDateFiltersSql .= " AND DATE(p.{$histPresencaDateColumn}) >= ?";
                                $historyParamsBase[] = $historyServerStart;
                            }
                            if ($historyServerEnd !== '') {
                                $historyDateFiltersSql .= " AND DATE(p.{$histPresencaDateColumn}) <= ?";
                                $historyParamsBase[] = $historyServerEnd;
                            }

                            // Count total rows for pagination
                            $histTotalRows  = 0;
                            $histTotalPages = 1;
                            try {
                                $stmtHistCount = $pdo->prepare(
                                    "SELECT COUNT(*)
                         FROM presencas p
                         INNER JOIN employees e ON e.id = p.funcionario_id
                         WHERE e.client_id = ? {$historyDateFiltersSql}"
                                );
                                $stmtHistCount->execute($historyParamsBase);
                                $histTotalRows  = (int)$stmtHistCount->fetchColumn();
                                $histTotalPages = max(1, (int)ceil($histTotalRows / $histPerPage));
                                if ($histPage > $histTotalPages) {
                                    $histPage   = $histTotalPages;
                                    $histOffset = ($histPage - 1) * $histPerPage;
                                }
                            } catch (Exception $e) {
                                error_log('Erro ao contar histórico de presença: ' . $e->getMessage());
                            }

                            $historyParams = array_merge($historyParamsBase, [$histPerPage, $histOffset]);

                            try {
                                $stmtHistoricoPresenca = $pdo->prepare(
                                    "SELECT
                            p.funcionario_id,
                            e.name AS funcionario_nome,
                            p.status,
                            p.{$histPresencaDateColumn} AS data_registro,
                            rp.hora_entrada,
                            rp.hora_saida,
                            rp.tipo_dia,
                            rp.falta_tipo,
                            rp.obs
                         FROM presencas p
                         INNER JOIN employees e ON e.id = p.funcionario_id
                         LEFT JOIN registros_ponto rp
                            ON rp.id = (
                                SELECT rp2.id
                                FROM registros_ponto rp2
                                WHERE rp2.funcionario_id = p.funcionario_id
                                  AND DATE(rp2.{$histPontoDateColumn}) = DATE(p.{$histPresencaDateColumn})
                                ORDER BY rp2.{$histPontoDateColumn} DESC, rp2.id DESC
                                LIMIT 1
                            )
                         WHERE e.client_id = ? {$historyDateFiltersSql}
                         ORDER BY p.{$histPresencaDateColumn} DESC, p.id DESC
                         LIMIT ? OFFSET ?"
                                );
                                $stmtHistoricoPresenca->execute($historyParams);
                                $historicoPresenca = $stmtHistoricoPresenca->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            } catch (Exception $e) {
                                error_log('Erro ao carregar histórico de presença: ' . $e->getMessage());
                                $historicoPresenca = [];
                            }

                            if (empty($historicoPresenca)):
                            ?>
                            <tr>
                                <td colspan="7"
                                    style="text-align: center; color: var(--text-secondary); padding: 1rem;">
                                    Sem histórico de presença para apresentar.
                                </td>
                            </tr>
                            <?php
                            else:
                                foreach ($historicoPresenca as $histRow):
                                    $rawDataHist = (string)($histRow['data_registro'] ?? '');
                                    $dataHistIso = '';
                                    $dataHistFmt = '--/--/----';
                                    if ($rawDataHist !== '') {
                                        $tsHist = strtotime($rawDataHist);
                                        if ($tsHist !== false) {
                                            $dataHistIso = date('Y-m-d', $tsHist);
                                            $dataHistFmt = date('d/m/Y H:i', $tsHist);
                                        }
                                    }

                                    $histEntrada = !empty($histRow['hora_entrada']) ? htmlspecialchars(substr((string)$histRow['hora_entrada'], 0, 5)) : '--:--';
                                    $histSaida = !empty($histRow['hora_saida']) ? htmlspecialchars(substr((string)$histRow['hora_saida'], 0, 5)) : '--:--';
                                    $histStatusRaw = mb_strtolower(trim((string)($histRow['status'] ?? '')));
                                    $histFaltaTipo = mb_strtolower(trim((string)($histRow['falta_tipo'] ?? '')));
                                    $histTipoDiaRaw = mb_strtolower(trim((string)($histRow['tipo_dia'] ?? ($histStatusRaw === 'falta' ? 'falta' : 'normal'))));
                                    $histTipoDiaMap = [
                                        'normal' => 'Normal',
                                        'folga' => 'Folga',
                                        'feriado' => 'Feriado',
                                        'falta' => 'Falta',
                                    ];
                                    $histTipoDiaLabel = $histTipoDiaMap[$histTipoDiaRaw] ?? 'Normal';

                                    if ($histStatusRaw === 'falta') {
                                        $histStatusLabel = $histFaltaTipo === 'justificada' ? 'Falta Justificada' : 'Falta Injustificada';
                                        $histStatusClass = 'status-falta';
                                        $histStatusKey = 'falta';
                                    } elseif ($histStatusRaw === 'presente' && $histEntrada !== '--:--' && $histSaida === '--:--') {
                                        $histStatusLabel = 'Em Aberto';
                                        $histStatusClass = 'status-warning';
                                        $histStatusKey = 'em-aberto';
                                    } elseif ($histStatusRaw === 'presente') {
                                        $histStatusLabel = 'Presente';
                                        $histStatusClass = 'status-presente';
                                        $histStatusKey = 'presente';
                                    } elseif ($histStatusRaw === 'invalidado') {
                                        $histStatusLabel = 'Invalidado';
                                        $histStatusClass = 'status-nao-marcado';
                                        $histStatusKey = 'invalidado';
                                    } else {
                                        $histStatusLabel = 'Não Registado';
                                        $histStatusClass = 'status-nao-marcado';
                                        $histStatusKey = 'nao-registrado';
                                    }

                                    $obsHist = trim((string)($histRow['obs'] ?? ''));
                                    if ($obsHist === '') {
                                        $obsHist = '-';
                                    }
                                ?>
                            <tr data-history-name="<?php echo htmlspecialchars(mb_strtolower((string)($histRow['funcionario_nome'] ?? ''))); ?>"
                                data-history-date="<?php echo htmlspecialchars($dataHistIso); ?>"
                                data-history-status-key="<?php echo htmlspecialchars($histStatusKey); ?>">
                                <td style="font-weight: 600;">
                                    <?php echo htmlspecialchars((string)($histRow['funcionario_nome'] ?? 'Funcionário')); ?>
                                </td>
                                <td><?php echo htmlspecialchars($dataHistFmt); ?></td>
                                <td><span class="status-badge <?php echo $histStatusClass; ?>"><?php echo htmlspecialchars($histStatusLabel); ?></span></td>
                                <td><?php echo htmlspecialchars($histTipoDiaLabel); ?></td>
                                <td><?php echo $histEntrada; ?></td>
                                <td><?php echo $histSaida; ?></td>
                                <td><?php echo htmlspecialchars($obsHist); ?></td>
                            </tr>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </tbody>
                    </table>
                    <?php if ($histTotalPages > 1): ?>
                    <div style="display:flex; justify-content:center; align-items:center; gap:.75rem; padding:1rem 0; flex-wrap:wrap;">
                        <?php if ($histPage > 1): ?>
                        <button type="button" class="btn btn-secondary" onclick="goToHistoryPage(<?php echo $histPage - 1; ?>)"
                            style="padding:.5rem 1rem;">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </button>
                        <?php endif; ?>
                        <span style="color:var(--text-secondary); font-size:.9rem; background:var(--bg-tertiary); border:1px solid var(--border-primary); border-radius:8px; padding:.4rem .8rem;">
                            Página <?php echo $histPage; ?> de <?php echo $histTotalPages; ?>
                            &nbsp;&middot;&nbsp;<?php echo number_format($histTotalRows); ?> registo(s)
                        </span>
                        <?php if ($histPage < $histTotalPages): ?>
                        <button type="button" class="btn btn-secondary" onclick="goToHistoryPage(<?php echo $histPage + 1; ?>)"
                            style="padding:.5rem 1rem;">
                            Próxima <i class="fas fa-chevron-right"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="presencaSummaryCards" class="pa-kpi-strip">
                <button type="button" class="pa-kpi-card" data-status-key="" style="--pa-accent:#60a5fa;"
                    onclick="document.getElementById('filterPresencaStatus').value=''; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaVisible" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico"><i class="fas fa-list"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Visíveis</span>
                </button>

                <button type="button" class="pa-kpi-card" data-status-key="presente" style="--pa-accent:#10b981;"
                    onclick="document.getElementById('filterPresencaStatus').value='presente'; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaPresentes" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico" style="color:#10b981;"><i class="fas fa-user-check"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Presentes</span>
                </button>

                <button type="button" class="pa-kpi-card" data-status-key="falta" style="--pa-accent:#f87171;"
                    onclick="document.getElementById('filterPresencaStatus').value='falta'; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaFaltas" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico" style="color:#f87171;"><i class="fas fa-user-times"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Faltas</span>
                </button>

                <button type="button" class="pa-kpi-card" data-status-key="atrasado" style="--pa-accent:#fbbf24;"
                    onclick="document.getElementById('filterPresencaStatus').value='atrasado'; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaAtrasados" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico" style="color:#fbbf24;"><i class="fas fa-clock"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Atrasados</span>
                </button>

                <button type="button" class="pa-kpi-card" data-status-key="em-aberto" style="--pa-accent:#fb923c;"
                    onclick="document.getElementById('filterPresencaStatus').value='em-aberto'; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaEmAberto" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico" style="color:#fb923c;"><i class="fas fa-hourglass-half"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Em aberto</span>
                </button>

                <button type="button" class="pa-kpi-card" data-status-key="sem-turno" style="--pa-accent:#94a3b8;"
                    onclick="document.getElementById('filterPresencaStatus').value='sem-turno'; if(window.filterPresencaTable){window.filterPresencaTable();}">
                    <div class="pa-kpi-top">
                        <span id="summaryPresencaSemTurno" class="pa-kpi-num">0</span>
                        <span class="pa-kpi-ico" style="color:#94a3b8;"><i class="fas fa-calendar-times"></i></span>
                    </div>
                    <span class="pa-kpi-lbl">Sem turno</span>
                </button>
            </div>

            <div class="data-table">
                <div class="pa-toolbar">
                    <div class="pa-toolbar-row1">
                        <span class="pa-tbar-title">Registos de Presença e Ponto</span>
                        <input type="text" id="searchPresenca" placeholder="Pesquisar funcionários..."
                            class="pa-inp pa-search">
                        <span id="resultCountPresenca" class="pa-chip"></span>
                        <div class="pa-spacer"></div>
                        <button type="button" class="fr-filter-toggle" id="paFilterToggle"
                            onclick="document.getElementById('paAdvFilters').classList.toggle('fr-adv-open'); this.classList.toggle('pa-filter-open')">
                            <i class="fas fa-sliders-h"></i> Filtros
                            <span class="fr-filter-badge" id="paFilterBadge" style="display:none"></span>
                        </button>
                        <button type="button" id="togglePresencaHistoryBtn" class="btn btn-secondary" disabled
                            aria-expanded="false"
                            aria-controls="presencaHistoryPanel"
                            title="Indisponível no momento"
                            style="padding:.5rem .9rem; white-space:nowrap;">
                            <i class="fas fa-history"></i>
                            <span>Histórico</span>
                            <i class="fas fa-chevron-down" style="margin-left:.3rem; font-size:.78em;"></i>
                        </button>
                        <div class="dropdown" style="position:relative; display:inline-block;">
                            <button class="btn btn-accent" style="white-space:nowrap; padding:.5rem .9rem;"
                                onclick="toggleExportPresencaDropdown()">
                                <i class="fas fa-download"></i>
                                <span>Exportar</span>
                                <i class="fas fa-chevron-down" style="margin-left:4px; font-size:.78em;"></i>
                            </button>
                            <div id="exportPresencaDropdown" class="dropdown-content"
                                style="display:none; position:absolute; right:0; background-color:white; min-width:180px; box-shadow:0 8px 16px rgba(0,0,0,.2); border-radius:8px; z-index:1; margin-top:5px;">
                                <a href="#" id="exportPresencaPDF" onclick="exportPresencaPDF(); return false;"
                                    style="color:#1f2937; padding:12px 16px; text-decoration:none; display:block; border-bottom:1px solid #e5e7eb;">
                                    <i class="fas fa-file-pdf" style="color:#e74c3c; margin-right:8px;"></i> Exportar PDF
                                </a>
                                <a href="#" id="expotpresenca" onclick="exportPresencaCSV(); return false;"
                                    style="color:#1f2937; padding:12px 16px; text-decoration:none; display:block;">
                                    <i class="fas fa-file-csv" style="color:#27ae60; margin-right:8px;"></i> Exportar CSV
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Collapsible advanced filters -->
                    <div class="fr-adv-filters" id="paAdvFilters">
                        <input type="date" id="filterPresencaStart" class="fr-select"
                            title="Data inicial" value="<?php echo htmlspecialchars($presencaServerStart); ?>">
                        <input type="date" id="filterPresencaEnd" class="fr-select"
                            title="Data final" value="<?php echo htmlspecialchars($presencaServerEnd); ?>">
                        <select id="filterPresencaStatus" class="fr-select">
                            <option value="">Todos os status</option>
                            <option value="presente">Presente</option>
                            <option value="falta">Falta</option>
                            <option value="atrasado">Atrasado</option>
                            <option value="em-aberto">Em aberto</option>
                            <option value="nao-registrado">Não registado</option>
                            <option value="sem-turno">Sem turno</option>
                            <option value="ferias">Férias</option>
                            <option value="inativo">Inativo</option>
                        </select>
                        <button id="clearFiltersPresenca" class="fr-clear-btn" style="display:none;">
                            <i class="fas fa-times"></i> Limpar
                        </button>
                        <button type="button" id="applyPresencaServer" class="fr-select"
                            onclick="applyPresencaServerFilter()"
                            style="cursor:pointer; background:rgba(59,130,246,.12); color:#60a5fa; border-color:rgba(59,130,246,.25); white-space:nowrap;">
                            <i class="fas fa-database"></i> Aplicar período
                        </button>
                        <button type="button" id="clearPresencaServer" class="fr-clear-btn"
                            onclick="clearPresencaServerFilter()"
                            style="border-color:rgba(148,163,184,.25); color:#94a3b8; background:rgba(148,163,184,.07);">
                            <i class="fas fa-eraser"></i> Limpar período
                        </button>
                    </div>
                </div>

                <table class="table fr-table" id="presencaTable">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-emp">Funcionário</th>
                            <th class="fr-th-status">Status</th>
                            <th>Data</th>
                            <th>Roteiro</th>
                            <th class="fr-th-acts">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // **IMPORTANTE:** O bloco de código a seguir assume que a variável $pdo 
                        // para a conexão com o banco de dados está definida e disponível aqui.

                        $pontoDateColumn = 'data_registro';
                        $presencaDateColumn = 'data_registro';

                        try {
                            $colsPonto = $pdo->query("SHOW COLUMNS FROM registros_ponto")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                            if (!in_array('data_registro', $colsPonto, true) && in_array('data', $colsPonto, true)) {
                                $pontoDateColumn = 'data';
                            }
                        } catch (Exception $e) {
                            // Mantém padrão data_registro
                        }

                        $pontoUpdatedSelect = in_array('updated_at', $colsPonto ?? [], true)
                            ? 'updated_at'
                            : 'NULL AS updated_at';

                        try {
                            $colsPresenca = $pdo->query("SHOW COLUMNS FROM presencas")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                            if (!in_array('data_registro', $colsPresenca, true) && in_array('data', $colsPresenca, true)) {
                                $presencaDateColumn = 'data';
                            }
                        } catch (Exception $e) {
                            // Mantém padrão data_registro
                        }

                        // Data efetiva de avaliação: sem filtro explícito de período, avalia SEMPRE hoje.
                        // (evita herdar o último ponto/turno de dias anteriores e não detectar falta/roteiro do dia atual)
                        $_presencaQueryStart = $presencaServerStart !== '' ? $presencaServerStart : date('Y-m-d');
                        $_presencaQueryEnd = $presencaServerEnd !== '' ? $presencaServerEnd : date('Y-m-d');
                        $_expectedDateRef = $_presencaQueryEnd;
                        $_expectedWeekdayMap = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];
                        $_expectedWeekdayTs = strtotime($_expectedDateRef);
                        $_expectedWeekdayToken = $_expectedWeekdayTs !== false ? $_expectedWeekdayMap[(int)date('w', $_expectedWeekdayTs)] : '';

                        $expectedStartByEmployee = [];
                        try {
                            $stmtExpectedStart = $pdo->prepare(
                                "SELECT t.funcionario_id, t.horario_inicio, t.dias_semana, t.data_inicio, t.data_fim
                     FROM turnos t
                     INNER JOIN employees e ON e.id = t.funcionario_id
                     WHERE e.client_id = ? AND LOWER(COALESCE(t.status, '')) IN ('ativo', 'active')
                     ORDER BY t.id DESC"
                            );
                            $stmtExpectedStart->execute([(int)$loggedInClientId]);
                            $rowsExpectedStart = $stmtExpectedStart->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            foreach ($rowsExpectedStart as $rowExpected) {
                                $empIdExp = (int)($rowExpected['funcionario_id'] ?? 0);
                                if ($empIdExp <= 0 || isset($expectedStartByEmployee[$empIdExp])) {
                                    continue;
                                }

                                // Só considera "turno esperado" se ele realmente cobre a data avaliada
                                // (dias da semana configurados + período de vigência do turno).
                                $turnoDiasRaw = trim((string)($rowExpected['dias_semana'] ?? ''));
                                $turnoDias = $turnoDiasRaw !== '' ? parseTurnoDays($turnoDiasRaw) : [];
                                $diaCorreto = empty($turnoDias) || $_expectedWeekdayToken === '' || in_array($_expectedWeekdayToken, $turnoDias, true);

                                $inicioVigencia = trim((string)($rowExpected['data_inicio'] ?? ''));
                                $fimVigencia = trim((string)($rowExpected['data_fim'] ?? ''));
                                $dentroVigencia = ($inicioVigencia === '' || $inicioVigencia === '0000-00-00' || $inicioVigencia <= $_expectedDateRef)
                                    && ($fimVigencia === '' || $fimVigencia === '0000-00-00' || $fimVigencia >= $_expectedDateRef);

                                if ($diaCorreto && $dentroVigencia) {
                                    $expectedStartByEmployee[$empIdExp] = (string)($rowExpected['horario_inicio'] ?? '');
                                }
                            }
                        } catch (Exception $e) {
                            error_log('Erro ao mapear horários esperados por funcionário: ' . $e->getMessage());
                        }

                        $justificativaLatestByEmployee = [];
                        $justificativasPendentes = [];
                        $allJustificativas = [];
                        try {
                            $pdo->exec(
                                "CREATE TABLE IF NOT EXISTS justificativas_presenca (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        client_id INT NOT NULL,
                        employee_id INT NOT NULL,
                        data_ocorrencia DATE NOT NULL,
                        tipo ENUM('falta','atraso') NOT NULL,
                        motivo TEXT NOT NULL,
                        anexo_path VARCHAR(255) NULL,
                        status ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
                        admin_observacao TEXT NULL,
                        decidido_por INT NULL,
                        decidido_em DATETIME NULL,
                        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        KEY idx_justificativas_client_status (client_id, status),
                        KEY idx_justificativas_employee_data (employee_id, data_ocorrencia)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                            );

                            $solJustSql    = '';
                            $solJustParams = [(int)$loggedInClientId];
                            if ($solServerStart !== '') {
                                $solJustSql    .= ' AND j.data_ocorrencia >= ?';
                                $solJustParams[] = $solServerStart;
                            }
                            if ($solServerEnd !== '') {
                                $solJustSql    .= ' AND j.data_ocorrencia <= ?';
                                $solJustParams[] = $solServerEnd;
                            }

                            $stmtJustificativas = $pdo->prepare(
                                "SELECT j.id, j.employee_id, j.data_ocorrencia, j.tipo, j.motivo, j.anexo_path, j.status, j.created_at,
                        j.admin_observacao, j.decidido_por, j.decidido_em,
                            e.name AS employee_name, e.profile_picture AS employee_profile_picture
                     FROM justificativas_presenca j
                     INNER JOIN employees e ON e.id = j.employee_id
                     WHERE j.client_id = ? {$solJustSql}
                     ORDER BY j.created_at DESC, j.id DESC"
                            );
                            $stmtJustificativas->execute($solJustParams);
                            $allJustificativas = $stmtJustificativas->fetchAll(PDO::FETCH_ASSOC) ?: [];

                            foreach ($allJustificativas as $jRow) {
                                $empJustId = (int)($jRow['employee_id'] ?? 0);
                                if ($empJustId <= 0) {
                                    continue;
                                }

                                if (!isset($justificativaLatestByEmployee[$empJustId])) {
                                    $justificativaLatestByEmployee[$empJustId] = $jRow;
                                }

                                if (mb_strtolower(trim((string)($jRow['status'] ?? ''))) === 'pendente') {
                                    $justificativasPendentes[] = $jRow;
                                }
                            }
                        } catch (Exception $e) {
                            error_log('Erro ao carregar justificativas na assiduidade: ' . $e->getMessage());
                        }

                        // Usa sempre $_presencaQueryStart/$_presencaQueryEnd (default = hoje quando não há filtro
                        // explícito) em vez de $presencaServerStart/$presencaServerEnd diretamente, para que o
                        // registo de ponto/presença avaliado seja sempre o do dia certo, nunca um resíduo de dias antigos.
                        $pontoPeriodSql = " AND DATE({$pontoDateColumn}) >= ? AND DATE({$pontoDateColumn}) <= ?";
                        $presencaPeriodSql = " AND DATE({$presencaDateColumn}) >= ? AND DATE({$presencaDateColumn}) <= ?";

                        foreach ($employees as $employee):
                            // 1. Lógica para buscar o registro de ponto mais recente do funcionário
                            $stmt = $pdo->prepare("
                    SELECT id, status, hora_entrada, hora_saida, obs, status_confirmacao, tipo_dia, falta_tipo, {$pontoDateColumn} AS data_registro, {$pontoUpdatedSelect}
                    FROM registros_ponto
                    WHERE funcionario_id = ? {$pontoPeriodSql}
                    ORDER BY {$pontoDateColumn} DESC, id DESC
                    LIMIT 1
                ");
                            $pontoParams = [(int)$employee['id'], $_presencaQueryStart, $_presencaQueryEnd];
                            $stmt->execute($pontoParams);
                            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

                            // Buscar presença mais recente para refletir Presente/Falta persistidos
                            $stmtPresencaHoje = $pdo->prepare("
                    SELECT status, {$presencaDateColumn} AS data_registro
                    FROM presencas
                    WHERE funcionario_id = ? {$presencaPeriodSql}
                    ORDER BY {$presencaDateColumn} DESC, id DESC
                    LIMIT 1
                ");
                            $presencaParams = [(int)$employee['id'], $_presencaQueryStart, $_presencaQueryEnd];
                            $stmtPresencaHoje->execute($presencaParams);
                            $presencaHoje = $stmtPresencaHoje->fetch(PDO::FETCH_ASSOC);
                            $presencaStatus = isset($presencaHoje['status']) ? mb_strtolower(trim((string)$presencaHoje['status'])) : '';

                            // Data de referência para exibição/filtros: ponto mais recente, fallback presença,
                            // e por fim a data avaliada (hoje, por padrão) — nunca fica em branco/desatualizada
                            // mesmo quando o funcionário ainda não tem nenhum registo no dia.
                            $rawDate = $registro['data_registro'] ?? ($presencaHoje['data_registro'] ?? null);
                            if (empty($rawDate)) {
                                $rawDate = $_presencaQueryEnd;
                            }
                            $dateIso = '';
                            $dateDisplay = '--/--/----';
                            if (!empty($rawDate)) {
                                $tsDate = strtotime((string)$rawDate);
                                if ($tsDate !== false) {
                                    $dateIso = date('Y-m-d', $tsDate);
                                    $dateDisplay = date('d/m/Y', $tsDate);
                                }
                            }

                            // Roteiro do dia — todos os períodos (entrada/pausa/regresso/saída) do dia de referência
                            $_timelineEventos = [];
                            $_pontosTimeline = [];
                            if ($dateIso !== '') {
                                try {
                                    $stmtTimelinePresenca = $pdo->prepare("
                                        SELECT hora_entrada, hora_saida, observacao
                                        FROM registros_ponto
                                        WHERE funcionario_id = ? AND {$pontoDateColumn} = ?
                                        ORDER BY id ASC
                                    ");
                                    $stmtTimelinePresenca->execute([(int)$employee['id'], $dateIso]);
                                    $_pontosTimeline = $stmtTimelinePresenca->fetchAll(PDO::FETCH_ASSOC) ?: [];
                                } catch (Exception $e) {
                                    $_pontosTimeline = [];
                                }

                                $_totalPontosTimeline = count($_pontosTimeline);
                                foreach ($_pontosTimeline as $_tiTimeline => $_tpTimeline) {
                                    $hEntTimeline = substr((string)($_tpTimeline['hora_entrada'] ?? ''), 0, 5);
                                    $hSaiTimeline = substr((string)($_tpTimeline['hora_saida'] ?? ''), 0, 5);
                                    $obsTimeline = mb_strtolower(trim((string)($_tpTimeline['observacao'] ?? '')));

                                    if ($hEntTimeline) {
                                        if ($_tiTimeline === 0) {
                                            $_timelineEventos[] = ['hora' => $hEntTimeline, 'label' => 'Entrada', 'icon' => 'fa-sign-in-alt', 'cls' => 'in'];
                                        } else {
                                            $_timelineEventos[] = ['hora' => $hEntTimeline, 'label' => 'Regresso ao trabalho', 'icon' => 'fa-undo-alt', 'cls' => 'regresso'];
                                        }
                                    }

                                    if ($hSaiTimeline) {
                                        if (str_contains($obsTimeline, 'pausa')) {
                                            if (str_contains($obsTimeline, 'almo')) {
                                                $iconTimeline = 'fa-utensils'; $lblTimeline = 'Pausa Almoço';
                                            } elseif (str_contains($obsTimeline, 'cigar')) {
                                                $iconTimeline = 'fa-smoking'; $lblTimeline = 'Pausa Cigarro';
                                            } else {
                                                $iconTimeline = 'fa-pause-circle'; $lblTimeline = 'Pausa';
                                            }
                                            $_timelineEventos[] = ['hora' => $hSaiTimeline, 'label' => $lblTimeline, 'icon' => $iconTimeline, 'cls' => 'pausa'];
                                        } else {
                                            $_timelineEventos[] = ['hora' => $hSaiTimeline, 'label' => 'Saída', 'icon' => 'fa-sign-out-alt', 'cls' => 'out'];
                                        }
                                    } elseif ($_tiTimeline === $_totalPontosTimeline - 1) {
                                        $_timelineEventos[] = ['hora' => null, 'label' => 'Em curso', 'icon' => 'fa-circle', 'cls' => 'ativo'];
                                    }
                                }
                            }
                            $_timelineEventosJson = htmlspecialchars(json_encode($_timelineEventos, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

                            // 2. Determinar o status automático
                            $entrada = isset($registro['hora_entrada']) && $registro['hora_entrada'] !== null && $registro['hora_entrada'] !== '';
                            $saida = isset($registro['hora_saida']) && $registro['hora_saida'] !== null && $registro['hora_saida'] !== '';
                            $confirmado = isset($registro['status_confirmacao']) && $registro['status_confirmacao'] === 'confirmado';

                            // Soma TODOS os períodos do dia (entrada/saída, regresso/saída, ...), não só o
                            // último registo — assim uma pausa (ex.: almoço) nunca é contada como horas trabalhadas.
                            $horasTrabalhadas = '--:--';
                            $_minTrabalhadosTotal = 0;
                            $_temPeriodoValido = false;
                            foreach ($_pontosTimeline as $_ptHoras) {
                                $_hEntHoras = $_ptHoras['hora_entrada'] ?? null;
                                $_hSaiHoras = $_ptHoras['hora_saida'] ?? null;
                                if ($_hEntHoras !== null && $_hEntHoras !== '' && $_hSaiHoras !== null && $_hSaiHoras !== '') {
                                    $_entTsHoras = strtotime('1970-01-01 ' . $_hEntHoras);
                                    $_saiTsHoras = strtotime('1970-01-01 ' . $_hSaiHoras);
                                    if ($_entTsHoras !== false && $_saiTsHoras !== false) {
                                        if ($_saiTsHoras < $_entTsHoras) {
                                            // Suporte a virada de dia (ex.: turno noturno)
                                            $_saiTsHoras += 24 * 60 * 60;
                                        }
                                        $_minTrabalhadosTotal += max(0, (int) floor(($_saiTsHoras - $_entTsHoras) / 60));
                                        $_temPeriodoValido = true;
                                    }
                                }
                            }
                            if ($_temPeriodoValido) {
                                $h = (int) floor($_minTrabalhadosTotal / 60);
                                $m = $_minTrabalhadosTotal % 60;
                                $horasTrabalhadas = sprintf('%02d:%02d', $h, $m);
                            }

                            $tipoDia = mb_strtolower(trim((string)($registro['tipo_dia'] ?? 'normal')));
                            $tipoDiaMap = [
                                'normal' => 'Normal',
                                'folga' => 'Folga',
                                'feriado' => 'Feriado',
                                'falta' => 'Falta',
                            ];
                            if (!isset($tipoDiaMap[$tipoDia])) {
                                $tipoDia = 'normal';
                            }
                            $tipoDiaLabel = $tipoDiaMap[$tipoDia];

                            $faltaTipoRaw = mb_strtolower(trim((string)($registro['falta_tipo'] ?? '')));
                            if (!in_array($faltaTipoRaw, ['justificada', 'injustificada'], true)) {
                                $faltaTipoRaw = '';
                            }
                            $faltaTipoLabel = $faltaTipoRaw === 'justificada' ? 'Falta Justificada' : ($faltaTipoRaw === 'injustificada' ? 'Falta Injustificada' : '-');

                            $temTurno = isset($expectedStartByEmployee[(int)$employee['id']]) && $expectedStartByEmployee[(int)$employee['id']] !== '';
                            $horarioPrevisto = $temTurno ? $expectedStartByEmployee[(int)$employee['id']] : null;
                            $atrasoTexto = '—';
                            if ($entrada && !in_array($tipoDia, ['folga', 'feriado', 'falta'], true)) {
                                $entradaTsCalc = strtotime('1970-01-01 ' . (string)$registro['hora_entrada']);
                                $previstoTsCalc = strtotime('1970-01-01 ' . (string)$horarioPrevisto);
                                $toleranciaMin = max(0, (int)($estHorario['tolerancia_atraso_min'] ?? 0));

                                if ($entradaTsCalc !== false && $previstoTsCalc !== false) {
                                    $diffMinAtraso = (int) floor(($entradaTsCalc - $previstoTsCalc) / 60) - $toleranciaMin;
                                    if ($diffMinAtraso > 0) {
                                        $atrasoTexto = 'Atrasado (+' . $diffMinAtraso . ' min)';
                                    } else {
                                        $atrasoTexto = 'Pontual';
                                    }
                                }
                            }

                            if (!$temTurno) {
                                $status_texto = 'SEM TURNO';
                                $status_classe = 'status-nao-marcado';
                            } elseif (isset($registro['status']) && $registro['status'] === 'invalidado') {
                                $status_texto = '—';
                                $status_classe = 'status-nao-marcado';
                            } elseif ($presencaStatus === 'falta') {
                                $status_texto = $faltaTipoRaw === 'justificada' ? 'FALTA JUSTIFICADA' : 'FALTA INJUSTIFICADA';
                                $status_classe = 'status-falta';
                            } elseif ($presencaStatus === 'presente') {
                                $status_texto = 'PRESENTE';
                                $status_classe = 'status-presente';
                            } elseif (!$entrada) {
                                // Se não marcou presença: o dia avaliado ($dateIso) pode ser passado, hoje ou futuro
                                // em relação à data real do servidor — a comparação por hora só faz sentido para hoje.
                                $_hojeRealAssiduidade = date('Y-m-d');
                                if ($dateIso !== '' && $dateIso > $_hojeRealAssiduidade) {
                                    // Dia futuro: o turno ainda nem começou, não é falta nem atraso.
                                    $status_texto = 'AGENDADO';
                                    $status_classe = 'status-nao-marcado';
                                } elseif ($dateIso !== '' && $dateIso < $_hojeRealAssiduidade) {
                                    // Dia passado sem marcação: já esgotou o dia inteiro, é falta direta.
                                    $status_texto = 'FALTA';
                                    $status_classe = 'status-falta';
                                } else {
                                    $agora = date('H:i');
                                    $horaTurno = substr($horarioPrevisto, 0, 5);
                                    $toleranciaMin = max(0, (int)($estHorario['tolerancia_atraso_min'] ?? 0));
                                    $agoraTs = strtotime('1970-01-01 ' . $agora);
                                    $turnoTs = strtotime('1970-01-01 ' . $horaTurno);
                                    $toleranciaTs = $turnoTs !== false ? $turnoTs + ($toleranciaMin * 60) : false;
                                    if ($agoraTs !== false && $toleranciaTs !== false) {
                                        if ($agoraTs > $toleranciaTs) {
                                            $status_texto = 'FALTA';
                                            $status_classe = 'status-falta';
                                        } elseif ($agoraTs > $turnoTs) {
                                            $status_texto = 'ATRASADO';
                                            $status_classe = 'status-warning';
                                        } else {
                                            $status_texto = 'NÃO REGISTADO';
                                            $status_classe = 'status-nao-marcado';
                                        }
                                    } else {
                                        $status_texto = 'NÃO REGISTADO';
                                        $status_classe = 'status-nao-marcado';
                                    }
                                }
                            } elseif ($entrada && (!$saida && !$confirmado)) {
                                $status_texto = 'EM ABERTO';
                                $status_classe = 'status-warning';
                            } else {
                                $status_texto = 'PRESENTE';
                                $status_classe = 'status-presente';
                            }

                            if (isset($registro['status']) && $registro['status'] === 'invalidado') {
                                $confirmacaoTexto = 'Invalidado';
                            } elseif (isset($registro['status']) && $registro['status'] === 'presente') {
                                $confirmacaoTexto = (isset($registro['status_confirmacao']) && $registro['status_confirmacao'] === 'confirmado') ? 'Confirmado' : 'Pendente';
                            } else {
                                $confirmacaoTexto = '-';
                            }

                            $obsTexto = isset($registro['obs']) && $registro['obs'] !== '' ? (string)$registro['obs'] : '-';

                            $registroUpdatedFmt = '-';
                            if (!empty($registro['updated_at'])) {
                                $tsUpdated = strtotime((string)$registro['updated_at']);
                                if ($tsUpdated !== false) {
                                    $registroUpdatedFmt = date('d/m/Y H:i', $tsUpdated);
                                }
                            }

                            $justificativaAtual = $justificativaLatestByEmployee[(int)$employee['id']] ?? null;
                            $justificativaStatusLabel = 'Sem justificativa';
                            $justificativaBadgeClass = 'status-nao-marcado';

                            if (is_array($justificativaAtual)) {
                                $justStatus = mb_strtolower(trim((string)($justificativaAtual['status'] ?? 'pendente')));
                                if ($justStatus === 'aprovada') {
                                    $justificativaStatusLabel = 'Aprovada';
                                    $justificativaBadgeClass = 'status-presente';
                                } elseif ($justStatus === 'rejeitada') {
                                    $justificativaStatusLabel = 'Rejeitada';
                                    $justificativaBadgeClass = 'status-falta';
                                } else {
                                    $justificativaStatusLabel = 'Pendente';
                                    $justificativaBadgeClass = 'status-warning';
                                }
                            }

                            $justificativaAdminObs = is_array($justificativaAtual)
                                ? trim((string)($justificativaAtual['admin_observacao'] ?? ''))
                                : '';
                            $justificativaDecididoPor = is_array($justificativaAtual)
                                ? trim((string)($justificativaAtual['decidido_por'] ?? ''))
                                : '';
                            $justificativaDecididoEmFmt = '-';
                            if (is_array($justificativaAtual) && !empty($justificativaAtual['decidido_em'])) {
                                $tsDecidido = strtotime((string)$justificativaAtual['decidido_em']);
                                if ($tsDecidido !== false) {
                                    $justificativaDecididoEmFmt = date('d/m/Y H:i', $tsDecidido);
                                }
                            }
                            $justificativaAnexo = is_array($justificativaAtual)
                                ? trim((string)($justificativaAtual['anexo_path'] ?? ''))
                                : '';
                        ?>
                        <tr class="fr-row" data-employee-id="<?php echo (int)$employee['id']; ?>"
                            data-funcionario-nome="<?php echo htmlspecialchars((string)$employee['name']); ?>"
                            data-presenca-date="<?php echo htmlspecialchars($dateIso); ?>"
                            data-presenca-year="<?php echo $dateIso ? htmlspecialchars(substr($dateIso, 0, 4)) : ''; ?>"
                            data-presenca-month="<?php echo $dateIso ? htmlspecialchars(substr($dateIso, 0, 7)) : ''; ?>"
                            data-expected-start="<?php echo htmlspecialchars(substr((string)$horarioPrevisto, 0, 5)); ?>"
                            data-tolerancia-min="<?php echo (int)($estHorario['tolerancia_atraso_min'] ?? 0); ?>"
                            data-tipo-dia="<?php echo htmlspecialchars($tipoDiaLabel); ?>"
                            data-falta-tipo="<?php echo htmlspecialchars($faltaTipoRaw); ?>"
                            data-obs="<?php echo htmlspecialchars($obsTexto); ?>"
                            data-confirmacao="<?php echo htmlspecialchars($confirmacaoTexto); ?>"
                            data-hora-entrada="<?php echo isset($registro['hora_entrada']) && $registro['hora_entrada'] !== null ? htmlspecialchars(substr((string)$registro['hora_entrada'], 0, 5)) : '--:--'; ?>"
                            data-hora-saida="<?php echo isset($registro['hora_saida']) && $registro['hora_saida'] !== null ? htmlspecialchars(substr((string)$registro['hora_saida'], 0, 5)) : '--:--'; ?>"
                            data-horas="<?php echo htmlspecialchars($horasTrabalhadas); ?>"
                            data-atraso="<?php echo htmlspecialchars($atrasoTexto); ?>"
                            data-updated-at="<?php echo htmlspecialchars($registroUpdatedFmt); ?>"
                            data-date-display="<?php echo htmlspecialchars($dateDisplay); ?>"
                            data-just-label="<?php echo htmlspecialchars($justificativaStatusLabel); ?>"
                            data-just-motivo="<?php echo is_array($justificativaAtual) ? htmlspecialchars(mb_substr((string)($justificativaAtual['motivo'] ?? ''), 0, 300)) : ''; ?>"
                            data-just-data="<?php echo is_array($justificativaAtual) ? htmlspecialchars((string)($justificativaAtual['data_ocorrencia'] ?? '')) : ''; ?>"
                            data-just-tipo="<?php echo is_array($justificativaAtual) ? htmlspecialchars((string)($justificativaAtual['tipo'] ?? '')) : ''; ?>"
                            data-just-admin-obs="<?php echo htmlspecialchars($justificativaAdminObs !== '' ? $justificativaAdminObs : '-'); ?>"
                            data-just-decidido-por="<?php echo htmlspecialchars($justificativaDecididoPor !== '' ? ('#' . $justificativaDecididoPor) : '-'); ?>"
                            data-just-decidido-em="<?php echo htmlspecialchars($justificativaDecididoEmFmt); ?>"
                            data-just-anexo="<?php echo htmlspecialchars($justificativaAnexo); ?>"
                            data-employee-status-key="<?php echo htmlspecialchars((string)($employee['status'] ?? '')); ?>"
                            data-roteiro="<?php echo $_timelineEventosJson; ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#475569,#334155);">
                                        <?php if (!empty($employee['profile_picture'])): ?>
                                        <img src="../<?php echo htmlspecialchars($employee['profile_picture']); ?>"
                                            alt="<?php echo htmlspecialchars($employee['name']); ?>" class="fr-av-img">
                                        <?php else: ?>
                                        <?php
                                            $partsName = preg_split('/\s+/', trim((string)$employee['name'])) ?: [];
                                            $initials = '';
                                            if (!empty($partsName[0])) $initials .= mb_strtoupper(mb_substr($partsName[0], 0, 1));
                                            if (count($partsName) > 1 && !empty($partsName[1])) $initials .= mb_strtoupper(mb_substr($partsName[1], 0, 1));
                                            echo htmlspecialchars($initials ?: 'FN');
                                        ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo htmlspecialchars($employee['name']); ?></span>
                                    </div>
                                </div>
                            </td>

                            <td class="fr-td-status">
                                <?php
                                // Priorizar status do funcionario (ex: ferias, inativo) sobre status automatico
                                $empStatusRaw = isset($employee['status']) ? $employee['status'] : '';
                                $empStatus = mb_strtolower(trim((string)$empStatusRaw));

                                if (in_array($empStatus, ['ferias', 'férias'], true)) {
                                    $pClass = 'status-ferias';
                                    $pLabel = 'Ferias';
                                    $statusKey = 'ferias';
                                } elseif (in_array($empStatus, ['inactive', 'inativo'], true)) {
                                    $pClass = 'status-inactive';
                                    $pLabel = 'Inativo';
                                    $statusKey = 'inativo';
                                } else {
                                    $pClass = $status_classe;
                                    $pLabel = $status_texto;
                                    $normalizedStatusTexto = mb_strtolower(trim((string)$status_texto));

                                    if (mb_stripos($normalizedStatusTexto, 'sem turno') !== false) {
                                        $statusKey = 'sem-turno';
                                    } elseif (mb_stripos($normalizedStatusTexto, 'agendado') !== false) {
                                        $statusKey = 'agendado';
                                    } elseif (mb_stripos($normalizedStatusTexto, 'falta') !== false) {
                                        $statusKey = 'falta';
                                    } elseif (mb_stripos($normalizedStatusTexto, 'em aberto') !== false) {
                                        $statusKey = 'em-aberto';
                                    } elseif (mb_stripos($normalizedStatusTexto, 'atras') !== false) {
                                        $statusKey = 'atrasado';
                                    } elseif (mb_stripos($normalizedStatusTexto, 'presente') !== false) {
                                        $statusKey = 'presente';
                                    } elseif (mb_stripos($normalizedStatusTexto, 'nao regist') !== false || mb_stripos($normalizedStatusTexto, 'não regist') !== false) {
                                        $statusKey = 'nao-registrado';
                                    } else {
                                        $statusKey = 'invalidado';
                                    }
                                }
                                    ?>
                                <span class="status-badge <?php echo $pClass; ?>"
                                    id="attendance-status-<?php echo $employee['id']; ?>"
                                    data-status-key="<?php echo htmlspecialchars($statusKey); ?>"><?php echo $pLabel; ?></span>
                            </td>

                            <td><?php echo $dateDisplay; ?></td>
                            <?php $_totalEventosCell = count($_timelineEventos); ?>
                            <td class="fr-td-roteiro">
                                <div class="fr-roteiro">
                                    <?php if ($_totalEventosCell === 0): ?>
                                        <span class="fr-roteiro-label">Sem registo</span>
                                    <?php elseif ($_totalEventosCell <= 3): ?>
                                        <?php foreach ($_timelineEventos as $_iCell => $_evCell): ?>
                                            <?php if ($_iCell > 0): ?><span class="fr-roteiro-sep"></span><?php endif; ?>
                                            <span class="fr-roteiro-item" title="<?php echo htmlspecialchars($_evCell['label'] . ($_evCell['hora'] ? ' ' . $_evCell['hora'] : '')); ?>">
                                                <span class="fr-roteiro-dot <?php echo $_evCell['cls']; ?>"><i class="fas <?php echo $_evCell['icon']; ?>"></i></span>
                                                <?php if ($_evCell['hora']): ?><span class="fr-roteiro-time"><?php echo $_evCell['hora']; ?></span><?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php $_firstCell = $_timelineEventos[0]; $_lastCell = $_timelineEventos[$_totalEventosCell - 1]; $_hiddenCell = $_totalEventosCell - 2; ?>
                                        <span class="fr-roteiro-item" title="<?php echo htmlspecialchars($_firstCell['label'] . ' ' . $_firstCell['hora']); ?>">
                                            <span class="fr-roteiro-dot <?php echo $_firstCell['cls']; ?>"><i class="fas <?php echo $_firstCell['icon']; ?>"></i></span>
                                            <span class="fr-roteiro-time"><?php echo $_firstCell['hora']; ?></span>
                                        </span>
                                        <span class="fr-roteiro-sep"></span>
                                        <span class="fr-roteiro-more" title="+<?php echo $_hiddenCell; ?> evento(s) — clique em Ver detalhes">+<?php echo $_hiddenCell; ?></span>
                                        <span class="fr-roteiro-sep"></span>
                                        <span class="fr-roteiro-item" title="<?php echo htmlspecialchars($_lastCell['label'] . ' ' . ($_lastCell['hora'] ?? '')); ?>">
                                            <span class="fr-roteiro-dot <?php echo $_lastCell['cls']; ?>"><i class="fas <?php echo $_lastCell['icon']; ?>"></i></span>
                                            <?php if ($_lastCell['hora']): ?><span class="fr-roteiro-time"><?php echo $_lastCell['hora']; ?></span><?php else: ?><span class="fr-roteiro-label">Em curso</span><?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <?php if ($statusKey === 'sem-turno'): ?>
                                    <button type="button" class="fr-btn fr-btn-activate" title="Atribuir turno"
                                        onclick="resolverSemTurno(<?php echo (int)$employee['id']; ?>, '<?php echo htmlspecialchars((string)$employee['name'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-calendar-plus"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="fr-btn fr-btn-view" title="Ver detalhes"
                                        onclick="verDetalhesPresenca(<?php echo $employee['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="fr-btn fr-btn-edit" title="Editar registo"
                                        onclick="editarPresenca(<?php echo $employee['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Modal de Visualização de Presença -->
            <div id="modalVerPresenca" class="modal" style="display:none;">
                <div class="am-sheet vm-sheet">
                    <button class="am-close" id="closeVerPresenca" type="button">&times;</button>

                    <!-- Navigation -->
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:.5rem; margin-bottom:1rem;">
                        <button type="button" id="view-presenca-prev" class="am-btn-cancel"
                            onclick="showPrevPresencaDetails()" style="padding:.38rem .75rem;">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </button>
                        <span id="view-presenca-nav-indicator" class="pa-chip" style="min-width:56px; text-align:center;">0/0</span>
                        <button type="button" id="view-presenca-next" class="am-btn-cancel"
                            onclick="showNextPresencaDetails()" style="padding:.38rem .75rem;">
                            Próximo <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <!-- Hero -->
                    <div class="vm-hero">
                        <div id="view-presenca-av" class="vm-hero-av" style="font-size:.88rem; font-weight:700;">--</div>
                        <div class="vm-hero-info">
                            <h2 class="vm-hero-name" id="view-presenca-funcionario"></h2>
                            <div id="view-presenca-status" style="margin-top:4px;"></div>
                        </div>
                    </div>

                    <!-- Roteiro do dia -->
                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-route"></i> Roteiro do Dia</div>
                        <div id="view-presenca-roteiro-full" class="roteiro-dia">
                            <span class="fr-roteiro-label">Sem registo.</span>
                        </div>
                    </div>

                    <!-- Registo do dia -->
                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-calendar-check"></i> Registo do Dia</div>
                        <div class="vm-g3">
                            <div>
                                <div class="vm-field-label">Data</div>
                                <div class="vm-field-value" id="view-presenca-data">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Tipo de Dia</div>
                                <div class="vm-field-value" id="view-presenca-tipo-dia">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Turno Previsto</div>
                                <div class="vm-field-value" id="view-presenca-turno-previsto">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Última Atualização</div>
                                <div class="vm-field-value" id="view-presenca-updated-at">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Horas -->
                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-clock"></i> Horas</div>
                        <div class="vm-g3">
                            <div>
                                <div class="vm-field-label">Entrada</div>
                                <div class="vm-field-value" id="view-presenca-entrada">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Saída</div>
                                <div class="vm-field-value" id="view-presenca-saida">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Horas</div>
                                <div class="vm-field-value" id="view-presenca-horas">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Atraso</div>
                                <div class="vm-field-value" id="view-presenca-atraso">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Atraso (min)</div>
                                <div class="vm-field-value" id="view-presenca-atraso-minutos">—</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Horas Extras</div>
                                <div class="vm-field-value" id="view-presenca-horas-extras">00:00</div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumo -->
                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-chart-bar"></i> Resumo</div>
                        <div class="vm-g3">
                            <div>
                                <div class="vm-field-label">Dias Trabalhados</div>
                                <div class="vm-field-value" id="view-presenca-dias-trabalhados">0</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Número de Faltas</div>
                                <div class="vm-field-value" id="view-presenca-numero-faltas">0</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Confirmação</div>
                                <div class="vm-field-value" id="view-presenca-confirmacao">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Observações -->
                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-sticky-note"></i> Observações</div>
                        <div class="vm-g2">
                            <div>
                                <div class="vm-field-label">Tipo de Falta</div>
                                <div class="vm-field-value" id="view-presenca-falta-tipo">-</div>
                            </div>
                            <div class="vm-full">
                                <div class="vm-field-label">Observação</div>
                                <div class="vm-field-value" id="view-presenca-obs">-</div>
                            </div>
                        </div>
                    </div>
















                    <!-- Justificativa -->
                    <div id="view-presenca-just-section" class="vm-section" style="display:none; border:1px solid rgba(139,92,246,.35); border-radius:10px; padding:.75rem;">
                        <div class="vm-sec-lbl" style="color:#a78bfa;"><i class="fas fa-file-alt"></i> Justificativa</div>
                        <div class="vm-g3">
                            <div>
                                <div class="vm-field-label">Estado</div>
                                <div class="vm-field-value" id="view-presenca-just-status">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Tipo</div>
                                <div class="vm-field-value" id="view-presenca-just-tipo">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Data Ocorrência</div>
                                <div class="vm-field-value" id="view-presenca-just-data">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Decidido Em</div>
                                <div class="vm-field-value" id="view-presenca-just-decidido-em">-</div>
                            </div>
                            <div>
                                <div class="vm-field-label">Decidido Por</div>
                                <div class="vm-field-value" id="view-presenca-just-decidido-por">-</div>
                            </div>
                        </div>
                        <div style="margin-top:.65rem;">
                            <div class="vm-field-label">Motivo</div>
                            <div class="vm-field-value" id="view-presenca-just-motivo" style="word-break:break-word;">-</div>
                        </div>
                        <div style="margin-top:.5rem;">
                            <div class="vm-field-label">Observação do Admin</div>
                            <div class="vm-field-value" id="view-presenca-just-admin-obs" style="word-break:break-word;">-</div>
                        </div>
                        <div id="view-presenca-just-anexo-wrap" style="display:none; margin-top:.5rem;">
                            <div class="vm-field-label">Anexo</div>
                            <a id="view-presenca-just-anexo" href="#" target="_blank" rel="noopener noreferrer" style="color:#93c5fd; font-size:.875rem;">Ver anexo</a>
                        </div>
                    </div>

                    <!-- Últimos 7 dias -->
                    <div id="view-presenca-history-section" class="vm-section" style="border:1px solid var(--border-primary); border-radius:10px; padding:.75rem;">
                        <div class="vm-sec-lbl"><i class="fas fa-chart-line"></i> Últimos 7 dias</div>
                        <div style="display:grid; grid-template-columns:88px 1fr 60px 60px; gap:.5rem; padding:0 4px 6px; color:var(--text-secondary); font-size:.78rem; font-weight:700;">
                            <span>Data</span><span>Status</span><span>Entrada</span><span>Saída</span>
                        </div>
                        <div id="view-presenca-mini-history-body" style="max-height:180px; overflow:auto; border-top:1px solid var(--border-primary); border-bottom:1px solid var(--border-primary);">
                            <div style="padding:8px; color:var(--text-secondary);">Abra um registo para carregar o histórico.</div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="am-footer" style="flex-wrap:wrap; margin-top:1rem;">
                        <button type="button" class="am-btn-cancel" onclick="copyPresencaDetailLink()"><i class="fas fa-link"></i> Copiar link</button>
                        <button type="button" class="am-btn-cancel" onclick="printPresencaDetail()"><i class="fas fa-print"></i> Imprimir</button>
                        <button type="button" class="am-btn-cancel" onclick="exportPresencaDetailPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
                        <button type="button" class="am-btn-cancel" onclick="openSolicitacoesFromViewModal()"><i class="fas fa-inbox"></i> Solicitações</button>
                        <button type="button" class="am-btn-submit" onclick="editarPresencaFromViewModal()"><i class="fas fa-edit"></i> Editar registo</button>
                    </div>
                </div>
            </div>




            <!-- Modal de Edição de Presença -->
            <div id="modalEditarPresenca" class="modal" style="display:none;">
                <div class="am-sheet" style="max-width:440px;">
                    <button class="am-close" id="closeEditarPresenca" type="button">&times;</button>
                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Editar Registo</h2>
                            <p class="am-subtitle">Alterar dados de presença</p>
                        </div>
                    </div>
                    <form id="formEditarPresenca">
                        <input type="hidden" id="edit-presenca-employee-id" name="employee_id">
                        <input type="hidden" id="edit-presenca-target-date" name="target_date" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-tag"></i> Classificação</div>
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl">Tipo de Dia</label>
                                    <select id="edit-presenca-tipo-dia" name="tipo_dia" class="am-inp am-sel" required>
                                        <option value="normal">Normal</option>
                                        <option value="folga">Folga</option>
                                        <option value="feriado">Feriado</option>
                                        <option value="falta">Falta</option>
                                    </select>
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl">Status</label>
                                    <select id="edit-presenca-status" name="status" class="am-inp am-sel" required>
                                        <option value="presente">Presente</option>
                                        <option value="falta">Falta</option>
                                        <option value="invalidado">Invalidado</option>
                                    </select>
                                </div>
                                <div id="edit-presenca-falta-tipo-wrap" class="am-f am-f-full" style="display:none;">
                                    <label class="am-lbl">Tipo de Falta</label>
                                    <select id="edit-presenca-falta-tipo" name="falta_tipo" class="am-inp am-sel">
                                        <option value="injustificada">Falta Injustificada</option>
                                        <option value="justificada">Falta Justificada</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-clock"></i> Horários</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl">Entrada</label>
                                    <input type="time" id="edit-presenca-entrada" name="hora_entrada" class="am-inp">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl">Saída</label>
                                    <input type="time" id="edit-presenca-saida" name="hora_saida" class="am-inp">
                                </div>
                            </div>
                            <div id="edit-presenca-time-error" class="am-error" style="display:none; margin-top:6px;">
                                A hora de saída deve ser maior que a hora de entrada.
                            </div>
                        </div>
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-sticky-note"></i> Observação</div>
                            <div class="am-f">
                                <textarea id="edit-presenca-obs" name="obs" class="am-inp" rows="2" style="resize:vertical;"></textarea>
                            </div>
                        </div>
                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel" id="cancelEditarPresenca">Cancelar</button>
                            <button type="submit" class="am-btn-submit" style="background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 4px 14px rgba(245,158,11,.3);">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
