<?php
// Secção "Relatórios" — incluída a partir de admin/dashboard.php (depende de $pdo, $employees, $turnosRelatorio, $folhaPagamento, etc. já definidos lá). Inclui, no final, o bloco de montagem de dados dos gráficos que ficava solto depois desta secção.
?>
        <section id="relatorios-section" class="content-section">



            <!-- HEADER -->
            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);box-shadow:0 4px 14px rgba(139,92,246,.35);"><i class="fas fa-chart-bar"></i></div>
                    <div>
                        <h2 class="frhd-title">Relatórios</h2>
                        <p class="frhd-sub">Período <?php echo htmlspecialchars(sprintf('%02d/%04d', (int)$reportMonth, (int)$reportYear)); ?> &middot; <?php echo count($employees); ?> funcionário<?php echo count($employees) !== 1 ? 's' : ''; ?></p>
                    </div>
                </div>
                <a href="optimize_indexes.php" target="_blank" class="frhd-add-btn" style="background:rgba(59,130,246,0.12);color:#3b82f6;border:1px solid rgba(59,130,246,0.25);font-size:.8rem;text-decoration:none;" title="Otimiza índices do banco para relatórios rápidos (executar 1x após 1-2 meses)">
                    <i class="fas fa-bolt"></i> Otimizar Performance
                </a>
            </div>

           

            <!-- NAVEGAÇÃO DOS RELATÓRIOS -->
            <style>
                .rp-nav-strip {
                    display:grid; grid-template-columns:repeat(5,1fr); gap:.75rem; margin-bottom:1.5rem;
                }
                .rp-nav-card {
                    display:flex; align-items:center; gap:.65rem;
                    background:var(--card-bg,#1e293b); border:1px solid rgba(255,255,255,.07);
                    border-radius:14px; padding:.75rem 1rem; cursor:pointer;
                    transition:all .2s; text-align:left; width:100%;
                }
                .rp-nav-card:hover { border-color:rgba(255,255,255,.15); transform:translateY(-1px); }
                .rp-nav-icon {
                    width:38px; height:38px; border-radius:10px; flex-shrink:0;
                    display:grid; place-items:center; font-size:.95rem;
                    transition:background .2s;
                }
                .rp-nav-body { display:flex; flex-direction:column; min-width:0; }
                .rp-nav-val  { font-size:1.3rem; font-weight:800; color:#e2e8f0; line-height:1.1; }
                .rp-nav-lbl  { font-size:.7rem; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.05em; margin-top:2px; }
                /* active per section */
                .rp-nav-resumido.active  { background:rgba(245,158,11,.1); border-color:rgba(245,158,11,.35); }
                .rp-nav-resumido.active .rp-nav-val { color:#fbbf24; }
                .rp-nav-presenca.active  { background:rgba(59,130,246,.1); border-color:rgba(59,130,246,.35); }
                .rp-nav-presenca.active .rp-nav-val { color:#60a5fa; }
                .rp-nav-turnos.active    { background:rgba(167,139,250,.1); border-color:rgba(167,139,250,.35); }
                .rp-nav-turnos.active .rp-nav-val { color:#a78bfa; }
                .rp-nav-gorjetas.active  { background:rgba(34,211,238,.1); border-color:rgba(34,211,238,.35); }
                .rp-nav-gorjetas.active .rp-nav-val { color:#22d3ee; }
                .rp-nav-folha.active     { background:rgba(251,113,133,.1); border-color:rgba(251,113,133,.35); }
                .rp-nav-folha.active .rp-nav-val { color:#fb7185; }
                @media(max-width:960px){ .rp-nav-strip{ grid-template-columns:repeat(3,1fr); } }
                @media(max-width:560px){ .rp-nav-strip{ grid-template-columns:repeat(2,1fr); } }

                .rp-alert-strip { display:flex; flex-wrap:wrap; gap:.6rem; margin-bottom:1.25rem; }
                .rp-alert {
                    display:inline-flex; align-items:center; gap:.45rem;
                    padding:.5rem .9rem; border-radius:10px; font-size:.8rem; font-weight:600;
                    border:1px solid; cursor:pointer; transition:all .18s; white-space:nowrap;
                }
                .rp-alert:hover { filter:brightness(1.18); transform:translateY(-1px); }
                .rp-alert strong { font-size:.95rem; }
                .rp-alert-ok { background:rgba(16,185,129,.1); color:#34d399; border-color:rgba(16,185,129,.3); cursor:default; }
                .rp-alert-ok:hover { filter:none; transform:none; }
            </style>

            <?php
                // Alertas operacionais — pendências que merecem atenção hoje
                $alFuncFaltas = array_filter($employees, function($e) {
                    $f = (int)($e['rel_faltas'] ?? ($e['faltas'] ?? 0));
                    return $f >= 3;
                });
                $alFuncFerias = array_filter($employees, fn($e) => strtolower(trim((string)($e['status'] ?? ''))) === 'ferias');
                $alGorjPendentes = array_filter($gorjetas, fn($g) => strtolower(trim((string)($g['status'] ?? ''))) === 'pendente');
                $alFolhaPendente = array_filter($folhaPagamento, fn($f) => strtolower(trim((string)($f['status'] ?? ''))) !== 'pago');

                $rpAlerts = [];
                if (count($alFuncFaltas) > 0) {
                    $rpAlerts[] = ['icon'=>'fa-user-clock','color'=>'#f87171','bg'=>'rgba(239,68,68,.12)','val'=>count($alFuncFaltas),'lbl'=>'funcionário'.(count($alFuncFaltas)!==1?'s':'').' com faltas elevadas','target'=>'funcionarios-resumido'];
                }
                if (count($alFolhaPendente) > 0) {
                    $rpAlerts[] = ['icon'=>'fa-file-invoice-dollar','color'=>'#fb7185','bg'=>'rgba(251,113,133,.12)','val'=>count($alFolhaPendente),'lbl'=>'pagamento'.(count($alFolhaPendente)!==1?'s':'').' de folha pendente'.(count($alFolhaPendente)!==1?'s':''),'target'=>'folha'];
                }
                if (count($alGorjPendentes) > 0) {
                    $rpAlerts[] = ['icon'=>'fa-hand-holding-usd','color'=>'#fbbf24','bg'=>'rgba(245,158,11,.12)','val'=>count($alGorjPendentes),'lbl'=>'gorjeta'.(count($alGorjPendentes)!==1?'s':'').' pendente'.(count($alGorjPendentes)!==1?'s':''),'target'=>'gorjetas'];
                }
                if (count($alFuncFerias) > 0) {
                    $rpAlerts[] = ['icon'=>'fa-umbrella-beach','color'=>'#60a5fa','bg'=>'rgba(59,130,246,.12)','val'=>count($alFuncFerias),'lbl'=>'funcionário'.(count($alFuncFerias)!==1?'s':'').' de férias','target'=>'funcionarios-resumido'];
                }
            ?>
            <div class="rp-alert-strip">
                <?php if (empty($rpAlerts)): ?>
                    <div class="rp-alert rp-alert-ok"><i class="fas fa-check-circle"></i> Tudo em dia — sem pendências para este período.</div>
                <?php else: foreach ($rpAlerts as $al): ?>
                    <button type="button" class="rp-alert" style="background:<?php echo $al['bg']; ?>;color:<?php echo $al['color']; ?>;border-color:<?php echo $al['color']; ?>55;"
                        onclick="switchRelatorio('<?php echo $al['target']; ?>', document.querySelector('.rp-nav-card[data-relatorio=&quot;<?php echo $al['target']; ?>&quot;]'))">
                        <i class="fas <?php echo $al['icon']; ?>"></i> <strong><?php echo $al['val']; ?></strong> <?php echo htmlspecialchars($al['lbl']); ?>
                    </button>
                <?php endforeach; endif; ?>
            </div>

            <div class="rp-nav-strip">
                <button type="button" class="rp-nav-card rp-nav-resumido relatorio-tab active" data-relatorio="funcionarios-resumido" onclick="switchRelatorio('funcionarios-resumido',this)">
                    <div class="rp-nav-icon" style="background:rgba(245,158,11,.15);color:#f59e0b;"><i class="fas fa-chart-bar"></i></div>
                    <div class="rp-nav-body">
                        <span class="rp-nav-val"><?php echo count($employees); ?></span>
                        <span class="rp-nav-lbl">Resumido</span>
                    </div>
                </button>
                <button type="button" class="rp-nav-card rp-nav-presenca relatorio-tab" data-relatorio="presenca" onclick="switchRelatorio('presenca',this)">
                    <div class="rp-nav-icon" style="background:rgba(59,130,246,.15);color:#60a5fa;"><i class="fas fa-calendar-check"></i></div>
                    <div class="rp-nav-body">
                        <span class="rp-nav-val"><?php echo count($presencas); ?></span>
                        <span class="rp-nav-lbl">Presenças</span>
                    </div>
                </button>
                <button type="button" class="rp-nav-card rp-nav-turnos relatorio-tab" data-relatorio="turnos" onclick="switchRelatorio('turnos',this)">
                    <div class="rp-nav-icon" style="background:rgba(167,139,250,.15);color:#a78bfa;"><i class="fas fa-clock"></i></div>
                    <div class="rp-nav-body">
                        <span class="rp-nav-val"><?php echo count($turnosRelatorio); ?></span>
                        <span class="rp-nav-lbl">Turnos</span>
                    </div>
                </button>
                <button type="button" class="rp-nav-card rp-nav-gorjetas relatorio-tab" data-relatorio="gorjetas" onclick="switchRelatorio('gorjetas',this)">
                    <div class="rp-nav-icon" style="background:rgba(34,211,238,.15);color:#22d3ee;"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="rp-nav-body">
                        <span class="rp-nav-val"><?php echo count($gorjetas); ?></span>
                        <span class="rp-nav-lbl">Gorjetas</span>
                    </div>
                </button>
                <button type="button" class="rp-nav-card rp-nav-folha relatorio-tab" data-relatorio="folha" onclick="switchRelatorio('folha',this)">
                    <div class="rp-nav-icon" style="background:rgba(251,113,133,.15);color:#fb7185;"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="rp-nav-body">
                        <span class="rp-nav-val"><?php echo count($folhaPagamento); ?></span>
                        <span class="rp-nav-lbl">Folha</span>
                    </div>
                </button>
            </div>


            <!-- TABELA DE RELATÓRIO RESUMIDO DE FUNCIONÁRIOS -->
            <div class="relatorio-content" id="content-funcionarios-resumido" style="display:block;">
            <div class="data-table fr-table-wrap" id="relatorio-funcionarios-resumido">
                <?php
                    $rTotalFuncs   = count($employees);
                    $rAtivosFuncs  = count(array_filter($employees, fn($e) => strtolower($e['status'] ?? 'ativo') === 'ativo'));
                    $rOutrosFuncs  = $rTotalFuncs - $rAtivosFuncs;
                    $rPctAtivos    = $rTotalFuncs > 0 ? round($rAtivosFuncs / $rTotalFuncs * 100) : 0;
                ?>
                <div class="fr-kpi-strip" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(245,158,11,.14);color:#f59e0b;"><i class="fas fa-users"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rTotalFuncs; ?></span>
                            <span class="fr-kpi-lbl">Total Funcionários</span>
                            <span class="fr-kpi-pct">período <?php echo htmlspecialchars(sprintf('%02d/%04d',(int)$reportMonth,(int)$reportYear)); ?></span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(16,185,129,.14);color:#10b981;"><i class="fas fa-user-check"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rAtivosFuncs; ?></span>
                            <span class="fr-kpi-lbl">Ativos</span>
                            <span class="fr-kpi-pct"><?php echo $rPctAtivos; ?>% do total</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(239,68,68,.12);color:#f87171;"><i class="fas fa-user-slash"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rOutrosFuncs; ?></span>
                            <span class="fr-kpi-lbl">Inativos / Férias</span>
                            <span class="fr-kpi-pct"><?php echo 100 - $rPctAtivos; ?>% do total</span>
                        </div>
                    </div>
                </div>
                <!-- GRÁFICO: Distribuição de Status e Cargos -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:1.5rem;">
                    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;">
                        <canvas id="chartFuncionariosStatus" style="max-height:250px;"></canvas>
                    </div>
                    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;">
                        <canvas id="chartFuncionariosCargos" style="max-height:250px;"></canvas>
                    </div>
                </div>
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="relatorioSearchName" name="filtro_nome" class="fr-search" placeholder="Pesquisar por nome…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="relatorioResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                            <button type="button" class="fr-filter-toggle" id="relatorioFilterToggle"
                                onclick="document.getElementById('relatorioAdvFilters').classList.toggle('fr-adv-open');this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                            </button>
                            <button id="btnExportarFuncionarios" type="button" class="fr-export-btn">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <div class="fr-adv-filters" id="relatorioAdvFilters">
                        <select id="relatorioFilterStatus" name="filtro_status" class="fr-select">
                            <option value="">Todos os status</option>
                            <option value="ativo">Ativo</option>
                            <option value="ferias">Férias</option>
                            <option value="inativo">Inativo</option>
                        </select>
                        <select id="relatorioFilterCargo" class="fr-select">
                            <option value="">Todos os cargos</option>
                        </select>
                        <select id="relatorioFilterDepartamento" class="fr-select">
                            <option value="">Todos os departamentos</option>
                        </select>
                        <input type="date" id="relatorioPeriodoInicio" class="fr-select" title="Data inicial (admissão)">
                        <input type="date" id="relatorioPeriodoFim" class="fr-select" title="Data final (admissão)">
                        <input type="number" id="relatorioFilterTotalMin" class="fr-select" placeholder="Total mín (€)" step="0.01" min="0">
                        <input type="number" id="relatorioFilterTotalMax" class="fr-select" placeholder="Total máx (€)" step="0.01" min="0">
                        <button type="button" class="fr-clear-btn" onclick="['relatorioFilterStatus','relatorioFilterCargo','relatorioFilterDepartamento','relatorioPeriodoInicio','relatorioPeriodoFim','relatorioFilterTotalMin','relatorioFilterTotalMax'].forEach(function(id){document.getElementById(id).value='';});document.getElementById('relatorioSearchName').value='';document.getElementById('relatorioSearchName').dispatchEvent(new Event('input'));">Limpar</button>
                    </div>
                </div>
                <table class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th>Funcionário</th>
                            <th>Cargo</th>
                            <th>Horas</th>
                            <th>Dias Trab.</th>
                            <th>Faltas</th>
                            <th>Base (€)</th>
                            <th>Gorjetas (€)</th>
                            <th>Total (€)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp):
                            $empNome = (string)($emp['name'] ?? '');
                            $empCargo = (string)($emp['role'] ?? ($emp['position'] ?? ''));
                            $empDepartamento = (string)($emp['department'] ?? '');
                            $empStatusRaw = mb_strtolower(trim((string)($emp['status'] ?? '')));
                            $empStatusFilter = match($empStatusRaw) {
                                'active', 'ativo' => 'ativo',
                                'inativo' => 'inativo',
                                'ferias', 'férias' => 'ferias',
                                default => $empStatusRaw
                            };
                            $empStatusLabel = match($empStatusFilter) {
                                'ativo' => 'Ativo',
                                'inativo' => 'Inativo',
                                'ferias' => 'Férias',
                                default => ($empStatusRaw !== '' ? ucfirst($empStatusRaw) : '—')
                            };
                            $empHorasTrabalhadas = (float)($emp['rel_horas_trabalhadas'] ?? ($emp['horas_trabalhadas'] ?? 0));
                            $empDiasTrabalhados = (int)($emp['rel_dias_trabalhados'] ?? 0);
                            $empFaltas = (int)($emp['rel_faltas'] ?? ($emp['faltas'] ?? 0));
                            $empSalarioBase = (float)($emp['rel_salary_base'] ?? ($emp['salary_base'] ?? 0));
                            $empGorjetas = (float)($emp['rel_gorjetas'] ?? ($emp['gorjetas'] ?? 0));
                            $empTotalLiquido = (float)($emp['rel_total_liquido'] ?? ($emp['total_liquido'] ?? 0));
                            // Verificar se Total Líquido é fallback (sem folha processada)
                            $empId = (int)($emp['id'] ?? 0);
                            $folhaEmp = $folhaPorFuncionario[$empId] ?? null;
                            $totalLiquidoIsFallback = empty($folhaEmp) || empty($folhaEmp['salario_liquido']) || (float)($folhaEmp['salario_liquido'] ?? 0) <= 0;
                            $totalLiquidoTooltip = $totalLiquidoIsFallback ? 'Valor estimado (bruto base + gorjetas). Aguarda folha processada.' : 'Valor de folha processada';
                            $empDataAdmissaoIso = '';
                            if (!empty($emp['startDate'])) {
                                $admissaoTs = strtotime((string)$emp['startDate']);
                                if ($admissaoTs !== false) {
                                    $empDataAdmissaoIso = date('Y-m-d', $admissaoTs);
                                }
                            }
                        ?>
                        <tr class="fr-row"
                            data-rel-name="<?php echo htmlspecialchars($empNome, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-cargo="<?php echo htmlspecialchars($empCargo, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-department="<?php echo htmlspecialchars($empDepartamento, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-status="<?php echo htmlspecialchars($empStatusFilter, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-hours="<?php echo htmlspecialchars((string)$empHorasTrabalhadas, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-dias="<?php echo htmlspecialchars((string)$empDiasTrabalhados, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-faltas="<?php echo htmlspecialchars((string)$empFaltas, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-date="<?php echo htmlspecialchars($empDataAdmissaoIso, ENT_QUOTES, 'UTF-8'); ?>"
                            data-rel-total="<?php echo htmlspecialchars((string)$empTotalLiquido, ENT_QUOTES, 'UTF-8'); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">
                                        <?php if (!empty($emp['profile_picture'])): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($emp['profile_picture']); ?>"
                                            alt="<?php echo htmlspecialchars($emp['name']); ?>"
                                            onerror="this.style.display='none'">
                                        <?php else: ?>
                                        <?php echo strtoupper(mb_substr($emp['name'] ?? '?',0,2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo htmlspecialchars($empNome !== '' ? $empNome : '—'); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($empCargo !== '' ? $empCargo : '—'); ?></td>
                            <td><?php echo number_format($empHorasTrabalhadas, 2, ',', '.'); ?></td>
                            <td><?php echo $empDiasTrabalhados; ?></td>
                            <td><?php echo $empFaltas; ?></td>
                            <td>€ <?php echo number_format($empSalarioBase, 2, ',', '.'); ?></td>
                            <td>€ <?php echo number_format($empGorjetas, 2, ',', '.'); ?></td>
                            <td title="<?php echo htmlspecialchars($totalLiquidoTooltip); ?>" style="<?php echo $totalLiquidoIsFallback ? 'background:rgba(251,146,60,0.1);color:#ea580c;font-weight:600;position:relative;' : ''; ?>">
                                € <?php echo number_format($empTotalLiquido,2,',','.'); ?>
                                <?php if ($totalLiquidoIsFallback): ?>
                                    <span style="font-size:0.65rem;margin-left:0.25rem;vertical-align:super;background:#f97316;color:white;padding:0.1rem 0.3rem;border-radius:2px;font-weight:700;">EST.</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($empStatusLabel); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>











            <div class="relatorio-content" id="content-presenca" style="display:none;">
            <div class="data-table fr-table-wrap" id="relatorio-presenca-table">
                <?php
                    $rTotalPresc   = count($presencas);
                    $rPresentes    = count(array_filter($presencas, fn($p) => strtolower($p['status'] ?? '') === 'presente'));
                    $rFaltas       = $rTotalPresc - $rPresentes;
                    $rTaxaPresc    = $rTotalPresc > 0 ? round($rPresentes / $rTotalPresc * 100) : 0;
                ?>
                <div class="fr-kpi-strip" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(59,130,246,.14);color:#60a5fa;"><i class="fas fa-calendar-alt"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rTotalPresc; ?></span>
                            <span class="fr-kpi-lbl">Total Registos</span>
                            <span class="fr-kpi-pct">no período</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(16,185,129,.14);color:#10b981;"><i class="fas fa-user-check"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rPresentes; ?></span>
                            <span class="fr-kpi-lbl">Presenças</span>
                            <span class="fr-kpi-pct"><?php echo $rTaxaPresc; ?>% taxa</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(239,68,68,.12);color:#f87171;"><i class="fas fa-user-times"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rFaltas; ?></span>
                            <span class="fr-kpi-lbl">Faltas / Ausências</span>
                            <span class="fr-kpi-pct"><?php echo 100 - $rTaxaPresc; ?>% do total</span>
                        </div>
                    </div>
                </div>
                <!-- GRÁFICO: Presença vs Faltas -->
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;margin-bottom:1.5rem;height:280px;">
                    <canvas id="chartPresencaStatus" style="max-height:250px;"></canvas>
                </div>
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="presencaSearchInput" class="fr-search" placeholder="Pesquisar funcionário…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="presencaResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                            <button type="button" class="fr-filter-toggle" id="presencaFilterToggle"
                                onclick="document.getElementById('presencaAdvFilters').classList.toggle('fr-adv-open');this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                            </button>
                            <button id="btnExportarPresencas" type="button" class="fr-export-btn">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <div class="fr-adv-filters" id="presencaAdvFilters">
                        <input type="date" id="presencaStartDate" class="fr-select" title="Data inicial">
                        <input type="date" id="presencaEndDate" class="fr-select" title="Data final">
                        <select id="presencaStatusFilter" class="fr-select">
                            <option value="">Todos</option>
                            <option value="presente">Presente</option>
                            <option value="ausente">Ausente</option>
                            <option value="falta">Falta</option>
                        </select>
                        <button type="button" class="fr-clear-btn" onclick="document.getElementById('presencaStartDate').value='';document.getElementById('presencaEndDate').value='';document.getElementById('presencaStatusFilter').value='';document.getElementById('presencaSearchInput').value='';document.getElementById('presencaSearchInput').dispatchEvent(new Event('input'));">Limpar</button>
                    </div>
                </div>
                <style>
                    .prc-date-badge     { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; background:rgba(99,102,241,.1); color:#818cf8; border:1px solid rgba(99,102,241,.22); border-radius:20px; font-size:.75rem; font-weight:600; white-space:nowrap; }
                </style>
                <table class="table fr-table" id="presencaTable">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-emp">Funcionário</th>
                            <th style="white-space:nowrap;">Data</th>
                            <th style="white-space:nowrap;">Horas</th>
                            <th style="white-space:nowrap;">Atraso</th>
                            <th style="white-space:nowrap;">Confirmação</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="presencaTableBody">
                        <?php
                            $_presWeekdayMap = [0=>'dom',1=>'seg',2=>'ter',3=>'qua',4=>'qui',5=>'sex',6=>'sab'];
                        ?>
                        <?php foreach ($presencas as $p):
                            $pNome     = htmlspecialchars($p['name'] ?? 'N/D');
                            $pData     = ($p['data_registro'] ?? '') ? date('d/m/Y', strtotime((string)$p['data_registro'])) : 'N/D';
                            $pDiaSem   = ($p['data_registro'] ?? '') ? ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][(int)date('w', strtotime((string)$p['data_registro']))] : '';
                            $pDataIso  = ($p['data_registro'] ?? '') ? date('Y-m-d', strtotime((string)$p['data_registro'])) : '';
                            $pStatus   = strtolower(trim($p['status'] ?? 'presente'));
                            $pPhoto    = trim((string)($p['profile_picture'] ?? ''));
                            $pInitials = strtoupper(mb_substr($pNome, 0, 2));
                            $pEmpId    = (int)($p['funcionario_id'] ?? 0);

                            $statusClass = match($pStatus) {
                                'presente' => 'status-presente',
                                'ausente'  => 'status-ausente',
                                'falta'    => 'status-falta',
                                default    => 'status-outro'
                            };
                            $statusLabel = ucfirst($pStatus);

                            // Parse day periods from GROUP_CONCAT ponto_timeline: primeira entrada
                            // (para Atraso) e soma de horas trabalhadas no dia.
                            $tlRaw          = trim((string)($p['ponto_timeline'] ?? ''));
                            $pPrimeiraEnt   = null;
                            $pHorasMin      = 0;
                            if ($tlRaw !== '') {
                                foreach (explode(';;', $tlRaw) as $ti => $period) {
                                    [$hEnt, $hSai, ] = array_pad(explode('|', $period, 3), 3, '');
                                    $hEnt = trim($hEnt);
                                    $hSai = trim($hSai);
                                    if ($ti === 0 && $hEnt !== '') {
                                        $pPrimeiraEnt = substr($hEnt, 0, 5);
                                    }
                                    if ($hEnt !== '' && $hSai !== '' && $pDataIso !== '') {
                                        $ts1 = strtotime($pDataIso . ' ' . $hEnt);
                                        $ts2 = strtotime($pDataIso . ' ' . $hSai);
                                        if ($ts1 !== false && $ts2 !== false) {
                                            if ($ts2 <= $ts1) {
                                                $ts2 += 24 * 60 * 60; // turno noturno
                                            }
                                            $pHorasMin += (int) round(($ts2 - $ts1) / 60);
                                        }
                                    }
                                }
                            }
                            $pHorasLabel = $pHorasMin > 0
                                ? sprintf('%dh%02d', intdiv($pHorasMin, 60), $pHorasMin % 60)
                                : '--:--';

                            // Encontra o turno esperado desse dia (dia da semana + vigência) para
                            // calcular o atraso da primeira entrada, tal como em Assiduidade.
                            $pAtraso = '—';
                            if ($pPrimeiraEnt !== null && $pDataIso !== '') {
                                $pWeekdayToken = $_presWeekdayMap[(int) date('w', strtotime($pDataIso))];
                                $pTurnoMatch = null;
                                foreach ($turnosRelatorio as $tCand) {
                                    if ((int) ($tCand['funcionario_id'] ?? 0) !== $pEmpId) {
                                        continue;
                                    }
                                    if (!in_array(mb_strtolower(trim((string) ($tCand['status'] ?? ''))), ['ativo', 'active'], true)) {
                                        continue;
                                    }
                                    $tDias = parseTurnoDays((string) ($tCand['dias_semana'] ?? ''));
                                    $diaOk = empty($tDias) || in_array($pWeekdayToken, $tDias, true);
                                    $tIni = trim((string) ($tCand['data_inicio'] ?? ''));
                                    $tFim = trim((string) ($tCand['data_fim'] ?? ''));
                                    $vigOk = ($tIni === '' || $tIni === '0000-00-00' || $tIni <= $pDataIso)
                                        && ($tFim === '' || $tFim === '0000-00-00' || $tFim >= $pDataIso);
                                    if ($diaOk && $vigOk) {
                                        $pTurnoMatch = $tCand;
                                        break;
                                    }
                                }
                                if ($pTurnoMatch) {
                                    $horaInicioTurno = substr((string) ($pTurnoMatch['horario_inicio'] ?? ''), 0, 5);
                                    $entradaTs = strtotime($pDataIso . ' ' . $pPrimeiraEnt);
                                    $inicioTs = strtotime($pDataIso . ' ' . $horaInicioTurno);
                                    if ($entradaTs !== false && $inicioTs !== false) {
                                        $diffMin = (int) floor(($entradaTs - $inicioTs) / 60) - max(0, (int) ($estHorario['tolerancia_atraso_min'] ?? 0));
                                        $pAtraso = $diffMin > 0 ? ('Atrasado (+' . $diffMin . ' min)') : 'Pontual';
                                    }
                                }
                            }

                            $pPendentes = (int) ($p['pendentes_count'] ?? 0);
                            $pPeriodos  = (int) ($p['periodos_count'] ?? 0);
                            $pConfirmacao = $pPeriodos === 0 ? '-' : ($pPendentes > 0 ? 'Pendente' : 'Confirmado');
                        ?>
                        <tr class="fr-row"
                            data-presenca-nome="<?php echo mb_strtolower($pNome); ?>"
                            data-presenca-status="<?php echo htmlspecialchars($pStatus); ?>"
                            data-presenca-date="<?php echo htmlspecialchars($pDataIso); ?>">

                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);">
                                        <?php if ($pPhoto !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($pPhoto); ?>" alt="Avatar">
                                        <?php else: ?>
                                        <?php echo $pInitials; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo $pNome; ?></span>
                                        <span class="fr-emp-email"><?php echo htmlspecialchars($pDiaSem); ?></span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="prc-date-badge">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo $pData; ?>
                                </span>
                            </td>

                            <td><?php echo htmlspecialchars($pHorasLabel); ?></td>

                            <td style="<?php echo str_starts_with($pAtraso, 'Atrasado') ? 'color:#f87171;' : ($pAtraso === 'Pontual' ? 'color:#4ade80;' : ''); ?>">
                                <?php echo htmlspecialchars($pAtraso); ?>
                            </td>

                            <td><?php echo htmlspecialchars($pConfirmacao); ?></td>

                            <td>
                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>







            </div>

            <div class="relatorio-content" id="content-turnos" style="display:none;">
            <div class="data-table fr-table-wrap" id="relatorio-turnos-table">
                <?php
                    $rTurnosTotal  = count($turnosRelatorio);
                    $rTurnosAtivos = count(array_filter($turnosRelatorio, fn($t) => strtolower($t['status'] ?? 'ativo') === 'ativo'));
                    $rTurnosTipos  = count(array_unique(array_filter(array_map(fn($t) => $t['turno_tipo'] ?? '', $turnosRelatorio))));
                ?>
                <div class="fr-kpi-strip" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(167,139,250,.14);color:#a78bfa;"><i class="fas fa-clock"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rTurnosTotal; ?></span>
                            <span class="fr-kpi-lbl">Atribuições</span>
                            <span class="fr-kpi-pct">total de registos</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(16,185,129,.14);color:#10b981;"><i class="fas fa-check-circle"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rTurnosAtivos; ?></span>
                            <span class="fr-kpi-lbl">Ativos</span>
                            <span class="fr-kpi-pct"><?php echo $rTurnosTotal > 0 ? round($rTurnosAtivos/$rTurnosTotal*100) : 0; ?>% do total</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(139,92,246,.14);color:#c4b5fd;"><i class="fas fa-layer-group"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rTurnosTipos; ?></span>
                            <span class="fr-kpi-lbl">Tipos de Turno</span>
                            <span class="fr-kpi-pct">distintos</span>
                        </div>
                    </div>
                </div>
                <!-- GRÁFICO: Distribuição de Funcionários por Turno -->
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;margin-bottom:1.5rem;height:280px;">
                    <canvas id="chartTurnosDistribuicao" style="max-height:250px;"></canvas>
                </div>
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="turnosSearchInput" class="fr-search" placeholder="Pesquisar turno…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="turnosResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                            <button type="button" class="fr-filter-toggle" id="relTurnosFilterToggle"
                                onclick="document.getElementById('relTurnosAdvFilters').classList.toggle('fr-adv-open');this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                            </button>
                            <button id="btnExportarTurnos" type="button" class="fr-export-btn">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <div class="fr-adv-filters" id="relTurnosAdvFilters">
                        <input type="date" id="turnosStartDate" class="fr-select" title="Data inicial">
                        <input type="date" id="turnosEndDate" class="fr-select" title="Data final">
                        <select id="turnosStatusFilter" class="fr-select">
                            <option value="">Todos</option>
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                        <button type="button" class="fr-clear-btn" onclick="document.getElementById('turnosStartDate').value='';document.getElementById('turnosEndDate').value='';document.getElementById('turnosStatusFilter').value='';document.getElementById('turnosSearchInput').value='';document.getElementById('turnosSearchInput').dispatchEvent(new Event('input'));">Limpar</button>
                    </div>
                </div>
                <table class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th>Funcionário</th>
                            <th>Turno</th>
                            <th>Horário</th>
                            <th>Dias</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($turnosRelatorio as $t):
                            $tNome = htmlspecialchars($t['name'] ?? 'N/D');
                            $tTipo = htmlspecialchars($t['turno_tipo'] ?? 'N/D');
                            $tHorario = date('H:i', strtotime($t['horario_inicio'])) . ' - ' . date('H:i', strtotime($t['horario_fim']));
                            $tDias = htmlspecialchars($t['dias_semana'] ?? 'N/D');
                            $tDataIso = '';
                            if (!empty($t['data_inicio'])) {
                                $tDataTs = strtotime((string)$t['data_inicio']);
                                if ($tDataTs !== false) {
                                    $tDataIso = date('Y-m-d', $tDataTs);
                                }
                            } elseif (!empty($t['created_at'])) {
                                $tCreatedTs = strtotime((string)$t['created_at']);
                                if ($tCreatedTs !== false) {
                                    $tDataIso = date('Y-m-d', $tCreatedTs);
                                }
                            }
                            $tStatus = strtolower(trim($t['status'] ?? 'ativo'));
                            $tEmpId = (int)($t['funcionario_id'] ?? 0);
                            $tEmpInfo = null;
                            foreach ($employees as $emp) {
                                if ($emp['id'] == $tEmpId) {
                                    $tEmpInfo = $emp;
                                    break;
                                }
                            }
                            $statusClass = match($tStatus) {
                                'ativo' => 'status-presente',
                                'inativo' => 'status-nao-marcado',
                                default => 'status-outro'
                            };
                        ?>
                        <tr class="fr-row" data-turno-nome="<?php echo mb_strtolower($tNome); ?>" data-turno-tipo="<?php echo mb_strtolower($tTipo); ?>" data-turno-status="<?php echo htmlspecialchars($tStatus); ?>" data-turno-date="<?php echo htmlspecialchars($tDataIso); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">
                                        <?php if ($tEmpInfo && !empty($tEmpInfo['profile_picture'])): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($tEmpInfo['profile_picture']); ?>" alt="Avatar">
                                        <?php else: ?>
                                        <?php echo strtoupper(mb_substr($tNome, 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo $tNome; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $tTipo; ?></td>
                            <td><?php echo $tHorario; ?></td>
                            <td><?php echo $tDias; ?></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($tStatus); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>

            <div class="relatorio-content" id="content-gorjetas" style="display:none;">
            <div class="data-table fr-table-wrap" id="relatorio-gorjetas-table">
                <?php
                    $rGorjTotalRecs = count($gorjetas);
                    $rGorjTotal     = array_sum(array_column($gorjetas, 'valor'));
                    $rGorjPend      = count(array_filter($gorjetas, fn($g) => strtolower($g['status'] ?? '') === 'pendente'));
                ?>
                <div class="fr-kpi-strip" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(34,211,238,.14);color:#22d3ee;"><i class="fas fa-hand-holding-usd"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rGorjTotalRecs; ?></span>
                            <span class="fr-kpi-lbl">Total Registos</span>
                            <span class="fr-kpi-pct">gorjetas lançadas</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(6,182,212,.14);color:#06b6d4;"><i class="fas fa-euro-sign"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val" style="font-size:1.1rem;">€ <?php echo number_format((float)$rGorjTotal, 2, ',', '.'); ?></span>
                            <span class="fr-kpi-lbl">Total (€)</span>
                            <span class="fr-kpi-pct">soma do período</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(245,158,11,.14);color:#fbbf24;"><i class="fas fa-clock"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rGorjPend; ?></span>
                            <span class="fr-kpi-lbl">Pendentes</span>
                            <span class="fr-kpi-pct">por processar</span>
                        </div>
                    </div>
                </div>
                <!-- GRÁFICO: Top Gorjetas por Funcionário -->
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;margin-bottom:1.5rem;height:280px;">
                    <canvas id="chartGorjetasTop" style="max-height:250px;"></canvas>
                </div>
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="gorjetasSearchInput" class="fr-search" placeholder="Pesquisar funcionário…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="gorjetasResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                            <button type="button" class="fr-filter-toggle" id="relGorjetasFilterToggle"
                                onclick="document.getElementById('relGorjetasAdvFilters').classList.toggle('fr-adv-open');this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                            </button>
                            <button id="btnExportarGorjetas" type="button" class="fr-export-btn">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <div class="fr-adv-filters" id="relGorjetasAdvFilters">
                        <input type="date" id="gorjetasStartDate" class="fr-select" title="Data inicial">
                        <input type="date" id="gorjetasEndDate" class="fr-select" title="Data final">
                        <select id="gorjetasStatusFilter" class="fr-select">
                            <option value="">Todos</option>
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                            <option value="cancelado">Cancelado</option>
                            <option value="rejeitado">Rejeitado</option>
                        </select>
                        <button type="button" class="fr-clear-btn" onclick="document.getElementById('gorjetasStartDate').value='';document.getElementById('gorjetasEndDate').value='';document.getElementById('gorjetasStatusFilter').value='';document.getElementById('gorjetasSearchInput').value='';document.getElementById('gorjetasSearchInput').dispatchEvent(new Event('input'));">Limpar</button>
                    </div>
                </div>
                <table class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th>Funcionário</th>
                            <th>Valor (€)</th>
                            <th>Data</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gorjetas as $g):
                            $gNome = htmlspecialchars($g['name'] ?? 'N/D');
                            $gValor = number_format((float)($g['valor'] ?? 0), 2, ',', '.');
                            $gData = ($g['data'] ?? '') && $g['data'] !== '0000-00-00' ? date('d/m/Y', strtotime($g['data'])) : 'N/D';
                            $gDataIso = ($g['data'] ?? '') && $g['data'] !== '0000-00-00' ? date('Y-m-d', strtotime((string)$g['data'])) : '';
                            $gStatus = strtolower(trim($g['status'] ?? 'pendente'));
                            $gEmpId = (int)($g['funcionario_id'] ?? 0);
                            $gEmpInfo = null;
                            foreach ($employees as $emp) {
                                if ($emp['id'] == $gEmpId) {
                                    $gEmpInfo = $emp;
                                    break;
                                }
                            }
                            $statusClass = match($gStatus) {
                                'pago' => 'status-presente',
                                'pendente' => 'status-warning',
                                'cancelado' => 'status-nao-marcado',
                                'rejeitado' => 'status-falta',
                                default => 'status-outro'
                            };
                        ?>
                        <tr class="fr-row" data-gorjeta-nome="<?php echo mb_strtolower($gNome); ?>" data-gorjeta-status="<?php echo htmlspecialchars($gStatus); ?>" data-gorjeta-date="<?php echo htmlspecialchars($gDataIso); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#06b6d4,#0284c7);">
                                        <?php if ($gEmpInfo && !empty($gEmpInfo['profile_picture'])): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($gEmpInfo['profile_picture']); ?>" alt="Avatar">
                                        <?php else: ?>
                                        <?php echo strtoupper(mb_substr($gNome, 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo $gNome; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>€ <?php echo $gValor; ?></td>
                            <td><?php echo $gData; ?></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($gStatus); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            </div>
            </div>

            <div class="relatorio-content" id="content-folha" style="display:none;">
            <div class="data-table fr-table-wrap" id="relatorio-folha-table">
                <?php
                    $rFolhaTotalRecs  = count($folhaPagamento);
                    $rFolhaBrutoSum   = array_sum(array_column($folhaPagamento, 'salary_bruto'));
                    $rFolhaLiquidoSum = array_sum(array_column($folhaPagamento, 'salary_liquido'));
                ?>
                <div class="fr-kpi-strip" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(251,113,133,.14);color:#fb7185;"><i class="fas fa-users"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val"><?php echo $rFolhaTotalRecs; ?></span>
                            <span class="fr-kpi-lbl">Funcionários</span>
                            <span class="fr-kpi-pct">na folha</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(239,68,68,.14);color:#f87171;"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val" style="font-size:1.05rem;">€ <?php echo number_format((float)$rFolhaBrutoSum, 2, ',', '.'); ?></span>
                            <span class="fr-kpi-lbl">Total Bruto</span>
                            <span class="fr-kpi-pct">custo total</span>
                        </div>
                    </div>
                    <div class="fr-kpi">
                        <div class="fr-kpi-icon" style="background:rgba(16,185,129,.14);color:#4ade80;"><i class="fas fa-wallet"></i></div>
                        <div class="fr-kpi-body">
                            <span class="fr-kpi-val" style="font-size:1.05rem;">€ <?php echo number_format((float)$rFolhaLiquidoSum, 2, ',', '.'); ?></span>
                            <span class="fr-kpi-lbl">Total Líquido</span>
                            <span class="fr-kpi-pct">a pagar</span>
                        </div>
                    </div>
                </div>
                <!-- GRÁFICO: Custos Salariais -->
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;margin-bottom:1.5rem;height:280px;">
                    <canvas id="chartFolhaCustos" style="max-height:250px;"></canvas>
                </div>
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="folhaSearchInput" class="fr-search" placeholder="Pesquisar funcionário…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="folhaResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                            <button type="button" class="fr-filter-toggle" id="relFolhaFilterToggle"
                                onclick="document.getElementById('relFolhaAdvFilters').classList.toggle('fr-adv-open');this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                            </button>
                            <button id="btnExportarFolha" type="button" class="fr-export-btn">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <div class="fr-adv-filters" id="relFolhaAdvFilters">
                        <input type="date" id="folhaStartDate" class="fr-select" title="Data inicial">
                        <input type="date" id="folhaEndDate" class="fr-select" title="Data final">
                        <button type="button" class="fr-clear-btn" onclick="document.getElementById('folhaStartDate').value='';document.getElementById('folhaEndDate').value='';document.getElementById('folhaSearchInput').value='';document.getElementById('folhaSearchInput').dispatchEvent(new Event('input'));">Limpar</button>
                    </div>
                </div>
                <table class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th>Funcionário</th>
                            <th>Bruto (€)</th>
                            <th>Líquido (€)</th>
                            <th>Período</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $currentMonth = date('n'); $currentYear = date('Y'); $mesesPt = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];
                        foreach ($folhaPagamento as $f):
                            $fNome = htmlspecialchars($f['name'] ?? 'N/D');
                            $fBruto = number_format((float)($f['salary_bruto'] ?? 0), 2, ',', '.');
                            $fLiquido = number_format((float)($f['salary_liquido'] ?? 0), 2, ',', '.');
                            $fMes = $mesesPt[(int)($f['fiscal_month'] ?? 1)] . ' ' . ($f['fiscal_year'] ?? $currentYear);
                            $fDataIso = sprintf('%04d-%02d-01', (int)($f['fiscal_year'] ?? $currentYear), (int)($f['fiscal_month'] ?? 1));
                            $fEmpId = (int)($f['employee_id'] ?? 0);
                            $fEmpInfo = null;
                            foreach ($employees as $emp) {
                                if ($emp['id'] == $fEmpId) {
                                    $fEmpInfo = $emp;
                                    break;
                                }
                            }
                        ?>
                        <tr class="fr-row" data-folha-nome="<?php echo mb_strtolower($fNome); ?>" data-folha-date="<?php echo htmlspecialchars($fDataIso); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                                        <?php if ($fEmpInfo && !empty($fEmpInfo['profile_picture'])): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($fEmpInfo['profile_picture']); ?>" alt="Avatar">
                                        <?php else: ?>
                                        <?php echo strtoupper(mb_substr($fNome, 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo $fNome; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>€ <?php echo $fBruto; ?></td>
                            <td>€ <?php echo $fLiquido; ?></td>
                            <td><?php echo $fMes; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>

            <div class="relatorio-content" id="content-ferias" style="display:none;">
            <div class="data-table fr-table-wrap" id="relatorio-ferias-table">
                <table class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th>Funcionário</th>
                            <th>Período</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($employees as $emp) {
                            $status = mb_strtolower(trim((string)($emp['status'] ?? '')));
                            if ($status === 'ferias' || $status === 'férias') {
                                $nome = htmlspecialchars($emp['name'] ?? '—');
                                $periodo = '—';
                                $statusLabel = 'Em Férias';
                                $initials = strtoupper(mb_substr($emp['name'] ?? '?', 0, 2));
                                echo '<tr class="fr-row">';
                                echo '<td class="fr-td-emp">';
                                echo '<div class="fr-emp-cell">';
                                echo '<div class="fr-av" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);">';
                                if (!empty($emp['profile_picture'])) {
                                    echo '<img class="fr-av-img" src="' . htmlspecialchars($emp['profile_picture']) . '" alt="Avatar">';
                                } else {
                                    echo $initials;
                                }
                                echo '</div>';
                                echo '<div class="fr-emp-info"><span class="fr-emp-name">' . $nome . '</span></div>';
                                echo '</div>';
                                echo '</td>';
                                echo '<td>' . $periodo . '</td>';
                                echo '<td><span class="status-badge status-ferias"><i class="fas fa-umbrella-beach"></i> ' . $statusLabel . '</span></td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            </div>

        </section>

        <script>
        // Dados para os gráficos dos relatórios
        document.addEventListener('DOMContentLoaded', function() {
            const colors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#00f2fe', '#43e97b', '#fa709a', '#fee140'];
            const chartsData = {};

            // 1. FUNCIONÁRIOS - Status Distribution
            const funcionariosStatus = <?php
                $statusCount = [];
                foreach ($employees as $emp) {
                    $status = strtolower($emp['status'] ?? 'ativo');
                    $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;
                }
                echo json_encode($statusCount);
            ?>;
            
            if (document.getElementById('chartFuncionariosStatus')) {
                const ctx1 = document.getElementById('chartFuncionariosStatus').getContext('2d');
                new Chart(ctx1, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(funcionariosStatus).map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                        datasets: [{
                            data: Object.values(funcionariosStatus),
                            backgroundColor: colors.slice(0, Object.keys(funcionariosStatus).length),
                            borderColor: '#1e293b',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: '#e2e8f0', font: { size: 12 } } },
                            title: { display: true, text: 'Distribuição de Status', color: '#e2e8f0' }
                        }
                    }
                });
            }

            // 2. FUNCIONÁRIOS - Cargo Distribution
            const funcionariosCargos = <?php
                $cargoCount = [];
                foreach ($employees as $emp) {
                    $cargo = $emp['role'] ?? ($emp['position'] ?? 'Sem Cargo');
                    $cargoCount[$cargo] = ($cargoCount[$cargo] ?? 0) + 1;
                }
                // Limitar a top 8 cargos
                arsort($cargoCount);
                $cargoCount = array_slice($cargoCount, 0, 8, true);
                echo json_encode($cargoCount);
            ?>;
            
            if (document.getElementById('chartFuncionariosCargos')) {
                const ctx2 = document.getElementById('chartFuncionariosCargos').getContext('2d');
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(funcionariosCargos),
                        datasets: [{
                            label: 'Funcionários',
                            data: Object.values(funcionariosCargos),
                            backgroundColor: colors[0],
                            borderColor: '#667eea',
                            borderWidth: 2,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#e2e8f0' } },
                            title: { display: true, text: 'Distribuição por Cargo', color: '#e2e8f0' }
                        },
                        scales: {
                            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        }
                    }
                });
            }

            // 3. PRESENÇA - Status Distribution (Últimos 7 dias)
            const presencaStatus = <?php
                $statusPresenca = ['presente' => 0, 'ausente' => 0, 'falta' => 0];
                $hoje = new DateTime();
                $umaSemanaAtras = clone $hoje;
                $umaSemanaAtras->modify('-7 days');
                
                foreach ($presencas as $p) {
                    $status = strtolower($p['status'] ?? 'presente');
                    if (isset($statusPresenca[$status])) {
                        $dataRegistro = $p['data_registro'] ?? date('Y-m-d');
                        $dataObj = new DateTime($dataRegistro);
                        if ($dataObj >= $umaSemanaAtras && $dataObj <= $hoje) {
                            $statusPresenca[$status]++;
                        }
                    }
                }
                echo json_encode($statusPresenca);
            ?>;
            
            if (document.getElementById('chartPresencaStatus')) {
                const ctx3 = document.getElementById('chartPresencaStatus').getContext('2d');
                new Chart(ctx3, {
                    type: 'bar',
                    data: {
                        labels: ['Presente', 'Ausente', 'Falta'],
                        datasets: [{
                            label: 'Presenças (7 dias)',
                            data: [presencaStatus.presente ?? 0, presencaStatus.ausente ?? 0, presencaStatus.falta ?? 0],
                            backgroundColor: ['#43e97b', '#fa709a', '#fee140'],
                            borderColor: ['#43e97b', '#fa709a', '#fee140'],
                            borderWidth: 2,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#e2e8f0' } },
                            title: { display: true, text: 'Presenças vs Faltas (7 dias)', color: '#e2e8f0' }
                        },
                        scales: {
                            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        }
                    }
                });
            }

            // 4. TURNOS - Distribution
            const turnosDistribuicao = <?php
                $turnoCount = [];
                foreach ($turnosRelatorio as $t) {
                    $turno = $t['turno_tipo'] ?? 'Sem Turno';
                    $turnoCount[$turno] = ($turnoCount[$turno] ?? 0) + 1;
                }
                echo json_encode($turnoCount);
            ?>;
            
            if (document.getElementById('chartTurnosDistribuicao')) {
                const ctx4 = document.getElementById('chartTurnosDistribuicao').getContext('2d');
                new Chart(ctx4, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(turnosDistribuicao),
                        datasets: [{
                            label: 'Funcionários por Turno',
                            data: Object.values(turnosDistribuicao),
                            backgroundColor: colors[1],
                            borderColor: '#764ba2',
                            borderWidth: 2,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#e2e8f0' } },
                            title: { display: true, text: 'Distribuição por Turno', color: '#e2e8f0' }
                        },
                        scales: {
                            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        }
                    }
                });
            }

            // 6. GORJETAS - Top Gorjetas por Funcionário
            const gorjetasTop = <?php
                $gorjetasPorFunc = [];
                foreach ($gorjetas as $g) {
                    $nome = $g['name'] ?? 'Desconhecido';
                    $valor = (float)($g['valor'] ?? 0);
                    if (!isset($gorjetasPorFunc[$nome])) {
                        $gorjetasPorFunc[$nome] = 0;
                    }
                    $gorjetasPorFunc[$nome] += $valor;
                }
                arsort($gorjetasPorFunc);
                $gorjetasPorFunc = array_slice($gorjetasPorFunc, 0, 10, true);
                echo json_encode($gorjetasPorFunc);
            ?>;
            
            if (document.getElementById('chartGorjetasTop')) {
                const ctx6 = document.getElementById('chartGorjetasTop').getContext('2d');
                new Chart(ctx6, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(gorjetasTop),
                        datasets: [{
                            label: 'Gorjetas (€)',
                            data: Object.values(gorjetasTop),
                            backgroundColor: colors[2],
                            borderColor: '#f093fb',
                            borderWidth: 2,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#e2e8f0' } },
                            title: { display: true, text: 'Top Gorjetas por Funcionário', color: '#e2e8f0' }
                        },
                        scales: {
                            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        }
                    }
                });
            }

            // 7. FOLHA - Custos Salariais Top Funcionários
            const folhaCustos = <?php
                $custosPorFunc = [];
                foreach ($folhaPagamento as $f) {
                    $nome = $f['name'] ?? 'Desconhecido';
                    $total = (float)($f['total_bruto'] ?? 0);
                    if (!isset($custosPorFunc[$nome])) {
                        $custosPorFunc[$nome] = 0;
                    }
                    $custosPorFunc[$nome] += $total;
                }
                arsort($custosPorFunc);
                $custosPorFunc = array_slice($custosPorFunc, 0, 10, true);
                echo json_encode($custosPorFunc);
            ?>;
            
            if (document.getElementById('chartFolhaCustos')) {
                const ctx7 = document.getElementById('chartFolhaCustos').getContext('2d');
                new Chart(ctx7, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(folhaCustos),
                        datasets: [{
                            label: 'Custos Salariais (€)',
                            data: Object.values(folhaCustos),
                            backgroundColor: colors[3],
                            borderColor: '#4facfe',
                            borderWidth: 2,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#e2e8f0' } },
                            title: { display: true, text: 'Custos Salariais (Top)', color: '#e2e8f0' }
                        },
                        scales: {
                            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        }
                    }
                });
            }
        });
        </script>









