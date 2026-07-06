<?php
// Secção "Folha de Pagamento" — incluída a partir de admin/dashboard.php (depende de $pdo, $employees, $folhaFiscalYear, $folhaFiscalMonth, $folhaResumo, etc. já definidos lá).
?>
        <section id="folha-pagamento-section" class="content-section">
            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);box-shadow:0 4px 14px rgba(59,130,246,.35);"><i class="fas fa-money-check-alt"></i></div>
                    <div>
                        <h2 class="frhd-title">Folha de Pagamento</h2>
                        <p class="frhd-sub">Período: <?php echo htmlspecialchars($folhaPeriodoLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
                    <span class="status-badge <?php echo $folhaFechada ? 'status-inactive' : 'status-presente'; ?>" style="font-size:.8rem;font-weight:700;letter-spacing:.05em;padding:.35rem .9rem;text-transform:uppercase;">
                        <?php echo $folhaFechada ? 'Mês Fechado' : 'Mês Aberto'; ?>
                    </span>
                    <span style="font-weight:700;color:var(--text-secondary);font-size:.9rem;white-space:nowrap;">
                        Período: <?php echo htmlspecialchars($folhaPeriodoLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
            </div>


            <style>
                .fp-kpi-custo .fr-kpi-icon  { background:rgba(139,92,246,.14); color:#a78bfa; }
                .fp-kpi-pagos .fr-kpi-icon  { background:rgba(16,185,129,.14); color:#10b981; }
                .fp-kpi-pend .fr-kpi-icon   { background:rgba(245,158,11,.14); color:#f59e0b; }
                #modalReciboFolha    { overflow-y:auto; padding:24px 16px 48px; }
                #modalFolhaVariaveis { overflow-y:auto; padding:24px 16px 48px; }
                #recibo-status-badge {
                    display:inline-flex; align-items:center; padding:.25rem .75rem;
                    border-radius:20px; font-size:.75rem; font-weight:700;
                    color:#e2e8f0; margin-top:.3rem; min-height:1.5rem;
                    border:1px solid rgba(255,255,255,.1);
                }
                .fp-tb-btn {
                    display:inline-flex; align-items:center; gap:.35rem;
                    padding:.42rem .85rem; border-radius:8px; font-size:.8rem;
                    font-weight:600; cursor:pointer; transition:all .18s;
                    white-space:nowrap; background:transparent;
                    border:1px solid rgba(255,255,255,.12);
                    color:var(--text-secondary,#94a3b8);
                    line-height:1;
                }
                .fp-tb-btn:hover:not(:disabled) { filter:brightness(1.18); transform:translateY(-1px); }
                .fp-tb-btn:disabled { opacity:.4; cursor:not-allowed; }
            </style>
            <div class="fr-kpi-strip" style="grid-template-columns:repeat(3,1fr);">
                <div class="fr-kpi fp-kpi-custo">
                    <div class="fr-kpi-icon"><i class="fas fa-euro-sign"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val" style="font-size:1.25rem;">€ <?php echo number_format((float)$folhaResumo['custo_total'], 2, ',', '.'); ?></span>
                        <span class="fr-kpi-lbl">Custo Total Mensal</span>
                        <span class="fr-kpi-pct">Total registado no mês</span>
                    </div>
                </div>
                <div class="fr-kpi fp-kpi-pagos">
                    <div class="fr-kpi-icon"><i class="fas fa-users"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)$folhaResumo['funcionarios_pagos']; ?> <span style="font-size:1rem;color:#64748b;">/ <?php echo (int)$folhaResumo['total_funcionarios']; ?></span></span>
                        <span class="fr-kpi-lbl">Funcionários Pagos</span>
                        <span class="fr-kpi-pct">na folha de pagamento</span>
                    </div>
                </div>
                <div class="fr-kpi fp-kpi-pend">
                    <div class="fr-kpi-icon"><i class="fas fa-clock"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)$folhaResumo['pendencias']; ?></span>
                        <span class="fr-kpi-lbl">Pendências</span>
                        <span class="fr-kpi-pct">pagamentos por processar</span>
                    </div>
                </div>
            </div>

            <?php if ($folhaFechada): ?>
            <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-left:3px solid #f59e0b;padding:.75rem 1rem;border-radius:10px;margin-bottom:1rem;display:flex;align-items:center;gap:.75rem;">
                <i class="fas fa-lock" style="color:#f59e0b;font-size:1rem;flex-shrink:0;"></i>
                <div>
                    <strong style="color:#fbbf24;font-size:.875rem;">Folha Fechada</strong>
                    <span style="color:#94a3b8;font-size:.8rem;margin-left:.5rem;">Os valores são históricos e imutáveis — alterações futuras nas configurações não afetarão estes dados.</span>
                </div>
            </div>
            <?php endif; ?>

            <div class="data-table fr-table-wrap">
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="searchFolhaEmployees" placeholder="Pesquisar funcionário..." class="fr-search">
                        </div>
                        <div class="fr-toolbar-right" style="gap:.65rem;flex-wrap:wrap;">
                            <div style="display:flex;align-items:center;gap:.4rem;">
                                <form method="get" action="dashboard.php" style="display:flex;gap:.4rem;align-items:center;">
                                    <input type="hidden" name="section" value="folha-pagamento">
                                    <select name="folha_mes" class="fr-select" style="width:140px;min-width:120px;">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m === (int)$folhaFiscalMonth ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mesesPt[$m], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                    <select name="folha_ano" class="fr-select" style="width:100px;min-width:90px;">
                                        <?php for ($y = $currentYear + 1; $y >= $currentYear - 5; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y === (int)$folhaFiscalYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <button type="submit" class="fp-tb-btn" style="background:rgba(99,102,241,.15);color:#818cf8;border-color:rgba(99,102,241,.3);">
                                        <i class="fas fa-calendar-alt"></i> Aplicar
                                    </button>
                                </form>
                            </div>

                            <span style="width:1px;height:22px;background:rgba(255,255,255,.1);flex-shrink:0;"></span>

                            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                            <button type="button" class="fr-filter-toggle" id="folhaFilterToggle"
                                onclick="document.getElementById('folhaAdvFilters').classList.toggle('fr-adv-open'); this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                                <span class="fr-filter-badge" id="folhaFilterBadge" style="display:none"></span>
                            </button>

                            <form method="post"
                                action="dashboard.php?section=folha-pagamento&folha_mes=<?php echo (int)$folhaFiscalMonth; ?>&folha_ano=<?php echo (int)$folhaFiscalYear; ?>"
                                style="display:inline;">
                                <input type="hidden" name="action" value="mark_all_paid">
                                <input type="hidden" name="fiscal_year" value="<?php echo (int)$folhaFiscalYear; ?>">
                                <input type="hidden" name="fiscal_month" value="<?php echo (int)$folhaFiscalMonth; ?>">
                                <button type="submit" class="fp-tb-btn"
                                    style="background:rgba(22,163,74,.15);color:#4ade80;border-color:rgba(22,163,74,.3);<?php echo $folhaFechada ? 'opacity:.4;' : ''; ?>"
                                    title="<?php echo $folhaFechada ? 'Folha fechada — não é possível alterar status' : 'Marcar todos os pendentes como pagos'; ?>"
                                    <?php echo $folhaFechada ? 'disabled' : ''; ?>>
                                    <i class="fas fa-check-double"></i> Marcar Pagos
                                </button>
                            </form>

                            <form id="closeFolhaForm" method="post"
                                action="dashboard.php?section=folha-pagamento&folha_mes=<?php echo (int)$folhaFiscalMonth; ?>&folha_ano=<?php echo (int)$folhaFiscalYear; ?>"
                                style="display:inline;" data-pending-count="<?php echo (int)$folhaResumo['pendencias']; ?>"
                                data-total-count="<?php echo (int)$folhaResumo['total_funcionarios']; ?>">
                                <input type="hidden" name="action" value="close_folha">
                                <input type="hidden" name="fiscal_year" value="<?php echo (int)$folhaFiscalYear; ?>">
                                <input type="hidden" name="fiscal_month" value="<?php echo (int)$folhaFiscalMonth; ?>">
                                <?php if (!$folhaFechada): ?>
                                <button type="submit" class="fp-tb-btn"
                                    style="background:rgba(245,158,11,.15);color:#fbbf24;border-color:rgba(245,158,11,.3);<?php echo ((int)$folhaResumo['total_funcionarios'] <= 0) ? 'opacity:.4;' : ''; ?>"
                                    title="<?php echo ((int)$folhaResumo['total_funcionarios'] <= 0) ? 'Não é possível fechar sem funcionários na folha' : 'Fechar folha e guardar snapshot dos valores (imutável)'; ?>"
                                    <?php echo ((int)$folhaResumo['total_funcionarios'] <= 0) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-lock"></i> Fechar
                                </button>
                                <?php else: ?>
                                <button type="button" class="fp-tb-btn" style="opacity:.4;cursor:not-allowed;" disabled title="Esta folha já está fechada.">
                                    <i class="fas fa-lock"></i> Fechado
                                </button>
                                <?php endif; ?>
                            </form>

                            <?php if ($folhaFechada && isset($_SESSION['user_level']) && in_array(mb_strtolower($_SESSION['user_level']), ['admin', 'administrador', 'superadmin'], true)): ?>
                            <form id="reopenFolhaForm" method="post"
                                action="dashboard.php?section=folha-pagamento&folha_mes=<?php echo (int)$folhaFiscalMonth; ?>&folha_ano=<?php echo (int)$folhaFiscalYear; ?>"
                                style="display:inline;">
                                <input type="hidden" name="action" value="reopen_folha">
                                <input type="hidden" name="fiscal_year" value="<?php echo (int)$folhaFiscalYear; ?>">
                                <input type="hidden" name="fiscal_month" value="<?php echo (int)$folhaFiscalMonth; ?>">
                                <button type="submit" class="fp-tb-btn"
                                    style="background:rgba(239,68,68,.12);color:#fca5a5;border-color:rgba(239,68,68,.3);"
                                    title="Reabrir folha para permitir edições (admin only)">
                                    <i class="fas fa-unlock"></i> Reabrir
                                </button>
                            </form>
                            <?php endif; ?>

                            <button type="button" id="btnExportFolhaCsv" class="fr-export-btn">
                                <i class="fas fa-download"></i> CSV
                            </button>
                            </div><!-- /acções -->
                        </div><!-- /fr-toolbar-right -->
                    </div><!-- /fr-toolbar-top -->
                    <div class="fr-adv-filters" id="folhaAdvFilters">
                        <select id="filtroStatusPagamento" class="fr-select" style="width:180px;">
                            <option value="">Todos os status</option>
                            <option value="pago">Pago</option>
                        </select>
                        <button type="button" class="fr-clear-btn" id="clearFolhaFiltersBtn" style="display:none">Limpar</button>
                    </div>
                </div>

                <table id="folhaTable" class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th>Funcionário</th>
                            <th>Salário Base</th>
                            <th>Total Bruto</th>
                            <th>Líquido</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
<tbody>
<?php if (!empty($employees)): ?>
<?php foreach ($employees as $employee):
    $employeeId = (int)($employee['id'] ?? 0);
    $folha = $folhaCalculos[$employeeId] ?? null;
    $gorjetaTotalFolha = $folha ? (float)($folha['gorjetas'] ?? 0) : 0.0;
    $gorjetaManualFolha = $folha ? (float)($folha['gorjeta_manual'] ?? 0) : 0.0;
    $gorjetaBaseFolha = max(0.0, $gorjetaTotalFolha - $gorjetaManualFolha);

    if ($folha) {
        $salario_base = number_format((float)$folha['salario_base'], 2, ',', '.');
        $total_subsidios = (float)($folha['total_subsidios'] ?? 0.0);
        $subsidios = number_format($total_subsidios, 2, ',', '.');
        $bruto = number_format((float)$folha['salario_bruto'], 2, ',', '.');
        $liquido = number_format((float)$folha['salario_liquido'], 2, ',', '.');
    } else {
        $salario_base = '--';
        $subsidios = '--';
        $bruto = '--';
        $liquido = '--';
    }

    $folhaStatusRaw = $folha ? strtolower((string)($folha['status_folha'] ?? 'ativo')) : 'ativo';

    // NOVO STATUS BONITO (sem alterar lógica existente)
    $pagStatusRaw = mb_strtolower(trim((string)($folha['status_pagamento'] ?? 'pendente')));
    $pagStatus = $pagStatusRaw;
    $pagDataPagamento = $folha ? ($folha['data_pagamento'] ?? null) : null;

    $pagamentoLabel = 'Pendente';
    $pagamentoClass = 'status-pagamento status-pagamento-pendente';
    $pagamentoIcon  = 'fa-clock';

    if ($pagStatusRaw === 'pago') {
        $pagamentoLabel = 'Pago';
        $pagamentoClass = 'status-pagamento status-pagamento-pago';
        $pagamentoIcon  = 'fa-circle-check';
    }

    // Compatibilidade
    $empStatusLabel = $pagamentoLabel;
    $empStatusClass = $pagamentoClass;

    $folhaJson = $folha ? htmlspecialchars(json_encode($folha, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') : '{}';
?>
<tr class="fr-row"
    data-folha="<?php echo $folhaJson; ?>"
    data-emp-name="<?php echo htmlspecialchars($employee['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
    data-emp-position="<?php echo htmlspecialchars($employee['position'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
    data-emp-department="<?php echo htmlspecialchars($employee['department'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
    data-emp-status="<?php echo htmlspecialchars($empStatusLabel, ENT_QUOTES, 'UTF-8'); ?>"
    data-periodo="<?php echo htmlspecialchars($folhaPeriodoLabel, ENT_QUOTES, 'UTF-8'); ?>"
    data-fiscal-year="<?php echo (int)$folhaFiscalYear; ?>"
    data-fiscal-month="<?php echo (int)$folhaFiscalMonth; ?>"
    data-horas-extra="<?php echo $folha ? (float)($folha['horas_extra_mensal'] ?? 0) : 0; ?>"
    data-bonus-mensal="<?php echo $folha ? (float)($folha['bonus_mensal'] ?? 0) : 0; ?>"
    data-subsidios-mensais="<?php echo $folha ? (float)($folha['subsidios_extra'] ?? 0) : 0; ?>"
    data-gorjeta-manual="<?php echo $folha ? (float)($folha['gorjeta_manual'] ?? 0) : 0; ?>"
    data-gorjeta-total="<?php echo $gorjetaTotalFolha; ?>"
    data-gorjeta-base="<?php echo $gorjetaBaseFolha; ?>"
    data-status-folha="<?php echo htmlspecialchars($folhaStatusRaw, ENT_QUOTES, 'UTF-8'); ?>"
    data-status-pagamento="<?php echo htmlspecialchars($pagStatus, ENT_QUOTES, 'UTF-8'); ?>"
    data-is-locked="<?php echo $folha ? (int)($folha['is_locked'] ?? 0) : 0; ?>">

    <td class="fr-td-emp">
        <div class="fr-emp-cell">
            <div class="fr-av" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);">
                <?php if (!empty($employee['profile_picture'])): ?>
                    <img class="fr-av-img" src="../<?php echo htmlspecialchars($employee['profile_picture']); ?>"
                        alt="<?php echo htmlspecialchars($employee['name']); ?>">
                <?php else: ?>
                    <?php echo strtoupper(substr($employee['name'] ?? 'XX', 0, 2)); ?>
                <?php endif; ?>
            </div>
            <div class="fr-emp-info">
                <span class="fr-emp-name"><?php echo htmlspecialchars($employee['name'] ?? ($employee['fullname'] ?? '--')); ?></span>
                <span class="fr-emp-email"><?php echo htmlspecialchars($employee['position'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </td>

    <td><?php echo $salario_base !== '--' ? '€ ' . $salario_base : '--'; ?></td>
    <td><?php echo $bruto !== '--' ? '€ ' . $bruto : '--'; ?></td>
    <td style="font-weight:700;color:#4ade80;"><?php echo $liquido !== '--' ? '€ ' . $liquido : '--'; ?></td>

    <td>
        <span class="<?php echo $pagamentoClass; ?>">
            <i class="fas <?php echo $pagamentoIcon; ?>"></i>
            <?php echo $pagamentoLabel; ?>
        </span>
        <?php if ($pagStatus === 'pago' && !empty($pagDataPagamento)): ?>
        <div style="font-size:.72rem;color:#64748b;margin-top:.2rem;">
            <?php echo htmlspecialchars(date('d/m/Y', strtotime($pagDataPagamento)), ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>
    </td>

    <td>
        <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
            <button type="button" class="fr-btn fr-btn-view btn-folha-ver employee-action-btn"
                data-id="<?php echo $employeeId; ?>" title="Ver Recibo de Vencimento">
                <i class="fas fa-eye"></i>
            </button>
        <button type="button" class="fr-btn fr-btn-edit btn-folha-edit employee-action-btn"
                data-id="<?php echo $employeeId; ?>"
                data-pago="<?php echo $pagStatus === 'pago' ? '1' : '0'; ?>"
                title="<?php echo $pagStatus === 'pago' ? 'Pagamento já efetuado — clique para reverter e editar' : 'Editar Variáveis Mensais'; ?>"
                <?php echo ($folhaFechada || ($folha && (int)($folha['is_locked'] ?? 0) === 1)) ? 'disabled' : ''; ?>>
                <i class="fas fa-edit"></i>
            </button>
            <?php if ($pagStatus === 'pendente' && $folha && !$folhaFechada): ?>
            <button type="button" class="fr-btn fr-btn-activate btn-folha-pagar employee-action-btn"
                data-emp-id="<?php echo $employeeId; ?>"
                data-fiscal-year="<?php echo (int)$folhaFiscalYear; ?>"
                data-fiscal-month="<?php echo (int)$folhaFiscalMonth; ?>"
                title="Marcar como Pago">
                <i class="fas fa-check"></i>
            </button>
            <?php elseif ($pagStatus === 'pendente' && $folha && $folhaFechada): ?>
            <button type="button" class="fr-btn fr-btn-off employee-action-btn" disabled
                title="Folha fechada — não é possível alterar status">
                <i class="fas fa-lock"></i>
            </button>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr class="fr-row">
    <td colspan="6" style="text-align:center;color:#64748b;padding:2rem;">Nenhum funcionário encontrado para o seu cliente.</td>
</tr>
<?php endif; ?>
</tbody>
                </table>
            </div>



            <!-- Modal Recibo de Vencimento -->
            <div id="modalReciboFolha" class="modal" style="display:none; overflow-y:auto; padding:24px 16px 48px;">
                <div class="am-sheet vm-sheet" id="reciboCard" style="max-width:680px;">

                    <button class="am-close" type="button" onclick="document.getElementById('modalReciboFolha').style.display='none'" aria-label="Fechar">&times;</button>

                    <!-- Header -->
                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);box-shadow:0 6px 16px rgba(29,78,216,.35);">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div>
                            <h3 class="am-title">Recibo de Vencimento</h3>
                            <p class="am-subtitle" id="recibo-periodo"></p>
                        </div>
                    </div>

                    <!-- Hero: avatar + nome + cargo + status -->
                    <div class="vm-hero">
                        <div id="view-recibo-avatar" class="vm-hero-av" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="vm-hero-info">
                            <h2 class="vm-hero-name" id="recibo-nome"></h2>
                            <p class="vm-hero-pos" id="recibo-cargo"></p>
                            <div id="recibo-status-badge"></div>
                        </div>
                    </div>

                    <!-- Remunerações -->
                    <div class="vm-section">
                        <div class="vm-sec-lbl"><i class="fas fa-plus-circle" style="color:#22c55e;"></i> Remunerações</div>
                        <div style="display:grid;gap:.35rem;">
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:.45rem 0;border-bottom:1px solid rgba(255,255,255,.05);">
                                <span style="font-size:.85rem;color:#94a3b8;">Salário Base</span>
                                <span id="r-salario-base" style="font-size:.88rem;color:#e2e8f0;font-weight:500;"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:.45rem 0;border-bottom:1px solid rgba(255,255,255,.05);">
                                <span style="font-size:.85rem;color:#94a3b8;">Subsídio de Alimentação</span>
                                <span id="r-subsidio-alim" style="font-size:.88rem;color:#e2e8f0;font-weight:500;"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:.45rem 0;border-bottom:1px solid rgba(255,255,255,.05);">
                                <span style="font-size:.85rem;color:#94a3b8;">Horas Extra</span>
                                <span id="r-horas-extra" style="font-size:.88rem;color:#e2e8f0;font-weight:500;"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:.45rem 0;border-bottom:1px solid rgba(255,255,255,.05);">
                                <span style="font-size:.85rem;color:#94a3b8;">Bónus</span>
                                <span id="r-bonus" style="font-size:.88rem;color:#e2e8f0;font-weight:500;"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:.45rem 0;border-bottom:1px solid rgba(255,255,255,.05);">
                                <span style="font-size:.85rem;color:#94a3b8;">Gorjetas</span>
                                <span id="r-gorjetas" style="font-size:.88rem;color:#e2e8f0;font-weight:500;"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-top:1px solid rgba(255,255,255,.1);margin-top:.2rem;">
                                <span style="font-size:.9rem;color:#e2e8f0;font-weight:700;">Salário Bruto</span>
                                <span id="r-bruto" style="font-size:.9rem;color:#e2e8f0;font-weight:700;"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Líquido -->
                    <div style="background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.2);border-radius:12px;padding:1rem 1.25rem;display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                        <div>
                            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#34d399;">Salário Líquido</div>
                            <div style="font-size:.75rem;color:#64748b;margin-top:2px;">a receber pelo trabalhador</div>
                        </div>
                        <div id="r-liquido" style="font-size:1.75rem;font-weight:800;color:#4ade80;"></div>
                    </div>

                    <!-- Footer -->
                    <div class="am-footer">
                        <button type="button" class="am-btn-cancel" onclick="document.getElementById('modalReciboFolha').style.display='none'">
                            Fechar
                        </button>
                        <button type="button" class="am-btn-submit" onclick="imprimirRecibo()" style="background:linear-gradient(135deg,#6366f1,#4f46e5);box-shadow:0 4px 14px rgba(99,102,241,.3);">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>

                </div>
            </div>

            <div id="modalFolhaVariaveis" class="modal" style="display:none; overflow-y:auto; padding:24px 16px 48px;">
                <div class="am-sheet">

                    <button class="am-close" type="button" id="closeFolhaVariaveis" aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 6px 16px rgba(217,119,6,.35);">
                            <i class="fas fa-sliders-h"></i>
                        </div>
                        <div>
                            <h3 class="am-title">Registo Mensal</h3>
                            <p class="am-subtitle" id="folhaVarPeriodo"></p>
                        </div>
                    </div>

                    <form method="post"
                        action="dashboard.php?section=folha-pagamento&folha_mes=<?php echo (int)$folhaFiscalMonth; ?>&folha_ano=<?php echo (int)$folhaFiscalYear; ?>">
                        <input type="hidden" name="action" value="save_folha_variavel">
                        <input type="hidden" name="employee_id" id="fvEmployeeId" value="">
                        <input type="hidden" name="fiscal_month" value="<?php echo (int)$folhaFiscalMonth; ?>">
                        <input type="hidden" name="fiscal_year" value="<?php echo (int)$folhaFiscalYear; ?>">

                        <!-- Funcionário (readonly) -->
                        <div class="am-section">
                            <div class="am-f">
                                <label class="am-lbl">Funcionário</label>
                                <input type="text" id="fvEmployeeName" readonly class="am-inp" style="opacity:.6;cursor:default;">
                            </div>
                        </div>

                        <!-- Valores mensais -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-euro-sign"></i> Valores Mensais</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl">Horas Extras (€)</label>
                                    <input type="number" step="0.01" min="0" name="horas_extra" id="fvHorasExtra" class="am-inp"
                                        <?php echo $folhaFechada ? 'disabled' : ''; ?>>
                                    <span class="am-hint">Valor em euros das h. extras</span>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl">Bónus Mensal (€)</label>
                                    <input type="number" step="0.01" min="0" name="bonus_mensal" id="fvBonusMensal" class="am-inp"
                                        <?php echo $folhaFechada ? 'disabled' : ''; ?>>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl">Subsídios Extra (€)</label>
                                    <input type="number" step="0.01" min="0" name="subsidios_mensais" id="fvSubsidiosMensais" class="am-inp"
                                        <?php echo $folhaFechada ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="faltas_dias" value="0">
                        <input type="hidden" name="outros_descontos" value="0">

                        <!-- Gorjetas -->
                        <?php if (!(int)($folhaConfigDefaults['gorjetas_auto_split'] ?? 0)): ?>
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-hand-holding-usd" style="color:#f59e0b;"></i> Gorjeta Manual</div>
                            <div class="am-f" style="background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:12px 14px;gap:.4rem;">
                                <label class="am-lbl">Gorjeta deste funcionário (€)</label>
                                <input type="number" step="0.01" min="0" name="gorjeta_manual" id="fvGorjetaManual" class="am-inp"
                                    <?php echo $folhaFechada ? 'disabled' : ''; ?>>
                                <span id="fvGorjetaManualPreview" class="am-hint" style="color:#fbbf24;font-weight:700;">EUR 0,00</span>
                                <span class="am-hint">Modo manual — introduza o valor da gorjeta atribuída a este funcionário.</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="gorjeta_manual" id="fvGorjetaManual" value="0">
                        <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:10px;padding:10px 14px;font-size:.82rem;color:#93c5fd;margin-bottom:14px;">
                            <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
                            Gorjetas em modo automático — €<?php echo number_format($gorjetaAutoValor ?? 0, 2, ',', '.'); ?> por funcionário.
                        </div>
                        <?php endif; ?>
                        <input type="hidden" id="fvGorjetaBaseAtual" value="0">

                        <!-- Bloquear -->
                        <div class="am-section">
                            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;user-select:none;">
                                <input type="checkbox" name="is_locked" id="fvIsLocked" value="1"
                                    style="width:1rem;height:1rem;accent-color:#3b82f6;flex-shrink:0;"
                                    <?php echo $folhaFechada ? 'disabled' : ''; ?>>
                                <span class="am-lbl" style="margin:0;">Bloquear edição deste funcionário neste mês</span>
                            </label>
                        </div>

                        <?php if ($folhaFechada): ?>
                        <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:10px 14px;font-size:.83rem;color:#fbbf24;margin-bottom:8px;">
                            <i class="fas fa-lock" style="margin-right:.35rem;"></i>Este mês está fechado. Não são permitidas alterações.
                        </div>
                        <?php endif; ?>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel" id="cancelFolhaVariaveis">Cancelar</button>
                            <button type="submit" class="am-btn-submit" <?php echo $folhaFechada ? 'disabled style="opacity:.4;"' : ''; ?>>
                                <i class="fas fa-save"></i> Guardar Variáveis
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </section>
