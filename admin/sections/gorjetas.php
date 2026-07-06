<?php
// Secção "Gorjetas" — incluída a partir de admin/dashboard.php (depende de $pdo, $employees, $loggedInClientId, $mesesPt, etc. já definidos lá).
?>
        <section id="gorjetas-section" class="content-section">
            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 4px 14px rgba(217,119,6,.35);"><i class="fas fa-hand-holding-usd"></i></div>
                    <div>
                        <h2 class="frhd-title">Gorjetas</h2>
                        <p class="frhd-sub">Registos de gorjetas dos colaboradores</p>
                    </div>
                </div>
                <button type="button" id="btnAddGorjeta" class="frhd-add-btn"
                    onclick="document.getElementById('gorjetaModal').style.display='block'; document.getElementById('gorjetaModalTitle').textContent='Adicionar Gorjeta'; document.getElementById('gorjetaForm').reset(); document.getElementById('gorjetaId').value='';">
                    <i class="fas fa-plus"></i> Adicionar Gorjeta
                </button>
            </div>

            <div class="data-table fr-table-wrap">
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="searchGorjetas" placeholder="Pesquisar funcionários..." class="fr-search">
                        </div>
                        <div class="fr-toolbar-right" style="gap:.65rem;flex-wrap:wrap;">
                            <span id="resultCountGorjetas"
                                style="font-weight: 600; color: var(--text-secondary); font-size: 0.9rem; white-space: nowrap;"></span>
                            <button type="button" class="fr-filter-toggle" id="gorjetasFilterToggle"
                                onclick="document.getElementById('gorjetasAdvFilters').classList.toggle('fr-adv-open'); this.classList.toggle('pa-filter-open')">
                                <i class="fas fa-sliders-h"></i> Filtros
                                <span class="fr-filter-badge" id="gorjetasFilterBadge" style="display:none"></span>
                            </button>
                            <button id="exportGorjetasBtn" type="button" class="fr-export-btn"
                                onclick="exportGorjetas()">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                        </div>
                    </div>











                    <div class="fr-adv-filters" id="gorjetasAdvFilters">
                        <input type="number" id="filtro-dia" class="fr-select" style="width:75px;" min="1" max="31" placeholder="Dia">
                        <select id="filtro-mes" class="fr-select" style="width:110px;">
                            <option value="">Mês</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>"><?php echo htmlspecialchars($mesesPt[$m], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="filtro-ano" class="fr-select" style="width:100px;">
                            <option value="">Ano</option>
                            <?php for ($y = $currentYear + 1; $y >= $currentYear - 5; $y--): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="filtro-status" class="fr-select" style="width:150px;">
                            <option value="">Todos os status</option>
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                            <option value="rejeitado">Rejeitado</option>
                        </select>
                        <button type="button" class="fr-clear-btn" id="gorjetasClearBtn" style="display:none"
                            onclick="clearGorjetasPanelFilters()">Limpar</button>
                    </div>
                        <script>
                        // Exporta apenas a tabela de gorjetas exibida (linhas visíveis)
                        function exportGorjetas() {
                            // Seleciona a tabela de gorjetas correta
                            const gorjetasSection = document.querySelector('#gorjetas-section');
                            if (!gorjetasSection) return;
                            const table = gorjetasSection.querySelector('.data-table table');
                            if (!table) return;
                            let csv = '';
                            // Cabeçalho
                            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.innerText
                                .trim());
                            csv += headers.join(';') + '\n';
                            // Linhas visíveis
                            Array.from(table.querySelectorAll('tbody tr')).forEach(tr => {
                                // Só exporta linhas visíveis (display diferente de 'none')
                                if (tr.offsetParent !== null) {
                                    const row = Array.from(tr.querySelectorAll('td')).map(td => td.innerText
                                        .trim().replace(/\n/g, ' '));
                                    if (row.length) csv += row.join(';') + '\n';
                                }
                            });
                            // Download
                            const blob = new Blob([csv], {
                                type: 'text/csv;charset=utf-8;'
                            });
                            const link = document.createElement('a');
                            link.href = URL.createObjectURL(blob);
                            link.download = 'gorjetas.csv';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        }

                        // Limpa busca e filtros da tabela de gorjetas
                        function clearGorjetasFilters() {
                            const search = document.getElementById('searchGorjetas');
                            if (search) search.value = '';
                            // Mostra todas as linhas da tabela de gorjetas
                            const gorjetasSection = document.querySelector('#gorjetas-section');
                            if (gorjetasSection) {
                                const table = gorjetasSection.querySelector('.data-table table');
                                if (table) {
                                    Array.from(table.querySelectorAll('tbody tr')).forEach(tr => {
                                        tr.style.display = '';
                                    });
                                }
                            }
                            // Atualiza contador de resultados, se existir
                            const resultCount = document.getElementById('resultCountGorjetas');
                            if (resultCount && gorjetasSection) {
                                const table = gorjetasSection.querySelector('.data-table table');
                                if (table) {
                                    const total = table.querySelectorAll('tbody tr').length;
                                    resultCount.textContent = total + ' resultados';
                                }
                            }
                        }
                        // Atualiza badge do botão Filtros de gorjetas
                        function updateGorjetasFilterBadge() {
                            const badge = document.getElementById('gorjetasFilterBadge');
                            if (!badge) return;
                            const count = [
                                document.getElementById('filtro-dia')?.value,
                                document.getElementById('filtro-mes')?.value,
                                document.getElementById('filtro-ano')?.value,
                                document.getElementById('filtro-status')?.value
                            ].filter(Boolean).length;
                            if (count > 0) { badge.textContent = String(count); badge.style.display = 'flex'; }
                            else { badge.style.display = 'none'; }
                            const clearBtn = document.getElementById('gorjetasClearBtn');
                            if (clearBtn) clearBtn.style.display = count > 0 ? '' : 'none';
                        }

                        // Limpa filtros do painel colapsável
                        function clearGorjetasPanelFilters() {
                            const dia = document.getElementById('filtro-dia');
                            const mes = document.getElementById('filtro-mes');
                            const ano = document.getElementById('filtro-ano');
                            const st = document.getElementById('filtro-status');
                            if (dia) dia.value = '';
                            if (mes) mes.value = '';
                            if (ano) ano.value = '';
                            if (st) st.value = '';
                            filtrarGorjetasDinamico();
                        }

                        // Filtro dinâmico de gorjetas
                        function filtrarGorjetasDinamico() {
                            const dia = document.getElementById('filtro-dia')?.value?.trim() || '';
                            const mes = document.getElementById('filtro-mes')?.value?.trim() || '';
                            const ano = document.getElementById('filtro-ano')?.value?.trim() || '';
                            const status = document.getElementById('filtro-status')?.value?.trim().toLowerCase() || '';
                            const funcionario = document.getElementById('searchGorjetas')?.value?.trim().toLowerCase() || '';
                            const tabela = document.querySelector('#gorjetas-section .data-table table');
                            if (!tabela) return;
                            const linhas = tabela.querySelectorAll('tbody tr');
                            let algumVisivel = false;
                            linhas.forEach(tr => {
                                let mostrar = true;
                                const tds = tr.querySelectorAll('td');
                                if (tds.length < 8) return;
                                const nomeFunc = tds[0].innerText.trim().toLowerCase();
                                const data = tds[1].innerText.trim();
                                const statusCol = tds[6].innerText.trim().toLowerCase();
                                let [d, m, a] = data.split('/');
                                if (dia && d !== dia.padStart(2, '0')) mostrar = false;
                                if (mes && m !== mes.padStart(2, '0')) mostrar = false;
                                if (ano && a !== ano) mostrar = false;
                                if (status && statusCol !== status) mostrar = false;
                                if (funcionario && !nomeFunc.includes(funcionario)) mostrar = false;
                                tr.style.display = mostrar ? '' : 'none';
                                if (mostrar) algumVisivel = true;
                            });
                            // Se nenhum resultado, mostra uma linha de "Nenhum resultado encontrado"
                            let semResultado = tabela.querySelector('tbody .tr-sem-resultado');
                            if (!algumVisivel) {
                                if (!semResultado) {
                                    semResultado = document.createElement('tr');
                                    semResultado.className = 'tr-sem-resultado';
                                    let td = document.createElement('td');
                                    td.colSpan = 8;
                                    td.style.textAlign = 'center';
                                    td.style.color = 'var(--text-secondary)';
                                    td.style.padding = '1rem';
                                    td.textContent = 'Nenhum resultado encontrado.';
                                    semResultado.appendChild(td);
                                    tabela.querySelector('tbody').appendChild(semResultado);
                                }
                            } else if (semResultado) {
                                semResultado.remove();
                            }
                            // Atualiza contador
                            const resultCount = document.getElementById('resultCountGorjetas');
                            if (resultCount && tabela) {
                                const total = Array.from(tabela.querySelectorAll('tbody tr')).filter(tr => tr.style
                                    .display !== 'none' && !tr.classList.contains('tr-sem-resultado')).length;
                                resultCount.textContent = total + ' resultados';
                            }
                            updateGorjetasFilterBadge();
                        }

                        ['filtro-dia', 'filtro-mes', 'filtro-ano', 'filtro-status', 'searchGorjetas'].forEach(
                            id => {
                                document.addEventListener('DOMContentLoaded', function() {
                                    const el = document.getElementById(id);
                                    if (el) el.addEventListener('input', filtrarGorjetasDinamico);
                                    if (el && (el.tagName === 'SELECT')) el.addEventListener('change',
                                        filtrarGorjetasDinamico);
                                });
                            });
                        </script>
                </div>

                <table class="table fr-table">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-emp">Funcionário</th>
                            <th>Data</th>
                            <th>Turno</th>
                            <th>Valor €</th>
                            <th>Forma de Pagamento</th>
                            <th>Origem</th>
                            <th class="fr-th-status">Status</th>
                            <th class="fr-th-acts">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
        try {
                        $gorjetasDateColumn = 'data';
                        if (function_exists('getGorjetaDateColumn')) {
                            $gorjetasDateColumn = getGorjetaDateColumn($pdo);
                        } else {
                            try {
                                $gorjetaColsLocal = $pdo->query('SHOW COLUMNS FROM gorjetas')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                                if (!in_array('data', $gorjetaColsLocal, true) && in_array('data_registro', $gorjetaColsLocal, true)) {
                                    $gorjetasDateColumn = 'data_registro';
                                }
                            } catch (Throwable $e) {
                                $gorjetasDateColumn = 'data';
                            }
                        }

                        $stmtGorjetas = $pdo->prepare("
                                SELECT g.*, DATE(g.{$gorjetasDateColumn}) AS data_ref, e.name as funcionario_nome, e.profile_picture as funcionario_profile_picture
                                FROM gorjetas g
                                INNER JOIN employees e ON g.funcionario_id = e.id
                                WHERE g.client_id = ?
                                ORDER BY g.{$gorjetasDateColumn} DESC, g.id DESC
                        ");
                        $stmtGorjetas->execute([$loggedInClientId]);
                        $gorjetas = $stmtGorjetas->fetchAll(PDO::FETCH_ASSOC);

            foreach ($gorjetas as $gorjeta):
                $dataRaw = (string)($gorjeta['data_ref'] ?? '');
                $dataTs = strtotime($dataRaw);
                $dataFormatada = $dataTs !== false ? date('d/m/Y', $dataTs) : '-';
                $statusAtual = strtolower(trim((string)($gorjeta['status'] ?? 'pendente')));
                $statusClass = 'status-pendente';
                $statusLabel = 'Pendente';

                if ($statusAtual === 'pago') {
                    $statusClass = 'status-active';
                    $statusLabel = 'Pago';
                } elseif (in_array($statusAtual, ['rejeitado', 'rejeitada'], true)) {
                    $statusClass = 'status-rejeitado';
                    $statusLabel = 'Rejeitado';
                } elseif (in_array($statusAtual, ['cancelado', 'cancelada'], true)) {
                    $statusClass = 'status-rejeitado';
                    $statusLabel = 'Cancelado';
                }
        ?>
                        <tr class="fr-row">

                            <!-- FUNCIONÁRIO: avatar + nome -->
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#667eea,#764ba2);">
                                        <?php if (!empty($gorjeta['funcionario_profile_picture'])): ?>
                                        <img src="../<?php echo $gorjeta['funcionario_profile_picture']; ?>"
                                            alt="<?php echo htmlspecialchars($gorjeta['funcionario_nome']); ?>"
                                            class="fr-av-img">
                                        <?php else: ?>
                                        <?php echo strtoupper(substr($gorjeta['funcionario_nome'], 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo htmlspecialchars($gorjeta['funcionario_nome']); ?></span>
                                    </div>
                                </div>
                            </td>

                            <!-- DATA -->
                            <td><?php echo htmlspecialchars($dataFormatada); ?></td>

                            <!-- TURNO -->
                            <td><?php echo htmlspecialchars($gorjeta['turno'] ?? '-'); ?></td>

                            <!-- VALOR -->
                            <td style="text-align:right;">
                                €<?php echo number_format($gorjeta['valor'], 2, ',', '.'); ?>
                            </td>

                            <!-- FORMA DE PAGAMENTO -->
                            <td><?php echo htmlspecialchars($gorjeta['forma_pagamento'] ?? 'Dinheiro'); ?></td>

                            <!-- ORIGEM -->
                            <td><?php echo htmlspecialchars($gorjeta['origem'] ?? '-'); ?></td>

                            <!-- STATUS -->
                            <td class="fr-td-status">
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo $statusLabel; ?>
                                </span>
                            </td>

                            <!-- AÇÕES -->
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <button type="button" class="fr-btn fr-btn-view btn-view-gorjeta"
                                        data-id="<?php echo (int)$gorjeta['id']; ?>" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <button type="button" class="fr-btn fr-btn-edit btn-edit" data-id="<?php echo (int)$gorjeta['id']; ?>"
                                        title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>

                        </tr>
                        <?php
            endforeach;
        } catch (PDOException $e) {
            error_log("Erro ao carregar gorjetas: " . $e->getMessage());
        }
        ?>
                    </tbody>
                </table>
            </div>




            <!-- Modal para criar/editar gorjeta -->
            <div id="gorjetaModal" class="modal" style="display:none; overflow-y:auto; padding:24px 16px 48px;">
                <div class="am-sheet">
                    <button class="am-close" type="button" id="closeGorjetaModal" aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 6px 16px rgba(217,119,6,.35);">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div>
                            <h2 class="am-title" id="gorjetaModalTitle">Adicionar Gorjeta</h2>
                            <p class="am-subtitle">Registe a gorjeta recebida pelo colaborador</p>
                        </div>
                    </div>

                    <form id="gorjetaForm">
                        <input type="hidden" name="id" id="gorjetaId">

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-user"></i> Funcionário</div>
                            <div class="am-f am-f-full">
                                <label class="am-lbl" for="gorjetaFuncionario">Funcionário *</label>
                                <select id="gorjetaFuncionario" name="funcionario_id" class="am-inp am-sel" required>
                                    <option value="">Selecione o funcionário...</option>
                                    <?php foreach ($employees as $emp):
                                    // Filtrar funcionários inativos e de férias
                                    $empStatus = mb_strtolower(trim((string)($emp['status'] ?? '')));
                                    if (in_array($empStatus, ['inactive', 'inativo', 'ferias', 'férias'], true)) continue;
                                ?>
                                    <option value="<?php echo (int)$emp['id']; ?>">
                                        <?php echo htmlspecialchars($emp['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="am-section">
                            <div class="am-sec-lbl"><i class="fas fa-euro-sign"></i> Detalhes da Gorjeta</div>
                            <div class="am-g2">
                                <div class="am-f">
                                    <label class="am-lbl" for="gorjetaData">Data *</label>
                                    <input type="date" id="gorjetaData" name="data" class="am-inp" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="gorjetaTurno">Turno *</label>
                                    <select id="gorjetaTurno" name="turno" class="am-inp am-sel" required>
                                        <option value="">Selecione...</option>
                                        <option value="Manhã">Manhã</option>
                                        <option value="Tarde">Tarde</option>
                                        <option value="Noite">Noite</option>
                                    </select>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="gorjetaValor">Valor (€) *</label>
                                    <input type="number" id="gorjetaValor" name="valor" step="0.01" min="0" class="am-inp" required>
                                </div>
                                <div class="am-f">
                                    <label class="am-lbl" for="gorjetaPagamento">Forma de Pagamento *</label>
                                    <select id="gorjetaPagamento" name="forma_pagamento" class="am-inp am-sel" required>
                                        <option value="">Selecione...</option>
                                        <option value="Dinheiro">Dinheiro</option>
                                        <option value="Cartão">Cartão</option>
                                        <option value="MB Way">MB Way</option>
                                        <option value="Transferência">Transferência</option>
                                    </select>
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="gorjetaOrigem">Origem</label>
                                    <input type="text" id="gorjetaOrigem" name="origem" class="am-inp" placeholder="Ex: Mesa 5, Entrega, etc.">
                                </div>
                                <div class="am-f am-f-full">
                                    <label class="am-lbl" for="gorjetaStatus">Status *</label>
                                    <select id="gorjetaStatus" name="status" class="am-inp am-sel" required>
                                        <option value="pago" selected>Pago</option>
                                        <option value="pendente">Pendente</option>
                                        <option value="rejeitado">Rejeitado</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="am-footer">
                            <button type="button" class="am-btn-cancel" onclick="document.getElementById('gorjetaModal').style.display='none'">Cancelar</button>
                            <button type="submit" class="am-btn-submit">
                                <i class="fas fa-save"></i> Salvar Gorjeta
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="gorjetaViewModal" class="modal" style="display:none; overflow-y:auto; padding:24px 16px 48px;">
                <div class="am-sheet" style="max-width:520px;">
                    <button class="am-close" type="button" id="closeGorjetaViewModal" aria-label="Fechar">&times;</button>

                    <div class="am-header">
                        <div class="am-header-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb);box-shadow:0 6px 16px rgba(37,99,235,.35);">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div>
                            <h2 class="am-title">Detalhes da Gorjeta</h2>
                            <p class="am-subtitle">Informação completa do registo</p>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:.85rem; padding:14px 16px; border-radius:12px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07); margin-bottom:18px;">
                        <div id="gv-avatar" style="width:52px; height:52px; border-radius:999px; overflow:hidden; display:flex; align-items:center; justify-content:center; font-weight:800; color:#fff; background:linear-gradient(135deg,#1d4ed8,#2563eb); flex-shrink:0;">--</div>
                        <div style="min-width:0;">
                            <div id="gv-nome" style="font-size:1rem; font-weight:700; color:#e2e8f0;">Funcionário</div>
                            <div id="gv-data" style="font-size:.82rem; color:#94a3b8;">Data</div>
                        </div>
                        <span id="gv-status" style="margin-left:auto; font-size:.75rem; font-weight:700; padding:.35rem .7rem; border-radius:999px; color:#fbbf24; background:rgba(245,158,11,.15); border:1px solid rgba(251,191,36,.35); white-space:nowrap;">Pendente</span>
                    </div>

                    <div class="am-g2">
                        <div class="am-f" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:10px 12px;">
                            <span class="am-lbl" style="margin-bottom:2px;">Turno</span>
                            <div id="gv-turno" style="font-size:.9rem;font-weight:700;color:#e2e8f0;">-</div>
                        </div>
                        <div class="am-f" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:10px 12px;">
                            <span class="am-lbl" style="margin-bottom:2px;">Valor</span>
                            <div id="gv-valor" style="font-size:.95rem;font-weight:800;color:#4ade80;">EUR 0,00</div>
                        </div>
                        <div class="am-f" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:10px 12px;">
                            <span class="am-lbl" style="margin-bottom:2px;">Forma de Pagamento</span>
                            <div id="gv-pagamento" style="font-size:.9rem;font-weight:700;color:#e2e8f0;">-</div>
                        </div>
                        <div class="am-f" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:10px 12px;">
                            <span class="am-lbl" style="margin-bottom:2px;">Origem</span>
                            <div id="gv-origem" style="font-size:.9rem;font-weight:700;color:#e2e8f0;">-</div>
                        </div>
                    </div>

                    <div class="am-footer">
                        <button type="button" id="closeGorjetaViewFooter" class="am-btn-cancel">Fechar</button>
                    </div>
                </div>
            </div>
        </section>
