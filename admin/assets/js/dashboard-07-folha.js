// ==========================================
// MELHORIAS: FUNCIONARIOS (SOFT DELETE + VALIDACAO + HISTORICO)
// ==========================================
(function () {
    const employeeModuleState = {
        fetchWrapped: false,
        lastHistory: []
    };

    function setInlineEmployeeError(target, message) {
        const id = target === 'edit' ? 'editEmployeeInlineError' : 'addEmployeeInlineError';
        const node = document.getElementById(id);
        if (!node) return;
        if (!message) {
            node.textContent = '';
            node.style.display = 'none';
            return;
        }
        node.textContent = message;
        node.style.display = 'block';
    }

    function renderEmployeeHistory(items) {
        const container = document.getElementById('view-employee-history');
        if (!container) return;
        const list = Array.isArray(items) ? items : [];

        if (list.length === 0) {
            container.innerHTML = '<div style="color:#bdc3c7; font-size: 0.92em;">Sem histórico disponível.</div>';
            return;
        }

        const html = list.map((item) => {
            const title = String(item.title || 'Atividade sem descrição');
            const ts = item.timestamp ? new Date(item.timestamp).toLocaleString('pt-PT') : 'Data indisponível';
            const status = item.status ? String(item.status) : '';
            return `
                <div style="padding:8px 10px; border-bottom:1px solid rgba(255,255,255,0.08);">
                    <div style="font-size:0.92em; color:#ecf0f1;">${title}</div>
                    <div style="margin-top:3px; font-size:0.8em; color:#bdc3c7; display:flex; gap:8px; flex-wrap:wrap;">
                        <span>${ts}</span>
                        ${status ? `<span style="color:#a78bfa;">${status}</span>` : ''}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html.join('');
    }

    // Função para alternar entre relatórios
    window.switchRelatorio = function(type, element) {
        // Remove classe active de todas as abas
        document.querySelectorAll('.relatorio-tab').forEach(tab => {
            tab.classList.remove('active');
        });

        // Adiciona classe active ao elemento clicado
        element.classList.add('active');

        // Esconde todos os conteúdos
        document.querySelectorAll('.relatorio-content').forEach(div => {
            div.style.display = 'none';
        });

        // Mostra o conteúdo selecionado
        const contentDiv = document.getElementById('content-' + type);
        if (contentDiv) {
            contentDiv.style.display = 'block';
            // Inicializar paginação para a tabela ativa
            window.initTablePagination(type);
        }

        if (typeof window.updateRelatorioChartsFromFilters === 'function') {
            window.updateRelatorioChartsFromFilters();
        }
    };

    // ========== PAGINAÇÃO DE TABELAS ==========
    window.initTablePagination = function(relatorioType) {
        const ROWS_PER_PAGE = 5;
        
        // Mapear tipo de relatório para ID da tabela
        const tableMap = {
            'funcionarios-resumido': 'relatorio-funcionarios-resumido',
            'presenca': 'relatorio-presenca-table',
            'turnos': 'relatorio-turnos-table',
            'gorjetas': 'relatorio-gorjetas-table',
            'folha': 'relatorio-folha-table'
        };

        const tableId = tableMap[relatorioType];
        if (!tableId) return;

        const table = document.querySelector(`#${tableId} table`);
        if (!table) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const rows = Array.from(tbody.querySelectorAll('tr'));

        // Remover botão "Ver mais" anterior se existir
        const oldLoadMore = table.parentNode?.nextElementSibling;
        if (oldLoadMore && oldLoadMore.classList.contains('load-more-container')) {
            oldLoadMore.remove();
        }

        if (rows.length === 0) return;

        const visibleRows = rows.filter((row) => row.dataset.filterVisible !== 'false');

        rows.forEach((row) => {
            if (row.dataset.filterVisible === 'false') {
                row.style.display = 'none';
                row.dataset.paginationIndex = '-1';
                return;
            }

            row.style.display = 'none';
        });

        // Mostrar apenas os primeiros 10 registos visíveis pelo filtro
        visibleRows.forEach((row, index) => {
            row.style.display = index < ROWS_PER_PAGE ? '' : 'none';
            row.dataset.paginationIndex = String(index);
        });

        // Se há mais registos que o limite, adicionar botão "Ver mais"
        if (visibleRows.length > ROWS_PER_PAGE) {
            const container = document.createElement('div');
            container.className = 'load-more-container';
            container.style.cssText = `
                display: flex;
                justify-content: center;
                padding: 1.5rem;
                gap: 0.5rem;
            `;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-accent';
            btn.innerHTML = `<i class="fas fa-chevron-down"></i> Ver mais ${visibleRows.length - ROWS_PER_PAGE} registos`;
            btn.style.cssText = `
                padding: 0.75rem 1.5rem;
                background: rgba(138, 43, 226, 0.15);
                color: #8a2be2;
                border: 1px solid #8a2be2;
                border-radius: 6px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            `;

            let currentPage = 1;

            btn.addEventListener('click', function() {
                currentPage++;
                const startIndex = (currentPage - 1) * ROWS_PER_PAGE;
                const endIndex = startIndex + ROWS_PER_PAGE;

                // Mostrar próximas 10 linhas
                visibleRows.forEach((row, index) => {
                    if (index < endIndex) {
                        row.style.display = '';
                    }
                });

                // Se chegou ao fim, mudar texto do botão
                if (endIndex >= visibleRows.length) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Todos os registos carregados';
                    btn.disabled = true;
                    btn.style.background = '#d1d5db';
                    btn.style.color = '#6b7280';
                    btn.style.cursor = 'default';
                } else {
                    // Mostrar quantos faltam
                    const remaining = visibleRows.length - endIndex;
                    btn.innerHTML = `<i class="fas fa-chevron-down"></i> Ver mais ${remaining} registos`;
                }
            });

            container.appendChild(btn);
            table.parentNode.insertAdjacentElement('afterend', container);
        }
    };

    // Reinicializar paginação para a tabela visível
    window.reinitTablePagination = function() {
        // Encontrar qual tabela está visível
        const visibleContent = document.querySelector('.relatorio-content[style*="display: block"]');
        if (!visibleContent) {
            // Se nenhuma está visível, assumir a primeira (funcionarios-resumido)
            window.initTablePagination('funcionarios-resumido');
            return;
        }

        // Verificar qual tabela está dentro do conteúdo visível
        const tableIds = [
            'relatorio-funcionarios-resumido',
            'relatorio-presenca-table',
            'relatorio-turnos-table',
            'relatorio-gorjetas-table',
            'relatorio-folha-table'
        ];

        for (const tableId of tableIds) {
            if (visibleContent.querySelector(`#${tableId}`)) {
                // Encontramos a tabela, agora descobrir qual relatório é
                const relatorioMap = {
                    'relatorio-funcionarios-resumido': 'funcionarios-resumido',
                    'relatorio-presenca-table': 'presenca',
                    'relatorio-turnos-table': 'turnos',
                    'relatorio-gorjetas-table': 'gorjetas',
                    'relatorio-folha-table': 'folha'
                };
                window.initTablePagination(relatorioMap[tableId]);
                return;
            }
        }
    };

    function parseEuroValue(text) {
        if (!text) return 0;
        const normalized = String(text)
            .replace(/\s/g, '')
            .replace(/€/g, '')
            .replace(/\./g, '')
            .replace(',', '.');
        const value = parseFloat(normalized);
        return Number.isFinite(value) ? value : 0;
    }

    function isRowVisible(row) {
        return row && row.style.display !== 'none';
    }

    function toTitleCase(value) {
        const text = String(value || '').trim();
        if (!text) return 'N/D';
        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    function updateChartData(chartId, labels, data) {
        if (typeof Chart === 'undefined') return;
        const canvas = document.getElementById(chartId);
        if (!canvas) return;

        const chart = Chart.getChart(canvas);
        if (!chart || !Array.isArray(chart.data?.datasets) || !chart.data.datasets[0]) return;

        chart.data.labels = labels;
        chart.data.datasets[0].data = data;

        if (!data.length) {
            chart.data.labels = ['Sem dados'];
            chart.data.datasets[0].data = [0];
        }

        chart.update();
    }

    window.updateRelatorioChartsFromFilters = function() {
        // Funcionarios resumido: status e cargos
        const relRows = Array.from(document.querySelectorAll('#relatorio-funcionarios-resumido tbody tr')).filter(isRowVisible);
        const statusMap = {};
        const cargoMap = {};

        relRows.forEach((row) => {
            const status = toTitleCase(row.getAttribute('data-rel-status'));
            const cargo = toTitleCase(row.getAttribute('data-rel-cargo'));
            statusMap[status] = (statusMap[status] || 0) + 1;
            cargoMap[cargo] = (cargoMap[cargo] || 0) + 1;
        });

        updateChartData('chartFuncionariosStatus', Object.keys(statusMap), Object.values(statusMap));

        const cargosOrdenados = Object.entries(cargoMap).sort((a, b) => b[1] - a[1]).slice(0, 8);
        updateChartData('chartFuncionariosCargos', cargosOrdenados.map((c) => c[0]), cargosOrdenados.map((c) => c[1]));

        // Presencas: status
        const presRows = Array.from(document.querySelectorAll('#relatorio-presenca-table tbody tr')).filter(isRowVisible);
        const presMap = { Presente: 0, Ausente: 0, Falta: 0 };
        presRows.forEach((row) => {
            const status = String(row.getAttribute('data-presenca-status') || '').toLowerCase();
            if (status === 'presente') presMap.Presente += 1;
            else if (status === 'ausente') presMap.Ausente += 1;
            else if (status === 'falta') presMap.Falta += 1;
        });
        updateChartData('chartPresencaStatus', Object.keys(presMap), Object.values(presMap));

        // Turnos: distribuicao por tipo
        const turnoRows = Array.from(document.querySelectorAll('#relatorio-turnos-table tbody tr')).filter(isRowVisible);
        const turnoMap = {};
        turnoRows.forEach((row) => {
            const tipo = toTitleCase(row.getAttribute('data-turno-tipo'));
            turnoMap[tipo] = (turnoMap[tipo] || 0) + 1;
        });
        updateChartData('chartTurnosDistribuicao', Object.keys(turnoMap), Object.values(turnoMap));

        // Gorjetas: top por funcionario (soma)
        const gorRows = Array.from(document.querySelectorAll('#relatorio-gorjetas-table tbody tr')).filter(isRowVisible);
        const gorMap = {};
        gorRows.forEach((row) => {
            const nomeCell = row.children[1];
            const valorCell = row.children[2];
            const nome = toTitleCase(nomeCell ? nomeCell.textContent : 'N/D');
            const valor = parseEuroValue(valorCell ? valorCell.textContent : '0');
            gorMap[nome] = (gorMap[nome] || 0) + valor;
        });
        const gorOrdenado = Object.entries(gorMap).sort((a, b) => b[1] - a[1]).slice(0, 10);
        updateChartData('chartGorjetasTop', gorOrdenado.map((g) => g[0]), gorOrdenado.map((g) => Number(g[1].toFixed(2))));

        // Folha: top custos por funcionario (bruto)
        const folhaRows = Array.from(document.querySelectorAll('#relatorio-folha-table tbody tr')).filter(isRowVisible);
        const folhaMap = {};
        folhaRows.forEach((row) => {
            const nomeCell = row.children[1];
            const brutoCell = row.children[2];
            const nome = toTitleCase(nomeCell ? nomeCell.textContent : 'N/D');
            const bruto = parseEuroValue(brutoCell ? brutoCell.textContent : '0');
            folhaMap[nome] = (folhaMap[nome] || 0) + bruto;
        });
        const folhaOrdenado = Object.entries(folhaMap).sort((a, b) => b[1] - a[1]).slice(0, 10);
        updateChartData('chartFolhaCustos', folhaOrdenado.map((f) => f[0]), folhaOrdenado.map((f) => Number(f[1].toFixed(2))));
    };

    document.addEventListener('DOMContentLoaded', function() {
        window.setTimeout(() => {
            if (typeof window.updateRelatorioChartsFromFilters === 'function') {
                window.updateRelatorioChartsFromFilters();
            }
        }, 200);
    });

    function validatePtNif(value) {
        return /^\d{9}$/.test(value);
    }

    function validatePtNiss(value) {
        return /^\d{11}$/.test(value);
    }

    function attachRealtimeValidation() {
        const addForm = document.getElementById('addEmployeeForm');
        const editForm = document.getElementById('editEmployeeForm');
        const forms = [
            { form: addForm, mode: 'add' },
            { form: editForm, mode: 'edit' }
        ].filter(Boolean);

        const attachToForm = ({ form, mode }) => {
            if (!form || form.dataset.validationReady === '1') return;
            form.dataset.validationReady = '1';

            const nifInput = form.querySelector('[name="nif"]');
            const nissInput = form.querySelector('[name="niss"]');
            const pinInput = form.querySelector('[name="pin"]');
            const emailInput = form.querySelector('[name="email"]');

            if (nifInput) {
                nifInput.addEventListener('input', () => {
                    nifInput.value = (nifInput.value || '').replace(/\D/g, '').slice(0, 9);
                    if (nifInput.value.length > 0 && !validatePtNif(nifInput.value)) {
                        nifInput.setCustomValidity('NIF deve ter exatamente 9 dígitos.');
                    } else {
                        nifInput.setCustomValidity('');
                    }
                });
            }

            if (nissInput) {
                nissInput.addEventListener('input', () => {
                    nissInput.value = (nissInput.value || '').replace(/\D/g, '').slice(0, 11);
                    if (nissInput.value.length > 0 && !validatePtNiss(nissInput.value)) {
                        nissInput.setCustomValidity('NISS deve ter exatamente 11 dígitos.');
                    } else {
                        nissInput.setCustomValidity('');
                    }
                });
            }

            if (pinInput) {
                pinInput.addEventListener('input', () => {
                    const val = (pinInput.value || '').trim();
                    if (val !== '' && val.length < 4) {
                        pinInput.setCustomValidity('PIN deve ter pelo menos 4 dígitos.');
                    } else {
                        pinInput.setCustomValidity('');
                    }
                });
            }

            if (emailInput) {
                emailInput.addEventListener('blur', async () => {
                    const email = (emailInput.value || '').trim();
                    if (!email) return;

                    const excludeId = mode === 'edit'
                        ? (form.querySelector('[name="id"]')?.value || '')
                        : '';

                    try {
                        const url = `../api/employees/check_email.php?email=${encodeURIComponent(email)}${excludeId ? `&exclude_id=${encodeURIComponent(excludeId)}` : ''}`;
                        const res = await fetch(url, { credentials: 'same-origin' });
                        const data = await res.json();
                        if (data && data.success && data.duplicate) {
                            emailInput.setCustomValidity('Este email já está cadastrado.');
                            setInlineEmployeeError(mode, 'Este email já está cadastrado para outro funcionário.');
                        } else if (data && data.success && data.valid === false) {
                            emailInput.setCustomValidity(data.message || 'Email inválido.');
                            setInlineEmployeeError(mode, data.message || 'Email inválido.');
                        } else {
                            emailInput.setCustomValidity('');
                            setInlineEmployeeError(mode, '');
                        }
                    } catch (e) {
                        console.warn('Falha ao validar email em tempo real:', e);
                    }
                });
            }

            form.addEventListener('submit', (event) => {
                const isValid = form.checkValidity();
                if (!isValid) {
                    event.preventDefault();
                    setInlineEmployeeError(mode, 'Corrija os campos destacados antes de continuar.');
                    form.reportValidity();
                    return;
                }

                if (nifInput && nifInput.value && !validatePtNif(nifInput.value)) {
                    event.preventDefault();
                    setInlineEmployeeError(mode, 'NIF inválido: deve conter 9 dígitos.');
                    return;
                }

                if (nissInput && nissInput.value && !validatePtNiss(nissInput.value)) {
                    event.preventDefault();
                    setInlineEmployeeError(mode, 'NISS inválido: deve conter 11 dígitos.');
                    return;
                }

                if (pinInput) {
                    const pin = (pinInput.value || '').trim();
                    if (pin !== '' && pin.length < 4) {
                        event.preventDefault();
                        setInlineEmployeeError(mode, 'PIN inválido: mínimo de 4 dígitos.');
                        return;
                    }
                }

                setInlineEmployeeError(mode, '');
            });
        };

        forms.forEach(attachToForm);
    }

    function updateRowToInactive(employeeId) {
        const rows = Array.from(document.querySelectorAll(`.btn-employee-deactivate[data-id="${employeeId}"]`))
            .map((btn) => btn.closest('tr'))
            .filter(Boolean);

        if (rows.length === 0) return;

        rows.forEach((row) => {
            row.classList.add('disabled-row');

            const statusBadge = row.querySelector(`#status-${employeeId}`) || row.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.classList.remove('status-active', 'status-ferias', 'status-pendente', 'status-rejeitado');
                statusBadge.classList.add('status-inactive');
                statusBadge.innerHTML = '<i class="fas fa-times-circle"></i> Inativo';
            }

            const editBtn = row.querySelector('.btn-edit');
            if (editBtn) {
                editBtn.classList.add('btn-disabled');
                editBtn.setAttribute('disabled', 'true');
                editBtn.setAttribute('title', 'Funcionário inativo ou em férias');
            }

            const deleteBtn = row.querySelector('.btn-employee-deactivate');
            if (deleteBtn) {
                deleteBtn.classList.add('btn-disabled');
                deleteBtn.setAttribute('disabled', 'true');
                deleteBtn.setAttribute('title', 'Funcionário já desativado');
            }

            const actions = row.querySelector('td:last-child div');
            const hasActivate = !!row.querySelector('.btn-activate');
            if (actions && !hasActivate) {
                const activateBtn = document.createElement('button');
                activateBtn.className = 'fr-btn fr-btn-activate btn-activate';
                activateBtn.setAttribute('data-id', String(employeeId));
                activateBtn.title = 'Ativar';
                activateBtn.innerHTML = '<i class="fas fa-user-check"></i>';
                actions.appendChild(activateBtn);
            }
        });
    }

    async function bulkDeleteSelectedEmployees(event) {
        const trigger = (event && event.currentTarget) ? event.currentTarget : document.activeElement;
        const isBulkTrigger = !!(trigger && trigger.closest && trigger.closest('#bulkActionsBar'));
        if (!isBulkTrigger) {
            return;
        }

        const selected = document.querySelectorAll('#funcionarios-section .employee-row .employee-checkbox:checked');
        if (selected.length === 0) {
            showWarning('Selecione pelo menos um funcionário');
            return;
        }

        const confirmed = await showConfirm(
            'Excluir Funcionários',
            `Deseja excluir ${selected.length} funcionário${selected.length !== 1 ? 's' : ''}? Esta ação é permanente.`,
            'Sim, excluir',
            'Cancelar'
        );
        if (!confirmed) return;

        for (const cb of selected) {
            const empId = cb.dataset.employeeId;
            if (!empId) continue;

            const fd = new FormData();
            fd.append('id', empId);
            fd.append('hard_delete', '1');

            try {
                const res = await fetch('../api/employees/delete_employee.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                });
                const data = await parseJsonResponseSafe(res);
                if (data.success) {
                    const row = cb.closest('tr');
                    if (row) row.remove();
                } else {
                    showError(data.message || 'Erro ao excluir funcionario.');
                }
            } catch (err) {
                console.error('Erro ao excluir em lote:', err);
                showError('Erro ao excluir funcionario.');
            }
        }

        showSuccess('Funcionários excluídos com sucesso');
        if (typeof window.clearBulkSelection === 'function') window.clearBulkSelection();
    }

    // Garante que o onclick do HTML sempre encontre a função.
    window.bulkDeleteSelected = bulkDeleteSelectedEmployees;

    async function deactivateEmployeeOnServer(employeeId) {
        const fd = new FormData();
        fd.append('id', employeeId);
        fd.append('status', 'inactive');

        try {
            const res = await fetch('../api/employees/update_employee.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await parseJsonResponseSafe(res);
            if (data && data.success) {
                return { success: true, data };
            }
        } catch (err) {
            console.warn('update_employee falhou, tentando fallback delete_employee:', err);
        }

        try {
            const resFallback = await fetch('../api/employees/delete_employee.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const dataFallback = await parseJsonResponseSafe(resFallback);
            return { success: !!(dataFallback && dataFallback.success), data: dataFallback };
        } catch (err) {
            console.error('delete_employee fallback falhou:', err);
            return { success: false, data: { message: 'Erro ao desativar funcionario.' } };
        }
    }

    function attachSoftDeleteInterception() {
        if (document.body.dataset.softDeleteReady === '1') return;
        document.body.dataset.softDeleteReady = '1';

        document.addEventListener('click', async (e) => {
            const deleteBtn = e.target.closest('.btn-employee-deactivate[data-id]');
            if (!deleteBtn) return;

            if (deleteBtn.disabled) return;

            e.preventDefault();
            e.stopImmediatePropagation();

            const employeeId = deleteBtn.getAttribute('data-id');
            if (!employeeId) return;

            const ok = await showConfirm(
                'Desativar Funcionário',
                'Deseja desativar este funcionário? Os dados serão preservados.',
                'Sim, desativar',
                'Cancelar'
            );
            if (!ok) return;

            // Atualiza UI imediatamente (mesma experiência do botão Ativar).
            updateRowToInactive(employeeId);
            showSuccess('O funcionario foi desativado com sucesso');

            const serverResult = await deactivateEmployeeOnServer(employeeId);
            if (!serverResult.success) {
                await reconcileDeactivateResult(employeeId, (serverResult.data && serverResult.data.message) || 'Erro ao desativar funcionario.');
            }
        }, true);

        // Mantém o handler global apontando para a implementação única.
        window.bulkDeleteSelected = bulkDeleteSelectedEmployees;

        // Botão rápido: somente inativos
        const onlyInactiveBtn = document.getElementById('filterOnlyInactiveBtn');
        const statusSelect = document.getElementById('filterStatus');
        if (onlyInactiveBtn && statusSelect && !onlyInactiveBtn.dataset.bound) {
            onlyInactiveBtn.dataset.bound = '1';
            onlyInactiveBtn.addEventListener('click', () => {
                statusSelect.value = 'inactive';
                statusSelect.dispatchEvent(new Event('change'));
                showInfo('Filtro aplicado: somente inativos.');
            });
        }
    }

    function wrapFetchForEmployeeFeedback() {
        if (employeeModuleState.fetchWrapped) return;
        employeeModuleState.fetchWrapped = true;

        const originalFetch = window.fetch.bind(window);
        window.fetch = async function (...args) {
            const res = await originalFetch(...args);
            const url = String(args[0] || '');

            try {
                if (url.includes('../api/employees/get_employee.php')) {
                    const payload = await res.clone().json();
                    if (payload && payload.activity_history) {
                        employeeModuleState.lastHistory = payload.activity_history;
                        renderEmployeeHistory(payload.activity_history);
                    }
                }

                if (url.includes('../api/employees/create_employee.php')) {
                    const payload = await res.clone().json();
                    if (payload && payload.success === false) {
                        setInlineEmployeeError('add', payload.message || 'Erro ao adicionar funcionário.');
                    } else if (payload && payload.success) {
                        setInlineEmployeeError('add', '');
                    }
                }

                if (url.includes('../api/employees/update_employee.php')) {
                    const payload = await res.clone().json();
                    if (payload && payload.success === false) {
                        setInlineEmployeeError('edit', payload.message || 'Erro ao atualizar funcionário.');
                    } else if (payload && payload.success) {
                        setInlineEmployeeError('edit', '');
                    }
                }
            } catch (e) {
                // Ignora parsing quando resposta não for JSON desse fluxo.
            }

            return res;
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
        attachRealtimeValidation();
        attachSoftDeleteInterception();
        wrapFetchForEmployeeFeedback();

        // Estado inicial do bloco de histórico
        renderEmployeeHistory(employeeModuleState.lastHistory);
    });

    // Garantia extra: se o script carregar após o DOM já pronto,
    // ativa o interceptador imediatamente para evitar conflito com handlers legados.
    if (document.readyState !== 'loading') {
        attachRealtimeValidation();
        attachSoftDeleteInterception();
        wrapFetchForEmployeeFeedback();
        renderEmployeeHistory(employeeModuleState.lastHistory);
    }
})();





// ============================================================
// Folha de pagamento mensal: recibo, variaveis, pesquisa e exportacao
// ============================================================
(function () {
    function fmtEuro(val) {
        var n = parseFloat(val) || 0;
        return 'EUR ' + n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function fmtPct(val) {
        return ((parseFloat(val) || 0) * 100).toFixed(2).replace('.', ',') + ' %';
    }

    function safeJsonParse(value) {
        try {
            return JSON.parse(value || '{}');
        } catch (e) {
            return {};
        }
    }

    function updateGorjetaPreview() {
        var input = document.getElementById('fvGorjetaManual');
        var preview = document.getElementById('fvGorjetaManualPreview');
        if (!input || !preview || input.type === 'hidden') return;
        var valor = parseFloat(input.value || '0');
        preview.textContent = fmtEuro(isNaN(valor) ? 0 : valor);
    }

    function openReciboModal(row) {
        var folha = safeJsonParse(row.dataset.folha);
        var nome = row.dataset.empName || '-';
        var cargo = row.dataset.empPosition || '';
        var depto = row.dataset.empDepartment || '';
        var statusText = row.dataset.empStatus || '';
        var periodo = row.dataset.periodo || '';

        var nomeEl = document.getElementById('recibo-nome');
        var cargoEl = document.getElementById('recibo-cargo');
        var periodoEl = document.getElementById('recibo-periodo');
        var badge = document.getElementById('recibo-status-badge');
        if (!nomeEl || !cargoEl || !periodoEl || !badge) return;

        nomeEl.textContent = nome;
        cargoEl.textContent = [cargo, depto].filter(Boolean).join(' | ');
        periodoEl.textContent = periodo;

        badge.textContent = statusText;
        badge.style.background = statusText === 'Pago'
            ? 'rgba(22,163,74,.3)'
            : (statusText === 'Processado' ? 'rgba(234,179,8,.3)' : 'rgba(100,116,139,.35)');

        var get = function (k) { return parseFloat(folha[k]) || 0; };
        var set = function (id, value) {
            var el = document.getElementById(id);
            if (el) el.textContent = value;
        };

        set('r-salario-base', fmtEuro(get('salario_base')));
        set('r-subsidio-alim', fmtEuro(get('subsidio_alimentacao')));
        set('r-horas-extra', fmtEuro(get('horas_extra')));
        set('r-bonus', fmtEuro(get('bonus')));
        set('r-gorjetas', fmtEuro(get('gorjetas')));
        set('r-bruto', fmtEuro(get('salario_bruto')));

        set('r-liquido', fmtEuro(get('salario_liquido')));

        var modal = document.getElementById('modalReciboFolha');
        if (modal) modal.style.display = 'block';
    }

    function openVariaveisModal(row) {
        var modal = document.getElementById('modalFolhaVariaveis');
        if (!modal) return;

        var employeeId = row.querySelector('.btn-folha-ver') ? row.querySelector('.btn-folha-ver').getAttribute('data-id') : '';
        var setVal = function (id, value) {
            var el = document.getElementById(id);
            if (!el) return;
            if (el.type === 'checkbox') {
                el.checked = String(value) === '1';
            } else {
                el.value = value;
            }
        };

        setVal('fvEmployeeId', employeeId || '');
        setVal('fvEmployeeName', row.dataset.empName || '');
        setVal('fvHorasExtra', row.dataset.horasExtra || '0');
        setVal('fvBonusMensal', row.dataset.bonusMensal || '0');
        setVal('fvSubsidiosMensais', row.dataset.subsidiosMensais || '0');
        var gorjetaInput = document.getElementById('fvGorjetaManual');
        var gorjetaBaseAtual = row.dataset.gorjetaBase || '0';
        var gorjetaValorModal = row.dataset.gorjetaManual || '0';
        if (gorjetaInput && gorjetaInput.type !== 'hidden') {
            // Em modo manual, o campo mostra o total atual de gorjetas do funcionário no mês.
            gorjetaValorModal = row.dataset.gorjetaTotal || gorjetaValorModal;
        }
        setVal('fvGorjetaManual', gorjetaValorModal);
        setVal('fvGorjetaBaseAtual', gorjetaBaseAtual);
        setVal('fvIsLocked', row.dataset.isLocked || '0');

        var periodoEl = document.getElementById('folhaVarPeriodo');
        if (periodoEl) periodoEl.textContent = 'Periodo: ' + (row.dataset.periodo || '');

        updateGorjetaPreview();

        modal.style.display = 'block';
    }

    function closeModalById(id) {
        var modal = document.getElementById(id);
        if (modal) modal.style.display = 'none';
    }

    document.addEventListener('click', function (e) {
        var closeVarBtn = e.target.closest('#closeFolhaVariaveis, #cancelFolhaVariaveis');
        if (closeVarBtn) {
            closeModalById('modalFolhaVariaveis');
            return;
        }

        var verBtn = e.target.closest('.btn-folha-ver');
        if (verBtn) {
            var verRow = verBtn.closest('tr');
            if (verRow) openReciboModal(verRow);
            return;
        }

        var editBtn = e.target.closest('.btn-folha-edit');
        if (editBtn) {
            if (editBtn.disabled) return;
            var editRow = editBtn.closest('tr');
            if (!editRow) return;

            if (editBtn.getAttribute('data-pago') === '1') {
                if (editBtn.dataset.unpaying === '1') return;

                var confirmPromise = (typeof showConfirm === 'function')
                    ? showConfirm(
                        'Reverter pagamento?',
                        'Esta folha já foi marcada como paga. Para editar as variáveis é preciso reverter o pagamento para pendente. Deseja continuar?',
                        'Sim, reverter e editar',
                        'Cancelar'
                    )
                    : Promise.resolve(
                        window.confirm('Esta folha já foi marcada como paga. Deseja reverter o pagamento para pendente e editar?')
                            ? { isConfirmed: true }
                            : { isConfirmed: false }
                    );

                Promise.resolve(confirmPromise).then(function (result) {
                    if (!result || !result.isConfirmed) return;

                    editBtn.dataset.unpaying = '1';
                    var empId  = editBtn.getAttribute('data-id');
                    var fyear  = editRow.getAttribute('data-fiscal-year');
                    var fmonth = editRow.getAttribute('data-fiscal-month');
                    var body = new URLSearchParams();
                    body.append('action', 'unmark_as_paid');
                    body.append('employee_id', empId);
                    body.append('fiscal_year', fyear);
                    body.append('fiscal_month', fmonth);

                    fetch(window.location.pathname, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString()
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        delete editBtn.dataset.unpaying;
                        if (!data || !data.ok) {
                            if (typeof showError === 'function') showError((data && data.error) || 'Não foi possível reverter o pagamento.');
                            return;
                        }

                        // Atualizar estado da linha sem recarregar a página
                        editRow.setAttribute('data-status-pagamento', 'pendente');
                        editBtn.setAttribute('data-pago', '0');
                        editBtn.title = 'Editar Variáveis Mensais';

                        var badge = editRow.querySelector('.status-pagamento');
                        if (badge) {
                            badge.className = 'status-pagamento status-pagamento-pendente';
                            badge.innerHTML = '<i class="fas fa-clock"></i> Pendente';
                            var dateHint = badge.nextElementSibling;
                            if (dateHint && dateHint.tagName === 'DIV') dateHint.remove();
                        }

                        if (!editRow.querySelector('.btn-folha-pagar')) {
                            var pagarBtnHtml = document.createElement('button');
                            pagarBtnHtml.type = 'button';
                            pagarBtnHtml.className = 'fr-btn fr-btn-activate btn-folha-pagar employee-action-btn';
                            pagarBtnHtml.setAttribute('data-emp-id', empId);
                            pagarBtnHtml.setAttribute('data-fiscal-year', fyear);
                            pagarBtnHtml.setAttribute('data-fiscal-month', fmonth);
                            pagarBtnHtml.title = 'Marcar como Pago';
                            pagarBtnHtml.innerHTML = '<i class="fas fa-check"></i>';
                            editBtn.insertAdjacentElement('afterend', pagarBtnHtml);
                        }

                        if (typeof showSuccess === 'function') {
                            showSuccess('Pagamento revertido. Editando variáveis...');
                        }

                        openVariaveisModal(editRow);
                    })
                    .catch(function (err) {
                        delete editBtn.dataset.unpaying;
                        console.error('Erro ao reverter pagamento:', err);
                        if (typeof showError === 'function') showError('Erro ao comunicar com o servidor ao reverter pagamento.');
                    });
                });
                return;
            }

            openVariaveisModal(editRow);
            return;
        }

        // ── Marcar como Pago (AJAX) ──
        var pagarBtn = e.target.closest('.btn-folha-pagar');
        if (pagarBtn) {
            if (pagarBtn.disabled) return;
            pagarBtn.disabled = true;
            pagarBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            var empId   = pagarBtn.getAttribute('data-emp-id');
            var fyear   = pagarBtn.getAttribute('data-fiscal-year');
            var fmonth  = pagarBtn.getAttribute('data-fiscal-month');
            var body    = new URLSearchParams();
            body.append('action', 'mark_as_paid');
            body.append('employee_id', empId);
            body.append('fiscal_year', fyear);
            body.append('fiscal_month', fmonth);

            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    var row = pagarBtn.closest('tr');
                    if (row) {
                        // Atualizar badge
                        var badge = row.querySelector('.status-badge');
                        if (badge) {
                            badge.className = 'status-badge status-active';
                            badge.textContent = 'Pago';
                        }
                        // Atualizar data attribute
                        row.setAttribute('data-status-pagamento', 'pago');
                        // Marcar edição como sujeita a reverter pagamento (mas continua clicável)
                        var rowEditBtn = row.querySelector('.btn-folha-edit');
                        if (rowEditBtn) {
                            rowEditBtn.setAttribute('data-pago', '1');
                            rowEditBtn.title = 'Pagamento já efetuado — clique para reverter e editar';
                        }
                        // Remover botão Pagar
                        pagarBtn.remove();
                    }
                } else {
                    pagarBtn.disabled = false;
                    pagarBtn.innerHTML = '<i class="fas fa-check"></i> Pagar';
                    alert('Erro ao marcar como pago: ' + (data.error || 'tente novamente.'));
                }
            })
            .catch(function () {
                pagarBtn.disabled = false;
                pagarBtn.innerHTML = '<i class="fas fa-check"></i> Pagar';
                alert('Erro de comunicação. Recarregue a página e tente novamente.');
            });
            return;
        }

        var reciboModal = document.getElementById('modalReciboFolha');
        if (reciboModal && e.target === reciboModal) {
            reciboModal.style.display = 'none';
            return;
        }

        var varModal = document.getElementById('modalFolhaVariaveis');
        if (varModal && e.target === varModal) {
            varModal.style.display = 'none';
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModalById('modalReciboFolha');
            closeModalById('modalFolhaVariaveis');
        }
    });

    var folhaVariaveisForm = document.querySelector('#modalFolhaVariaveis form');
    if (folhaVariaveisForm) {
        folhaVariaveisForm.addEventListener('submit', function () {
            var gorjetaInput = document.getElementById('fvGorjetaManual');
            var gorjetaBaseInput = document.getElementById('fvGorjetaBaseAtual');
            if (!gorjetaInput || !gorjetaBaseInput || gorjetaInput.type === 'hidden') {
                return;
            }

            var gorjetaTotal = parseFloat(gorjetaInput.value || '0');
            var gorjetaBase = parseFloat(gorjetaBaseInput.value || '0');
            if (isNaN(gorjetaTotal)) gorjetaTotal = 0;
            if (isNaN(gorjetaBase)) gorjetaBase = 0;

            // O backend guarda apenas o adicional manual.
            var gorjetaManualSalvar = Math.max(0, gorjetaTotal - gorjetaBase);
            gorjetaInput.value = gorjetaManualSalvar.toFixed(2);
        });
    }

    var gorjetaInputLive = document.getElementById('fvGorjetaManual');
    if (gorjetaInputLive) {
        gorjetaInputLive.addEventListener('input', updateGorjetaPreview);
        gorjetaInputLive.addEventListener('change', updateGorjetaPreview);
    }

    function updateFolhaFilterBadge() {
        var badge = document.getElementById('folhaFilterBadge');
        if (!badge) return;
        var count = [searchInput && searchInput.value, filtroStatus && filtroStatus.value].filter(Boolean).length;
        if (count > 0) { badge.textContent = String(count); badge.style.display = 'flex'; }
        else { badge.style.display = 'none'; }
        var clearBtn = document.getElementById('clearFolhaFiltersBtn');
        if (clearBtn) clearBtn.style.display = count > 0 ? '' : 'none';
    }

    function applyFolhaFilters() {
        var term   = ((searchInput && searchInput.value) || '').toLowerCase().trim();
        var status = (filtroStatus && filtroStatus.value) || '';
        var rows   = document.querySelectorAll('#folha-pagamento-section tbody tr');
        rows.forEach(function (row) {
            var txt  = (row.textContent || '').toLowerCase();
            var spag = (row.getAttribute('data-status-pagamento') || '').toLowerCase();
            var matchTerm   = (term   === '' || txt.indexOf(term) >= 0);
            var matchStatus = (status === '' || spag === status);
            row.style.display = (matchTerm && matchStatus) ? '' : 'none';
        });
        updateFolhaFilterBadge();
    }

    var searchInput = document.getElementById('searchFolhaEmployees');
    var filtroStatus = document.getElementById('filtroStatusPagamento');

    if (searchInput) {
        searchInput.addEventListener('input', applyFolhaFilters);
    }
    if (filtroStatus) {
        filtroStatus.addEventListener('change', applyFolhaFilters);
    }

    var clearFolhaBtn = document.getElementById('clearFolhaFiltersBtn');
    if (clearFolhaBtn) {
        clearFolhaBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            if (filtroStatus) filtroStatus.value = '';
            applyFolhaFilters();
        });
    }

    var exportBtn = document.getElementById('btnExportFolhaCsv');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            var rows = Array.prototype.slice.call(document.querySelectorAll('#folha-pagamento-section tbody tr'));
            var csv = ['Funcionario;Salario Base;Total Bruto;Liquido;Status Pagamento'];

            rows.forEach(function (row) {
                if (row.style.display === 'none') return;
                var cells = row.querySelectorAll('td');
                if (!cells || cells.length < 6) return;
                var item = [
                    (cells[0].textContent || '').trim(),
                    (cells[1].textContent || '').trim(),
                    (cells[2].textContent || '').trim(),
                    (cells[3].textContent || '').trim(),
                    (cells[4].textContent || '').trim(),
                    (row.getAttribute('data-status-pagamento') || '').trim()
                ].join(';');
                csv.push(item);
            });

            var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'folha_pagamento.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        });
    }
}());

// ============================================================
// Configuracao salarial: tabela de escaloes IRS dinamica
// ============================================================
(function () {
    function buildBracketRow() {
        var tr = document.createElement('tr');
        tr.className = 'irs-bracket-row';
        tr.innerHTML = [
            '<td style="padding:.4rem .5rem;"><input type="number" step="0.01" min="0" name="irs_min[]" value="0.00" class="search-input" style="width:100%;"></td>',
            '<td style="padding:.4rem .5rem;"><input type="number" step="0.01" min="0" name="irs_max[]" value="" class="search-input" style="width:100%;" placeholder="Sem limite"></td>',
            '<td style="padding:.4rem .5rem;"><div style="display:flex;align-items:center;gap:.3rem;"><input type="number" step="0.01" min="0" max="100" name="irs_taxa[]" value="0.00" class="search-input" style="width:100%;"><span style="white-space:nowrap;color:var(--text-secondary);font-size:.85rem;">%</span></div></td>',
            '<td style="padding:.4rem .5rem;"><input type="number" step="0.01" min="0" name="irs_parcela[]" value="0.00" class="search-input" style="width:100%;"></td>',
            '<td style="padding:.4rem .5rem; text-align:center;"><button type="button" class="btn btn-danger btn-sm btn-remove-irs-bracket" title="Remover escalão"><i class="fas fa-trash"></i></button></td>'
        ].join('');
        return tr;
    }

    document.addEventListener('click', function (e) {
        var addBtn = e.target.closest('#btnAddIrsBracket');
        if (addBtn) {
            var body = document.getElementById('irsBracketsBody');
            if (body) {
                body.appendChild(buildBracketRow());
            }
            return;
        }

        var removeBtn = e.target.closest('.btn-remove-irs-bracket');
        if (!removeBtn) return;

        var bodyEl = document.getElementById('irsBracketsBody');
        if (!bodyEl) return;

        var row = removeBtn.closest('tr');
        if (!row) return;

        if (bodyEl.querySelectorAll('tr.irs-bracket-row').length <= 1) {
            if (typeof showWarning === 'function') {
                showWarning('A tabela IRS precisa de pelo menos um escalão.');
            } else {
                alert('A tabela IRS precisa de pelo menos um escalão.');
            }
            return;
        }

        row.remove();
    });
}());

function imprimirRecibo() {
    window.print();
}

