    document.addEventListener('DOMContentLoaded', () => {

        // Parser JSON robusto: captura texto bruto e tenta parse; em caso de falha loga o conteúdo para facilitar debug
        const parseJsonSafe = async (res) => {
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Resposta inválida do servidor (esperado JSON):', text);
                throw new Error('Resposta inválida do servidor: JSON inválido');
            }
        };

        // Função para atualizar o status visualmente
        const updateStatusOnScreen = (employeeId, newStatus) => {
            // Encontrar o badge de status do funcionário na seção de assiduidade
            const statusBadge = document.querySelector(`#assiduidade-section #attendance-status-${employeeId}`);
            
            if (statusBadge) {
                const currentTime = new Date().toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
                let statusText = '';
                let statusClass = '';

                if (newStatus === 'presente') {
                    statusText = 'Presente';
                    statusClass = 'status-badge status-presente';
                } else if (newStatus === 'falta') {
                    statusText = 'Falta';
                    statusClass = 'status-badge status-falta';
                } 
                
                statusBadge.textContent = statusText;
                statusBadge.className = statusClass;
                
                console.log(`Status atualizado para funcionário ${employeeId}: ${statusText}`);
            } else {
                console.error(`Badge de status não encontrado para funcionário ${employeeId}`);
            }
        };

       

        const handleAttendanceClick = (employeeId, newStatus) => {
            // Bloqueia marcar presença/falta para inativos ou em férias
            const statusEl = document.getElementById('status-' + employeeId);
            if (statusEl) {
                const cls = statusEl.className || '';
                const txt = statusEl.textContent ? statusEl.textContent.trim().toLowerCase() : '';
                if (cls.includes('status-ferias') || cls.includes('status-inactive') || txt === 'ferias' || txt === 'férias' || txt === 'inativo' || txt === 'inactive') {
                    showError('Funcionário inativo ou em férias não pode ser marcado aqui.');
                    return;
                }
            }
            fetch('../api/gorjetas/presenca/salvar_presenca.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: employeeId,
                    status: newStatus
                })
            })
            .then(parseJsonSafe)
            .then(data => {
                if (data.success) {
                    // Atualizar visualmente sem recarregar a página
                    updateStatusOnScreen(employeeId, newStatus);

                    // Se o servidor retornou os dados da atividade, adiciona imediatamente à lista de atividades
                    if (data.activity) {
                        appendActivityToList(data.activity, data.employee || null);
                    }

                    // Caso o servidor não tenha enviado dados do employee, ou a foto não exista, tentar buscar server-side pelo id
                    if (data.activity && (!data.employee || !data.employee.profile_picture) && data.activity.employee_id) {
                        fetch('../api/employees/get_employee.php?id=' + encodeURIComponent(data.activity.employee_id))
                            .then(parseJsonSafe)
                            .then(empFull => {
                                // atualiza o primeiro item (o que acabámos de inserir)
                                // tenta encontrar o primeiro .activity-item e ajusta o avatar
                                const list = document.querySelector('.activity-list');
                                if (!list || !list.firstChild) return;
                                const first = list.firstChild;
                                // substitui avatar
                                let avatarEl = first.querySelector('.activity-item-avatar');
                                if (!avatarEl) {
                                    // se não existir, adiciona um novo
                                    const div = document.createElement('div');
                                    div.className = empFull.profile_picture ? 'activity-item-avatar' : 'activity-item-avatar activity-item-avatar--initials';
                                    if (empFull.profile_picture) div.innerHTML = `<img src="../${empFull.profile_picture}" alt="${empFull.name}">`;
                                    else {
                                        const parts = (empFull.name || '').split(/\s+/).filter(Boolean);
                                        let initials = (parts[0]||'').charAt(0).toUpperCase(); if (parts.length>1) initials += (parts[1]||'').charAt(0).toUpperCase();
                                        div.innerHTML = `<span>${initials}</span>`;
                                    }
                                    first.insertBefore(div, first.firstChild);
                                } else {
                                    // atualiza conteúdo existente
                                    if (empFull.profile_picture) avatarEl.innerHTML = `<img src="../${empFull.profile_picture}" alt="${empFull.name}">`;
                                    else {
                                        const parts = (empFull.name || '').split(/\s+/).filter(Boolean);
                                        let initials = (parts[0]||'').charAt(0).toUpperCase(); if (parts.length>1) initials += (parts[1]||'').charAt(0).toUpperCase();
                                        avatarEl.classList.add('activity-item-avatar--initials');
                                        avatarEl.innerHTML = `<span>${initials}</span>`;
                                    }
                                }
                            })
                            .catch(e => console.warn('Não foi possível obter empregado para atividade:', e));
                    }

                    showSuccess(newStatus === 'presente' ? 'Presença marcada com sucesso!' : 'Falta marcada com sucesso!');

                    // NÃO desabilitar os botões - permite alterações
                    // Usuário pode mudar de presença para falta ou vice-versa
                } else {
                    console.error('Erro:', data.message);
                    showError('Erro ao marcar presença. Verifique o funcionário.');
                }
            })
            .catch(error => {
                console.error('Erro na comunicação:', error);
                showError('Erro na comunicação com o servidor. Tente novamente.');
            });
        };

        // Adiciona um "ouvinte de evento" para cada botão
        document.querySelectorAll('.marcar-presenca').forEach(button => {
            button.addEventListener('click', () => {
                const employeeId = button.getAttribute('data-id');
                handleAttendanceClick(employeeId, 'presente');
            });
        });

        document.querySelectorAll('.marcar-falta').forEach(button => {
            button.addEventListener('click', () => {

                const employeeId = button.getAttribute('data-id');
                handleAttendanceClick(employeeId, 'falta');
            });
        });

        const ACTIVITY_ITEM_TTL_MS = 30000;

        function ensureActivityListPlaceholder(list) {
            if (!list) return;
            const hasItems = !!list.querySelector('.activity-item');
            if (hasItems) return;

            list.innerHTML = `
                <div class="activity-item info">
                    <div class="activity-item-icon"><i class="fas fa-info-circle"></i></div>
                    <div class="activity-details">
                        <div class="activity-item-title">Nenhuma atividade registada.</div>
                    </div>
                </div>
            `;
        }

        function scheduleActivityItemRemoval(item, createdAtMs = Date.now()) {
            if (!item) return;

            const elapsed = Math.max(0, Date.now() - createdAtMs);
            const delay = Math.max(0, ACTIVITY_ITEM_TTL_MS - elapsed);

            window.setTimeout(() => {
                if (!item.isConnected) return;
                const list = item.closest('.activity-list');
                item.remove();
                ensureActivityListPlaceholder(list);
            }, delay);
        }

        // Helper para inserir um item de atividade na lista (client-side)
        const appendActivityToList = (activity, employee=null) => {
            try {
                const list = document.querySelector('.activity-list');
                if (!list) return;

                const placeholder = list.querySelector('.activity-item.info .activity-item-title');
                if (placeholder && placeholder.textContent && placeholder.textContent.includes('Nenhuma atividade registada.')) {
                    list.innerHTML = '';
                }

                const item = document.createElement('div');
                item.className = 'activity-item ' + (activity.type || 'info');

                // Avatar
                let avatarHTML = '';
                if (employee && employee.profile_picture) {
                    avatarHTML = `<div class="activity-item-avatar"><img src="../${employee.profile_picture}" alt="${employee.name}"></div>`;
                } else if (employee && employee.name) {
                    const parts = employee.name.split(/\s+/).filter(Boolean);
                    let initials = (parts[0] || '').charAt(0).toUpperCase();
                    if (parts.length > 1) initials += (parts[1] || '').charAt(0).toUpperCase();
                    avatarHTML = `<div class="activity-item-avatar activity-item-avatar--initials"><span>${initials}</span></div>`;
                } else {
                    avatarHTML = `<div class="activity-item-icon"><i class="fas fa-info-circle"></i></div>`;
                }

                // Badge
                const statusText = activity.status || '';
                const badgeHTML = statusText ? `<span class="activity-badge status-${statusText.toLowerCase()}">${statusText}</span>` : '';

                const titleHTML = `<div class="activity-details"><div class="activity-item-title">${escapeHtml(activity.title || '')} ${badgeHTML}</div><div class="activity-item-time">${formatTime(activity.timestamp)}</div></div>`;

                item.innerHTML = avatarHTML + titleHTML;

                // inserir no topo
                if (list.firstChild) list.insertBefore(item, list.firstChild);
                else list.appendChild(item);

                scheduleActivityItemRemoval(item, Date.now());

                // limita número de itens (mantém 5)
                while (list.children.length > 5) list.removeChild(list.lastChild);

            } catch (e) {
                console.error('Erro appendActivityToList', e);
            }
        };

        // util helpers
        const escapeHtml = (unsafe) => {
            return (unsafe || '').replace(/[&<"'>]/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]; });
        };

        const formatTime = (ts) => {
            if (!ts) return '--:--:--';
            const d = new Date(ts.replace(' ', 'T'));
            if (isNaN(d.getTime())) return '--:--:--';
            return d.toLocaleTimeString();
        };

        document.querySelectorAll('.activity-list .activity-item').forEach((item) => {
            const titleEl = item.querySelector('.activity-item-title');
            if (titleEl && titleEl.textContent && titleEl.textContent.includes('Nenhuma atividade registada.')) {
                return;
            }
            scheduleActivityItemRemoval(item, Date.now());
        });
        
        // Ativar funcionário (presente nas linhas desabilitadas)
        document.querySelectorAll('.btn-activate').forEach(button => {
            button.addEventListener('click', async () => {
                const employeeId = button.getAttribute('data-id');
                const confirmed = await showConfirm(
                    'Ativar Funcionário',
                    'Deseja mudar o status deste funcionário para Ativo?',
                    'Sim, ativar',
                    'Cancelar'
                );
                
                if (!confirmed) return;

                const fd = new FormData();
                fd.append('id', employeeId);
                fd.append('status', 'active');
                fd.append('quick_status_toggle', '1');

                fetch('../api/employees/update_employee.php', {
                    method: 'POST',
                    body: fd
                })
                .then(parseJsonSafe)
                .then(data => {
                    if (data.success) {
                        showSuccess('Funcionário ativado com sucesso!');
                        
                        // Atualizar a linha dinamicamente
                        const row = button.closest('tr');
                        if (row) {
                            // Remover classe disabled-row
                            row.classList.remove('disabled-row');
                            
                            // Atualizar badge de status
                            const statusBadge = row.querySelector(`#status-${employeeId}`);
                            if (statusBadge) {
                                statusBadge.className = 'status-badge status-active';
                                statusBadge.textContent = 'Ativo';
                            }
                            
                            // Atualizar botões de ação
                            const actionsCell = row.cells[row.cells.length - 1];
                            const editBtn = actionsCell.querySelector('.btn-edit');
                            const deleteBtn = actionsCell.querySelector('.btn-employee-deactivate');
                            const activateBtn = actionsCell.querySelector('.btn-activate');
                            
                            // Habilitar botão de editar
                            if (editBtn) {
                                editBtn.classList.remove('btn-disabled');
                                editBtn.removeAttribute('disabled');
                                editBtn.setAttribute('title', 'Editar');
                            }

                            if (deleteBtn) {
                                deleteBtn.classList.remove('btn-disabled');
                                deleteBtn.removeAttribute('disabled');
                                deleteBtn.setAttribute('title', 'Desativar');
                            }
                            
                            // Remover botão de ativar
                            if (activateBtn) {
                                activateBtn.remove();
                            }
                        }
                    } else {
                        showError(data.message || 'Erro ao ativar funcionário');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showError('Erro ao ativar funcionário');
                });
            });
        });
    });

document.addEventListener('DOMContentLoaded', () => {
  const turnoModal = document.getElementById('turnoModal');
  const btnAddTurno = document.getElementById('btnAddTurno');
  const closeModal  = document.getElementById('closeTurnoModal');
  const turnoForm   = document.getElementById('turnoForm');
  const tbody       = document.querySelector('#turnosTable tbody');
      const bulkTurnoModal = document.getElementById('bulkTurnoModal');
      const btnOpenBulkTurnoModal = document.getElementById('btnOpenBulkTurnoModal');
      const closeBulkTurnoModal = document.getElementById('closeBulkTurnoModal');
      const bulkTurnoForm = document.getElementById('bulkTurnoForm');
        const turnoSwapModal = document.getElementById('turnoSwapModal');
        const btnOpenTurnoSwapModal = document.getElementById('btnOpenTurnoSwapModal');
        const closeTurnoSwapModal = document.getElementById('closeTurnoSwapModal');
        const turnoSwapForm = document.getElementById('turnoSwapForm');
        const swapRequesterTurno = document.getElementById('swapRequesterTurno');
        const swapTargetTurno = document.getElementById('swapTargetTurno');
    const turnoDiasPicker = document.getElementById('turnoDiasPicker');
    const turnoDiasToggle = document.getElementById('turnoDiasToggle');
    const turnoDiasLabel = document.getElementById('turnoDiasLabel');
    const turnoDiasMenu = document.getElementById('turnoDiasMenu');
    const turnoDiasInput = document.getElementById('turnoDiasInput');
    const turnoDataInicioInput = document.getElementById('turnoDataInicio');
    const turnoDataFimInput = document.getElementById('turnoDataFim');
    const turnosPublicationStart = document.getElementById('turnosPublicationStart');
    const turnosPublicationEnd = document.getElementById('turnosPublicationEnd');
    const btnCheckTurnosPublication = document.getElementById('btnCheckTurnosPublication');
    const btnPublishTurnosPeriod = document.getElementById('btnPublishTurnosPeriod');
    const btnCloseTurnosPeriod = document.getElementById('btnCloseTurnosPeriod');
    const btnReopenTurnosPeriod = document.getElementById('btnReopenTurnosPeriod');
    const turnosPublicationBadge = document.getElementById('turnosPublicationBadge');
    const turnosPublicationMeta = document.getElementById('turnosPublicationMeta');

  // ✅ VERIFICAR SE OS ELEMENTOS EXISTEM
    if (!turnoModal || !btnAddTurno || !closeModal || !turnoForm || !tbody || !turnoDiasToggle || !turnoDiasMenu || !turnoDiasInput) {
    console.warn('⚠️ Elementos de turnos não encontrados. Seção de turnos pode não estar visível.');
    return;
  }

  console.log('Turnos init: tbody found=', !!tbody, 'edit buttons=', tbody ? tbody.querySelectorAll('.btn-edit').length : 0, 'delete buttons=', tbody ? tbody.querySelectorAll('.btn-delete').length : 0);

    if (btnOpenTurnoSwapModal && turnoSwapModal && turnoSwapForm) {
        btnOpenTurnoSwapModal.addEventListener('click', () => {
            turnoSwapForm.reset();
            turnoSwapModal.style.display = 'block';
        });
    }

    if (closeTurnoSwapModal && turnoSwapModal) {
        closeTurnoSwapModal.addEventListener('click', () => {
            turnoSwapModal.style.display = 'none';
        });
    }

    if (turnoSwapForm && swapRequesterTurno && swapTargetTurno) {
        turnoSwapForm.addEventListener('submit', (e) => {
            const requesterId = swapRequesterTurno.value;
            const targetId = swapTargetTurno.value;
            if (!requesterId || !targetId) {
                e.preventDefault();
                showError('Selecione os dois turnos para solicitar a troca.');
                return;
            }

            if (requesterId === targetId) {
                e.preventDefault();
                showError('Escolha turnos diferentes para a troca.');
                return;
            }

            const requesterOption = swapRequesterTurno.options[swapRequesterTurno.selectedIndex];
            const targetOption = swapTargetTurno.options[swapTargetTurno.selectedIndex];
            const requesterEmployeeId = requesterOption ? requesterOption.getAttribute('data-employee-id') || '' : '';
            const targetEmployeeId = targetOption ? targetOption.getAttribute('data-employee-id') || '' : '';

            if (requesterEmployeeId && targetEmployeeId && requesterEmployeeId === targetEmployeeId) {
                e.preventDefault();
                showError('A troca deve ser entre funcionários diferentes.');
            }
        });
    }

    if (turnosPublicationStart && turnosPublicationEnd) {
        const today = new Date();
        const weekStart = new Date(today);
        const day = weekStart.getDay();
        const diff = day === 0 ? -6 : 1 - day;
        weekStart.setDate(weekStart.getDate() + diff);
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6);

        if (!turnosPublicationStart.value) {
            turnosPublicationStart.value = toIsoDateLocal(weekStart);
        }
        if (!turnosPublicationEnd.value) {
            turnosPublicationEnd.value = toIsoDateLocal(weekEnd);
        }
    }

    if (btnCheckTurnosPublication) {
        btnCheckTurnosPublication.addEventListener('click', () => checkTurnosPublicationStatus({ silent: false }));
    }

    if (btnPublishTurnosPeriod) {
        btnPublishTurnosPeriod.addEventListener('click', publishTurnosPeriod);
    }

    if (btnCloseTurnosPeriod) {
        btnCloseTurnosPeriod.addEventListener('click', closeTurnosPeriod);
    }

    if (btnReopenTurnosPeriod) {
        btnReopenTurnosPeriod.addEventListener('click', reopenTurnosPeriod);
    }

    if (turnosPublicationStart) {
        turnosPublicationStart.addEventListener('change', () => checkTurnosPublicationStatus({ silent: true }));
    }

    if (turnosPublicationEnd) {
        turnosPublicationEnd.addEventListener('change', () => checkTurnosPublicationStatus({ silent: true }));
    }

    checkTurnosPublicationStatus({ silent: true });


    try {
        const params = new URLSearchParams(window.location.search || '');
        if (params.get('section') === 'turnos') {
            const swap = params.get('swap');
            if (swap === 'created') {
                showSuccess('Solicitação de troca de turno enviada com sucesso.');
            } else if (swap === 'duplicate') {
                showWarning('Já existe uma solicitação pendente para esta troca.');
            } else if (swap === 'conflict') {
                showError('Não foi possível solicitar: a troca geraria conflito de horários.');
            } else if (swap === 'invalid') {
                showError('Dados inválidos na solicitação de troca de turno.');
            } else if (swap === 'notfound') {
                showError('Turno não encontrado para criar a solicitação.');
            } else if (swap === 'csrf') {
                showError('Token CSRF inválido. Atualize a página e tente novamente.');
            } else if (swap === 'error') {
                showError('Erro interno ao criar solicitação de troca de turno.');
            }
        }
    } catch (e) {
        console.warn('Falha ao processar feedback de troca de turno:', e);
    }
  
  // ✅ PARSER JSON SEGURO
  const parseJsonSafe = async (res) => {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('Resposta inválida do servidor (esperado JSON):', text);
      throw new Error('Resposta inválida do servidor: JSON inválido');
    }
  };

    const getBulkTurnoDiaOptions = () => Array.from(document.querySelectorAll('.bulk-turno-dia-option'));
    const getBulkTurnoEmployeeOptions = () => Array.from(document.querySelectorAll('.bulk-turno-employee-option'));

    function getSelectedBulkDias() {
        return getBulkTurnoDiaOptions().filter(option => option.checked).map(option => option.value);
    }

    function getSelectedBulkEmployees() {
        return getBulkTurnoEmployeeOptions().filter(option => option.checked).map(option => option.value);
    }

    function closeBulkTurnoModalUI() {
        if (bulkTurnoModal) {
            bulkTurnoModal.style.display = 'none';
        }
    }

    function toIsoDateLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function setTurnosPublicationControls(status, total = 0) {
        if (btnPublishTurnosPeriod) btnPublishTurnosPeriod.disabled = status === 'fechado';
        if (btnCloseTurnosPeriod) btnCloseTurnosPeriod.disabled = status === 'fechado' || total <= 0;
        if (btnReopenTurnosPeriod) btnReopenTurnosPeriod.disabled = status !== 'fechado';
    }

    function formatPublicationDateTime(value) {
        const text = String(value || '').trim();
        if (!text) return '';
        const normalized = text.replace(' ', 'T');
        const dt = new Date(normalized);
        if (Number.isNaN(dt.getTime())) return text;
        return dt.toLocaleString('pt-PT', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function setTurnosPublicationMeta(status, updatedAt = '', actorName = '') {
        if (!turnosPublicationMeta) return;
        if (!status || !updatedAt) {
            turnosPublicationMeta.textContent = '';
            return;
        }

        const when = formatPublicationDateTime(updatedAt);
        const actor = String(actorName || '').trim();
        const actionLabel = status === 'fechado' ? 'Fechado' : 'Oficializado';
        turnosPublicationMeta.textContent = `${actionLabel}${actor ? ` por ${actor}` : ''}${when ? ` em ${when}` : ''}`;
    }

    function setTurnosPublicationBadge(status, total = 0) {
        if (!turnosPublicationBadge) return;

        let text = 'Sem período';
        let background = '#e2e8f0';
        let color = '#334155';

        if (status === 'publicado') {
            text = `Publicado (${total})`;
            background = '#dcfce7';
            color = '#166534';
        } else if (status === 'fechado') {
            text = `Fechado (${total})`;
            background = '#dbeafe';
            color = '#1d4ed8';
        } else if (status === 'sem_publicacao') {
            text = `Sem publicação (${total})`;
            background = '#fef3c7';
            color = '#92400e';
        } else if (status === 'sem_dados') {
            text = 'Sem turnos';
            background = '#fee2e2';
            color = '#991b1b';
        }

        turnosPublicationBadge.textContent = text;
        turnosPublicationBadge.style.background = background;
        turnosPublicationBadge.style.color = color;
        setTurnosPublicationControls(status, total);
    }

    function setTurnoRowLockedState(row, locked) {
        if (!row) return;
        row.dataset.turnoLocked = locked ? '1' : '0';

        const editBtn = row.querySelector('.btn-edit');
        const deleteBtn = row.querySelector('.btn-delete');
        [editBtn, deleteBtn].forEach((btn) => {
            if (!btn) return;
            btn.disabled = !!locked;
            btn.classList.toggle('btn-disabled', !!locked);
            if (locked) {
                btn.title = 'Período fechado';
            } else if (btn.classList.contains('btn-edit')) {
                btn.title = 'Editar';
            } else {
                btn.title = 'Excluir';
            }
        });
    }

    function rowOverlapsPeriod(row, periodStart, periodEnd) {
        const start = normalizeIsoDate(row?.dataset?.turnoDateStart) || '1000-01-01';
        const end = normalizeIsoDate(row?.dataset?.turnoDateEnd) || '9999-12-31';
        return start <= periodEnd && periodStart <= end;
    }

    function setRowsLockedByPeriod(periodStart, periodEnd, locked) {
        if (!tbody || !periodStart || !periodEnd) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.forEach((row) => {
            if (rowOverlapsPeriod(row, periodStart, periodEnd)) {
                setTurnoRowLockedState(row, locked);
            }
        });
    }

    async function checkTurnosPublicationStatus(options = {}) {
        const silent = !!options.silent;
        if (!turnosPublicationStart || !turnosPublicationEnd) return;

        const start = String(turnosPublicationStart.value || '').trim();
        const end = String(turnosPublicationEnd.value || '').trim();
        if (!start || !end) {
            setTurnosPublicationBadge('', 0);
            if (!silent) {
                showWarning('Selecione a data de início e fim para consultar o estado.');
            }
            return;
        }

        if (start > end) {
            showError('A data inicial não pode ser maior que a final.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'get_turnos_publication_status');
        formData.append('csrf_token', getCsrfToken());
        formData.append('period_start', start);
        formData.append('period_end', end);

        try {
            const response = await fetch('dashboard.php', {
                method: 'POST',
                body: formData
            });
            const result = await parseJsonSafe(response);
            if (!result || !result.success) {
                showError((result && result.message) ? result.message : 'Falha ao consultar oficialização.');
                return;
            }

            const status = String(result.status || '');
            const total = Number(result.total_turnos || 0);
            setTurnosPublicationBadge(status, total);
            setTurnosPublicationMeta(status, result.updated_at || '', result.published_by_name || '');

            if (!silent) {
                if (status === 'publicado') {
                    showInfo(`Estado atual: Publicado (${total} turno(s) no período).`);
                } else if (status === 'fechado') {
                    showInfo(`Estado atual: Fechado (${total} turno(s) no período). Alterações ficam bloqueadas.`);
                } else if (status === 'sem_publicacao') {
                    showInfo(`Estado atual: Sem publicação (${total} turno(s) no período).`);
                } else {
                    showInfo('Estado atual: Sem turnos para o período selecionado.');
                }
            }
        } catch (error) {
            console.error('Erro ao consultar oficialização:', error);
            showError('Erro ao consultar estado da escala.');
        }
    }

    async function publishTurnosPeriod() {
        if (!turnosPublicationStart || !turnosPublicationEnd) return;

        const start = String(turnosPublicationStart.value || '').trim();
        const end = String(turnosPublicationEnd.value || '').trim();

        if (!start || !end) {
            showError('Selecione o período antes de publicar.');
            return;
        }

        if (start > end) {
            showError('A data inicial não pode ser maior que a final.');
            return;
        }

        if (btnPublishTurnosPeriod) btnPublishTurnosPeriod.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'publish_turnos_period');
            formData.append('csrf_token', getCsrfToken());
            formData.append('period_start', start);
            formData.append('period_end', end);

            const response = await fetch('dashboard.php', {
                method: 'POST',
                body: formData
            });
            const result = await parseJsonSafe(response);

            if (!result || !result.success) {
                showError((result && result.message) ? result.message : 'Falha ao publicar escala.');
                return;
            }

            showSuccess(result.message || 'Escala publicada com sucesso.');
            setTurnosPublicationBadge('publicado', Number(result.total_turnos || 0));
            checkTurnosPublicationStatus({ silent: true });
        } catch (error) {
            console.error('Erro ao publicar escala:', error);
            showError('Erro ao comunicar com o servidor ao publicar escala.');
        } finally {
            if (btnPublishTurnosPeriod) btnPublishTurnosPeriod.disabled = false;
        }
    }

    async function closeTurnosPeriod() {
        if (!turnosPublicationStart || !turnosPublicationEnd) return;

        const start = String(turnosPublicationStart.value || '').trim();
        const end = String(turnosPublicationEnd.value || '').trim();

        if (!start || !end) {
            showError('Selecione o período antes de fechar.');
            return;
        }

        if (start > end) {
            showError('A data inicial não pode ser maior que a final.');
            return;
        }

        const confirmed = typeof showConfirm === 'function'
            ? await showConfirm('Fechar escala', 'Deseja fechar este período? Alterações nos turnos deste intervalo ficarão bloqueadas.', 'Sim, fechar', 'Cancelar')
            : window.confirm('Deseja fechar este período? Alterações nos turnos deste intervalo ficarão bloqueadas.');

        if (!confirmed) return;

        if (btnCloseTurnosPeriod) btnCloseTurnosPeriod.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'close_turnos_period');
            formData.append('csrf_token', getCsrfToken());
            formData.append('period_start', start);
            formData.append('period_end', end);

            const response = await fetch('dashboard.php', {
                method: 'POST',
                body: formData
            });
            const result = await parseJsonSafe(response);

            if (!result || !result.success) {
                showError((result && result.message) ? result.message : 'Falha ao fechar escala.');
                return;
            }

            showSuccess(result.message || 'Escala fechada com sucesso.');
            setTurnosPublicationBadge('fechado', Number(result.total_turnos || 0));
            setRowsLockedByPeriod(start, end, true);
            checkTurnosPublicationStatus({ silent: true });
        } catch (error) {
            console.error('Erro ao fechar escala:', error);
            showError('Erro ao comunicar com o servidor ao fechar escala.');
        } finally {
            if (btnCloseTurnosPeriod) btnCloseTurnosPeriod.disabled = false;
        }
    }

    async function reopenTurnosPeriod() {
        if (!turnosPublicationStart || !turnosPublicationEnd) return;

        const start = String(turnosPublicationStart.value || '').trim();
        const end = String(turnosPublicationEnd.value || '').trim();

        if (!start || !end) {
            showError('Selecione o período antes de reabrir.');
            return;
        }

        if (start > end) {
            showError('A data inicial não pode ser maior que a final.');
            return;
        }

        const confirmed = typeof showConfirm === 'function'
            ? await showConfirm('Reabrir escala', 'Deseja reabrir este período? As alterações voltarão a ser permitidas.', 'Sim, reabrir', 'Cancelar')
            : window.confirm('Deseja reabrir este período? As alterações voltarão a ser permitidas.');

        if (!confirmed) return;

        if (btnReopenTurnosPeriod) btnReopenTurnosPeriod.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'reopen_turnos_period');
            formData.append('csrf_token', getCsrfToken());
            formData.append('period_start', start);
            formData.append('period_end', end);

            const response = await fetch('dashboard.php', {
                method: 'POST',
                body: formData
            });
            const result = await parseJsonSafe(response);

            if (!result || !result.success) {
                showError((result && result.message) ? result.message : 'Falha ao reabrir escala.');
                return;
            }

            showSuccess(result.message || 'Escala reaberta com sucesso.');
            setTurnosPublicationBadge('publicado', Number(result.total_turnos || 0));
            checkTurnosPublicationStatus({ silent: true });
            window.setTimeout(() => window.location.reload(), 300);
        } catch (error) {
            console.error('Erro ao reabrir escala:', error);
            showError('Erro ao comunicar com o servidor ao reabrir escala.');
        } finally {
            if (btnReopenTurnosPeriod) btnReopenTurnosPeriod.disabled = false;
        }
    }

    const emitTurnosChanged = () => {
        document.dispatchEvent(new CustomEvent('turnosTableDataChanged'));
    };

    const getTurnoDiaOptions = () => Array.from(document.querySelectorAll('.turno-dia-option'));

    function normalizeDia(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/-feira/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function normalizeIsoDate(value) {
        const text = String(value || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) return '';
        return text;
    }

    function formatIsoDateToPt(value) {
        const iso = normalizeIsoDate(value);
        if (!iso) return '';
        const [year, month, day] = iso.split('-');
        return `${day}/${month}/${year}`;
    }

    function formatTurnoRangeLabel(dataInicio, dataFim) {
        const startLabel = formatIsoDateToPt(dataInicio);
        const endLabel = formatIsoDateToPt(dataFim);
        if (!startLabel && !endLabel) return '';
        return `${startLabel || '...'} a ${endLabel || '...'}`;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildTurnoDaysCellHtml(diasSemana, dataInicio, dataFim) {
        const rangeLabel = formatTurnoRangeLabel(dataInicio, dataFim);
        if (!rangeLabel) {
            return escapeHtml(diasSemana);
        }

        return `
            <div style="display:flex;flex-direction:column;gap:.2rem;">
                <span>${escapeHtml(diasSemana)}</span>
                <small style="color:var(--text-secondary);font-weight:600;">Vigência: ${escapeHtml(rangeLabel)}</small>
            </div>
        `;
    }

    function applyTurnoRowData(row, payload) {
        if (!row || !payload) return;

        row.dataset.funcionario = payload.funcionarioNome || '';
        row.dataset.turnoEmployeeId = payload.funcionarioId || '';
        row.dataset.turnoTipo = payload.turnoTipo || '';
        row.dataset.turnoDias = payload.diasSemana || '';
        row.dataset.turnoDateStart = payload.dataInicio || '';
        row.dataset.turnoDateEnd = payload.dataFim || '';
        row.dataset.turnoTeam = payload.team || '';
        row.dataset.turnoEscala = payload.escala || '';
        row.dataset.turnoStatus = payload.status || '';
        if (Object.prototype.hasOwnProperty.call(payload, 'locked')) {
            setTurnoRowLockedState(row, !!payload.locked);
        }
    }

    function getSelectedDias() {
        return getTurnoDiaOptions().filter(option => option.checked).map(option => option.value);
    }

    function closeTurnoDiasMenu() {
        if (turnoDiasMenu) {
            turnoDiasMenu.style.display = 'none';
        }
    }

    function updateTurnoDiasField(customText = '') {
        if (!turnoDiasInput || !turnoDiasLabel) return;

        const selected = getSelectedDias();
        if (selected.length > 0) {
            const joined = selected.join(', ');
            turnoDiasInput.value = joined;
            turnoDiasLabel.textContent = joined;
            return;
        }

        if (customText) {
            turnoDiasInput.value = customText;
            turnoDiasLabel.textContent = customText;
            return;
        }

        turnoDiasInput.value = '';
        turnoDiasLabel.textContent = 'Selecione os dias...';
    }

    function clearTurnoDiasSelection() {
        getTurnoDiaOptions().forEach(option => {
            option.checked = false;
        });
        updateTurnoDiasField();
    }

    function setTurnoDiasSelectionFromText(rawText) {
        const text = String(rawText || '').trim();
        clearTurnoDiasSelection();

        if (!text) return;

        const normalizedMap = {
            segunda: 'Segunda',
            terca: 'Terça',
            quarta: 'Quarta',
            quinta: 'Quinta',
            sexta: 'Sexta',
            sabado: 'Sábado',
            domingo: 'Domingo'
        };

        const tokens = text.split(',').map(item => item.trim()).filter(Boolean);
        let matched = 0;

        tokens.forEach(token => {
            const normalized = normalizeDia(token);
            const targetValue = normalizedMap[normalized] || null;
            if (!targetValue) return;

            const option = getTurnoDiaOptions().find(item => item.value === targetValue);
            if (option) {
                option.checked = true;
                matched += 1;
            }
        });

        if (matched > 0) {
            updateTurnoDiasField();
        } else {
            // Mantém legados (ex.: "Seg - Sex") visíveis sem quebrar edição.
            updateTurnoDiasField(text);
        }
    }

    turnoDiasToggle.addEventListener('click', (e) => {
        e.preventDefault();
        const isOpen = turnoDiasMenu.style.display === 'block';
        turnoDiasMenu.style.display = isOpen ? 'none' : 'block';
    });

    getTurnoDiaOptions().forEach(option => {
        option.addEventListener('change', () => updateTurnoDiasField());
    });

    document.addEventListener('click', (e) => {
        if (!turnoDiasPicker) return;
        if (!turnoDiasPicker.contains(e.target)) {
            closeTurnoDiasMenu();
        }
    });
  
  // ✅ FUNÇÃO GLOBAL PARA EDITAR TURNO - movida para fora do event listener
  async function openEditTurno(id) {
    console.log('Abrindo edição turno id=', id);
    if (!id) { showError('ID do turno não disponível'); return; }
    try {
      const res = await fetch(`../api/turnos/get_turnos.php?id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
      const data = await parseJsonSafe(res);
      console.log('get_turnos response:', data);
      if (data && data.id) {
                if (data.locked) {
                    showWarning('Este turno pertence a um período fechado e não pode ser editado agora.');
                    return;
                }
        const diasSemana = (data.dias_semana || '').trim();
        setTurnoDiasSelectionFromText(diasSemana);

        document.getElementById('turnoId').value = data.id;
        document.getElementById('turnoFuncionario').value = data.funcionario_id;
        document.getElementById('turnoTipo').value = data.turno_tipo;
        document.getElementById('turnoInicio').value = (data.horario_inicio || '').slice(0,5);
        document.getElementById('turnoFim').value = (data.horario_fim || '').slice(0,5);
        if (turnoDataInicioInput) turnoDataInicioInput.value = data.data_inicio || '';
        if (turnoDataFimInput) turnoDataFimInput.value = data.data_fim || '';
        document.getElementById('turnoEscala').value = data.escala;
        document.getElementById('turnoStatus').value = data.status;

        document.getElementById('turnoModalTitle').textContent = 'Editar Turno';
        turnoModal.style.display = 'block';
      } else {
        showError(data.message || 'Erro ao carregar dados do turno');
      }
    } catch (err) {
      console.error('Erro ao buscar turno:', err);
      showError('Erro ao comunicar com o servidor');
    }
  }

  // Add direct listeners to existing edit buttons as fallback
  if (tbody) {
    tbody.querySelectorAll('.btn-edit').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
                const row = btn.closest('tr');
                if (row && row.dataset.turnoLocked === '1') {
                    showWarning('Este turno está num período fechado e não pode ser editado.');
                    return;
                }
        const id = btn.dataset.id;
        console.log('Fallback edit button listener, id=', id);
        openEditTurno(id);
      });
    });

        tbody.querySelectorAll('tr').forEach((row) => {
            setTurnoRowLockedState(row, row.dataset.turnoLocked === '1');
        });
  }

  // Abrir modal Novo Turno
  btnAddTurno.addEventListener('click', () => {
    document.getElementById('turnoModalTitle').textContent = 'Novo Turno';
    turnoForm.reset();
        clearTurnoDiasSelection();
        closeTurnoDiasMenu();
    document.getElementById('turnoId').value = '';
    turnoModal.style.display = 'block';
  });

    if (btnOpenBulkTurnoModal && bulkTurnoModal && bulkTurnoForm) {
        btnOpenBulkTurnoModal.addEventListener('click', () => {
            bulkTurnoForm.reset();
            closeTurnoDiasMenu();
            bulkTurnoModal.style.display = 'block';
        });
    }

    if (closeBulkTurnoModal) {
        closeBulkTurnoModal.addEventListener('click', () => {
            closeBulkTurnoModalUI();
        });
    }

    if (bulkTurnoForm) {
        bulkTurnoForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('saveBulkTurnoBtn');
            if (submitBtn && submitBtn.disabled) return;
            if (submitBtn) submitBtn.disabled = true;

            try {
                const formData = new FormData(bulkTurnoForm);
                const selectedDias = getSelectedBulkDias();
                const selectedEmployees = getSelectedBulkEmployees();
                const dataInicio = normalizeIsoDate(formData.get('data_inicio'));
                const dataFim = normalizeIsoDate(formData.get('data_fim'));

                if (selectedDias.length === 0) {
                    showError('Selecione pelo menos um dia da semana para criação em massa.');
                    if (submitBtn) submitBtn.disabled = false;
                    return;
                }

                if (selectedEmployees.length === 0) {
                    showError('Selecione pelo menos um funcionário para criação em massa.');
                    if (submitBtn) submitBtn.disabled = false;
                    return;
                }

                if ((dataInicio && !dataFim) || (!dataInicio && dataFim)) {
                    showError('Preencha data de início e data de fim para definir a vigência.');
                    if (submitBtn) submitBtn.disabled = false;
                    return;
                }

                if (dataInicio && dataFim && dataInicio > dataFim) {
                    showError('A data de início não pode ser maior que a data de fim.');
                    if (submitBtn) submitBtn.disabled = false;
                    return;
                }

                formData.set('dias_semana', selectedDias.join(', '));
                formData.set('data_inicio', dataInicio);
                formData.set('data_fim', dataFim);
                formData.set('csrf_token', getCsrfToken());
                formData.set('action', 'save_bulk_turnos');

                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await parseJsonSafe(response);
                if (!result || !result.success) {
                    showError((result && result.message) ? result.message : 'Falha ao criar turnos em massa.');
                    if (submitBtn) submitBtn.disabled = false;
                    return;
                }

                showSuccess(result.message || 'Turnos em massa criados com sucesso.');
                closeBulkTurnoModalUI();

                setTimeout(() => {
                    window.location.reload();
                }, 450);
            } catch (error) {
                console.error('Erro ao criar turnos em massa:', error);
                showError('Erro ao comunicar com o servidor na criação em massa.');
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }

  // Fechar modal
    closeModal.addEventListener('click', () => {
        closeTurnoDiasMenu();
        turnoModal.style.display = 'none';
    });
  
  // Comentado: evento que fechava modal ao clicar fora
  /*
  window.addEventListener('click', (e) => { 
    if (e.target === turnoModal) turnoModal.style.display = 'none';
  });
  */

  // SUBMIT - Criar / Atualizar turno
  turnoForm.addEventListener('submit', (e) => {
    e.preventDefault();
    e.stopImmediatePropagation(); // ✅ Previne múltiplos disparos
    
    console.log('📝 Form submit iniciado');
    
    // ✅ Proteção contra duplo envio
    const submitBtn = turnoForm.querySelector('button[type="submit"]');
    if (submitBtn && submitBtn.disabled) {
      console.log('⚠️ Envio já em andamento, ignorando...');
      return;
    }
    
    // Desabilita botão durante o envio
    if (submitBtn) submitBtn.disabled = true;
    
    const formData = new FormData(turnoForm);
    updateTurnoDiasField();
    const diasSemanaValue = (turnoDiasInput.value || '').trim();
        const dataInicio = normalizeIsoDate(formData.get('data_inicio'));
        const dataFim = normalizeIsoDate(formData.get('data_fim'));

    if (!diasSemanaValue) {
      if (submitBtn) submitBtn.disabled = false;
      showError('Selecione pelo menos um dia da semana.');
      return;
    }

        if ((dataInicio && !dataFim) || (!dataInicio && dataFim)) {
            if (submitBtn) submitBtn.disabled = false;
            showError('Preencha a data de início e a data de fim para definir a vigência da escala.');
            return;
        }

        if (dataInicio && dataFim && dataInicio > dataFim) {
            if (submitBtn) submitBtn.disabled = false;
            showError('A data de início não pode ser maior que a data de fim.');
            return;
        }

    formData.set('dias_semana', diasSemanaValue);
        formData.set('data_inicio', dataInicio);
        formData.set('data_fim', dataFim);
    formData.append('action', 'save_turno');
    formData.set('csrf_token', getCsrfToken());
    
    // Se tem ID, é edição (incluir turno_id)
    const turnoId = document.getElementById('turnoId').value;
    if (turnoId) {
      formData.append('turno_id', turnoId);
      console.log('✏️ Modo EDIÇÃO - ID:', turnoId);
    } else {
      console.log('➕ Modo CRIAÇÃO');
    }

    console.log('🚀 Enviando para servidor...');
    fetch('dashboard.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      console.log('📡 Resposta recebida, status:', response.status);
      
      // ✅ Verificar se é JSON antes de parsear
      const contentType = response.headers.get('content-type');
      console.log('📋 Content-Type:', contentType);
      
      if (!contentType || !contentType.includes('application/json')) {
        console.error('⚠️ Resposta não é JSON! Content-Type:', contentType);
        return response.text().then(text => {
          console.error('📄 Resposta bruta do servidor:', text.substring(0, 500));
          throw new Error('Servidor retornou HTML/texto em vez de JSON. Verifique erros PHP no console.');
        });
      }
      
      return parseJsonSafe(response);
    })
    .then(result => {
      console.log('📦 Resultado parseado:', result);
      
      // ✅ Reabilita botão de submit
      const submitBtn = turnoForm.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = false;
      
      if (result.success) {
        console.log('✅ Sucesso! Fechando modal...');
        showSuccess('Turno salvo com sucesso!');
        
        // ✅ FECHAR O MODAL APÓS SUCESSO
        turnoModal.style.display = 'none';
        turnoForm.reset();
        console.log('🔒 Modal fechado e formulário resetado');
        
        // Atualizar a tabela dinamicamente SEM recarregar
        if (turnoId) {
          // EDITAR - Atualizar linha existente
          const row = tbody.querySelector(`button[data-id="${turnoId}"]`)?.closest('tr');
          if (row) {
            const funcionarioSelect = document.getElementById('turnoFuncionario');
            const funcionarioNome = funcionarioSelect.options[funcionarioSelect.selectedIndex]?.text || '';
                        const funcionarioId = funcionarioSelect.value || '';
                        const team = funcionarioSelect.options[funcionarioSelect.selectedIndex]?.dataset?.team || '';
            const turnoTipo = formData.get('turno_tipo');
            const diasSemana = formData.get('dias_semana');
            const horarioInicio = formData.get('horario_inicio');
            const horarioFim = formData.get('horario_fim');
                        const dataInicioValue = formData.get('data_inicio');
                        const dataFimValue = formData.get('data_fim');
            const escala = formData.get('escala');
            const status = formData.get('status');
                        applyTurnoRowData(row, {
                            funcionarioNome,
                            funcionarioId,
                            turnoTipo,
                            diasSemana,
                            dataInicio: dataInicioValue,
                            dataFim: dataFimValue,
                            team,
                            locked: row.dataset.turnoLocked === '1',
                            escala,
                            status
                        });
            
            // ✅ CORRIGIDO: Ordem correta das colunas
            // 0=Funcionário, 1=Turno, 2=Horário, 3=Dias, 4=Escala, 5=Status, 6=Ações
            row.cells[0].textContent = funcionarioNome;
            row.cells[1].textContent = turnoTipo;
            row.cells[2].textContent = `${horarioInicio} - ${horarioFim}`;
                        row.cells[3].innerHTML = buildTurnoDaysCellHtml(diasSemana, dataInicioValue, dataFimValue);
            row.cells[4].textContent = escala;
            
            // Atualizar status badge
            const statusCell = row.cells[5];
            const badgeClass = status === 'ativo' ? 'status-badge status-presente' : 'status-badge status-falta';
            const statusText = status.charAt(0).toUpperCase() + status.slice(1);
            statusCell.innerHTML = `<span class="${badgeClass}">${statusText}</span>`;
                        emitTurnosChanged();
          }
        } else {
          // ✅ CRIAR - Adicionar nova linha DINAMICAMENTE (mesma rapidez das gorjetas)
          const funcionarioSelect = document.getElementById('turnoFuncionario');
          const funcionarioNome = funcionarioSelect.options[funcionarioSelect.selectedIndex]?.text || '';
          const funcionarioId = funcionarioSelect.value || '';
          const team = funcionarioSelect.options[funcionarioSelect.selectedIndex]?.dataset?.team || '';
          const turnoTipo = formData.get('turno_tipo');
          const diasSemana = formData.get('dias_semana');
          const horarioInicio = formData.get('horario_inicio');
          const horarioFim = formData.get('horario_fim');
          const dataInicioValue = formData.get('data_inicio');
          const dataFimValue = formData.get('data_fim');
          const escala = formData.get('escala');
          const status = formData.get('status');
          const newId = result.id || 'new';
          
          // Badge de status com cores
          const badgeClass = status === 'ativo' ? 'status-badge status-presente' : 'status-badge status-falta';
          const statusText = status.charAt(0).toUpperCase() + status.slice(1);
          
          // Criar nova linha com estilo otimizado
          const newRow = document.createElement('tr');
                    applyTurnoRowData(newRow, {
                        funcionarioNome,
                        funcionarioId,
                        turnoTipo,
                        diasSemana,
                        dataInicio: dataInicioValue,
                        dataFim: dataFimValue,
                        team,
                        locked: false,
                        escala,
                        status
                    });
          newRow.innerHTML = `
            <td style="font-weight: 600; padding: 0.875rem 1rem;">${funcionarioNome}</td>
            <td style="padding: 0.875rem 1rem;">${turnoTipo}</td>
            <td style="padding: 0.875rem 1rem;">${horarioInicio} - ${horarioFim}</td>
                        <td style="padding: 0.875rem 1rem;">${buildTurnoDaysCellHtml(diasSemana, dataInicioValue, dataFimValue)}</td>
            <td style="padding: 0.875rem 1rem;">${escala}</td>
            <td style="text-align: center; padding: 0.875rem;">
              <span class="${badgeClass}">${statusText}</span>
            </td>
            <td style="text-align: center; padding: 0.875rem;">
              <div style="display: inline-flex; gap: 0.5rem;">
                <button type="button" class="btn btn-edit btn-sm" data-id="${newId}" title="Editar" style="padding: 0.4rem 0.75rem;">
                  <i class="fas fa-edit"></i> Editar
                </button>
                <button type="button" class="btn btn-delete btn-sm" data-id="${newId}" title="Excluir" style="padding: 0.4rem 0.75rem;">
                  <i class="fas fa-trash"></i> Excluir
                </button>
              </div>
            </td>
          `;
          
          // Inserir no topo com animação suave
          if (tbody) {
            tbody.insertBefore(newRow, tbody.firstChild);
            
            // Pequena animação de entrada
            newRow.style.opacity = '0';
            newRow.style.transform = 'translateY(-10px)';
            setTimeout(() => {
              newRow.style.transition = 'all 0.3s ease';
              newRow.style.opacity = '1';
              newRow.style.transform = 'translateY(0)';
            }, 10);
            
            console.log('✅ Nova linha de turno adicionada à tabela');
                        emitTurnosChanged();
          }

          // Se o servidor retornou uma atividade relacionada, adiciona ao topo da lista de atividades
          if (result.activity) {
            try {
              appendActivityToList(result.activity, null);

              // Se tivermos apenas o employee_id, tentar buscar dados do employee para o avatar
              if (result.activity.employee_id) {
                fetch('../api/employees/get_employee.php?id=' + encodeURIComponent(result.activity.employee_id))
                  .then(parseJsonSafe)
                  .then(emp => {
                    // Atualiza o primeiro item da lista (a que acabámos de inserir)
                    const list = document.querySelector('.activity-list');
                    if (!list || !list.firstChild) return;
                    const first = list.firstChild;
                    let avatarEl = first.querySelector('.activity-item-avatar');
                    if (!avatarEl) {
                      const div = document.createElement('div');
                      div.className = emp.profile_picture ? 'activity-item-avatar' : 'activity-item-avatar activity-item-avatar--initials';
                      if (emp.profile_picture) div.innerHTML = `<img src="../${emp.profile_picture}" alt="${emp.name}">`;
                      else {
                        const parts = (emp.name || '').split(/\s+/).filter(Boolean);
                        let initials = (parts[0]||'').charAt(0).toUpperCase(); if (parts.length>1) initials += (parts[1]||'').charAt(0).toUpperCase();
                        div.innerHTML = `<span>${initials}</span>`;
                      }
                      first.insertBefore(div, first.firstChild);
                    } else {
                      if (emp.profile_picture) avatarEl.innerHTML = `<img src="../${emp.profile_picture}" alt="${emp.name}">`;
                      else {
                        const parts = (emp.name || '').split(/\s+/).filter(Boolean);
                        let initials = (parts[0]||'').charAt(0).toUpperCase(); if (parts.length>1) initials += (parts[1]||'').charAt(0).toUpperCase();
                        avatarEl.classList.add('activity-item-avatar--initials');
                        avatarEl.innerHTML = `<span>${initials}</span>`;
                      }
                    }
                  })
                  .catch(e => console.warn('Não foi possível obter empregado para atividade:', e));
              }
            } catch (e) {
              console.warn('Erro ao processar atividade, mas turno foi salvo:', e);
            }
          }
        }
      } else {
        console.log('❌ Erro retornado pelo servidor:', result.message);
        showError(result.message || 'Erro ao salvar turno.');
      }
    })
    .catch(err => {
      console.error('💥 ERRO CATCH:', err);
      showError('Erro ao comunicar com o servidor.');
      // ✅ Reabilita botão em caso de erro
      const submitBtn = turnoForm.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = false;
    });
  });

  // Delegação - editar / excluir
  tbody.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.btn-edit');
    const delBtn  = e.target.closest('.btn-delete');
    // Impede qualquer ação padrão (p.ex. submit de um form envolvente) para evitar reload
    if (editBtn || delBtn) e.preventDefault();

    if (editBtn) {
            const row = editBtn.closest('tr');
            if (row && row.dataset.turnoLocked === '1') {
                showWarning('Este turno está num período fechado e não pode ser editado.');
                return;
            }
      const id = editBtn.dataset.id;
      console.log('Editar turno clicado, id=', id);
      openEditTurno(id);
    }

    // ❌ EXCLUIR
    if (delBtn) {
            const row = delBtn.closest('tr');
            if (row && row.dataset.turnoLocked === '1') {
                showWarning('Este turno está num período fechado e não pode ser excluído.');
                return;
            }
      const id = delBtn.dataset.id;
      
      showConfirm(
        'Tem certeza?',
        'Deseja realmente excluir este turno? Esta ação não pode ser desfeita.',
        'Sim, excluir',
        'Cancelar'
      ).then(async (confirmed) => {
        if (!confirmed) return;

        try {
          // Desabilitar botão para evitar múltiplos cliques
          delBtn.disabled = true;

                    const deleteData = new FormData();
                    deleteData.append('action', 'delete_turno');
                    deleteData.append('turno_id', id);
                    deleteData.append('csrf_token', getCsrfToken());

                    const res = await fetch('dashboard.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: deleteData
                    });

          console.log('delete_turno http status:', res.status);
          if (res.status === 401 || res.status === 403) {
            showError('Sessão expirada ou não autenticado. Faça login novamente.');
            delBtn.disabled = false;
            return;
          }

                    const result = await parseJsonSafe(res);
                    console.log('delete_turno result:', result);
                    if (result && result.success) {
                        showSuccess('Turno excluído com sucesso!');
                        const row = delBtn.closest('tr');
                        if (row) {
                            row.remove();
                            emitTurnosChanged();
                        }
                    } else {
                        showError(result.message || 'Erro ao excluir turno.');
                        delBtn.disabled = false;
                    }
        } catch (err) {
          console.error('Erro ao excluir turno:', err);
          showError('Erro ao comunicar com o servidor.');
          if (delBtn) delBtn.disabled = false;
        }
      });
    }
  });

});
