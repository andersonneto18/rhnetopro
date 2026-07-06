<?php
// Secção "Funcionários" — incluída a partir de admin/dashboard.php (depende de $pdo, $employees, $loggedInClientId, $csrfToken, etc. já definidos lá).
?>
        <section id="funcionarios-section" class="content-section">
            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon"><i class="fas fa-users"></i></div>
                    <div>
                        <h2 class="frhd-title">Funcionários</h2>
                        <p class="frhd-sub"><?php echo $totalEmployees ?? 0; ?> no total &middot; <?php echo $activeCount ?? 0; ?> ativos hoje</p>
                    </div>
                </div>
                <button id="addEmployeeBtn" type="button" class="frhd-add-btn">
                    <i class="fas fa-plus"></i> Novo Funcionário
                </button>
            </div>


            <?php
            $totalEmployees = count($employees);
            $activeCount = 0; $inactiveCount = 0; $feriasCount = 0; $newThisMonth = 0;
            $currentMonth = date('Y-m');
            foreach ($employees as $emp) {
                $st = mb_strtolower(trim((string)($emp['status'] ?? '')));
                if ($st === 'active') $activeCount++;
                elseif ($st === 'inactive' || $st === 'inativo') $inactiveCount++;
                elseif ($st === 'ferias' || $st === 'férias') $feriasCount++;
                $startField = $emp['startDate'] ?? $emp['start_date'] ?? '';
                if (!empty($startField) && strpos($startField, $currentMonth) === 0) $newThisMonth++;
            }
            $pctAtivos = $totalEmployees > 0 ? round(($activeCount / $totalEmployees) * 100) : 0;
            ?>

            <!-- KPI Strip -->
            <div class="fr-kpi-strip">
                <div class="fr-kpi fr-kpi-total">
                    <div class="fr-kpi-icon"><i class="fas fa-users"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?= $totalEmployees ?></span>
                        <span class="fr-kpi-lbl">Total</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-active">
                    <div class="fr-kpi-icon"><i class="fas fa-user-check"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?= $activeCount ?></span>
                        <span class="fr-kpi-lbl">Ativos</span>
                        <span class="fr-kpi-pct"><?= $pctAtivos ?>%</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-inactive">
                    <div class="fr-kpi-icon"><i class="fas fa-user-times"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?= $inactiveCount ?></span>
                        <span class="fr-kpi-lbl">Inativos</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-ferias">
                    <div class="fr-kpi-icon"><i class="fas fa-umbrella-beach"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?= $feriasCount ?></span>
                        <span class="fr-kpi-lbl">Em Férias</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-new">
                    <div class="fr-kpi-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?= $newThisMonth ?></span>
                        <span class="fr-kpi-lbl">Novas admissões</span>
                        <span class="fr-kpi-pct">este mês</span>
                    </div>
                </div>
            </div>

            <div class="data-table fr-table-wrap">
                <!-- Toolbar -->
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="employeeTableSearch" class="fr-search"
                                placeholder="Pesquisar por nome, email, cargo…">
                        </div>
                        <div class="fr-toolbar-right">
                            <button type="button" class="fr-filter-toggle" id="frFilterToggle" onclick="document.getElementById('frAdvFilters').classList.toggle('fr-adv-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                                <span class="fr-filter-badge" id="frFilterBadge" style="display:none"></span>
                            </button>
                            <div class="fr-export-wrap" style="position:relative;">
                                <button class="fr-export-btn" onclick="toggleExportDropdown()">
                                    <i class="fas fa-arrow-up-from-bracket"></i> Exportar <i class="fas fa-chevron-down" style="font-size:.7em;margin-left:2px;"></i>
                                </button>
                                <div id="exportDropdown" class="fr-export-menu" style="display:none;">
                                    <a href="#" onclick="exportEmployeesPDF(); return false;" class="fr-export-item">
                                        <i class="fas fa-file-pdf" style="color:#e74c3c;"></i> PDF
                                    </a>
                                    <a href="#" onclick="exportEmployeesExcel(); return false;" class="fr-export-item">
                                        <i class="fas fa-file-excel" style="color:#27ae60;"></i> Excel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status chips -->
                    <div class="fr-chips">
                        <button class="fr-chip fr-chip-all active" data-chip-status="">
                            <i class="fas fa-th-large"></i> Todos
                            <span class="fr-chip-count"><?= $totalEmployees ?></span>
                        </button>
                        <button class="fr-chip fr-chip-active" data-chip-status="active">
                            <span class="fr-dot fr-dot-green"></span> Ativos
                            <span class="fr-chip-count"><?= $activeCount ?></span>
                        </button>
                        <button class="fr-chip fr-chip-inactive" data-chip-status="inactive">
                            <span class="fr-dot fr-dot-red"></span> Inativos
                            <span class="fr-chip-count"><?= $inactiveCount ?></span>
                        </button>
                        <button class="fr-chip fr-chip-ferias" data-chip-status="ferias">
                            <span class="fr-dot fr-dot-blue"></span> Férias
                            <span class="fr-chip-count"><?= $feriasCount ?></span>
                        </button>
                    </div>

                    <!-- Advanced filters (collapsible) -->
                    <div class="fr-adv-filters" id="frAdvFilters">
                        <select id="employeeTableStatus" class="fr-select" style="display:none">
                            <option value="">Todos os status</option>
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                            <option value="ferias">Férias</option>
                        </select>
                        <select id="employeeTablePosition" class="fr-select">
                            <option value="">Cargo</option>
                        </select>
                        <select id="employeeTableDepartment" class="fr-select">
                            <option value="">Departamento</option>
                        </select>
                        <select id="employeeTableContractType" class="fr-select">
                            <option value="">Tipo de contrato</option>
                            <option value="efetivo">Efetivo</option>
                            <option value="temporario">Temporário</option>
                            <option value="part-time">Part-time</option>
                            <option value="estagio">Estágio</option>
                            <option value="freelancer">Freelancer</option>
                        </select>
                        <select id="employeeTableExpiry" class="fr-select">
                            <option value="">Vigência</option>
                            <option value="expiring">Expira em 30d</option>
                            <option value="expired">Expirado</option>
                            <option value="active">Sem data de fim</option>
                        </select>
                        <button type="button" class="fr-clear-btn" onclick="clearAllFilters()">
                            <i class="fas fa-times"></i> Limpar
                        </button>
                    </div>
                </div>

                <style>
                /* ═══════════════════════════════════════════════
                   FUNCIONÁRIOS — REDESIGN PROFISSIONAL
                ═══════════════════════════════════════════════ */

                /* Header */
                .frhd { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.07)); border-radius:14px; padding:.875rem 1.25rem; width:100%; box-sizing:border-box; }
                .frhd-left { display:flex; align-items:center; gap:14px; }
                .frhd-icon { width:48px; height:48px; border-radius:14px; background:linear-gradient(135deg,#3b82f6,#1d4ed8); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.25rem; box-shadow:0 4px 14px rgba(59,130,246,.35); flex-shrink:0; }
                .frhd-title { margin:0; font-size:1.5rem; font-weight:700; color:var(--text-primary,#f1f5f9); line-height:1.1; }
                .frhd-sub { margin:2px 0 0; font-size:.8rem; color:var(--text-secondary,#94a3b8); }
                .frhd-add-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; border:none; border-radius:10px; font-size:.875rem; font-weight:600; cursor:pointer; transition:all .2s; box-shadow:0 4px 12px rgba(59,130,246,.3); white-space:nowrap; }
                .frhd-add-btn:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(59,130,246,.4); }

                /* KPI strip */
                .fr-kpi-strip { display:grid; grid-template-columns:repeat(5,1fr); gap:.875rem; margin-bottom:1.75rem; }
                @media(max-width:900px){ .fr-kpi-strip{ grid-template-columns:repeat(3,1fr); } }
                @media(max-width:560px){ .fr-kpi-strip{ grid-template-columns:repeat(2,1fr); } }
                .fr-kpi { display:flex; align-items:center; gap:14px; background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.07)); border-radius:14px; padding:1rem 1.1rem; transition:transform .15s,box-shadow .15s; }
                .fr-kpi:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.18); }
                .fr-kpi-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
                .fr-kpi-total .fr-kpi-icon  { background:rgba(148,163,184,.12); color:#94a3b8; }
                .fr-kpi-active .fr-kpi-icon  { background:rgba(16,185,129,.12); color:#10b981; }
                .fr-kpi-inactive .fr-kpi-icon{ background:rgba(239,68,68,.12); color:#ef4444; }
                .fr-kpi-ferias .fr-kpi-icon  { background:rgba(59,130,246,.12); color:#3b82f6; }
                .fr-kpi-new .fr-kpi-icon     { background:rgba(167,139,250,.12); color:#a78bfa; }
                .fr-kpi-body { display:flex; flex-direction:column; }
                .fr-kpi-val { font-size:1.6rem; font-weight:700; color:var(--text-primary,#f1f5f9); line-height:1; }
                .fr-kpi-lbl { font-size:.72rem; font-weight:500; color:var(--text-secondary,#94a3b8); margin-top:2px; }
                .fr-kpi-pct { font-size:.68rem; color:#64748b; margin-top:1px; }

                /* Toolbar */
                .fr-table-wrap .fr-toolbar { background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.07)); border-radius:14px; padding:1rem 1.1rem; margin-bottom:.1rem; display:flex; flex-direction:column; gap:.75rem; }
                .fr-toolbar-top { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
                .fr-search-wrap { position:relative; flex:1; min-width:200px; }
                .fr-search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#64748b; font-size:.85rem; pointer-events:none; }
                .fr-search { width:100%; padding:9px 12px 9px 36px; border:1px solid var(--border-color,rgba(255,255,255,.12)); border-radius:9px; background:var(--input-bg,#0f172a); color:var(--text-primary,#f1f5f9); font-size:.875rem; outline:none; transition:border-color .2s; }
                .fr-search:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
                .fr-search::placeholder { color:#475569; }
                .fr-toolbar-right { display:flex; align-items:center; gap:.5rem; flex-shrink:0; }
                .fr-filter-toggle { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border:1px solid var(--border-color,rgba(255,255,255,.12)); border-radius:9px; background:transparent; color:var(--text-secondary,#94a3b8); font-size:.8rem; font-weight:600; cursor:pointer; transition:all .2s; position:relative; }
                .fr-filter-toggle:hover { border-color:#3b82f6; color:#3b82f6; }
                .fr-filter-toggle.pa-filter-open,
                .fr-filter-toggle.active { border-color:#3b82f6; color:#60a5fa; background:rgba(59,130,246,.08); }
                .fr-filter-badge { position:absolute; top:-5px; right:-5px; width:16px; height:16px; border-radius:50%; background:#3b82f6; color:#fff; font-size:.6rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
                .fr-export-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border:1px solid var(--border-color,rgba(255,255,255,.12)); border-radius:9px; background:transparent; color:var(--text-secondary,#94a3b8); font-size:.8rem; font-weight:600; cursor:pointer; transition:all .2s; }
                .fr-export-btn:hover { border-color:#10b981; color:#10b981; }
                .fr-export-menu { position:absolute; right:0; top:calc(100% + 6px); background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.1)); border-radius:10px; min-width:150px; box-shadow:0 8px 24px rgba(0,0,0,.3); z-index:50; overflow:hidden; }
                .fr-export-item { display:flex; align-items:center; gap:9px; padding:10px 14px; color:var(--text-primary,#f1f5f9); font-size:.85rem; text-decoration:none; transition:background .15s; }
                .fr-export-item:hover { background:rgba(255,255,255,.05); }

                /* Status chips */
                .fr-chips { display:flex; gap:.5rem; flex-wrap:wrap; }
                .fr-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 13px; border:1px solid transparent; border-radius:999px; font-size:.78rem; font-weight:600; cursor:pointer; transition:all .18s; background:rgba(255,255,255,.04); color:var(--text-secondary,#94a3b8); }
                .fr-chip:hover { background:rgba(255,255,255,.08); }
                .fr-chip.active { color:#fff; border-color:transparent; }
                .fr-chip-all.active   { background:rgba(99,102,241,.25); color:#a5b4fc; border-color:rgba(99,102,241,.35); }
                .fr-chip-active.active  { background:rgba(16,185,129,.2); color:#34d399; border-color:rgba(16,185,129,.35); }
                .fr-chip-inactive.active{ background:rgba(239,68,68,.2); color:#f87171; border-color:rgba(239,68,68,.35); }
                .fr-chip-ferias.active  { background:rgba(59,130,246,.2); color:#60a5fa; border-color:rgba(59,130,246,.35); }
                .fr-chip-count { opacity:.65; font-size:.7rem; }
                .fr-dot { width:7px; height:7px; border-radius:50%; display:inline-block; }
                .fr-dot-green{ background:#10b981; }
                .fr-dot-red  { background:#ef4444; }
                .fr-dot-blue { background:#3b82f6; }

                /* Advanced filters */
                .fr-adv-filters { display:none; flex-wrap:wrap; gap:.5rem; align-items:center; padding-top:.25rem; }
                .fr-adv-filters.fr-adv-open { display:flex; flex-basis:100%; width:100%; }
                .fr-select { padding:7px 10px; border:1px solid var(--border-color,rgba(255,255,255,.12)); border-radius:8px; background:var(--input-bg,#0f172a); color:var(--text-primary,#f1f5f9); font-size:.78rem; cursor:pointer; outline:none; }
                .fr-select:focus { border-color:#3b82f6; }
                .fr-clear-btn { padding:6px 12px; border:1px solid rgba(239,68,68,.3); border-radius:8px; background:rgba(239,68,68,.08); color:#f87171; font-size:.78rem; cursor:pointer; transition:all .18s; }
                .fr-clear-btn:hover { background:rgba(239,68,68,.15); }

                /* Table */
                .fr-table { border-collapse:separate; border-spacing:0; width:100%; }
                .fr-thead-row th { padding:.75rem 1rem; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#64748b; border-bottom:1px solid var(--border-color,rgba(255,255,255,.07)); background:transparent; }
                .fr-th-check { width:48px; text-align:center; }
                .fr-th-emp   { min-width:220px; }
                .fr-th-role  { min-width:160px; }
                .fr-th-status{ width:140px; }
                .fr-th-acts  { width:110px; text-align:center; }

                /* Rows */
                .fr-row { transition:background .15s; }
                .fr-row:hover { background:rgba(59,130,246,.05) !important; }
                .fr-row-dim { opacity:.6; }
                .fr-row-dim:hover { opacity:.85; }

                /* Cells */
                .fr-td-check { width:48px; text-align:center; padding:.875rem 0; vertical-align:middle; }
                .fr-td-emp   { padding:.75rem 1rem .75rem .5rem; vertical-align:middle; }
                .fr-td-role  { padding:.75rem 1rem; vertical-align:middle; }
                .fr-td-status{ padding:.75rem .5rem; vertical-align:middle; }
                .fr-td-acts  { padding:.75rem .5rem; vertical-align:middle; text-align:center; }

                /* Employee cell */
                .fr-emp-cell { display:flex; align-items:center; gap:12px; }
                .fr-av { width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:.9rem; flex-shrink:0; overflow:hidden; }
                .fr-av-img { width:100%; height:100%; object-fit:cover; }
                .fr-emp-info { display:flex; flex-direction:column; gap:1px; min-width:0; }
                .fr-emp-name { font-size:.88rem; font-weight:600; color:var(--text-primary,#f1f5f9); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                .fr-emp-email { font-size:.72rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
                .fr-contract-badge { display:inline-flex; align-items:center; gap:4px; margin-top:4px; padding:2px 6px; border-radius:4px; font-size:.65rem; font-weight:600; width:fit-content; }
                .fr-contract-expired { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
                .fr-contract-expiring{ background:#fffbeb; color:#d97706; border:1px solid #fde68a; }

                /* Roteiro do dia: mini timeline horizontal compacta (célula da tabela) */
                .fr-td-roteiro { max-width:1px; }
                .fr-roteiro { display:flex; align-items:center; gap:.3rem; white-space:nowrap; overflow:hidden; font-size:.78rem; }
                .fr-roteiro-item { display:inline-flex; align-items:center; gap:.3rem; flex-shrink:0; }
                .fr-roteiro-dot {
                    width:17px; height:17px; border-radius:50%; flex-shrink:0;
                    display:flex; align-items:center; justify-content:center;
                    font-size:.58rem; color:#0f172a; box-shadow:0 0 0 2px rgba(255,255,255,.05);
                }
                .fr-roteiro-dot.in       { background:#4ade80; }
                .fr-roteiro-dot.regresso { background:#86efac; }
                .fr-roteiro-dot.pausa    { background:#fbbf24; }
                .fr-roteiro-dot.out      { background:#f87171; }
                .fr-roteiro-dot.ativo    { background:#38bdf8; }
                .fr-roteiro-time  { font-weight:700; color:var(--text-primary,#e2e8f0); }
                .fr-roteiro-label { color:var(--text-secondary,#94a3b8); font-size:.72rem; }
                .fr-roteiro-sep { width:14px; height:2px; background:rgba(255,255,255,.14); flex-shrink:0; border-radius:2px; }
                .fr-roteiro-more {
                    flex-shrink:0; font-size:.68rem; font-weight:700; color:#60a5fa;
                    background:rgba(59,130,246,.14); padding:.1rem .45rem; border-radius:999px;
                }

                /* Roteiro do dia: timeline vertical completa (modal "Ver Detalhes") */
                .roteiro-dia { margin:0; padding:0; }
                .roteiro-evento { display:grid; grid-template-columns:3rem 1.25rem 1fr; align-items:flex-start; gap:0 .5rem; position:relative; }
                .roteiro-hora { font-size:.8rem; font-weight:700; font-variant-numeric:tabular-nums; color:var(--text-secondary,#94a3b8); text-align:right; padding-top:.05rem; line-height:1.4rem; }
                .roteiro-dot-col { display:flex; flex-direction:column; align-items:center; }
                .roteiro-dot { width:.75rem; height:.75rem; border-radius:50%; border:2px solid currentColor; background:#0f172a; flex-shrink:0; margin-top:.3rem; }
                .roteiro-line { width:2px; flex:1; min-height:1.6rem; background:rgba(255,255,255,.1); margin-bottom:-2px; }
                .roteiro-info { padding-bottom:1.1rem; }
                .roteiro-lbl { font-size:.85rem; font-weight:600; color:var(--text-primary,#e2e8f0); display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; line-height:1.4rem; }
                .roteiro-lbl .fas { font-size:.78rem; }
                .tl-entrada  .roteiro-dot, .tl-entrada  .roteiro-lbl { color:#22c55e; }
                .tl-regresso .roteiro-dot, .tl-regresso .roteiro-lbl { color:#4ade80; }
                .tl-pausa    .roteiro-dot, .tl-pausa    .roteiro-lbl { color:#f59e0b; }
                .tl-saida    .roteiro-dot, .tl-saida    .roteiro-lbl { color:#f87171; }
                .tl-ativo    .roteiro-dot, .tl-ativo    .roteiro-lbl { color:#38bdf8; }
                .tl-entrada  .roteiro-dot { background:#22c55e; }
                .tl-regresso .roteiro-dot { background:#4ade80; }
                .tl-pausa    .roteiro-dot { background:#f59e0b; }
                .tl-saida    .roteiro-dot { background:#f87171; }
                @keyframes rot-pulse-dot { 0%,100%{box-shadow:0 0 0 0 rgba(56,189,248,.5)} 50%{box-shadow:0 0 0 5px rgba(56,189,248,0)} }
                .tl-ativo .roteiro-dot { animation:rot-pulse-dot 1.8s ease-in-out infinite; background:#38bdf8; }

                /* Role cell */
                .fr-role-pos  { display:block; font-size:.83rem; font-weight:600; color:var(--text-primary,#f1f5f9); }
                .fr-role-dept { display:inline-flex; align-items:center; margin-top:4px; padding:2px 8px; background:rgba(99,102,241,.12); color:#a5b4fc; border-radius:4px; font-size:.68rem; font-weight:600; }

                /* Presence pills */
                .fr-presence { display:inline-flex; align-items:center; gap:5px; margin-top:5px; padding:2px 7px; border-radius:999px; font-size:.68rem; font-weight:600; }
                .fr-pdot { width:6px; height:6px; border-radius:50%; display:inline-block; }
                .fr-p-present  { background:rgba(16,185,129,.1); color:#10b981; }
                .fr-p-present .fr-pdot { background:#10b981; }
                .fr-p-late     { background:rgba(245,158,11,.1); color:#f59e0b; }
                .fr-p-late .fr-pdot { background:#f59e0b; }
                .fr-p-absent   { background:rgba(239,68,68,.1); color:#ef4444; }
                .fr-p-absent .fr-pdot { background:#ef4444; }
                .fr-p-unknown  { background:rgba(100,116,139,.1); color:#64748b; }
                .fr-p-unknown .fr-pdot { background:#475569; }

                /* Action buttons */
                .fr-acts { display:flex; align-items:center; justify-content:center; gap:5px; }
                .fr-btn { width:32px; height:32px; border-radius:8px; border:none; display:inline-flex; align-items:center; justify-content:center; font-size:.78rem; cursor:pointer; transition:all .18s; }
                .fr-btn-view   { background:rgba(59,130,246,.12); color:#3b82f6; }
                .fr-btn-view:hover{ background:#3b82f6; color:#fff; }
                .fr-btn-edit   { background:rgba(234,179,8,.12); color:#ca8a04; }
                .fr-btn-edit:hover{ background:#eab308; color:#fff; }
                .fr-btn-deact  { background:rgba(239,68,68,.1); color:#ef4444; }
                .fr-btn-deact:hover{ background:#ef4444; color:#fff; }
                .fr-btn-activate{ background:rgba(16,185,129,.12); color:#10b981; }
                .fr-btn-activate:hover{ background:#10b981; color:#fff; }
                .fr-btn-off    { opacity:.35; cursor:not-allowed; }
                .fr-btn:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 3px 10px rgba(0,0,0,.2); }
                .fr-btn:disabled { opacity:.35; cursor:not-allowed; filter:grayscale(.6); }
                .fr-btn:disabled:hover { background:inherit; color:inherit; }

                /* Checkbox */
                .fr-checkbox { width:16px; height:16px; accent-color:#3b82f6; cursor:pointer; }

                .active-relatorio-card {
                    background: linear-gradient(120deg, #2563eb 0%, #60a5fa 100%);
                    color: #fff;
                    border: 2px solid #2563eb;
                    box-shadow: 0 8px 24px 0 #2563eb44, 0 2px 8px #2563eb22;
                    transform: translateY(-6px) scale(1.03);
                    z-index: 2;
                    transition: box-shadow 0.2s, border 0.2s, background 0.2s, transform 0.18s;
                }

                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-15px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                #employeesTable .employee-actions,
                #turnosTable .employee-actions,
                #assiduidade-section .employee-actions,
                #folha-pagamento-section .employee-actions,
                #feriasSectionTable .employee-actions,
                #gorjetas-section .employee-actions,
                #gorjetas-section .gorjeta-actions {
                    display: flex;
                    gap: 0.4rem;
                    flex-wrap: nowrap;
                    justify-content: center;
                    align-items: center;
                    padding: 0;
                    margin: 0;
                }

                #employeesTable .employee-action-btn,
                #turnosTable .employee-action-btn,
                #assiduidade-section .employee-action-btn,
                #folha-pagamento-section .employee-action-btn,
                #feriasSectionTable .employee-action-btn,
                #gorjetas-section .employee-action-btn {
                    min-width: 66px !important;
                    height: 30px !important;
                    min-height: 30px !important;
                    max-height: 30px !important;
                    padding: 0.3rem 0.5rem !important;
                    border-radius: 7px !important;
                    font-size: 0.74rem !important;
                    line-height: 1 !important;
                    font-weight: 700;
                    letter-spacing: 0.01em;
                    gap: 0.26rem !important;
                    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.14);
                    transition: transform 0.14s ease, box-shadow 0.18s ease, filter 0.18s ease;
                }

                #employeesTable .employee-action-btn i,
                #turnosTable .employee-action-btn i,
                #assiduidade-section .employee-action-btn i,
                #folha-pagamento-section .employee-action-btn i,
                #feriasSectionTable .employee-action-btn i,
                #gorjetas-section .employee-action-btn i {
                    font-size: 0.72rem !important;
                }

                #employeesTable .employee-action-btn:not(:disabled):hover,
                #turnosTable .employee-action-btn:not(:disabled):hover,
                #assiduidade-section .employee-action-btn:not(:disabled):hover,
                #folha-pagamento-section .employee-action-btn:not(:disabled):hover,
                #feriasSectionTable .employee-action-btn:not(:disabled):hover,
                #gorjetas-section .employee-action-btn:not(:disabled):hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.2);
                    filter: brightness(1.03);
                }

                #employeesTable .employee-action-btn.btn-activate,
                #turnosTable .employee-action-btn.btn-activate,
                #assiduidade-section .employee-action-btn.btn-activate,
                #folha-pagamento-section .employee-action-btn.btn-activate,
                #gorjetas-section .employee-action-btn.btn-activate {
                    min-width: 62px !important;
                    background: linear-gradient(145deg, #10b981, #059669) !important;
                }

                #presencaTable td:last-child {
                    text-align: center;
                }

                #presencaTable td:last-child .employee-actions {
                    width: 100%;
                    justify-content: center !important;
                }

                #gorjetasTable thead th:last-child {
                    text-align: center !important;
                    padding-right: 1rem !important;
                }

                #gorjetasTable tbody td:last-child {
                    display: table-cell !important;
                    text-align: center !important;
                    padding-right: 1rem !important;
                }

                #gorjetasTable tbody td:last-child .employee-actions {
                    width: 100%;
                    justify-content: center !important;
                }

                @media (max-width: 1200px) {
                    #employeesTable .employee-actions,
                    #turnosTable .employee-actions,
                    #assiduidade-section .employee-actions,
                    #folha-pagamento-section .employee-actions,
                    #gorjetas-section .employee-actions,
                    #gorjetas-section .gorjeta-actions {
                        flex-wrap: wrap;
                        justify-content: flex-end;
                    }

                    #presencaTable td:last-child .employee-actions {
                        justify-content: center !important;
                    }
                }

                /* ── Add Employee Modal (am-*) ────────────────── */
                #addEmployeeModal { overflow-y:auto; padding:24px 16px 48px; }
                #turnoModal, #bulkTurnoModal, #turnoSwapModal { overflow-y:auto; padding:24px 16px 48px; }
                #gorjetaModal, #gorjetaViewModal { overflow-y:auto; padding:24px 16px 48px; }
                #notifyModal, #bulkVacationModal, #bulkStatusModal, #bulkDepartmentModal { overflow-y:auto; padding:24px 16px 48px; }
                .am-sheet {
                    background:#0f172a;
                    border:1px solid rgba(255,255,255,.1);
                    border-radius:20px;
                    width:100%; max-width:660px;
                    padding:28px 28px 24px;
                    position:relative;
                    box-shadow:0 24px 60px rgba(0,0,0,.5);
                    margin:0 auto;
                }
                .am-close {
                    position:absolute; top:14px; right:14px;
                    background:rgba(255,255,255,.07); border:none;
                    color:#94a3b8; width:32px; height:32px;
                    border-radius:8px; font-size:19px; cursor:pointer;
                    display:grid; place-items:center; transition:background .15s,color .15s;
                    line-height:1;
                }
                .am-close:hover { background:rgba(255,255,255,.14); color:#e2e8f0; }
                .am-header {
                    display:flex; align-items:center; gap:14px;
                    margin-bottom:20px; padding-bottom:16px;
                    border-bottom:1px solid rgba(255,255,255,.08);
                }
                .am-header-icon {
                    width:44px; height:44px; border-radius:12px; flex-shrink:0;
                    background:linear-gradient(135deg,#3b82f6,#2563eb);
                    display:grid; place-items:center; color:#fff; font-size:18px;
                    box-shadow:0 6px 16px rgba(37,99,235,.35);
                }
                .am-title { margin:0; font-size:1.2rem; font-weight:700; color:#e2e8f0; }
                .am-subtitle { margin:2px 0 0; font-size:.78rem; color:#64748b; }
                .am-error {
                    background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3);
                    color:#fca5a5; padding:10px 14px; border-radius:10px;
                    font-size:.85rem; margin-bottom:14px;
                }
                /* Avatar row */
                .am-avatar-row {
                    display:flex; align-items:center; gap:16px;
                    margin-bottom:20px; padding:14px 16px;
                    background:rgba(255,255,255,.04);
                    border-radius:12px; border:1px solid rgba(255,255,255,.07);
                }
                .am-av-preview {
                    width:68px; height:68px; border-radius:50%;
                    background:linear-gradient(135deg,#667eea,#764ba2);
                    display:grid; place-items:center; color:#fff;
                    font-size:26px; overflow:hidden; flex-shrink:0;
                    border:3px solid rgba(255,255,255,.1);
                }
                .am-av-preview img { width:100%;height:100%;object-fit:cover;border-radius:50%; }
                .am-file-label {
                    display:inline-flex; align-items:center; gap:6px;
                    padding:7px 14px; border-radius:8px;
                    background:rgba(59,130,246,.14); color:#93c5fd;
                    font-size:.8rem; font-weight:600; cursor:pointer;
                    border:1px solid rgba(59,130,246,.25); transition:background .15s;
                }
                .am-file-label:hover { background:rgba(59,130,246,.25); }
                .am-av-hint { display:block; font-size:.72rem; color:#475569; margin-top:5px; }
                /* Sections */
                .am-section { margin-bottom:18px; }
                .am-sec-lbl {
                    font-size:.7rem; font-weight:700; color:#64748b;
                    text-transform:uppercase; letter-spacing:.07em;
                    display:flex; align-items:center; gap:6px;
                    margin-bottom:10px; padding-bottom:8px;
                    border-bottom:1px solid rgba(255,255,255,.06);
                }
                .am-sec-lbl i { color:#3b82f6; }
                /* Grids */
                .am-g2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
                .am-g3 { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
                .am-f { display:flex; flex-direction:column; }
                .am-f-full { grid-column:1/-1; }
                .am-lbl { font-size:.75rem; font-weight:600; color:#94a3b8; margin-bottom:4px; }
                .am-opt { font-weight:400; opacity:.6; }
                .am-inp {
                    background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
                    border-radius:8px; padding:9px 12px; color:#e2e8f0;
                    font-size:.875rem; outline:none; width:100%; box-sizing:border-box;
                    transition:border-color .15s, background .15s;
                }
                .am-inp::placeholder { color:#475569; }
                .am-inp:focus { border-color:#3b82f6; background:rgba(59,130,246,.07); }
                .am-sel {
                    cursor:pointer; appearance:none;
                    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2364748b' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
                    background-repeat:no-repeat; background-position:right 10px center; padding-right:30px;
                }
                .am-sel option { background:#1e293b; color:#e2e8f0; }
                .am-ico-wrap { position:relative; }
                .am-ico {
                    position:absolute; left:10px; top:50%; transform:translateY(-50%);
                    color:#475569; font-size:.78rem; pointer-events:none;
                }
                .am-inp-ico { padding-left:28px; }
                .am-hint { font-size:.7rem; color:#475569; margin-top:3px; }
                /* Footer */
                .am-footer {
                    display:flex; justify-content:flex-end; gap:10px;
                    margin-top:22px; padding-top:16px;
                    border-top:1px solid rgba(255,255,255,.08);
                }
                .am-btn-cancel {
                    padding:10px 20px; border-radius:10px;
                    border:1px solid rgba(255,255,255,.11);
                    background:transparent; color:#94a3b8;
                    font-size:.875rem; font-weight:600; cursor:pointer; transition:background .15s;
                }
                .am-btn-cancel:hover { background:rgba(255,255,255,.06); }
                .am-btn-submit {
                    display:inline-flex; align-items:center; gap:8px;
                    padding:10px 22px; border-radius:10px; border:none;
                    background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff;
                    font-size:.875rem; font-weight:700; cursor:pointer;
                    box-shadow:0 4px 14px rgba(37,99,235,.3); transition:opacity .15s;
                }
                .am-btn-submit:hover { opacity:.9; }
                @media(max-width:580px){
                    .am-g2,.am-g3 { grid-template-columns:1fr; }
                    .am-sheet { padding:20px 14px; }
                }

                /* ── Edit / View modal overrides ────────────── */
                #editEmployeeModal { overflow-y:auto; padding:24px 16px 48px; }
                #viewEmployeeModal { overflow-y:auto; padding:24px 16px 48px; }
                .vm-sheet { max-width:700px; }
                /* View Modal — hero */
                .vm-hero {
                    display:flex; align-items:center; gap:20px;
                    margin-bottom:20px; padding:18px 20px;
                    background:rgba(255,255,255,.04);
                    border-radius:14px; border:1px solid rgba(255,255,255,.07);
                }
                .vm-hero-av {
                    width:80px; height:80px; border-radius:50%; overflow:hidden; flex-shrink:0;
                    background:linear-gradient(135deg,#667eea,#764ba2);
                    display:grid; place-items:center; color:#fff; font-size:32px;
                    border:3px solid rgba(255,255,255,.12);
                }
                .vm-hero-av img { width:100%;height:100%;object-fit:cover;border-radius:50%; }
                .vm-hero-info { flex:1; min-width:0; }
                .vm-hero-name { margin:0 0 3px; font-size:1.15rem; font-weight:700; color:#e2e8f0; }
                .vm-hero-pos { font-size:.82rem; color:#64748b; margin:0 0 8px; }
                /* View Modal — sections */
                .vm-section { margin-bottom:18px; }
                .vm-sec-lbl {
                    font-size:.7rem; font-weight:700; color:#64748b;
                    text-transform:uppercase; letter-spacing:.07em;
                    display:flex; align-items:center; gap:6px;
                    margin-bottom:10px; padding-bottom:8px;
                    border-bottom:1px solid rgba(255,255,255,.06);
                }
                .vm-sec-lbl i { color:#3b82f6; }
                .vm-g2 { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
                .vm-g3 { display:grid; grid-template-columns:repeat(3,1fr); gap:12px 16px; }
                .vm-full { grid-column:1/-1; }
                .vm-field-label { font-size:.7rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:.05em; margin-bottom:3px; }
                .vm-field-value { font-size:.88rem; color:#cbd5e1; min-height:1.1em; }
                /* Ponto box */
                .vm-ponto-box {
                    background:rgba(16,185,129,.07); border:1px solid rgba(16,185,129,.18);
                    border-radius:10px; padding:14px; margin-top:12px;
                }
                /* History */
                .vm-history-box {
                    background:rgba(139,92,246,.06); border:1px solid rgba(139,92,246,.18);
                    border-radius:10px; padding:10px;
                    max-height:200px; overflow-y:auto;
                    color:#c4b5fd; font-size:.85rem;
                }
                /* Docs */
                .vm-checklist {
                    padding:10px 12px; border:1px solid rgba(148,163,184,.2);
                    border-radius:8px; background:rgba(15,23,42,.4);
                    color:#94a3b8; font-size:.83rem; margin-bottom:10px;
                }
                .vm-docs-list { max-height:280px; overflow-y:auto; }
                /* PDF button */
                .vm-btn-pdf {
                    display:inline-flex; align-items:center; gap:8px;
                    padding:10px 20px; border-radius:10px;
                    border:1px solid rgba(239,68,68,.3);
                    background:rgba(239,68,68,.1); color:#fca5a5;
                    font-size:.875rem; font-weight:600; cursor:pointer; transition:background .15s;
                }
                .vm-btn-pdf:hover { background:rgba(239,68,68,.2); }
                @media(max-width:580px){
                    .vm-g2,.vm-g3 { grid-template-columns:1fr; }
                    .vm-hero { flex-direction:column; text-align:center; }
                }

                /* ── Upload Document Modal drop zone (udm-*) ──── */
                #uploadDocumentModal { overflow-y:auto; padding:24px 16px 48px; }
                .udm-dropzone {
                    display:flex; flex-direction:column; align-items:center;
                    justify-content:center; gap:6px; padding:26px 16px;
                    border:2px dashed rgba(59,130,246,.28);
                    border-radius:12px; background:rgba(59,130,246,.04);
                    cursor:pointer; transition:border-color .2s, background .2s;
                    text-align:center;
                }
                .udm-dropzone:hover { border-color:rgba(59,130,246,.55); background:rgba(59,130,246,.09); }
                .udm-dz-icon { font-size:2rem; color:#3b82f6; opacity:.75; margin-bottom:2px; }
                .udm-dz-title { font-size:.875rem; font-weight:600; color:#cbd5e1; }
                .udm-dz-hint { font-size:.72rem; color:#475569; }
                .udm-dropzone.udm-has-file { border-color:rgba(16,185,129,.4); background:rgba(16,185,129,.05); border-style:solid; }
                .udm-dropzone.udm-has-file .udm-dz-icon { color:#10b981; opacity:1; }
                .udm-dropzone.udm-has-file .udm-dz-title { color:#6ee7b7; }
                </style>

                 <table id="employeesTable" class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-check">
                                <input type="checkbox" id="selectAllCheckbox" class="employee-checkbox fr-checkbox" title="Selecionar tudo">
                            </th>
                            <th class="fr-th-emp">Funcionário</th>
                            <th class="fr-th-role">Cargo &amp; Departamento</th>
                            <th class="fr-th-status">Estado</th>
                            <th class="fr-th-acts">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee):
                            $statusRaw = (string)($employee['status'] ?? '');
                            $statusNormalized = mb_strtolower(trim($statusRaw));
                            $isDisabledRow = in_array($statusNormalized, ['inactive', 'inativo', 'ferias', 'férias'], true);
                            $profilePicture = !empty($employee['profile_picture']) ? htmlspecialchars($employee['profile_picture']) : '';

                            $badgeClass = 'status-badge ';
                            switch ($statusRaw) {
                                case 'active':
                                    $badgeClass .= 'status-active';
                                    $statusLabel = 'Ativo';
                                    break;
                                case 'inactive':
                                    $badgeClass .= 'status-inactive';
                                    $statusLabel = 'Inativo';
                                    break;
                                case 'ferias':
                                    $badgeClass .= 'status-ferias';
                                    $statusLabel = 'Férias';
                                    break;
                                default:
                                    $badgeClass .= 'status-inactive';
                                    $statusLabel = $statusRaw !== '' ? $statusRaw : '—';
                                    break;
                            }

                            $rowAttributes = [
                                'data-employee-id' => $employee['id'] ?? '',
                                'data-name' => $employee['name'] ?? '',
                                'data-fullname' => $employee['name'] ?? '',
                                'data-position' => $employee['position'] ?? '',
                                'data-department' => $employee['department'] ?? '',
                                'data-email' => $employee['email'] ?? '',
                                'data-phone' => $employee['phone'] ?? '',
                                'data-start-date' => $employee['startDate'] ?? '',
                                'data-end-date' => $employee['endDate'] ?? '',
                                'data-vacation-days' => isset($employee['vacation_days']) ? (int)$employee['vacation_days'] : 22,
                                'data-contract-type' => $employee['contractType'] ?? '',
                                'data-status' => $statusRaw,
                                'data-status-label' => $statusLabel
                            ];

                            $attrString = '';
                            foreach ($rowAttributes as $attrKey => $attrValue) {
                                if ($attrValue === null || $attrValue === '') {
                                    continue;
                                }
                                $attrString .= ' ' . $attrKey . '="' . htmlspecialchars((string)$attrValue, ENT_QUOTES, 'UTF-8') . '"';
                            }
                            $isInactiveOnly = ($statusRaw === 'inactive' || $statusRaw === 'inativo');
                        ?>
                        <?php
                            $empId   = htmlspecialchars((string)($employee['id'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $empName = htmlspecialchars($employee['name'] ?? '', ENT_QUOTES, 'UTF-8');
                            $nameParts = explode(' ', $empName);
                            $displayName = count($nameParts) > 1 ? $nameParts[0] . ' ' . end($nameParts) : $empName;
                            $empEmail = htmlspecialchars($employee['email'] ?? '', ENT_QUOTES, 'UTF-8');
                            $empPos   = htmlspecialchars($employee['position'] ?? '—', ENT_QUOTES, 'UTF-8');
                            $empDept  = htmlspecialchars($employee['department'] ?? '—', ENT_QUOTES, 'UTF-8');
                            $avatarInitials = strtoupper(substr($employee['name'] ?? 'U', 0, 2));

                            // Avatar gradient por letra
                            $gradients = ['#667eea,#764ba2','#f093fb,#f5576c','#4facfe,#00f2fe','#43e97b,#38f9d7','#fa709a,#fee140','#a18cd1,#fbc2eb','#ffecd2,#fcb69f','#a1c4fd,#c2e9fb','#fd7043,#ff8a65','#26c6da,#00acc1'];
                            $gi = ord($avatarInitials[0]) % count($gradients);
                            [$gc1, $gc2] = explode(',', $gradients[$gi]);

                            // Contract expiry
                            $expiryBadge = '';
                            $endDateStr = trim((string)($employee['endDate'] ?? ''));
                            if ($endDateStr !== '' && $endDateStr !== '0000-00-00') {
                                $endTs = strtotime($endDateStr);
                                if ($endTs !== false) {
                                    $daysLeft = (int)((strtotime(date('Y-m-d')) - $endTs) / -86400);
                                    if ($daysLeft < 0) {
                                        $expiryBadge = '<span class="fr-contract-badge fr-contract-expired"><i class="fas fa-exclamation-triangle"></i> Expirado</span>';
                                    } elseif ($daysLeft <= 30) {
                                        $expiryBadge = '<span class="fr-contract-badge fr-contract-expiring"><i class="fas fa-clock"></i> Expira em ' . $daysLeft . 'd</span>';
                                    }
                                }
                            }

                            // Presence pill
                            $pStatus = (string)($employee['presence_status'] ?? '');
                            if ($pStatus === 'presente') {
                                $presencePill = '<span class="fr-presence fr-p-present"><span class="fr-pdot"></span>Presente</span>';
                            } elseif ($pStatus === 'atrasado') {
                                $presencePill = '<span class="fr-presence fr-p-late"><span class="fr-pdot"></span>Atrasado</span>';
                            } elseif (in_array($pStatus, ['falta', 'falta_justificada'], true)) {
                                $presencePill = '<span class="fr-presence fr-p-absent"><span class="fr-pdot"></span>Falta</span>';
                            } elseif ($statusRaw === 'active') {
                                $presencePill = '<span class="fr-presence fr-p-unknown"><span class="fr-pdot"></span>Não registado</span>';
                            } else {
                                $presencePill = '';
                            }
                        ?>
                        <tr<?php echo $isDisabledRow ? ' class="disabled-row employee-row fr-row fr-row-dim"' : ' class="employee-row fr-row"'; echo $attrString; ?>>

                            <!-- Checkbox -->
                            <td class="fr-td-check">
                                <input type="checkbox" class="employee-checkbox fr-checkbox"
                                    data-employee-id="<?= $empId ?>"
                                    data-employee-name="<?= $empName ?>">
                            </td>

                            <!-- Funcionário: avatar + nome + email + expiry -->
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,<?= $gc1 ?>,<?= $gc2 ?>);">
                                        <?php if ($profilePicture): ?>
                                            <img src="../<?= $profilePicture ?>" alt="<?= $empName ?>" class="fr-av-img">
                                        <?php else: ?>
                                            <?= $avatarInitials ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?= $displayName ?></span>
                                        <?php if ($empEmail): ?>
                                            <span class="fr-emp-email"><?= $empEmail ?></span>
                                        <?php endif; ?>
                                        <?= $expiryBadge ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Cargo + Departamento -->
                            <td class="fr-td-role">
                                <span class="fr-role-pos"><?= $empPos ?></span>
                                <?php if ($empDept && $empDept !== '—'): ?>
                                    <span class="fr-role-dept"><?= $empDept ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Estado + presença -->
                            <td class="fr-td-status">
                                <span class="<?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"
                                    id="status-<?= $empId ?>">
                                    <?php
                                        $icon = 'fa-circle-info';
                                        if ($statusRaw === 'active')   $icon = 'fa-check-circle';
                                        elseif ($statusRaw === 'inactive') $icon = 'fa-times-circle';
                                        elseif ($statusRaw === 'ferias')   $icon = 'fa-umbrella-beach';
                                    ?>
                                    <i class="fas <?= $icon ?>"></i>
                                    <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?= $presencePill ?>
                            </td>

                            <!-- Ações: icon-only buttons -->
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <button type="button" class="fr-btn fr-btn-view btn-view"
                                        data-id="<?= $empId ?>" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="fr-btn fr-btn-edit btn-edit<?= $isDisabledRow ? ' fr-btn-off' : '' ?>"
                                        data-id="<?= $empId ?>"
                                        <?= $isDisabledRow ? 'disabled title="Inativo ou em férias"' : 'title="Editar"' ?>>
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <?php if ($isDisabledRow): ?>
                                        <button type="button" class="fr-btn fr-btn-activate btn-activate"
                                            data-id="<?= $empId ?>" title="Ativar">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="fr-btn fr-btn-deact btn-employee-deactivate"
                                            data-id="<?= $empId ?>" title="Desativar">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="bulkActionsBar" class="bulk-actions-bar" aria-hidden="true">

                <div class="bulk-left">
                    <button type="button" class="bulk-close" onclick="closeBulkActionsBar()">
                        <i class="fas fa-times"></i>
                    </button>

                    <div class="bulk-info">
                        <i class="fas fa-check-square"></i>
                        <span id="bulkCount">
                            <strong>0</strong> funcionários selecionados
                        </span>
                    </div>
                </div>

                <div class="bulk-actions">
                    <div class="bulk-primary">
                        <button type="button" onclick="bulkMarkVacation()">
                            <i class="fas fa-umbrella-beach"></i> Férias
                        </button>

                        <button type="button" onclick="bulkChangeStatus()">
                            <i class="fas fa-toggle-on"></i> Status
                        </button>

                        <button type="button" onclick="bulkChangeDepartment()">
                            <i class="fas fa-exchange-alt"></i> Departamento
                        </button>

                        <button type="button" onclick="bulkExportSelected()">
                            <i class="fas fa-download"></i> Exportar
                        </button>

                        <button type="button" onclick="openNotifyModal()">
                            <i class="fas fa-sms"></i> Notificar
                        </button>
                    </div>

                    <div class="bulk-danger">
                        <button type="button" onclick="clearBulkSelection()" class="btn-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </button>

                        <button type="button" onclick="bulkDeleteSelected()" class="btn-danger">
                            <i class="fas fa-trash-alt"></i> Excluir
                        </button>
                    </div>
                </div>

            </div>

            <div id="notifyModal" class="modal" style="display: none; overflow-y:auto; padding:24px 16px 48px;">
                <div class="am-sheet" style="max-width:480px;">
                    <button class="am-close" type="button" aria-label="Fechar" onclick="closeNotifyModal()">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb);box-shadow:0 6px 16px rgba(37,99,235,.35);">
                            <i class="fas fa-comment-dots"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Enviar SMS</h2>
                            <p class="am-subtitle">Envie uma mensagem rápida para os funcionários selecionados</p>
                        </div>
                    </div>

                    <div class="am-section">
                        <div style="display:flex;flex-direction:column;align-items:center;gap:.4rem;text-align:center;">
                            <span id="selectedCount" style="background:rgba(59,130,246,.14);color:#93c5fd;font-weight:700;border-radius:8px;padding:.3em 1em;font-size:.85rem;">0 funcionários selecionados</span>
                            <div id="notifyRecipientPreview" style="color:#64748b;font-size:.82rem;">Nenhum destinatário selecionado.</div>
                        </div>
                    </div>

                    <div class="am-section">
                        <div class="am-sec-lbl"><i class="fas fa-paper-plane"></i> Canal de Envio</div>
                        <div style="display:flex;flex-wrap:wrap;gap:.85rem;">
                            <label class="am-lbl" style="display:flex;align-items:center;gap:.35em;cursor:pointer;font-weight:400;">
                                <input type="radio" name="notifyDeliveryMode" value="app" />
                                App do funcionário
                            </label>
                            <label class="am-lbl" style="display:flex;align-items:center;gap:.35em;cursor:pointer;font-weight:400;">
                                <input type="radio" name="notifyDeliveryMode" value="phone" />
                                Número de telefone
                            </label>
                            <label class="am-lbl" style="display:flex;align-items:center;gap:.35em;cursor:pointer;font-weight:400;">
                                <input type="radio" name="notifyDeliveryMode" value="both" checked />
                                Ambos
                            </label>
                        </div>
                    </div>

                    <div class="am-section">
                        <label class="am-lbl" for="smsMessage">Mensagem <span class="am-opt">(máx 160 caracteres)</span></label>
                        <textarea id="smsMessage" maxlength="160" class="am-inp" style="min-height:90px;"
                            placeholder="Digite uma mensagem clara e objetiva para a equipa."></textarea>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.4rem;">
                            <p id="charLimitMsg" style="display:none;margin:0;color:#f87171;font-size:.82rem;"></p>
                            <span id="smsCharCounter" class="am-hint">160 restantes</span>
                        </div>
                    </div>

                    <div class="am-footer">
                        <button type="button" class="am-btn-cancel" onclick="closeNotifyModal()">Cancelar</button>
                        <button type="button" class="am-btn-submit" onclick="sendBulkSMS()">
                            <i class="fas fa-paper-plane"></i> Enviar SMS
                        </button>
                    </div>
                </div>
            </div>


            <div id="editEmployeeModal" class="modal" style="display:none;">
                <div class="am-sheet">

                    <button class="am-close" type="button"
                        onclick="document.getElementById('editEmployeeModal').style.display='none'"
                        aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 6px 16px rgba(217,119,6,.35);">
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Editar Funcionário</h2>
                            <p class="am-subtitle">Actualize os dados do colaborador</p>
                        </div>
                    </div>

                    <div id="editEmployeeInlineError" class="am-error" style="display:none;"></div>

                    <form id="editEmployeeForm">
                        <input type="hidden" id="employee-id" name="id">

                        <!-- Avatar -->
                        <div class="am-avatar-row">
                            <div id="edit-avatar-preview" class="am-av-preview">
                                <span id="edit-avatar-initials">FN</span>
                            </div>
                            <div>
                                <label class="am-file-label" for="edit-profile-picture">
                                    <i class="fas fa-camera"></i> Alterar foto
                                </label>
                                <input type="file" id="edit-profile-picture" name="profile_picture" accept="image/*" style="display:none;">
                                <span class="am-av-hint">JPG, PNG &mdash; máx. 2 MB</span>
                            </div>
                        </div>

                        <!-- Básicas -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-id-card"></i> Informações Básicas</div>
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="edit-name">Nome Completo *</label>
                                    <input class="am-inp" type="text" id="edit-name" name="name" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-position">Cargo *</label>
                                    <input class="am-inp" type="text" id="edit-position" name="position" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-department">Departamento *</label>
                                    <input class="am-inp" type="text" id="edit-department" name="department" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-email">Email *</label>
                                    <input class="am-inp" type="email" id="edit-email" name="email" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-phone">Telefone</label>
                                    <input class="am-inp" type="text" id="edit-phone" name="phone">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-status">Estado</label>
                                    <select class="am-inp am-sel" id="edit-status" name="status" required>
                                        <option value="active">Ativo</option>
                                        <option value="inactive">Inativo</option>
                                        <option value="ferias">Férias</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Pessoal -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-user"></i> Informações Pessoais</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-birthDate">Data de Nascimento</label>
                                    <input class="am-inp" type="date" id="edit-birthDate" name="birthDate">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-nif">NIF</label>
                                    <input class="am-inp" type="text" id="edit-nif" name="nif" placeholder="123456789" maxlength="9">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-niss">NISS</label>
                                    <input class="am-inp" type="text" id="edit-niss" name="niss" placeholder="12345678901" maxlength="11">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-emergencyContact">Contacto de Emergência</label>
                                    <input class="am-inp" type="text" id="edit-emergencyContact" name="emergencyContact" placeholder="Nome: +351 ...">
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="edit-address">Morada</label>
                                    <input class="am-inp" type="text" id="edit-address" name="address" placeholder="Rua, Número, Cidade, Código Postal">
                                </div>
                            </div>
                        </div>

                        <!-- Contrato -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-file-contract"></i> Contrato</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-startDate">Início</label>
                                    <input class="am-inp" type="date" id="edit-startDate" name="startDate">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-endDate">Fim <span class="am-opt">(branco = efectivo)</span></label>
                                    <input class="am-inp" type="date" id="edit-endDate" name="endDate">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-contractType">Tipo</label>
                                    <select class="am-inp am-sel" id="edit-contractType" name="contractType">
                                        <option value="">Selecione...</option>
                                        <option value="efetivo">Efetivo</option>
                                        <option value="temporario">Temporário</option>
                                        <option value="part-time">Part-time</option>
                                        <option value="estagio">Estágio</option>
                                        <option value="freelancer">Freelancer</option>
                                    </select>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-vacation-days">Dias de Férias</label>
                                    <input class="am-inp" type="number" id="edit-vacation-days" name="vacation_days" placeholder="22" min="0" max="365">
                                    <span class="am-hint">Mínimo legal PT: 22 dias</span>
                                </div>
                            </div>
                        </div>

                        <!-- Remuneração -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-euro-sign"></i> Remuneração</div>
                            <div class="am-g3">
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-salary_base">Salário Base</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico">€</span>
                                        <input class="am-inp am-inp-ico" type="number" id="edit-salary_base" name="salary_base" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-subsidio_alimentacao">Sub. Alimentação</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico"><i class="fas fa-utensils"></i></span>
                                        <input class="am-inp am-inp-ico" type="number" id="edit-subsidio_alimentacao" name="subsidio_alimentacao" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-bonus">Bónus</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico"><i class="fas fa-gift"></i></span>
                                        <input class="am-inp am-inp-ico" type="number" id="edit-bonus" name="bonus" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alteração crítica -->
                        <div class="am-section">
                            <div class="am-sec-lbl" style="color:#f59e0b;border-color:rgba(245,158,11,.2);">
                                <i class="fas fa-triangle-exclamation" style="color:#f59e0b;"></i> Alteração Crítica
                            </div>
                            <div class="am-f">
                                <label class="am-lbl" for="edit-approval-reason">Motivo <span class="am-opt">(status, contrato ou remuneração)</span></label>
                                <textarea class="am-inp" id="edit-approval-reason" name="approval_reason" rows="2"
                                    placeholder="Explique o motivo quando alterar status, contrato ou remuneração." style="resize:vertical;"></textarea>
                            </div>
                        </div>

                        <!-- Acesso -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-shield-alt"></i> Acesso</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="edit-pin">PIN <span class="am-opt">(branco = manter actual)</span></label>
                                    <input class="am-inp" type="password" id="edit-pin" name="pin" placeholder="Novo PIN (4+ dígitos)" minlength="4" autocomplete="new-password">
                                </div>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel"
                                onclick="document.getElementById('editEmployeeModal').style.display='none'">Cancelar</button>
                            <button type="submit" class="am-btn-submit" style="background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 4px 14px rgba(217,119,6,.28);">
                                <i class="fas fa-floppy-disk"></i> Guardar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para Adicionar Funcionário -->
            <div id="addEmployeeModal" class="modal" style="display:none;">
                <div class="am-sheet">

                    <button class="am-close close-btn-add" type="button" aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon"><i class="fas fa-user-plus"></i></div>
                        <div>
                            <h2 class="am-title">Novo Funcionário</h2>
                            <p class="am-subtitle">Preencha os dados para registar o novo colaborador</p>
                        </div>
                    </div>

                    <div id="addEmployeeInlineError" class="am-error" style="display:none;"></div>

                    <form id="addEmployeeForm" enctype="multipart/form-data" autocomplete="off">

                        <!-- Foto de perfil -->
                        <div class="am-avatar-row">
                            <div id="add-avatar-preview" class="am-av-preview">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <label class="am-file-label" for="add-profile-picture">
                                    <i class="fas fa-camera"></i> Escolher foto
                                </label>
                                <input type="file" id="add-profile-picture" name="profile_picture" accept="image/*" style="display:none;">
                                <span class="am-av-hint">JPG, PNG &mdash; máx. 2 MB</span>
                            </div>
                        </div>

                        <!-- Informações Básicas -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-id-card"></i> Informações Básicas</div>
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="add-name">Nome Completo *</label>
                                    <input class="am-inp" type="text" id="add-name" name="name" required placeholder="Ex: Ana Silva">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-position">Cargo *</label>
                                    <input class="am-inp" type="text" id="add-position" name="position" required placeholder="Ex: Cozinheiro">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-department">Departamento *</label>
                                    <input class="am-inp" type="text" id="add-department" name="department" required placeholder="Ex: Cozinha">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-email">Email *</label>
                                    <input class="am-inp" type="email" id="add-email" name="email" required placeholder="funcionario@email.com">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-phone">Telefone</label>
                                    <input class="am-inp" type="text" id="add-phone" name="phone" placeholder="+351 900 000 000">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-status">Estado</label>
                                    <select class="am-inp am-sel" id="add-status" name="status" required>
                                        <option value="active">Ativo</option>
                                        <option value="inactive">Inativo</option>
                                        <option value="ferias">Férias</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Informações Pessoais -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-user"></i> Informações Pessoais</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="add-birthDate">Data de Nascimento</label>
                                    <input class="am-inp" type="date" id="add-birthDate" name="birthDate">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-nif">NIF</label>
                                    <input class="am-inp" type="text" id="add-nif" name="nif" placeholder="123456789" maxlength="9">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-niss">NISS</label>
                                    <input class="am-inp" type="text" id="add-niss" name="niss" placeholder="12345678901" maxlength="11">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-emergencyContact">Contacto de Emergência</label>
                                    <input class="am-inp" type="text" id="add-emergencyContact" name="emergencyContact" placeholder="Nome: +351 ...">
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="add-address">Morada</label>
                                    <input class="am-inp" type="text" id="add-address" name="address" placeholder="Rua, Número, Cidade, Código Postal">
                                </div>
                            </div>
                        </div>

                        <!-- Contrato -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-file-contract"></i> Contrato</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="add-startDate">Início *</label>
                                    <input class="am-inp" type="date" id="add-startDate" name="startDate" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-endDate">Fim <span class="am-opt">(branco = efectivo)</span></label>
                                    <input class="am-inp" type="date" id="add-endDate" name="endDate">
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-contractType">Tipo</label>
                                    <select class="am-inp am-sel" id="add-contractType" name="contractType">
                                        <option value="">Selecione...</option>
                                        <option value="efetivo">Efetivo</option>
                                        <option value="temporario">Temporário</option>
                                        <option value="part-time">Part-time</option>
                                        <option value="estagio">Estágio</option>
                                        <option value="freelancer">Freelancer</option>
                                    </select>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-vacation-days">Dias de Férias</label>
                                    <input class="am-inp" type="number" id="add-vacation-days" name="vacation_days" value="22" min="0" max="365" placeholder="22">
                                    <span class="am-hint">Mínimo legal PT: 22 dias</span>
                                </div>
                            </div>
                        </div>

                        <!-- Remuneração -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-euro-sign"></i> Remuneração</div>
                            <div class="am-g3">
                                <div class="am-f">
                                    <label class="am-lbl" for="add-salary_base">Salário Base</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico">€</span>
                                        <input class="am-inp am-inp-ico" type="number" id="add-salary_base" name="salary_base" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-subsidio_alimentacao">Sub. Alimentação</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico"><i class="fas fa-utensils"></i></span>
                                        <input class="am-inp am-inp-ico" type="number" id="add-subsidio_alimentacao" name="subsidio_alimentacao" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="add-bonus">Bónus</label>
                                    <div class="am-ico-wrap">
                                        <span class="am-ico"><i class="fas fa-gift"></i></span>
                                        <input class="am-inp am-inp-ico" type="number" id="add-bonus" name="bonus" placeholder="0.00" min="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Acesso -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-shield-alt"></i> Acesso</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="add-pin">PIN <span class="am-opt">(opcional)</span></label>
                                    <input class="am-inp" type="password" id="add-pin" name="pin" placeholder="Mínimo 4 dígitos" minlength="4">
                                    <span class="am-hint">Deixe em branco para não definir PIN</span>
                                </div>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel close-btn-add">Cancelar</button>
                            <button type="submit" class="am-btn-submit">
                                <i class="fas fa-user-plus"></i> Registar Funcionário
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Modal para Visualizar Detalhes do Funcionário -->
            <div id="viewEmployeeModal" class="modal" style="display:none;">
                <div class="am-sheet vm-sheet">

                    <button class="am-close close-btn-view" type="button" aria-label="Fechar">&times;</button>

                    <!-- Hero: avatar + nome + posição + estado -->
                    <div class="vm-hero">
                        <div id="view-avatar" class="vm-hero-av">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="vm-hero-info">
                            <h2 class="vm-hero-name" id="view-name"></h2>
                            <p class="vm-hero-pos">
                                <span id="view-position"></span>
                                <span id="vm-dept-sep"> &bull; </span>
                                <span id="view-department"></span>
                            </p>
                            <div id="view-status"></div>
                        </div>
                    </div>

                    <div id="employeeDetailsContent">

                        <!-- Básicas -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl"><i class="fas fa-id-card"></i> Contacto</div>
                            <div class="vm-g2">
                                <div>
                                    <div class="vm-field-label">Email</div>
                                    <div class="vm-field-value" id="view-email"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Telefone</div>
                                    <div class="vm-field-value" id="view-phone"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Pessoal -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl"><i class="fas fa-user"></i> Informações Pessoais</div>
                            <div class="vm-g2">
                                <div>
                                    <div class="vm-field-label">Data de Nascimento</div>
                                    <div class="vm-field-value" id="view-birthDate"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">NIF</div>
                                    <div class="vm-field-value" id="view-nif"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">NISS</div>
                                    <div class="vm-field-value" id="view-niss"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Contacto de Emergência</div>
                                    <div class="vm-field-value" id="view-emergencyContact"></div>
                                </div>
                                <div class="vm-full">
                                    <div class="vm-field-label">Morada</div>
                                    <div class="vm-field-value" id="view-address"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Contrato -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl"><i class="fas fa-file-contract"></i> Contrato</div>
                            <div class="vm-g2">
                                <div>
                                    <div class="vm-field-label">Início</div>
                                    <div class="vm-field-value" id="view-startDate"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Fim de Contrato</div>
                                    <div class="vm-field-value" id="view-endDate"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Tipo</div>
                                    <div class="vm-field-value" id="view-contractType"></div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Dias de Férias</div>
                                    <div class="vm-field-value" id="view-vacation-days"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Turno & Ponto -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl" style="color:#10b981;">
                                <i class="fas fa-clock" style="color:#10b981;"></i> Turno &amp; Ponto
                            </div>
                            <div class="vm-g3">
                                <div>
                                    <div class="vm-field-label">Turno Atual</div>
                                    <div class="vm-field-value" id="view-turno-atual">—</div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Horário</div>
                                    <div class="vm-field-value" id="view-turno-horario">—</div>
                                </div>
                                <div>
                                    <div class="vm-field-label">Status Turno</div>
                                    <div class="vm-field-value" id="view-turno-status">—</div>
                                </div>
                            </div>
                            <div class="vm-ponto-box">
                                <div class="vm-sec-lbl" style="border:none;padding:0;margin-bottom:8px;color:#10b981;">
                                    <i class="fas fa-stamp" style="color:#10b981;"></i> Último Registo de Ponto
                                </div>
                                <div class="vm-g3">
                                    <div>
                                        <div class="vm-field-label">Data</div>
                                        <div class="vm-field-value" id="view-ponto-data">—</div>
                                    </div>
                                    <div>
                                        <div class="vm-field-label">Entrada</div>
                                        <div class="vm-field-value" id="view-ponto-entrada" style="color:#10b981;font-weight:600;">—</div>
                                    </div>
                                    <div>
                                        <div class="vm-field-label">Saída</div>
                                        <div class="vm-field-value" id="view-ponto-saida" style="color:#ef4444;font-weight:600;">—</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Histórico -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl" style="color:#8b5cf6;">
                                <i class="fas fa-scroll" style="color:#8b5cf6;"></i> Histórico Individual
                            </div>
                            <div id="view-employee-history" class="vm-history-box">
                                <div style="color:#64748b;font-size:.85rem;">Sem histórico disponível.</div>
                            </div>
                        </div>

                        <!-- Documentos -->
                        <div class="vm-section">
                            <div class="vm-sec-lbl" style="justify-content:space-between;">
                                <span style="display:flex;align-items:center;gap:6px;">
                                    <i class="fas fa-paperclip"></i> Documentos
                                </span>
                                <button onclick="openUploadDocumentModal()" type="button"
                                    class="am-btn-submit" style="padding:5px 12px;font-size:.76rem;margin-left:auto;background:linear-gradient(135deg,#10b981,#059669);box-shadow:none;">
                                    <i class="fas fa-upload"></i> Anexar
                                </button>
                            </div>
                            <div id="view-documents-checklist" class="vm-checklist">
                                Checklist documental será carregado com os documentos do funcionário.
                            </div>
                            <div id="view-documents-list" class="vm-docs-list">
                                <p style="color:#64748b;font-size:.85rem;text-align:center;padding:16px;">
                                    Selecione um funcionário para ver os documentos.</p>
                            </div>
                        </div>

                    </div>
                    </div>

                    <div class="am-footer">
                        <button onclick="downloadEmployeePDF()" type="button" class="vm-btn-pdf">
                            <i class="fas fa-file-pdf"></i> Baixar PDF
                        </button>
                        <button type="button" onclick="closeViewEmployeeModal(event)" class="am-btn-submit">
                            <i class="fas fa-times"></i> Fechar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Upload Documento -->
            <div id="uploadDocumentModal" class="modal" style="display:none;">
                <div class="am-sheet" style="max-width:520px;">

                    <button class="am-close close-btn-upload-doc" type="button" aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 6px 16px rgba(5,150,105,.35);">
                            <i class="fas fa-file-upload"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Anexar Documento</h2>
                            <p class="am-subtitle">Associe um ficheiro ao perfil do funcionário</p>
                        </div>
                    </div>

                    <form id="uploadDocumentForm" enctype="multipart/form-data">
                        <input type="hidden" id="upload-employee-id" name="employee_id">

                        <!-- Tipo -->
                        <div class="am-section">
                            <div class="am-f">
                                <label class="am-lbl" for="upload-document-type">Tipo de Documento *</label>
                                <select class="am-inp am-sel" id="upload-document-type" name="document_type" required>
                                    <option value="">Selecione o tipo...</option>
                                    <option value="Contrato">Contrato</option>
                                    <option value="Certidão">Certidão</option>
                                    <option value="Identificação">Identificação (BI/CC)</option>
                                    <option value="Comprovativo Morada">Comprovativo de Morada</option>
                                    <option value="Outros">Outros</option>
                                </select>
                            </div>
                        </div>

                        <!-- Ficheiro -->
                        <div class="am-section">
                            <label class="am-lbl">Ficheiro *</label>
                            <label class="udm-dropzone" for="upload-document-file" id="udmDropzone">
                                <div class="udm-dz-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                <span class="udm-dz-title" id="udm-file-name">Clique para escolher ou arraste aqui</span>
                                <span class="udm-dz-hint">PDF, DOC, DOCX, JPG, PNG, XLS &mdash; máx. 5 MB</span>
                            </label>
                            <input type="file" id="upload-document-file" name="document"
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt,.xls,.xlsx" required style="display:none;">
                        </div>

                        <!-- Descrição -->
                        <div class="am-section">
                            <div class="am-f">
                                <label class="am-lbl" for="upload-description">Descrição <span class="am-opt">(opcional)</span></label>
                                <textarea class="am-inp" id="upload-description" name="description" rows="3"
                                    placeholder="Observações sobre o documento..." style="resize:vertical;"></textarea>
                            </div>
                        </div>

                        <!-- Validade -->
                        <div class="am-section">
                            <div class="am-f">
                                <label class="am-lbl" for="upload-expiry-date">
                                    Data de Validade <span class="am-opt">(opcional)</span>
                                </label>
                                <input class="am-inp" type="date" id="upload-expiry-date" name="expiry_date">
                                <span class="am-hint"><i class="fas fa-bell" style="color:#f59e0b;font-size:.7rem;"></i> Receberá alertas automáticos antes da expiração</span>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel close-btn-upload-doc">Cancelar</button>
                            <button type="submit" class="am-btn-submit" style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 4px 14px rgba(5,150,105,.3);">
                                <i class="fas fa-upload"></i> Enviar Documento
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal: Marcar Férias em Lote -->
            <div id="bulkVacationModal" class="modal" style="display: none; overflow-y:auto; padding:24px 16px 48px;">
                <div class="am-sheet" style="max-width:480px;">
                    <button class="am-close" type="button" aria-label="Fechar"
                        onclick="document.getElementById('bulkVacationModal').style.display='none'">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);box-shadow:0 6px 16px rgba(2,132,199,.35);">
                            <i class="fas fa-umbrella-beach"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Marcar Férias em Lote</h2>
                            <p class="am-subtitle">Aplica o período de férias aos funcionários selecionados</p>
                        </div>
                    </div>

                    <form id="bulkVacationForm">
                        <div class="am-section">
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="vacationStartDate">Data Início *</label>
                                    <input type="date" id="vacationStartDate" name="start_date" class="am-inp" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="vacationEndDate">Data Fim *</label>
                                    <input type="date" id="vacationEndDate" name="end_date" class="am-inp" required>
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="vacationNote">Observação</label>
                                    <textarea id="vacationNote" name="note" rows="3" class="am-inp"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel"
                                onclick="document.getElementById('bulkVacationModal').style.display='none'">Cancelar</button>
                            <button type="submit" class="am-btn-submit">
                                <i class="fas fa-check"></i> Confirmar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal: Alterar Status em Lote -->
            <div id="bulkStatusModal" class="modal" style="display: none; overflow-y:auto; padding:24px 16px 48px;">
                <div class="am-sheet" style="max-width:480px;">
                    <button class="am-close" type="button" aria-label="Fechar"
                        onclick="document.getElementById('bulkStatusModal').style.display='none'">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb);box-shadow:0 6px 16px rgba(37,99,235,.35);">
                            <i class="fas fa-toggle-on"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Alterar Status em Lote</h2>
                            <p class="am-subtitle">Aplica o novo status aos funcionários selecionados</p>
                        </div>
                    </div>

                    <form id="bulkStatusForm">
                        <div class="am-section">
                            <div class="am-g2">
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="bulkNewStatus">Novo Status *</label>
                                    <select id="bulkNewStatus" name="status" class="am-inp am-sel" required>
                                        <option value="">Selecione...</option>
                                        <option value="active">Ativo</option>
                                        <option value="inactive">Inativo</option>
                                        <option value="ferias">Férias</option>
                                    </select>
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl">Razão <span class="am-opt">(opcional)</span></label>
                                    <textarea id="bulkStatusReason" name="reason" rows="2" class="am-inp"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel"
                                onclick="document.getElementById('bulkStatusModal').style.display='none'">Cancelar</button>
                            <button type="submit" class="am-btn-submit">
                                <i class="fas fa-check"></i> Confirmar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal: Alterar Departamento em Lote -->
            <div id="bulkDepartmentModal" class="modal" style="display: none; overflow-y:auto; padding:24px 16px 48px;">
                <div class="am-sheet" style="max-width:480px;">
                    <button class="am-close" type="button" aria-label="Fechar"
                        onclick="document.getElementById('bulkDepartmentModal').style.display='none'">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#64748b,#475569);box-shadow:0 6px 16px rgba(71,85,105,.35);">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Alterar Departamento em Lote</h2>
                            <p class="am-subtitle">Move os funcionários selecionados para outro departamento</p>
                        </div>
                    </div>

                    <form id="bulkDepartmentForm">
                        <div class="am-section">
                            <div class="am-f am-f-full">
                                <label class="am-lbl" for="bulkNewDepartment">Novo Departamento *</label>
                                <input type="text" id="bulkNewDepartment" name="department" class="am-inp" required placeholder="Ex: Marketing">
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel"
                                onclick="document.getElementById('bulkDepartmentModal').style.display='none'">Cancelar</button>
                            <button type="submit" class="am-btn-submit">
                                <i class="fas fa-check"></i> Confirmar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
