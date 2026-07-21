<?php
// Secção "Definições" — incluída a partir de admin/dashboard.php (depende de $ADMIN_DIR, $pdo, $adminUser, $estHorario, $csrfToken, etc. já definidos lá). Inclui também os modais de horários e perfil de admin, que ficavam soltos após esta secção.
?>
        <section id="definicoes-section" class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <span>Definições do Sistema</span>
                </div>
            </div>

            <?php
            $horariosSaveAttempted = isset($_GET['horarios_saved']);
            $horariosSaved = $horariosSaveAttempted && $_GET['horarios_saved'] === '1';
            ?>

            <?php if ($payrollConfigSaveAttempted): ?>
            <div
                style="margin:0 0 1rem; padding:.75rem .9rem; border-radius:10px; <?php echo $payrollConfigSaved ? 'background:#dcfce7; color:#166534; border:1px solid #86efac;' : 'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;'; ?>">
                <?php echo $payrollConfigSaved ? 'Configuração salarial guardada com sucesso.' : 'Não foi possível guardar a configuração salarial. Verifique os dados e tente novamente.'; ?>
            </div>
            <?php endif; ?>

            <?php if ($horariosSaveAttempted): ?>
            <div
                style="margin:0 0 1rem; padding:.75rem .9rem; border-radius:10px; <?php echo $horariosSaved ? 'background:#dcfce7; color:#166534; border:1px solid #86efac;' : 'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;'; ?>">
                <?php echo $horariosSaved ? 'Horários do estabelecimento guardados com sucesso.' : 'Não foi possível guardar os horários do estabelecimento.'; ?>
            </div>
            <?php endif; ?>

            <?php
            $planStatusRaw = strtolower((string)($trialSubscriptionStatus ?? 'trial'));
            $planStatusLabel = 'Período de Teste';
            $planStatusColor = '#92400e';
            $planStatusBg = '#fef3c7';
            if ($planStatusRaw === 'active') {
                $planStatusLabel = 'Ativo';
                $planStatusColor = '#166534';
                $planStatusBg = '#dcfce7';
            } elseif ($planStatusRaw === 'blocked') {
                $planStatusLabel = 'Pagamento em Atraso';
                $planStatusColor = '#991b1b';
                $planStatusBg = '#fee2e2';
            } elseif ($planStatusRaw === 'inactive') {
                $planStatusLabel = 'Inativo';
                $planStatusColor = '#374151';
                $planStatusBg = '#f3f4f6';
            } elseif (!empty($trialExpired)) {
                $planStatusLabel = 'Período de Teste Expirado';
                $planStatusColor = '#991b1b';
                $planStatusBg = '#fee2e2';
            }

            $planTrialText = '';
            if ($planStatusRaw === 'trial' && !empty($trialEndsAtIso)) {
                $trialEndTs = strtotime((string)$trialEndsAtIso);
                if ($trialEndTs !== false) {
                    $planTrialText = 'Termina em ' . date('d/m/Y \à\s H:i', $trialEndTs);
                }
            }
            ?>
            <style>
                #definicoes-section .card-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: var(--space-6);
                    margin-bottom: var(--space-12);
                    align-items: stretch;
                }
                @media (max-width: 1200px) { #definicoes-section .card-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 640px)  { #definicoes-section .card-grid { grid-template-columns: 1fr; } }
                #definicoes-section .info-card { display: flex; flex-direction: column; }
                #definicoes-section .card-content { flex: 1; display: flex; flex-direction: column; }
                #definicoes-section .settings-plan-rows { display: grid; gap: .4rem; font-size: .85rem; margin-bottom: .85rem; }
                #definicoes-section .settings-plan-row { display: flex; justify-content: space-between; gap: .5rem; }
                #definicoes-section .settings-plan-row span:first-child { color: var(--text-secondary); }
                #definicoes-section .settings-badge { display: inline-flex; align-items: center; align-self: flex-start; padding: .3rem .75rem; border-radius: 999px; font-size: .8rem; font-weight: 700; }
                #definicoes-section .settings-card-note { margin-top: .6rem; }
                #definicoes-section .settings-card-actions { margin-top: auto; padding-top: 1rem; display: flex; gap: .5rem; flex-wrap: wrap; }
                #definicoes-section .settings-card-actions form { display: inline; }
                #definicoes-section .info-card--soon { opacity: .7; }
                #definicoes-section .settings-soon-badge { display: inline-flex; align-items: center; padding: .15rem .55rem; border-radius: 999px; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; background: var(--neutral-100, #f3f4f6); color: var(--text-secondary, #6b7280); margin-left: .5rem; }
            </style>
            <div class="card-grid">

                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon" style="background: #ede9fe; color: #6d28d9;">
                            <i class="fas fa-crown"></i>
                        </div>
                        <h3 class="card-title">Plano e Assinatura</h3>
                    </div>
                    <div class="card-content">
                        <div class="settings-plan-rows">
                            <div class="settings-plan-row">
                                <span>Plano atual</span>
                                <strong><?php echo htmlspecialchars($subscriptionPlanName !== '' ? $subscriptionPlanName : 'RHNeto Pro Premium'); ?></strong>
                            </div>
                            <div class="settings-plan-row">
                                <span>Preço</span>
                                <strong>2,00 € / mês</strong>
                            </div>
                            <?php if ($planStatusRaw === 'active' && $subscriptionRenewsAtIso !== ''): ?>
                            <div class="settings-plan-row">
                                <span>Próxima renovação</span>
                                <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($subscriptionRenewsAtIso))); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="settings-badge" style="background:<?php echo $planStatusBg; ?>; color:<?php echo $planStatusColor; ?>;">
                            <?php echo htmlspecialchars($planStatusLabel); ?>
                        </div>
                        <?php if ($planTrialText !== ''): ?>
                        <p class="settings-card-note"><?php echo htmlspecialchars($planTrialText); ?></p>
                        <?php elseif ($planStatusRaw === 'active'): ?>
                        <p class="settings-card-note">A sua assinatura está ativa. Obrigado por usar o RHNeto Pro.</p>
                        <?php else: ?>
                        <p class="settings-card-note">Assine para continuar a usar o painel sem interrupções.</p>
                        <?php endif; ?>
                        <div class="settings-card-actions">
                            <a href="../planos/" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem; text-decoration:none; display:inline-flex;">
                                <i class="fas fa-bolt"></i>
                                <span><?php echo $planStatusRaw === 'active' ? 'Ver plano' : 'Assinar agora'; ?></span>
                            </a>
                            <?php if ($subscriptionStripeCustomerId !== ''): ?>
                            <form method="POST" action="../planos/create-portal-session.php">
                                <button type="submit" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Gerir Assinatura</span>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon" style="background: var(--primary-100); color: var(--primary-600);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="card-title">Horários do Estabelecimento</h3>
                    </div>
                    <div class="card-content">
                        <p>Defina horário padrão para cálculo automático de atraso no módulo de presença.</p>
                        <div class="settings-card-actions">
                            <button class="btn btn-primary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;"
                                onclick="document.getElementById('modalHorariosEstabelecimento').style.display='flex'">
                                <i class="fas fa-clock"></i>
                                <span>Configurar Horários</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon" style="background: var(--success-100); color: var(--success-600);">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                        <h3 class="card-title">Configuração de Registo Salarial</h3>
                    </div>
                    <div class="card-content">
                        <p>Definir parâmetros básicos de registo mensal (sem cálculos fiscais automáticos).</p>
                        <div class="settings-card-actions">
                            <button class="btn btn-primary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;"
                                onclick="document.getElementById('modalConfiguracaoSalarial').style.display='flex'">
                                <i class="fas fa-cogs"></i>
                                <span>Configurar</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon" style="background: var(--primary-100); color: var(--primary-600);">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <h3 class="card-title">Perfil do Administrador</h3>
                    </div>
                    <div class="card-content">
                        <p>Alterar dados pessoais, palavra-passe e preferências de conta.</p>
                        <div class="settings-card-actions">
                            <button type="button" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;"
                                onclick="document.getElementById('modalAdminProfile').style.display='flex'">
                                <i class="fas fa-edit"></i>
                                <span>Editar Perfil</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="info-card info-card--soon">
                    <div class="card-header">
                        <div class="card-icon" style="background: var(--secondary-100); color: var(--secondary-600);">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="card-title">Dados da Empresa <span class="settings-soon-badge">Em breve</span></h3>
                    </div>
                    <div class="card-content">
                        <p>Configurar informações da empresa, logotipo e dados fiscais.</p>
                        <div class="settings-card-actions">
                            <button type="button" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;" disabled title="Funcionalidade ainda não disponível">
                                <i class="fas fa-lock"></i>
                                <span>Configurar</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon" style="background: #e8f4ff; color: #1d4ed8;">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3 class="card-title">Notificações e Mensagens</h3>
                    </div>
                    <div class="card-content">
                        <p>Aceda rapidamente às notificações e à lista de mensagens enviadas para gerir envios no app.</p>
                        <div class="settings-card-actions">
                            <button type="button" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;"
                                onclick="openNotificationsSentSection()">
                                <i class="fas fa-paper-plane"></i>
                                <span>Ver mensagens enviadas</span>
                            </button>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <!-- Modal: Configuração de Registo Salarial -->
            <div id="modalConfiguracaoSalarial" class="modal"
                style="display:none; align-items:flex-start; justify-content:center; padding:24px 16px 48px; overflow-y:auto;">
                <div class="am-sheet" style="max-width:920px;">
                    <button type="button" id="btnClosePayrollModal" class="am-close" aria-label="Fechar">&times;</button>
                    <div class="am-header">
                        <div class="am-header-icon"><i class="fas fa-sliders-h"></i></div>
                        <div>
                            <h2 class="am-title">Configuração de Registo Salarial</h2>
                            <p class="am-subtitle">Regras básicas de registo mensal — cálculos fiscais e legais ficam a cargo do contabilista</p>
                        </div>
                    </div>

                    <form id="formPayrollConfig" method="post" action="dashboard.php?section=definicoes">
                        <input type="hidden" name="action" value="save_payroll_config">
                        <input type="hidden" name="config_ano_hidden" id="payrollConfigAnoHidden"
                            value="<?php echo (int)$payrollConfigYear; ?>">

                        <!-- ── BLOCO 1: Ano Fiscal ── -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-calendar-alt"></i> Ano Fiscal</div>
                            <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                                <select name="config_year" id="payrollConfigYearSelect" class="am-inp am-sel"
                                    style="max-width:160px;">
                                    <?php for ($y = $currentYear + 1; $y >= $currentYear - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>"
                                        <?php echo $y === (int)$payrollConfigYear ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <small class="am-hint">Ao alterar o ano, as configurações
                                    correspondentes
                                    são carregadas automaticamente.</small>
                            </div>
                        </div>

                        <!-- ── BLOCO 2: Segurança Social (oculto no modo simples) ── -->
                        <div class="am-section" style="display:none;">
                            <div class="am-sec-lbl"><i class="fas fa-shield-alt"></i> Segurança Social</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl">Taxa do Trabalhador (%)</label>
                                    <input class="am-inp" type="number" step="0.01" min="0" max="100" name="social_security_rate"
                                        value="<?php echo htmlspecialchars(number_format((float)($payrollAdminTaxRules['social_security_rate'] ?? 0) * 100, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <small class="am-hint">Em Portugal: 11,00%</small>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl">Taxa da Entidade Patronal (%)</label>
                                    <input class="am-inp" type="number" step="0.01" min="0" max="100"
                                        name="employer_social_security_rate"
                                        value="<?php echo htmlspecialchars(number_format((float)($payrollAdminTaxRules['employer_social_security_rate'] ?? 0) * 100, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <small class="am-hint">Em Portugal: 23,75%</small>
                                </div>
                            </div>
                        </div>

                        <!-- ── BLOCO 3: Subsídios e Extras ── -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-coins"></i> Subsídios e Extras</div>
                            <div class="am-g3">
                                <div class="am-f">
                                    <label class="am-lbl">Subsídio de Alimentação Padrão (€)</label>
                                    <input class="am-inp" type="number" step="0.01" min="0" name="default_subsidios"
                                        value="<?php echo htmlspecialchars(number_format((float)($payrollAdminConfig['default_subsidios'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        placeholder="0.00">
                                    <small class="am-hint">Valor aplicado quando não definido
                                        individualmente.</small>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl">Fator de Horas Extras</label>
                                    <input class="am-inp" type="number" step="0.01" min="1.00" max="5.00" name="fator_horas_extra"
                                        value="<?php echo htmlspecialchars(number_format(max(1.0, (float)($payrollAdminConfig['default_horas_extra'] ?? 1.0)), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <small class="am-hint">1.25 = 25% acima da hora normal. Em PT:
                                        1.25
                                        (1.ªs 60h/ano), 1.625 (excedentes).</small>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl">Bónus Padrão (€)</label>
                                    <input class="am-inp" type="number" step="0.01" min="0" name="default_bonus"
                                        value="<?php echo htmlspecialchars(number_format((float)($payrollAdminConfig['default_bonus'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        placeholder="0.00">
                                    <small class="am-hint">Bónus periódico aplicado a todos os
                                        colaboradores.</small>
                                </div>
                            </div>
                        </div>

                        <!-- ── BLOCO 4: Escalões de IRS (oculto no modo simples) ── -->
                        <div class="am-section" style="display:none;">
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; gap:.8rem; flex-wrap:wrap; margin-bottom:.75rem;">
                                <div class="am-sec-lbl" style="margin-bottom:0; padding-bottom:0; border-bottom:none;"><i class="fas fa-table"></i> Escalões de IRS</div>
                                <button type="button" class="am-btn-cancel" id="btnAddIrsBracket"
                                    style="padding:.45rem .85rem; font-size:.85rem;">
                                    <i class="fas fa-plus"></i> Adicionar Escalão
                                </button>
                            </div>
                            <p style="margin:0 0 .75rem; font-size:.8rem; color:#94a3b8;">
                                Fórmula: <strong>IRS = (Rendimento × Taxa) − Parcela a Abater</strong>. O último escalão
                                sem
                                máximo aplica-se a rendimentos superiores.
                                Os intervalos não podem sobrepor-se.
                            </p>
                            <div id="irsOverlapError" class="am-error" style="display:none; margin-bottom:.75rem;">
                            </div>
                            <div style="overflow:auto; border:1px solid rgba(255,255,255,.1); border-radius:8px;">
                                <table style="width:100%; border-collapse:collapse; min-width:680px;">
                                    <thead>
                                        <tr style="background:rgba(255,255,255,.05);">
                                            <th
                                                style="padding:.65rem .75rem; text-align:left; font-size:.825rem; white-space:nowrap; color:#cbd5e1;">
                                                Mínimo (€)</th>
                                            <th
                                                style="padding:.65rem .75rem; text-align:left; font-size:.825rem; white-space:nowrap; color:#cbd5e1;">
                                                Máximo (€)</th>
                                            <th
                                                style="padding:.65rem .75rem; text-align:left; font-size:.825rem; white-space:nowrap; color:#cbd5e1;">
                                                Taxa (%)</th>
                                            <th
                                                style="padding:.65rem .75rem; text-align:left; font-size:.825rem; white-space:nowrap; color:#cbd5e1;">
                                                Parcela a Abater (€)</th>
                                            <th style="padding:.65rem .75rem; width:60px; text-align:center;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="irsBracketsBody">
                                        <?php
                                $adminBrackets = $payrollAdminTaxRules['brackets'] ?? [];
                                if (empty($adminBrackets)) {
                                    $adminBrackets = [['min_amount' => 0, 'max_amount' => null, 'rate' => 0.0, 'parcela_abater' => 0.0]];
                                }
                                foreach ($adminBrackets as $br):
                                    $brMax = ($br['max_amount'] !== null && $br['max_amount'] !== '') ? htmlspecialchars(number_format((float)$br['max_amount'], 2, '.', ''), ENT_QUOTES, 'UTF-8') : '';
                                    // Rate stored as decimal (0.145) → display as % (14.50)
                                    $brRate = htmlspecialchars(number_format((float)($br['rate'] ?? 0) * 100, 2, '.', ''), ENT_QUOTES, 'UTF-8');
                                ?>
                                        <tr class="irs-bracket-row">
                                            <td style="padding:.4rem .5rem;">
                                                <input type="number" step="0.01" min="0" name="irs_min[]"
                                                    value="<?php echo htmlspecialchars(number_format((float)($br['min_amount'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    class="am-inp" style="width:100%;">
                                            </td>
                                            <td style="padding:.4rem .5rem;">
                                                <input type="number" step="0.01" min="0" name="irs_max[]"
                                                    value="<?php echo $brMax; ?>" class="am-inp"
                                                    style="width:100%;" placeholder="Sem limite">
                                            </td>
                                            <td style="padding:.4rem .5rem;">
                                                <div style="display:flex; align-items:center; gap:.3rem;">
                                                    <input type="number" step="0.01" min="0" max="100" name="irs_taxa[]"
                                                        value="<?php echo $brRate; ?>" class="am-inp"
                                                        style="width:100%;">
                                                    <span
                                                        style="white-space:nowrap; color:#94a3b8; font-size:.85rem;">%</span>
                                                </div>
                                            </td>
                                            <td style="padding:.4rem .5rem;">
                                                <input type="number" step="0.01" min="0" name="irs_parcela[]"
                                                    value="<?php echo htmlspecialchars(number_format((float)($br['parcela_abater'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    class="am-inp" style="width:100%;">
                                            </td>
                                            <td style="padding:.4rem .5rem; text-align:center;">
                                                <button type="button"
                                                    class="btn btn-danger btn-sm btn-remove-irs-bracket"
                                                    title="Remover escalão">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ── BLOCO 5: Gorjetas ── -->
                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-hand-holding-usd"></i> Gorjetas</div>

                            <!-- Toggle switch -->
                            <div
                                style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem; padding:.75rem 1rem; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:10px; margin-bottom:.85rem;">
                                <div>
                                    <div style="font-weight:600; font-size:.9rem; color:#e2e8f0;">Dividir gorjetas
                                        automaticamente</div>
                                    <div style="font-size:.78rem; color:#64748b; margin-top:.2rem;">
                                        <strong>ON</strong> — inserir total do mês e dividir igualmente pelos
                                        funcionários
                                        ativos.<br>
                                        <strong>OFF</strong> — inserir o valor individualmente em cada funcionário na
                                        folha.
                                    </div>
                                </div>
                                <label
                                    style="display:flex; align-items:center; gap:.55rem; cursor:pointer; user-select:none; flex-shrink:0;">
                                    <div style="position:relative; width:48px; height:26px;">
                                        <input type="checkbox" name="gorjetas_auto_split" id="chkGorjetasAutoSplit"
                                            value="1"
                                            <?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? 'checked' : ''; ?>
                                            style="opacity:0; width:0; height:0; position:absolute;">
                                        <span id="toggleGorjetasTrack"
                                            style="position:absolute; inset:0; border-radius:26px; cursor:pointer; transition:.3s;
                                background:<?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? '#2563eb' : '#475569'; ?>;"></span>
                                        <span id="toggleGorjetasThumb"
                                            style="position:absolute; left:<?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? '24px' : '2px'; ?>;
                                top:2px; width:22px; height:22px; border-radius:50%; background:#fff; transition:.3s; box-shadow:0 1px 3px rgba(0,0,0,.3);"></span>
                                    </div>
                                    <span id="lblGorjetasToggle"
                                        style="font-weight:700; font-size:.9rem; color:<?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? '#3b82f6' : '#64748b'; ?>;">
                                        <?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? 'ON' : 'OFF'; ?>
                                    </span>
                                </label>
                            </div>

                            <!-- Campo total gorjetas (só visível quando ON) -->
                            <div id="gorjetasTotalBlock"
                                style="display:<?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? 'grid' : 'none'; ?>; gap:.35rem;">
                                <label class="am-lbl">Total de Gorjetas do
                                    Mês
                                    (€)</label>
                                <div style="display:flex; align-items:center; gap:.5rem;">
                                    <input class="am-inp" type="number" step="0.01" min="0" name="gorjetas_total_mes"
                                        id="gorjetasTotalMes"
                                        value="<?php echo htmlspecialchars(number_format((float)($payrollAdminConfig['gorjetas_total_mes'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        style="max-width:200px;" placeholder="0.00">
                                    <span style="color:#94a3b8; font-weight:700;">€</span>
                                </div>
                                <small class="am-hint">
                                    Valor dividido igualmente por todos os funcionários ativos no período.
                                    <?php
                            $nAtivos = 0;
                            if (isset($employees) && is_array($employees)) {
                                foreach ($employees as $_e) {
                                    $st = mb_strtolower(trim((string)($_e['status'] ?? '')));
                                    if (in_array($st, ['active', 'ativo', 'ativa'], true)) $nAtivos++;
                                }
                            }
                            if ($nAtivos > 0) {
                                $valorPorEmp = (float)($payrollAdminConfig['gorjetas_total_mes'] ?? 0);
                                echo htmlspecialchars('Atualmente ' . $nAtivos . ' funcionários ativos' .
                                    ($valorPorEmp > 0 ? ' → ' . number_format($valorPorEmp / $nAtivos, 2, ',', '.') . ' € / funcionário.' : '.'), ENT_QUOTES, 'UTF-8');
                            }
                            ?>
                                </small>
                            </div>
                            <div id="gorjetasManualInfo"
                                style="display:<?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? 'none' : 'flex'; ?>; align-items:center; gap:.5rem; padding:.6rem .8rem; background:rgba(217,119,6,.12); border:1px solid rgba(217,119,6,.3); border-radius:8px; font-size:.82rem; color:#fbbf24;">
                                <i class="fas fa-info-circle"></i>
                                Modo manual: insira a gorjeta de cada funcionário clicando em <strong>"Editar"</strong>
                                na folha
                                de pagamento.
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel" id="btnCancelPayrollModal">Cancelar</button>
                            <button type="submit" class="am-btn-submit" id="btnSubmitPayrollConfig">
                                <i class="fas fa-save"></i> Guardar Configuração
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            (function restoreSolicitacoesTableFromQuery() {
                var params = new URLSearchParams(window.location.search);
                if (params.get('section') !== 'solicitacoes') {
                    return;
                }

                var solicitacaoCard = (params.get('solicitacao_card') || '').toLowerCase();
                var cardToTableMap = {
                    justificativas: 'solicitacoes-justificativas-table',
                    presenca: 'solicitacoes-presenca-table',
                    gorjetas: 'solicitacoes-gorjetas-table',
                    ferias: 'solicitacoes-ferias-table',
                    trocas_turno: 'solicitacoes-trocas-turno-table',
                    historico: 'solicitacoes-decididas-table'
                };

                var tableId = cardToTableMap[solicitacaoCard];
                if (!tableId) {
                    return;
                }

                document.querySelectorAll('.data-table.solicitacoes-table[id^="solicitacoes-"]').forEach(function(tbl) {
                    tbl.style.display = 'none';
                });

                var table = document.getElementById(tableId);
                if (table) {
                    table.style.display = '';
                }

                document.querySelectorAll('.stats-grid .solicitacao-card-btn').forEach(function(btn) {
                    btn.classList.remove('active-solicitacao-card');
                    var onclickAttr = btn.getAttribute('onclick') || '';
                    if (onclickAttr.includes(tableId)) {
                        btn.classList.add('active-solicitacao-card');
                    }
                });
            })();

            (function persistSolicitacoesScrollPosition() {
                var SCROLL_KEY = 'dashboard_solicitacoes_scroll_y';
                var params = new URLSearchParams(window.location.search);
                var isSolicitacoesPage = params.get('section') === 'solicitacoes';

                if (isSolicitacoesPage) {
                    var savedScrollY = parseInt(sessionStorage.getItem(SCROLL_KEY) || '', 10);
                    if (!Number.isNaN(savedScrollY) && savedScrollY >= 0) {
                        // Espera o layout estabilizar antes de reposicionar o viewport.
                        window.requestAnimationFrame(function() {
                            window.requestAnimationFrame(function() {
                                window.scrollTo(0, savedScrollY);
                                sessionStorage.removeItem(SCROLL_KEY);
                            });
                        });
                    }
                }

                document.querySelectorAll('form[action*="section=solicitacoes"], form[action*="review_justificativa.php"]').forEach(function(form) {
                    form.addEventListener('submit', function() {
                        sessionStorage.setItem(SCROLL_KEY, String(window.scrollY || window.pageYOffset || 0));
                        // Mantém o admin na secção de Solicitações após aprovar/rejeitar,
                        // em vez de cair na secção padrão depois do reload da página.
                        sessionStorage.setItem('activeSection', 'solicitacoes');
                    });
                });
            })();

            (function() {
                'use strict';

                var modal = document.getElementById('modalConfiguracaoSalarial');
                var form = document.getElementById('formPayrollConfig');
                var yearSel = document.getElementById('payrollConfigYearSelect');
                var overlapEl = document.getElementById('irsOverlapError');

                function closeModal() {
                    if (modal) modal.style.display = 'none';
                }

                // Fechar modal
                if (modal) {
                    document.getElementById('btnClosePayrollModal') && document.getElementById(
                            'btnClosePayrollModal')
                        .addEventListener('click', closeModal);
                    document.getElementById('btnCancelPayrollModal') && document.getElementById(
                            'btnCancelPayrollModal')
                        .addEventListener('click', closeModal);
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) closeModal();
                    });
                }

                // Auto-reload ao mudar o ano
                if (yearSel) {
                    yearSel.addEventListener('change', function() {
                        var y = encodeURIComponent(this.value);
                        window.location.href = 'dashboard.php?section=definicoes&config_ano=' + y;
                    });
                }

                // ── Toggle Gorjetas ──
                var chkGorjeta = document.getElementById('chkGorjetasAutoSplit');
                var trackEl = document.getElementById('toggleGorjetasTrack');
                var thumbEl = document.getElementById('toggleGorjetasThumb');
                var lblToggle = document.getElementById('lblGorjetasToggle');
                var totalBlock = document.getElementById('gorjetasTotalBlock');
                var manualInfo = document.getElementById('gorjetasManualInfo');

                function updateGorjetaToggle() {
                    if (!chkGorjeta) return;
                    var on = chkGorjeta.checked;
                    if (trackEl) trackEl.style.background = on ? '#2563eb' : '#d1d5db';
                    if (thumbEl) thumbEl.style.left = on ? '24px' : '2px';
                    if (lblToggle) {
                        lblToggle.textContent = on ? 'ON' : 'OFF';
                        lblToggle.style.color = on ? '#2563eb' : '#9ca3af';
                    }
                    if (totalBlock) totalBlock.style.display = on ? 'grid' : 'none';
                    if (manualInfo) manualInfo.style.display = on ? 'none' : 'flex';
                }

                if (chkGorjeta) {
                    // Clicar no track/thumb também alterna
                    [trackEl, thumbEl].forEach(function(el) {
                        if (el) el.addEventListener('click', function() {
                            chkGorjeta.checked = !chkGorjeta.checked;
                            updateGorjetaToggle();
                        });
                    });
                    chkGorjeta.addEventListener('change', updateGorjetaToggle);
                }

                // Validação de sobreposição antes de submeter
                if (form) {
                    form.addEventListener('submit', function(e) {
                        if (!overlapEl) return;
                        overlapEl.style.display = 'none';
                        overlapEl.textContent = '';

                        var rows = document.querySelectorAll('#irsBracketsBody tr.irs-bracket-row');
                        var brackets = [];
                        rows.forEach(function(tr) {
                            var minV = parseFloat(tr.querySelector('[name="irs_min[]"]').value) ||
                                0;
                            var maxRaw = tr.querySelector('[name="irs_max[]"]').value.trim();
                            var maxV = maxRaw === '' ? null : parseFloat(maxRaw);
                            brackets.push({
                                min: minV,
                                max: maxV
                            });
                        });

                        // Ordenar por mínimo
                        brackets.sort(function(a, b) {
                            return a.min - b.min;
                        });

                        var error = '';
                        for (var i = 0; i < brackets.length - 1; i++) {
                            var curr = brackets[i];
                            var next = brackets[i + 1];
                            if (curr.max !== null && curr.max > next.min) {
                                error = 'Sobreposição detetada: escalão com máximo ' + curr.max.toFixed(2) +
                                    ' € sobrepõe-se com o escalão seguinte que começa em ' + next.min
                                    .toFixed(2) +
                                    ' €. Corrija os intervalos antes de guardar.';
                                break;
                            }
                        }
                        if (error) {
                            e.preventDefault();
                            overlapEl.textContent = error;
                            overlapEl.style.display = 'block';
                            overlapEl.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                    });
                }

                <?php if ($payrollConfigSaveAttempted): ?>
                // Auto-abrir modal após tentativa de guardar
                if (modal) modal.style.display = 'flex';
                <?php endif; ?>

                // Confirmacao SweetAlert para fechar folha
                var closeFolhaForm = document.getElementById('closeFolhaForm');
                if (closeFolhaForm) {
                    closeFolhaForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        var pendingCount = parseInt(closeFolhaForm.dataset.pendingCount || '0', 10) || 0;
                        var totalCount = parseInt(closeFolhaForm.dataset.totalCount || '0', 10) || 0;
                        var confirmText =
                            'Tem certeza que deseja fechar a folha? Apos isso, nao sera possivel editar os dados.';

                        if (totalCount <= 0) {
                            if (typeof showWarning === 'function') {
                                showWarning('Nao e possivel fechar a folha sem funcionarios.');
                            }
                            return;
                        }

                        if (pendingCount > 0) {
                            confirmText = 'Existem ' + pendingCount +
                                ' pagamento(s) pendente(s). Tem certeza que deseja fechar a folha? Apos isso, nao sera possivel editar os dados.';
                        }

                        if (typeof showConfirm === 'function') {
                            showConfirm(
                                'Fechar folha deste mes?',
                                confirmText,
                                'Sim, fechar folha',
                                'Cancelar'
                            ).then(function(confirmed) {
                                if (confirmed) {
                                    closeFolhaForm.submit();
                                }
                            });
                            return;
                        }

                        Swal.fire({
                            title: 'Fechar folha deste mes?',
                            text: confirmText,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#f59e0b',
                            cancelButtonColor: '#6b7280',
                            confirmButtonText: 'Sim, fechar folha',
                            cancelButtonText: 'Cancelar'
                        }).then(function(result) {
                            if (result.isConfirmed) {
                                closeFolhaForm.submit();
                            }
                        });
                    });
                }

                var reopenFolhaForm = document.getElementById('reopenFolhaForm');
                if (reopenFolhaForm) {
                    reopenFolhaForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        if (typeof showConfirm === 'function') {
                            showConfirm(
                                'Reabrir folha deste mes?',
                                'Tem certeza que deseja reabrir a folha? Isto permitira correcoes e novas alteracoes nos dados.',
                                'Sim, reabrir folha',
                                'Cancelar'
                            ).then(function(confirmed) {
                                if (confirmed) {
                                    reopenFolhaForm.submit();
                                }
                            });
                            return;
                        }

                        Swal.fire({
                            title: 'Reabrir folha deste mes?',
                            text: 'Tem certeza que deseja reabrir a folha? Isto permitira correcoes e novas alteracoes nos dados.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#dc2626',
                            cancelButtonColor: '#6b7280',
                            confirmButtonText: 'Sim, reabrir folha',
                            cancelButtonText: 'Cancelar'
                        }).then(function(result) {
                            if (result.isConfirmed) {
                                reopenFolhaForm.submit();
                            }
                        });
                    });
                }

                // Feedback apos fechar folha (sucesso/erro)
                var searchParams = new URLSearchParams(window.location.search);
                var folhaClose = searchParams.get('folha_close');
                var folhaCloseReason = searchParams.get('folha_close_reason');
                if (folhaClose === '1') {
                    if (typeof showSuccess === 'function') {
                        showSuccess('Folha fechada com sucesso. Mes bloqueado e historico preservado.');
                    } else {
                        Swal.fire({
                            title: 'Folha fechada com sucesso',
                            text: 'O mes foi bloqueado e os valores historicos foram preservados.',
                            icon: 'success',
                            confirmButtonColor: '#16a34a'
                        });
                    }
                } else if (folhaClose === '0') {
                    var closeErrorMessage = 'Nao foi possivel fechar a folha. Verifique e tente novamente.';
                    if (folhaCloseReason === 'no_employees') {
                        closeErrorMessage = 'Nao e possivel fechar a folha sem funcionarios.';
                    } else if (folhaCloseReason === 'sync_error') {
                        closeErrorMessage = 'Nao foi possivel preparar os calculos da folha antes do fecho.';
                    } else if (folhaCloseReason === 'no_payroll_rows' || folhaCloseReason ===
                        'missing_calculations') {
                        closeErrorMessage = 'Existem salarios por calcular antes de fechar a folha.';
                    } else if (folhaCloseReason === 'invalid_calculations') {
                        closeErrorMessage = 'Existem calculos incompletos ou invalidos na folha.';
                    }

                    if (typeof showError === 'function') {
                        showError(closeErrorMessage);
                    } else {
                        Swal.fire({
                            title: 'Nao foi possivel fechar a folha',
                            text: closeErrorMessage,
                            icon: 'error',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                }

                var folhaReopen = searchParams.get('folha_reopen');
                if (folhaReopen === '1') {
                    if (typeof showSuccess === 'function') {
                        showSuccess('Folha reaberta com sucesso. O mes voltou a ficar editavel.');
                    }
                } else if (folhaReopen === '0') {
                    if (typeof showError === 'function') {
                        showError('Nao foi possivel reabrir a folha.');
                    }
                }

                if (searchParams.get('error') === 'permissao' && typeof showError === 'function') {
                    showError('Apenas administradores podem reabrir a folha.');
                }
            }());
            </script>
        </section>

    <div id="modalHorariosEstabelecimento" class="modal" style="display:none; overflow-y:auto; padding:24px 16px 48px;">
        <div class="am-sheet" style="max-width:480px;">
            <button type="button" id="btnCloseHorariosModal" class="am-close" aria-label="Fechar">&times;</button>
            <div class="am-header">
                <div class="am-header-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <h2 class="am-title">Horários do Estabelecimento</h2>
                    <p class="am-subtitle">Usados para cálculo de atrasos e controlo de presença</p>
                </div>
            </div>
            <form method="post" action="dashboard.php?section=definicoes">
                <input type="hidden" name="action" value="save_estabelecimento_horarios">
                <div class="am-section">
                    <div class="am-g2">
                        <div class="am-f">
                            <label class="am-lbl">Abertura</label>
                            <input class="am-inp" type="time" name="hora_abertura"
                                value="<?php echo htmlspecialchars(substr((string)$estHorario['hora_abertura'], 0, 5)); ?>"
                                required>
                        </div>
                        <div class="am-f">
                            <label class="am-lbl">Encerramento</label>
                            <input class="am-inp" type="time" name="hora_encerramento"
                                value="<?php echo htmlspecialchars(substr((string)$estHorario['hora_encerramento'], 0, 5)); ?>"
                                required>
                        </div>
                        <div class="am-f">
                            <label class="am-lbl">Entrada Padrão Funcionários</label>
                            <input class="am-inp" type="time" name="hora_entrada_padrao"
                                value="<?php echo htmlspecialchars(substr((string)$estHorario['hora_entrada_padrao'], 0, 5)); ?>"
                                required>
                        </div>
                        <div class="am-f">
                            <label class="am-lbl">Tolerância de atraso (min)</label>
                            <input class="am-inp" type="number" min="0" max="180" name="tolerancia_atraso_min"
                                value="<?php echo (int)$estHorario['tolerancia_atraso_min']; ?>" required>
                        </div>
                    </div>
                </div>
                <div class="am-footer">
                    <button type="button" class="am-btn-cancel" id="btnCancelHorariosModal">Cancelar</button>
                    <button type="submit" class="am-btn-submit">
                        <i class="fas fa-save"></i> Guardar Horários
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
    (function() {
        var modal = document.getElementById('modalHorariosEstabelecimento');
        if (!modal) return;

        function closeModal() {
            modal.style.display = 'none';
        }

        var btnClose = document.getElementById('btnCloseHorariosModal');
        var btnCancel = document.getElementById('btnCancelHorariosModal');
        if (btnClose) btnClose.addEventListener('click', closeModal);
        if (btnCancel) btnCancel.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
    })();
    </script>

    <div id="modalAdminProfile" class="modal"
        style="display:none; align-items:flex-start; justify-content:center; padding:24px 16px 48px; overflow-y:auto;">
        <div class="am-sheet" style="max-width:480px; margin-top:2.5rem;">
            <button type="button" id="btnCloseAdminProfileModal" class="am-close" aria-label="Fechar">&times;</button>
            <div class="am-header">
                <div class="am-header-icon"><i class="fas fa-user-cog"></i></div>
                <div>
                    <h2 class="am-title">Editar Perfil do Administrador</h2>
                    <p class="am-subtitle">Dados pessoais, palavra-passe e preferências de conta</p>
                </div>
            </div>
            <form method="post" action="dashboard.php?section=definicoes">
                <input type="hidden" name="action" value="save_admin_profile">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <div class="am-section">
                    <div class="am-f am-f-full">
                        <label class="am-lbl">Nome</label>
                        <input class="am-inp" type="text" name="admin_nome"
                            value="<?php echo htmlspecialchars($adminUser['name'] ?? ''); ?>" required>
                    </div>
                    <div class="am-f am-f-full">
                        <label class="am-lbl">Email</label>
                        <input class="am-inp" type="email" name="admin_email"
                            value="<?php echo htmlspecialchars($adminUser['email'] ?? ''); ?>" required>
                    </div>
                    <div class="am-f am-f-full">
                        <label class="am-lbl">Telefone</label>
                        <input class="am-inp" type="text" name="admin_telefone"
                            value="<?php echo htmlspecialchars($adminUser['phone'] ?? ''); ?>">
                    </div>
                    <div class="am-f am-f-full">
                        <label class="am-lbl">Nova Palavra-passe</label>
                        <input class="am-inp" type="password" name="admin_nova_senha"
                            placeholder="Deixe em branco para não alterar">
                    </div>
                    <div class="am-f am-f-full">
                        <label class="am-lbl">Confirmar Nova Palavra-passe</label>
                        <input class="am-inp" type="password" name="admin_confirmar_senha"
                            placeholder="Confirme a nova palavra-passe">
                    </div>
                </div>
                <div class="am-footer">
                    <button type="button" class="am-btn-cancel" id="btnCancelAdminProfileModal">Cancelar</button>
                    <button type="submit" class="am-btn-submit">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
