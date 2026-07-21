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
                
            </div>

           

            <!-- ALERTAS DE RELATÓRIOS -->
            <style>
                .rp-alert-strip { display:flex; flex-wrap:wrap; gap:.6rem; margin-bottom:1.25rem; }
                .rp-alert {
                    display:inline-flex; align-items:center; gap:.45rem;
                    padding:.5rem .9rem; border-radius:10px; font-size:.8rem; font-weight:600;
                    border:1px solid; white-space:nowrap;
                }
                .rp-alert strong { font-size:.95rem; }
                .rp-alert-ok { background:rgba(16,185,129,.1); color:#34d399; border-color:rgba(16,185,129,.3); }
            </style>

            <?php
                // Alertas operacionais — pendências que merecem atenção hoje
                $alFuncFaltas = array_filter($employees, function($e) {
                    $f = (int)($e['rel_faltas'] ?? ($e['faltas'] ?? 0));
                    return $f >= 3;
                });
                $alFuncFerias = array_filter($employees, fn($e) => strtolower(trim((string)($e['status'] ?? ''))) === 'ferias');
                $alGorjPendentes = array_filter($gorjetas, fn($g) => strtolower(trim((string)($g['status'] ?? ''))) === 'pendente');

                $rpAlerts = [];
                if (count($alFuncFaltas) > 0) {
                    $rpAlerts[] = ['icon'=>'fa-user-clock','color'=>'#f87171','bg'=>'rgba(239,68,68,.12)','val'=>count($alFuncFaltas),'lbl'=>'funcionário'.(count($alFuncFaltas)!==1?'s':'').' com faltas elevadas'];
                }
                if (count($alGorjPendentes) > 0) {
                    $rpAlerts[] = ['icon'=>'fa-hand-holding-usd','color'=>'#fbbf24','bg'=>'rgba(245,158,11,.12)','val'=>count($alGorjPendentes),'lbl'=>'gorjeta'.(count($alGorjPendentes)!==1?'s':'').' pendente'.(count($alGorjPendentes)!==1?'s':'')];
                }
                if (count($alFuncFerias) > 0) {
                    $rpAlerts[] = ['icon'=>'fa-umbrella-beach','color'=>'#60a5fa','bg'=>'rgba(59,130,246,.12)','val'=>count($alFuncFerias),'lbl'=>'funcionário'.(count($alFuncFerias)!==1?'s':'').' de férias'];
                }
            ?>
            

            <!-- TABELA DE RELATÓRIO DE FUNCIONÁRIOS -->
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
                            <th>Ação</th>
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
                            $empStatusBadgeClass = match($empStatusFilter) {
                                'ativo' => 'status-active',
                                'inativo' => 'status-inactive',
                                'ferias' => 'status-ferias',
                                default => 'status-nao-marcado'
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
                            <td><span class="status-badge <?php echo $empStatusBadgeClass; ?>"><?php echo htmlspecialchars($empStatusLabel); ?></span></td>
                            <td>
                                <button type="button" class="fr-btn fr-btn-view" title="Ver relatório" onclick="verRelatorioFuncionario(<?php echo $empId; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- MODAL: Relatório completo do funcionário -->
            <div id="modalRelatorioFuncionario" class="modal" style="display:none;overflow-y:auto;padding:24px 16px 48px;">
                <div class="am-sheet" style="max-width:720px;">
                    <button class="am-close" type="button" aria-label="Fechar"
                        onclick="document.getElementById('modalRelatorioFuncionario').style.display='none'">&times;</button>

                    <div class="am-header" style="align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div id="rfModalAvatar" class="fr-av" style="width:52px;height:52px;font-size:1.1rem;background:linear-gradient(135deg,#8b5cf6,#7c3aed);flex-shrink:0;"></div>
                            <div>
                                <h2 class="am-title" id="rfModalNome">Relatório do Funcionário</h2>
                                <p class="am-subtitle" id="rfModalCargo"></p>
                            </div>
                        </div>
                        
                    </div>

                    <div id="rfModalConteudo" class="am-section"></div>

                    <div class="am-footer" style="justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
                        <button type="button" class="am-btn-cancel" onclick="rfVerHistorico()">
                            <i class="fas fa-history"></i> Ver Histórico
                        </button>
                        <div style="display:flex;gap:.5rem;">
                            <button type="button" class="fr-export-btn" onclick="baixarRelatorioFuncionarioPDF()">
                                <i class="fas fa-file-pdf"></i> Exportar PDF
                            </button>
                            <button type="button" class="am-btn-cancel"
                                onclick="document.getElementById('modalRelatorioFuncionario').style.display='none'">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            window.relatorioPorFuncionario = <?php
                $anoAtualRel = (int)date('Y');
                $porFuncionario = [];
                foreach ($employees as $emp) {
                    $eid = (int)($emp['id'] ?? 0);

                    $presencasEmp = array_values(array_filter($presencas, fn($p) => (int)($p['funcionario_id'] ?? 0) === $eid));
                    usort($presencasEmp, fn($a, $b) => strcmp((string)($b['data_registro'] ?? ''), (string)($a['data_registro'] ?? '')));

                    $turnosEmp = array_values(array_filter($turnosRelatorio, fn($t) => (int)($t['funcionario_id'] ?? 0) === $eid));
                    $horarioInicioRef = $turnosEmp[0]['horario_inicio'] ?? null;

                    $atrasos = 0;
                    $ultimosRegistros = [];
                    foreach ($presencasEmp as $idxP => $p) {
                        $timeline = trim((string)($p['ponto_timeline'] ?? ''));
                        $primeiraEntrada = '';
                        $ultimaSaida = '';
                        if ($timeline !== '') {
                            foreach (explode(';;', $timeline) as $periodo) {
                                [$hEnt, $hSai] = array_pad(explode('|', $periodo, 3), 2, '');
                                $hEnt = trim($hEnt);
                                $hSai = trim($hSai);
                                if ($primeiraEntrada === '' && $hEnt !== '') {
                                    $primeiraEntrada = $hEnt;
                                }
                                if ($hSai !== '') {
                                    $ultimaSaida = $hSai;
                                }
                            }
                        }
                        if ($primeiraEntrada !== '' && $horarioInicioRef) {
                            $diffMin = (strtotime($primeiraEntrada) - strtotime((string)$horarioInicioRef)) / 60;
                            if ($diffMin > 5) {
                                $atrasos++;
                            }
                        }
                        if ($idxP < 5) {
                            $dataRegP = (string)($p['data_registro'] ?? '');
                            $tsP = $dataRegP !== '' ? strtotime($dataRegP) : false;
                            $ultimosRegistros[] = [
                                'data' => $tsP !== false ? date('d M', $tsP) : 'N/D',
                                'entrada' => $primeiraEntrada !== '' ? substr($primeiraEntrada, 0, 5) : '--:--',
                                'saida' => $ultimaSaida !== '' ? substr($ultimaSaida, 0, 5) : '--:--',
                            ];
                        }
                    }

                    $diasFeriasTotal = max(0, (int)($emp['vacation_days'] ?? 22));
                    $diasUsados = 0;
                    try {
                        $stmtFeriasUsadasRel = $pdo->prepare(
                            "SELECT COALESCE(SUM(DATEDIFF(LEAST(data_fim, ?), GREATEST(data_inicio, ?)) + 1), 0) AS total
                             FROM ferias WHERE funcionario_id = ? AND LOWER(COALESCE(status, '')) IN ('aprovada', 'aprovado')"
                        );
                        $stmtFeriasUsadasRel->execute([$anoAtualRel . '-12-31', $anoAtualRel . '-01-01', $eid]);
                        $diasUsados = max(0, (int)$stmtFeriasUsadasRel->fetchColumn());
                    } catch (Throwable $e) {
                    }

                    $folhaEmpAtual = null;
                    foreach ($folhaPagamento as $fRow) {
                        if ((int)($fRow['employee_id'] ?? 0) === $eid) {
                            $folhaEmpAtual = $fRow;
                            break;
                        }
                    }

                    $empStatusRawRel = mb_strtolower(trim((string)($emp['status'] ?? '')));
                    $empStatusLabelRel = match($empStatusRawRel) {
                        'active', 'ativo' => 'Funcionário(a) Ativo(a)',
                        'inactive', 'inativo' => 'Funcionário(a) Inativo(a)',
                        'ferias', 'férias' => 'Funcionário(a) em Férias',
                        default => 'Estado desconhecido',
                    };

                    $porFuncionario[$eid] = [
                        'nome' => $emp['name'] ?? '',
                        'cargo' => $emp['role'] ?? ($emp['position'] ?? ''),
                        'foto' => $emp['profile_picture'] ?? '',
                        'statusLabel' => $empStatusLabelRel,
                        'kpi' => [
                            'presencas' => (int)($emp['rel_dias_trabalhados'] ?? 0),
                            'faltas' => (int)($emp['rel_faltas'] ?? 0),
                            'atrasos' => $atrasos,
                            'horas' => round((float)($emp['rel_horas_trabalhadas'] ?? 0)),
                        ],
                        'financeiro' => [
                            'salarioBase' => number_format((float)($emp['rel_salary_base'] ?? 0), 0, ',', '.'),
                            'horasExtra' => number_format((float)($folhaEmpAtual['horas_extra'] ?? 0), 0, ',', '.'),
                            'total' => number_format((float)($emp['rel_total_liquido'] ?? 0), 0, ',', '.'),
                        ],
                        'ferias' => [
                            'disponiveis' => max(0, $diasFeriasTotal - $diasUsados),
                            'utilizados' => $diasUsados,
                        ],
                        'ultimosRegistros' => $ultimosRegistros,
                    ];
                }
                echo json_encode($porFuncionario, JSON_UNESCAPED_UNICODE);
            ?>;

            let rfCurrentId = null;

            function rfIniciais(nome) {
                return String(nome || '?').trim().substring(0, 2).toUpperCase();
            }

            window.verRelatorioFuncionario = function(id) {
                const dados = window.relatorioPorFuncionario[id];
                if (!dados) { return; }
                rfCurrentId = id;

                document.getElementById('rfModalNome').textContent = dados.nome || 'Relatório do Funcionário';
                document.getElementById('rfModalCargo').textContent = dados.cargo || '';

                const avatarEl = document.getElementById('rfModalAvatar');
                avatarEl.innerHTML = dados.foto
                    ? `<img src="../${dados.foto}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" onerror="this.parentElement.textContent='${rfIniciais(dados.nome)}';">`
                    : rfIniciais(dados.nome);

                const registrosHtml = dados.ultimosRegistros.length
                    ? dados.ultimosRegistros.map(r => `
                        <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:.85rem;">
                            <span style="color:#94a3b8;">${r.data}</span>
                            <span>Entrada <strong>${r.entrada}</strong> &middot; Saída <strong>${r.saida}</strong></span>
                        </div>`).join('')
                    : '<p style="color:#94a3b8;font-size:.85rem;margin:.25rem 0 0;">Sem registos.</p>';

                document.getElementById('rfModalConteudo').innerHTML = `
                    <p style="color:#94a3b8;font-size:.85rem;margin:0 0 1rem;">${dados.statusLabel}</p>

                    <div class="fr-kpi-strip" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.25rem;">
                        <div class="fr-kpi"><div class="fr-kpi-icon" style="background:rgba(16,185,129,.14);color:#10b981;"><i class="fas fa-user-check"></i></div>
                            <div class="fr-kpi-body"><span class="fr-kpi-val">${dados.kpi.presencas}</span><span class="fr-kpi-lbl">Presenças</span></div></div>
                        <div class="fr-kpi"><div class="fr-kpi-icon" style="background:rgba(239,68,68,.12);color:#f87171;"><i class="fas fa-user-times"></i></div>
                            <div class="fr-kpi-body"><span class="fr-kpi-val">${dados.kpi.faltas}</span><span class="fr-kpi-lbl">Faltas</span></div></div>
                        <div class="fr-kpi"><div class="fr-kpi-icon" style="background:rgba(245,158,11,.14);color:#fbbf24;"><i class="fas fa-clock"></i></div>
                            <div class="fr-kpi-body"><span class="fr-kpi-val">${dados.kpi.atrasos}</span><span class="fr-kpi-lbl">Atrasos</span></div></div>
                        <div class="fr-kpi"><div class="fr-kpi-icon" style="background:rgba(59,130,246,.14);color:#60a5fa;"><i class="fas fa-hourglass-half"></i></div>
                            <div class="fr-kpi-body"><span class="fr-kpi-val">${dados.kpi.horas}h</span><span class="fr-kpi-lbl">Horas</span></div></div>
                    </div>

                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-euro-sign"></i> Financeiro (mês atual)</div>
                        <div class="vm-g2">
                            <div><div class="vm-field-label">Salário Base</div><div class="vm-field-value">€ ${dados.financeiro.salarioBase}</div></div>
                            <div><div class="vm-field-label">Horas Extra</div><div class="vm-field-value">€ ${dados.financeiro.horasExtra}</div></div>
                            <div><div class="vm-field-label">Total Líquido</div><div class="vm-field-value" style="font-weight:700;color:#4ade80;">€ ${dados.financeiro.total}</div></div>
                        </div>
                    </div>

                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-umbrella-beach"></i> Férias</div>
                        <div class="vm-g2">
                            <div><div class="vm-field-label">Disponíveis</div><div class="vm-field-value">${dados.ferias.disponiveis} dias</div></div>
                            <div><div class="vm-field-label">Utilizados</div><div class="vm-field-value">${dados.ferias.utilizados} dias</div></div>
                        </div>
                    </div>

                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-calendar-day"></i> Últimos Registos</div>
                        ${registrosHtml}
                    </div>
                `;

                document.getElementById('modalRelatorioFuncionario').style.display = 'block';
            };

            window.rfVerHistorico = function() {
                const dados = window.relatorioPorFuncionario[rfCurrentId];
                document.getElementById('modalRelatorioFuncionario').style.display = 'none';
                if (typeof showSection === 'function') showSection('assiduidade');
                if (typeof openPresencaHistoryModal === 'function') openPresencaHistoryModal();
                setTimeout(function() {
                    const searchEl = document.getElementById('searchHistoryPresenca');
                    if (searchEl && dados && dados.nome) {
                        searchEl.value = dados.nome;
                        searchEl.dispatchEvent(new Event('input'));
                    }
                }, 150);
            };

            window.baixarRelatorioFuncionarioPDF = function() {
                const dados = window.relatorioPorFuncionario[rfCurrentId];
                if (!dados) { return; }

                if (!window.jspdf || !window.jspdf.jsPDF) {
                    alert('Biblioteca de PDF não carregada. Recarregue a página e tente novamente.');
                    return;
                }

                const doc = new window.jspdf.jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
                const pageWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();
                const marginX = 12;
                const generatedAt = new Date().toLocaleString('pt-PT');

                doc.setFillColor(139, 92, 246);
                doc.rect(0, 0, pageWidth, 22, 'F');
                doc.setTextColor(255, 255, 255);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(14);
                doc.text('RHNeto Pro', marginX, 10);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8.5);
                doc.text('Relatório do Funcionário', marginX, 16);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(11);
                doc.text(String(dados.nome || ''), pageWidth - marginX, 10, { align: 'right' });
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8.5);
                doc.text(String(dados.cargo || ''), pageWidth - marginX, 16, { align: 'right' });

                doc.setTextColor(51, 65, 85);
                doc.setFontSize(8);
                doc.text(`Gerado em ${generatedAt} — ${dados.statusLabel}`, marginX, 29);

                let cursorY = 38;
                doc.autoTable({
                    head: [['Presenças', 'Faltas', 'Atrasos', 'Horas']],
                    body: [[String(dados.kpi.presencas), String(dados.kpi.faltas), String(dados.kpi.atrasos), dados.kpi.horas + 'h']],
                    startY: cursorY,
                    margin: { left: marginX, right: marginX },
                    styles: { fontSize: 9, cellPadding: 3, halign: 'center' },
                    headStyles: { fillColor: [139, 92, 246], textColor: [255, 255, 255], fontStyle: 'bold' }
                });
                cursorY = doc.lastAutoTable.finalY + 8;

                doc.autoTable({
                    head: [['Salário Base', 'Horas Extra', 'Total Líquido']],
                    body: [[`€ ${dados.financeiro.salarioBase}`, `€ ${dados.financeiro.horasExtra}`, `€ ${dados.financeiro.total}`]],
                    startY: cursorY,
                    margin: { left: marginX, right: marginX },
                    styles: { fontSize: 9, cellPadding: 3, halign: 'center' },
                    headStyles: { fillColor: [124, 58, 237], textColor: [255, 255, 255], fontStyle: 'bold' }
                });
                cursorY = doc.lastAutoTable.finalY + 8;

                doc.autoTable({
                    head: [['Férias Disponíveis', 'Férias Utilizadas']],
                    body: [[`${dados.ferias.disponiveis} dias`, `${dados.ferias.utilizados} dias`]],
                    startY: cursorY,
                    margin: { left: marginX, right: marginX },
                    styles: { fontSize: 9, cellPadding: 3, halign: 'center' },
                    headStyles: { fillColor: [124, 58, 237], textColor: [255, 255, 255], fontStyle: 'bold' }
                });
                cursorY = doc.lastAutoTable.finalY + 10;

                doc.setFont('helvetica', 'bold');
                doc.setFontSize(10);
                doc.setTextColor(30, 41, 59);
                doc.text('Últimos Registos', marginX, cursorY);
                cursorY += 4;

                if (dados.ultimosRegistros.length) {
                    doc.autoTable({
                        head: [['Data', 'Entrada', 'Saída']],
                        body: dados.ultimosRegistros.map(r => [r.data, r.entrada, r.saida]),
                        startY: cursorY,
                        margin: { left: marginX, right: marginX, bottom: 16 },
                        styles: { fontSize: 8.5, cellPadding: 2.2 },
                        headStyles: { fillColor: [139, 92, 246], textColor: [255, 255, 255], fontStyle: 'bold' }
                    });
                } else {
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(9);
                    doc.setTextColor(100, 116, 139);
                    doc.text('Sem registos.', marginX, cursorY + 4);
                }

                const pageCount = doc.internal.getNumberOfPages();
                for (let i = 1; i <= pageCount; i++) {
                    doc.setPage(i);
                    doc.setDrawColor(226, 232, 240);
                    doc.line(marginX, pageHeight - 12, pageWidth - marginX, pageHeight - 12);
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(8);
                    doc.setTextColor(100, 116, 139);
                    doc.text('RHNeto Pro — Relatório gerado automaticamente', marginX, pageHeight - 7);
                    doc.text(`Página ${i} de ${pageCount}`, pageWidth - marginX, pageHeight - 7, { align: 'right' });
                }

                const nomeSemAcentos = String(dados.nome || 'funcionario').toLowerCase().normalize('NFD').replace(/\p{M}/gu, '');
                const nomeSlug = nomeSemAcentos.replace(/[^a-z0-9]+/g, '_');
                doc.save(`relatorio_${nomeSlug}_${new Date().toISOString().split('T')[0]}.pdf`);
            };
            </script>
        </section>









