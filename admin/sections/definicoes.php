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
            <div class="card-grid">

                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon" style="background: #ede9fe; color: #6d28d9;">
                            <i class="fas fa-crown"></i>
                        </div>
                        <h3 class="card-title">Plano e Assinatura</h3>
                    </div>
                    <div class="card-content">
                        <div style="display:grid; gap:.4rem; font-size:.85rem; margin-bottom:.75rem;">
                            <div style="display:flex; justify-content:space-between; gap:.5rem;">
                                <span style="color:var(--text-secondary);">Plano atual</span>
                                <strong><?php echo htmlspecialchars($subscriptionPlanName !== '' ? $subscriptionPlanName : 'RHNeto Pro Premium'); ?></strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; gap:.5rem;">
                                <span style="color:var(--text-secondary);">Preço</span>
                                <strong>2,00 € / mês</strong>
                            </div>
                            <?php if ($planStatusRaw === 'active' && $subscriptionRenewsAtIso !== ''): ?>
                            <div style="display:flex; justify-content:space-between; gap:.5rem;">
                                <span style="color:var(--text-secondary);">Próxima renovação</span>
                                <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($subscriptionRenewsAtIso))); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="display:inline-block; padding:.3rem .75rem; border-radius:999px; font-size:.8rem; font-weight:700; background:<?php echo $planStatusBg; ?>; color:<?php echo $planStatusColor; ?>;">
                            <?php echo htmlspecialchars($planStatusLabel); ?>
                        </div>
                        <?php if ($planTrialText !== ''): ?>
                        <p style="margin-top:.6rem;"><?php echo htmlspecialchars($planTrialText); ?></p>
                        <?php elseif ($planStatusRaw === 'active'): ?>
                        <p style="margin-top:.6rem;">A sua assinatura está ativa. Obrigado por usar o RHNeto Pro.</p>
                        <?php else: ?>
                        <p style="margin-top:.6rem;">Assine para continuar a usar o painel sem interrupções.</p>
                        <?php endif; ?>
                        <div style="margin-top: 1rem; display:flex; gap:.5rem; flex-wrap:wrap;">
                            <a href="../planos/" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem; text-decoration:none; display:inline-flex;">
                                <i class="fas fa-bolt"></i>
                                <span><?php echo $planStatusRaw === 'active' ? 'Ver plano' : 'Assinar agora'; ?></span>
                            </a>
                            <?php if ($subscriptionStripeCustomerId !== ''): ?>
                            <form method="POST" action="../planos/create-portal-session.php" style="display:inline;">
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
                        <div style="margin-top: 1rem;">
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
                        <div style="margin-top: 1rem;">
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
                        <div style="margin-top: 1rem;">
                            <button class="btn btn-primary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">
                                <i class="fas fa-edit"></i>
                                <span>Editar Perfil</span>
                            </button>
                            
                           
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon" style="background: var(--secondary-100); color: var(--secondary-600);">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="card-title">Dados da Empresa</h3>
                    </div>
                    <div class="card-content">
                        <p>Configurar informações da empresa, logotipo e dados fiscais.</p>
                        <div style="margin-top: 1rem;">
                            <button class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">
                                <i class="fas fa-edit"></i>
                                <span>Configurar</span>
                            </button>
                            
                            <script>
                            (function() {
                                var modal = document.getElementById('modalEmpresa');
                                // Seleciona o botão "Configurar" do card correto
                                var empresaCard = Array.from(document.querySelectorAll('.info-card')).find(function(
                                    card) {
                                    var title = card.querySelector('.card-title');
                                    return title && title.innerText.trim() === 'Dados da Empresa';
                                });
                                var btn = empresaCard ? empresaCard.querySelector('.btn.btn-secondary') : null;

                                function openModal() {
                                    if (modal) {
                                        modal.style.display = 'flex';
                                        window.scrollTo({
                                            top: 0,
                                            behavior: 'smooth'
                                        });
                                    }
                                }

                                function closeModal() {
                                    if (modal) modal.style.display = 'none';
                                }
                                document.getElementById('btnCloseEmpresaModal') && document.getElementById(
                                    'btnCloseEmpresaModal').addEventListener('click', closeModal);
                                document.getElementById('btnCancelEmpresaModal') && document.getElementById(
                                    'btnCancelEmpresaModal').addEventListener('click', closeModal);
                                if (modal) {
                                    modal.addEventListener('click', function(e) {
                                        if (e.target === modal) closeModal();
                                    });
                                }
                                if (btn) {
                                    btn.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        openModal();
                                    });
                                }
                            })();
                            </script>
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
                        <div style="margin-top: 1rem;">
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
                style="display:none; align-items:flex-start; justify-content:center; padding:2rem 1rem; overflow-y:auto;">
                <div class="modal-content"
                    style="max-width:920px; width:100%; margin:auto; max-height:92vh; overflow-y:auto; padding:1.75rem;">

                    <!-- Cabeçalho -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.25rem;">
                        <h3 style="margin:0; font-size:1.25rem; font-weight:700; color:var(--text-primary);">
                            <i class="fas fa-sliders-h" style="margin-right:.5rem; color:var(--primary-600);"></i>
                            Configuração de Registo Salarial
                        </h3>
                        <span id="btnClosePayrollModal"
                            style="font-size:1.75rem; line-height:1; cursor:pointer; color:var(--text-secondary); padding:.25rem .5rem;">&times;</span>
                    </div>
                    <p
                        style="margin:0 0 1.5rem; font-size:.85rem; color:var(--text-secondary); border-bottom:1px solid var(--neutral-200); padding-bottom:1rem;">
                        Defina apenas regras básicas de registo salarial mensal. Cálculos fiscais e legais devem ser
                        tratados externamente pelo contabilista.
                    </p>

                    <form id="formPayrollConfig" method="post" action="dashboard.php?section=definicoes"
                        style="display:grid; gap:1.5rem;">
                        <input type="hidden" name="action" value="save_payroll_config">
                        <input type="hidden" name="config_ano_hidden" id="payrollConfigAnoHidden"
                            value="<?php echo (int)$payrollConfigYear; ?>">

                        <!-- ── BLOCO 1: Ano Fiscal ── -->
                        <div
                                                    style="background:#f1f5f9; border:1px solid var(--neutral-200); border-radius:12px; padding:1rem 1.25rem;">
                            <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.75rem;">
                                <i class="fas fa-calendar-alt" style="color:var(--primary-600);"></i>
                                <span style="font-weight:700; font-size:.95rem;">Ano Fiscal</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                                <select name="config_year" id="payrollConfigYearSelect" class="search-input"
                                    style="max-width:160px;">
                                    <?php for ($y = $currentYear + 1; $y >= $currentYear - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>"
                                        <?php echo $y === (int)$payrollConfigYear ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <small style="color:var(--text-secondary);">Ao alterar o ano, as configurações
                                    correspondentes
                                    são carregadas automaticamente.</small>
                            </div>
                        </div>

                        <!-- ── BLOCO 2: Segurança Social (oculto no modo simples) ── -->
                        <div
                            style="display:none; background:var(--neutral-50,#f9fafb); border:1px solid var(--neutral-200); border-radius:12px; padding:1rem 1.25rem;">
                            <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.75rem;">
                                <i class="fas fa-shield-alt" style="color:#0284c7;"></i>
                                <span style="font-weight:700; font-size:.95rem;">Segurança Social</span>
                            </div>
                            <div
                                style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:.85rem;">
                                <div style="display:grid; gap:.35rem;">
                                    <label style="font-size:.875rem; font-weight:600;">Taxa do Trabalhador (%)</label>
                                    <div style="display:flex; align-items:center; gap:.4rem;">
                                        <input type="number" step="0.01" min="0" max="100" name="social_security_rate"
                                            value="<?php echo htmlspecialchars(number_format((float)($payrollAdminTaxRules['social_security_rate'] ?? 0) * 100, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            class="search-input" style="max-width:120px;">
                                        <span style="font-weight:700; color:var(--text-secondary);">%</span>
                                    </div>
                                    <small style="color:var(--text-secondary);">Em Portugal: 11,00%</small>
                                </div>
                                <div style="display:grid; gap:.35rem;">
                                    <label style="font-size:.875rem; font-weight:600;">Taxa da Entidade Patronal
                                        (%)</label>
                                    <div style="display:flex; align-items:center; gap:.4rem;">
                                        <input type="number" step="0.01" min="0" max="100"
                                            name="employer_social_security_rate"
                                            value="<?php echo htmlspecialchars(number_format((float)($payrollAdminTaxRules['employer_social_security_rate'] ?? 0) * 100, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            class="search-input" style="max-width:120px;">
                                        <span style="font-weight:700; color:var(--text-secondary);">%</span>
                                    </div>
                                    <small style="color:var(--text-secondary);">Em Portugal: 23,75%</small>
                                </div>
                            </div>
                        </div>

                        <!-- ── BLOCO 3: Subsídios e Extras ── -->
                        <div
                            style="background:var(--neutral-50,#f9fafb); border:1px solid var(--neutral-200); border-radius:12px; padding:1rem 1.25rem;">
                            <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.75rem;">
                                <i class="fas fa-coins" style="color:#16a34a;"></i>
                                <span style="font-weight:700; font-size:.95rem;">Subsídios e Extras</span>
                            </div>
                            <div
                                style="display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:.85rem; align-items:start;">
                                <div style="display:grid; gap:.35rem;">
                                    <label style="font-size:.875rem; font-weight:600;">Subsídio de Alimentação Padrão
                                        (€)</label>
                                    <input type="number" step="0.01" min="0" name="default_subsidios"
                                        value="<?php echo htmlspecialchars(number_format((float)($payrollAdminConfig['default_subsidios'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        class="search-input" placeholder="0.00"
                                        style="width:100%; max-width:100%; min-width:0; box-sizing:border-box;">
                                    <small style="color:var(--text-secondary);">Valor aplicado quando não definido
                                        individualmente.</small>
                                </div>
                                <div style="display:grid; gap:.35rem;">
                                    <label style="font-size:.875rem; font-weight:600;">Fator de Horas Extras</label>
                                    <input type="number" step="0.01" min="1.00" max="5.00" name="fator_horas_extra"
                                        value="<?php echo htmlspecialchars(number_format(max(1.0, (float)($payrollAdminConfig['default_horas_extra'] ?? 1.0)), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        class="search-input"
                                        style="width:100%; max-width:100%; min-width:0; box-sizing:border-box;">
                                    <small style="color:var(--text-secondary);">1.25 = 25% acima da hora normal. Em PT:
                                        1.25
                                        (1.ªs 60h/ano), 1.625 (excedentes).</small>
                                </div>
                                <div style="display:grid; gap:.35rem;">
                                    <label style="font-size:.875rem; font-weight:600;">Bónus Padrão (€)</label>
                                    <input type="number" step="0.01" min="0" name="default_bonus"
                                        value="<?php echo htmlspecialchars(number_format((float)($payrollAdminConfig['default_bonus'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        class="search-input" placeholder="0.00"
                                        style="width:100%; max-width:100%; min-width:0; box-sizing:border-box;">
                                    <small style="color:var(--text-secondary);">Bónus periódico aplicado a todos os
                                        colaboradores.</small>
                                </div>
                            </div>
                        </div>

                        <!-- ── BLOCO 4: Escalões de IRS (oculto no modo simples) ── -->
                        <div
                            style="display:none; background:var(--neutral-50,#f9fafb); border:1px solid var(--neutral-200); border-radius:12px; padding:1rem 1.25rem;">
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; gap:.8rem; flex-wrap:wrap; margin-bottom:.75rem;">
                                <div style="display:flex; align-items:center; gap:.5rem;">
                                    <i class="fas fa-table" style="color:#7c3aed;"></i>
                                    <span style="font-weight:700; font-size:.95rem;">Escalões de IRS</span>
                                </div>
                                <button type="button" class="btn btn-secondary" id="btnAddIrsBracket"
                                    style="padding:.45rem .85rem; font-size:.85rem;">
                                    <i class="fas fa-plus"></i> Adicionar Escalão
                                </button>
                            </div>
                            <p style="margin:0 0 .75rem; font-size:.8rem; color:var(--text-secondary);">
                                Fórmula: <strong>IRS = (Rendimento × Taxa) − Parcela a Abater</strong>. O último escalão
                                sem
                                máximo aplica-se a rendimentos superiores.
                                Os intervalos não podem sobrepor-se.
                            </p>
                            <div id="irsOverlapError"
                                style="display:none; padding:.6rem .8rem; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; border-radius:8px; margin-bottom:.75rem; font-size:.85rem;">
                            </div>
                            <div style="overflow:auto; border:1px solid var(--neutral-200); border-radius:8px;">
                                <table style="width:100%; border-collapse:collapse; min-width:680px;">
                                    <thead>
                                        <tr style="background:var(--neutral-100);">
                                            <th
                                                style="padding:.65rem .75rem; text-align:left; font-size:.825rem; white-space:nowrap;">
                                                Mínimo (€)</th>
                                            <th
                                                style="padding:.65rem .75rem; text-align:left; font-size:.825rem; white-space:nowrap;">
                                                Máximo (€)</th>
                                            <th
                                                style="padding:.65rem .75rem; text-align:left; font-size:.825rem; white-space:nowrap;">
                                                Taxa (%)</th>
                                            <th
                                                style="padding:.65rem .75rem; text-align:left; font-size:.825rem; white-space:nowrap;">
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
                                                    class="search-input" style="width:100%;">
                                            </td>
                                            <td style="padding:.4rem .5rem;">
                                                <input type="number" step="0.01" min="0" name="irs_max[]"
                                                    value="<?php echo $brMax; ?>" class="search-input"
                                                    style="width:100%;" placeholder="Sem limite">
                                            </td>
                                            <td style="padding:.4rem .5rem;">
                                                <div style="display:flex; align-items:center; gap:.3rem;">
                                                    <input type="number" step="0.01" min="0" max="100" name="irs_taxa[]"
                                                        value="<?php echo $brRate; ?>" class="search-input"
                                                        style="width:100%;">
                                                    <span
                                                        style="white-space:nowrap; color:var(--text-secondary); font-size:.85rem;">%</span>
                                                </div>
                                            </td>
                                            <td style="padding:.4rem .5rem;">
                                                <input type="number" step="0.01" min="0" name="irs_parcela[]"
                                                    value="<?php echo htmlspecialchars(number_format((float)($br['parcela_abater'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    class="search-input" style="width:100%;">
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
                        <div
                            style="background:var(--neutral-50,#f9fafb); border:1px solid var(--neutral-200); border-radius:12px; padding:1rem 1.25rem;">
                            <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.75rem;">
                                <i class="fas fa-hand-holding-usd" style="color:#d97706;"></i>
                                <span style="font-weight:700; font-size:.95rem;">Gorjetas</span>
                            </div>

                            <!-- Toggle switch -->
                            <div
                                style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem; padding:.75rem 1rem; background:#fff; border:1px solid var(--neutral-200); border-radius:10px; margin-bottom:.85rem;">
                                <div>
                                    <div style="font-weight:600; font-size:.9rem; color:#374151;">Dividir gorjetas
                                        automaticamente</div>
                                    <div style="font-size:.78rem; color:#6b7280; margin-top:.2rem;">
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
                                background:<?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? '#2563eb' : '#d1d5db'; ?>;"></span>
                                        <span id="toggleGorjetasThumb"
                                            style="position:absolute; left:<?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? '24px' : '2px'; ?>;
                                top:2px; width:22px; height:22px; border-radius:50%; background:#fff; transition:.3s; box-shadow:0 1px 3px rgba(0,0,0,.3);"></span>
                                    </div>
                                    <span id="lblGorjetasToggle"
                                        style="font-weight:700; font-size:.9rem; color:<?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? '#2563eb' : '#9ca3af'; ?>;">
                                        <?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? 'ON' : 'OFF'; ?>
                                    </span>
                                </label>
                            </div>

                            <!-- Campo total gorjetas (só visível quando ON) -->
                            <div id="gorjetasTotalBlock"
                                style="display:<?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? 'grid' : 'none'; ?>; gap:.35rem;">
                                <label style="font-size:.875rem; font-weight:600; color:#374151;">Total de Gorjetas do
                                    Mês
                                    (€)</label>
                                <div style="display:flex; align-items:center; gap:.5rem;">
                                    <input type="number" step="0.01" min="0" name="gorjetas_total_mes"
                                        id="gorjetasTotalMes"
                                        value="<?php echo htmlspecialchars(number_format((float)($payrollAdminConfig['gorjetas_total_mes'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        class="search-input" style="max-width:200px;" placeholder="0.00">
                                    <span style="color:var(--text-secondary); font-weight:700;">€</span>
                                </div>
                                <small style="color:#6b7280; font-size:.78rem;">
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
                                style="display:<?php echo (int)($payrollAdminConfig['gorjetas_auto_split'] ?? 0) === 1 ? 'none' : 'flex'; ?>; align-items:center; gap:.5rem; padding:.6rem .8rem; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; font-size:.82rem; color:#92400e;">
                                <i class="fas fa-info-circle"></i>
                                Modo manual: insira a gorjeta de cada funcionário clicando em <strong>"Editar"</strong>
                                na folha
                                de pagamento.
                            </div>
                        </div>

                        <!-- Ações -->
                        <div style="display:flex; justify-content:flex-end; gap:.75rem; padding-top:.25rem;">
                            <button type="button" class="btn btn-secondary" id="btnCancelPayrollModal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="btnSubmitPayrollConfig"
                                style="padding:.65rem 1.25rem;">
                                <i class="fas fa-save"></i> Guardar Configuração
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            // --- Solicitações: Ativar apenas um card e tabela ---
            function focusSolicitacoesTable(tableId) {
                // Esconde todas as tabelas de solicitações
                document.querySelectorAll('.data-table.solicitacoes-table').forEach(function(tbl) {
                    tbl.style.display = 'none';
                });
                // Mostra a tabela selecionada
                var table = document.getElementById(tableId);
                if (table) table.style.display = '';

                // Remove destaque de todos os cards de solicitações
                document.querySelectorAll('.stats-grid .solicitacao-card-btn').forEach(function(btn) {
                    btn.classList.remove('active-solicitacao-card');
                });

                // Adiciona destaque ao card clicado
                // Busca todos os cards e compara o onclick
                var btns = document.querySelectorAll('.stats-grid .solicitacao-card-btn');
                btns.forEach(function(btn) {
                    var onclickAttr = btn.getAttribute('onclick') || '';
                    if (onclickAttr.includes(tableId)) {
                        btn.classList.add('active-solicitacao-card');
                    }
                });
            }
            // --- Relatórios: Ativar apenas um card e tabela ---
            function focusSolicitacoesTable(tableId) {
                // Esconde todas as tabelas de relatório
                document.querySelectorAll('.data-table.solicitacoes-table').forEach(function(tbl) {
                    tbl.style.display = 'none';
                });
                // Mostra a tabela selecionada
                var table = document.getElementById(tableId);
                if (table) table.style.display = '';

                // Remove destaque de todos os cards
                document.querySelectorAll('.relatorios-flex-grid .solicitacao-card-btn').forEach(function(btn) {
                    btn.classList.remove('active-relatorio-card');
                });
                // Adiciona destaque ao card clicado
                var btns = document.querySelectorAll('.relatorios-flex-grid .solicitacao-card-btn');
                btns.forEach(function(btn) {
                    if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(tableId)) {
                        btn.classList.add('active-relatorio-card');
                    }
                });
            }

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
                            ).then(function(result) {
                                if (result && result.isConfirmed) {
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
                            ).then(function(result) {
                                if (result && result.isConfirmed) {
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

    <div id="modalHorariosEstabelecimento" class="modal" style="display:none;">
                            <div class="modal-content horarios-modal-content">
                                <div class="modal-header">
                                    <h3 class="modal-title">
                                        <i class="fas fa-clock"></i>
                                        Horários do Estabelecimento
                                    </h3>
                                    <span id="btnCloseHorariosModal" class="modal-close">&times;</span>
                                </div>
                                <p class="modal-desc">
                                    Defina os horários padrão do estabelecimento. Estes horários são usados para cálculo
                                    de atrasos e controle de presença.
                                </p>
                                <form method="post" action="dashboard.php?section=definicoes" class="modal-form">
                                    <input type="hidden" name="action" value="save_estabelecimento_horarios">
                                    <label
                                        style="font-size:.85rem; font-weight:600; color:var(--text-secondary);">Abertura</label>
                                    <input type="time" name="hora_abertura" class="search-input"
                                        value="<?php echo htmlspecialchars(substr((string)$estHorario['hora_abertura'], 0, 5)); ?>"
                                        required>
                                    <label
                                        style="font-size:.85rem; font-weight:600; color:var(--text-secondary);">Encerramento</label>
                                    <input type="time" name="hora_encerramento" class="search-input"
                                        value="<?php echo htmlspecialchars(substr((string)$estHorario['hora_encerramento'], 0, 5)); ?>"
                                        required>
                                    <label
                                        style="font-size:.85rem; font-weight:600; color:var(--text-secondary);">Entrada
                                        Padrão Funcionários</label>
                                    <input type="time" name="hora_entrada_padrao" class="search-input"
                                        value="<?php echo htmlspecialchars(substr((string)$estHorario['hora_entrada_padrao'], 0, 5)); ?>"
                                        required>
                                    <label
                                        style="font-size:.85rem; font-weight:600; color:var(--text-secondary);">Tolerância
                                        de atraso (min)</label>
                                    <input type="number" min="0" max="180" name="tolerancia_atraso_min"
                                        class="search-input"
                                        value="<?php echo (int)$estHorario['tolerancia_atraso_min']; ?>" required>
                                    <div class="modal-actions">
                                        <button type="button" class="btn btn-secondary"
                                            id="btnCancelHorariosModal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Guardar Horários
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>








<div id="modalAdminProfile" class="modal"
                                style="display:none; align-items:flex-start; justify-content:center; padding:2rem 1rem; overflow-y:auto;">
                                <div class="modal-content"
                                    style="max-width: 480px; width:100%; margin:auto; margin-top:2.5rem; max-height:92vh; overflow-y:auto; padding:1.75rem; box-shadow:0 8px 32px rgba(0,0,0,0.18); border-radius:14px; background:#fff;">
                                    <div
                                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.25rem;">
                                        <h3
                                            style="margin:0; font-size:1.25rem; font-weight:700; color:var(--text-primary);">
                                            <i class="fas fa-user-cog"
                                                style="margin-right:.5rem; color:var(--primary-600);"></i>
                                            Editar Perfil do Administrador
                                        </h3>
                                        <span id="btnCloseAdminProfileModal"
                                            style="font-size:1.75rem; line-height:1; cursor:pointer; color:var(--text-secondary); padding:.25rem .5rem;">&times;</span>
                                    </div>
                                    <form method="post" action="dashboard.php?section=definicoes"
                                        style="display:grid; gap:.85rem;">
                                        <input type="hidden" name="action" value="save_admin_profile">
                                        <label
                                            style="font-size:.85rem; font-weight:600; color:var(--text-secondary);">Nome</label>
                                        <input type="text" name="admin_nome" class="search-input"
                                            value="<?php echo htmlspecialchars($adminUser['name'] ?? ''); ?>" required>
                                        <label
                                            style="font-size:.85rem; font-weight:600; color:var(--text-secondary);">Email</label>
                                        <input type="email" name="admin_email" class="search-input"
                                            value="<?php echo htmlspecialchars($adminUser['email'] ?? ''); ?>" required>
                                        <label
                                            style="font-size:.85rem; font-weight:600; color:var(--text-secondary);">Telefone</label>
                                        <input type="text" name="admin_telefone" class="search-input"
                                            value="<?php echo htmlspecialchars($adminUser['phone'] ?? ''); ?>">
                                        <label
                                            style="font-size:.85rem; font-weight:600; color:var(--text-secondary);">Nova
                                            Senha</label>
                                        <input type="password" name="admin_nova_senha" class="search-input"
                                            placeholder="Deixe em branco para não alterar">
                                        <label
                                            style="font-size:.85rem; font-weight:600; color:var(--text-secondary);">Confirmar
                                            Nova Senha</label>
                                        <input type="password" name="admin_confirmar_senha" class="search-input"
                                            placeholder="Confirme a nova senha">
                                        <div
                                            style="display:flex; justify-content:flex-end; gap:.75rem; padding-top:.25rem;">
                                            <button type="button" class="btn btn-secondary"
                                                id="btnCancelAdminProfileModal">Cancelar</button>
                                            <button type="submit" class="btn btn-primary"
                                                style="padding:.65rem 1.25rem;">
                                                <i class="fas fa-save"></i> Salvar Alterações
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>




