document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('tbody .employee-checkbox');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            // Define o estado de todos os checkboxes baseado no "Selecionar Tudo"
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
                
                // Opcional: Adiciona uma classe visual na linha (tr) se desejar
                const row = checkbox.closest('tr');
                if (selectAll.checked) {
                    row.classList.add('selected-row');
                } else {
                    row.classList.remove('selected-row');
                }
            });
        });
    }

    // Lógica inversa: Se desmarcar um item manual, desmarca o "Selecionar Tudo"
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(checkboxes).every(c => c.checked);
            const anyUnchecked = Array.from(checkboxes).some(c => !c.checked);
            
            if (anyUnchecked) {
                selectAll.checked = false;
            } else if (allChecked && checkboxes.length > 1) {
                // não ligar "selecionar tudo" se só existir um checkbox
                selectAll.checked = true;
            }
        });
    });
});





document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('tbody .employee-checkbox');
    const bulkBar = document.getElementById('bulkActionsBar');
    const bulkCount = document.getElementById('bulkCount');

    // Função para atualizar a visibilidade da barra e o contador
    function updateBulkBar() {
        const selectedCount = document.querySelectorAll('tbody .employee-checkbox:checked').length;
        const notifyModal = document.getElementById('notifyModal');
        const notifyVisible = !!(notifyModal && getComputedStyle(notifyModal).display !== 'none');

        if (window.__notifyModalOpen || notifyVisible) {
            bulkBar.classList.remove('active');
            bulkBar.classList.remove('show');
            bulkBar.setAttribute('aria-hidden', 'true');
            bulkBar.style.display = 'none';
            document.body.classList.remove('bulk-bar-visible');
            return;
        }
        
        if (selectedCount > 0) {
            bulkBar.classList.add('active'); // Adiciona classe para mostrar
            bulkBar.setAttribute('aria-hidden', 'false');
            bulkBar.style.display = 'flex';   // Garante que o display apareça
            document.body.classList.add('bulk-bar-visible');
            bulkCount.textContent = `${selectedCount} funcionário(s) selecionado(s)`;
        } else {
            bulkBar.classList.remove('active');
            bulkBar.setAttribute('aria-hidden', 'true');
            bulkBar.style.display = 'none';
            document.body.classList.remove('bulk-bar-visible');
            selectAll.checked = false; // Desmarca o "marcar todos" se nada estiver selecionado
        }
    }

    // Evento para o checkbox "Selecionar Tudo"
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateBulkBar();
        });
    }

    // Evento para cada checkbox individual
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBulkBar();
            
            // Lógica para desmarcar o "Selecionar Tudo" se um item for desmarcado
            if (!this.checked) {
                selectAll.checked = false;
            } else {
                const allChecked = Array.from(checkboxes).every(c => c.checked);
                if (allChecked && checkboxes.length > 1) selectAll.checked = true;
            }
        });
    });

    // Função para o botão "Limpar" da sua barra
    window.clearBulkSelection = function() {
        checkboxes.forEach(checkbox => checkbox.checked = false);
        selectAll.checked = false;
        updateBulkBar();
    };
});



// removido comentário estragado e função duplicada para evitar erros e conflitos


// notificacao adm manda funcionarios
// centralizar abertura/fecho do modal de notificação

// ========== MODAL DE CADASTRO DE FUNCIONÁRIO ==========
document.addEventListener('DOMContentLoaded', function() {
    // Abrir modal ao clicar no botão
    const addEmployeeBtn = document.getElementById('addEmployeeBtn');
    const addEmployeeModal = document.getElementById('addEmployeeModal');
    if (addEmployeeBtn && addEmployeeModal) {
        addEmployeeBtn.addEventListener('click', function() {
            addEmployeeModal.style.display = 'block';
            document.body.classList.add('modal-open');
        });
    }
    document.querySelectorAll('#addEmployeeModal .close-btn-add').forEach(btn => {
        btn.addEventListener('click', function() {
            if (addEmployeeModal) {
                addEmployeeModal.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        });
    });
    window.addEventListener('click', function(event) {
        if (event.target === addEmployeeModal) {
            addEmployeeModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
    });
});

/**
 * Abre o modal de notificação e atualiza o contador de selecionados.
 */
function openNotifyModal() {
    window.__notifyModalOpen = true;
    const modal = document.getElementById('notifyModal');
    // first hide any visible bulk bar and remove its overlay, avoids "fusco" effect
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    if (bulkActionsBar) {
        hideBulkBarSafely(bulkActionsBar);
    }

    if (modal) {
        modal.style.display = 'flex';
        const selected = getSelectedSMSRecipients();
        updateNotifyAudience(selected);
    }
}

/**
 * Fecha o modal e limpa o textarea.
 */
function closeNotifyModal() {
    window.__notifyModalOpen = false;
    const modal = document.getElementById('notifyModal');
    if (modal) {
        modal.style.display = 'none';
    }
    // ao fechar modal, volta atualizar a barra caso ainda existam itens selecionados
    if (typeof updateBulkActionsBar === 'function') {
        updateBulkActionsBar();
    }
}

// Fechar ao clicar fora (usa addEventListener para não sobrescrever outros handlers)
window.addEventListener('click', function(event) {
    const modal = document.getElementById('notifyModal');
    if (modal && event.target === modal) {
        closeNotifyModal();
    }
});

// 1. Lógica do Contador de Caracteres
const smsTextarea = document.getElementById('smsMessage');
const charLimitMsg = document.getElementById('charLimitMsg');
const smsCharCounter = document.getElementById('smsCharCounter');
const MAX_CHARS = 160;

window.__lastSentSmsData = window.__lastSentSmsData || null;

function formatSMSHistoryDate(dateValue) {
    if (!dateValue) return '--';
    const d = new Date(String(dateValue).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '--';
    return d.toLocaleString('pt-PT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeSMSHistoryText(text) {
    return String(text || '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[char]));
}

function renderNavbarSMSNotifications(history) {
    const badgeEl = document.getElementById('navNotifyBadge');

    if (badgeEl) {
        // Regra de UX: envio de SMS para funcionarios nao deve exibir contador no sino.
        badgeEl.textContent = '0';
        badgeEl.style.display = 'none';
    }

}

async function loadNavbarSMSNotifications(options = {}) {
    const { silent = false } = options;

    try {
        const res = await fetch('../api/employees/get_sms_history.php', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Falha ao carregar notificações da barra.');
        }

        renderNavbarSMSNotifications(data.history || []);
    } catch (err) {
        console.error('Erro ao carregar notificações da navbar:', err);
        renderNavbarSMSNotifications([]);
        if (!silent && typeof showWarning === 'function') {
            showWarning('Não foi possível atualizar o contador de notificações.');
        }
    }
}

function closeNavNotifications() {
    // Mantido para compatibilidade com código existente.
}

function toggleNavNotifications(event) {
    if (event) event.preventDefault();
    openNotificationsSection();
}

function refreshNavNotifications() {
    if ((window.__notificationsView || 'received') === 'sent') {
        loadSentSMSOptions();
    } else {
        loadNotificationsSection();
    }
}

function openNotificationsSection() {
    if (typeof showSection === 'function') {
        showSection('notificacoes');
    }
    setNotificationsView('received');
}

function openNotificationsSentSection() {
    if (typeof showSection === 'function') {
        showSection('notificacoes');
    }
    setNotificationsView('sent');
}

function renderNotificationsReceivedPlaceholder() {
    const feed = document.getElementById('adminNotificationsFeed');
    const totalCountBadge = document.getElementById('notificationsTotalCountBadge');

    if (totalCountBadge) {
        totalCountBadge.innerHTML = '<i class="fas fa-eye"></i> -- notificações';
    }

    if (feed) {
        feed.innerHTML = `
            <div style="border: 1px dashed var(--border-primary); border-radius: 10px; padding: 0.9rem; color: var(--text-secondary); text-align: center;">
                Carregando notificações...
            </div>
        `;
    }
}

function renderAdminNotificationsFeed(notifications) {
    const feed = document.getElementById('adminNotificationsFeed');
    const totalCountBadge = document.getElementById('notificationsTotalCountBadge');
    if (!feed) return;

    const rows = Array.isArray(notifications) ? notifications : [];

    if (totalCountBadge) {
        totalCountBadge.innerHTML = `<i class="fas fa-eye"></i> ${rows.length} notificações`;
    }

    if (!rows.length) {
        feed.innerHTML = `
            <div style="border: 1px dashed var(--border-primary); border-radius: 10px; padding: 0.9rem; color: var(--text-secondary); text-align: center;">
                Sem notificações para exibir.
            </div>
        `;
        return;
    }

    feed.innerHTML = rows.map((item) => {
        const employeeName = escapeSMSHistoryText(item.employee_name || 'Funcionário');
        const message = escapeSMSHistoryText(item.message || '');
        const sentAt = formatSMSHistoryDate(item.sent_at);
        const statusLabel = item.read ? 'Lida' : 'Não lida';
        const statusColor = item.read ? '#10b981' : '#f59e0b';

        return `
            <div style="border: 1px solid var(--border-primary); border-radius: 10px; padding: 0.75rem; background: rgba(148,163,184,0.08);">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem; flex-wrap:wrap; margin-bottom:0.35rem;">
                    <strong style="color: var(--text-primary);">${employeeName}</strong>
                    <span style="font-size:0.78rem; font-weight:700; color:${statusColor};">${statusLabel}</span>
                </div>
                <div style="color: var(--text-primary); font-size: 0.9rem; line-height: 1.35; margin-bottom: 0.45rem; white-space: normal; word-break: break-word;">${message}</div>
                <div style="font-size: 0.78rem; color: var(--text-secondary);">${sentAt}</div>
            </div>
        `;
    }).join('');
}

function setNotificationsView(view = 'received') {
    const receivedBtn = document.getElementById('btnViewReceivedNotifications');
    const sentBtn = document.getElementById('btnViewSentSMSOptions');
    const receivedPanel = document.getElementById('notificationsReceivedPanel');
    const sentPanel = document.getElementById('notificationsSentPanel');

    const isSent = view === 'sent';
    window.__notificationsView = isSent ? 'sent' : 'received';

    if (receivedPanel) receivedPanel.style.display = isSent ? 'none' : 'block';
    if (sentPanel) sentPanel.style.display = isSent ? 'block' : 'none';

    if (receivedBtn) {
        receivedBtn.classList.toggle('btn-primary', !isSent);
        receivedBtn.classList.toggle('btn-secondary', isSent);
    }
    if (sentBtn) {
        sentBtn.classList.toggle('btn-primary', isSent);
        sentBtn.classList.toggle('btn-secondary', !isSent);
    }

    if (isSent) {
        loadSentSMSOptions();
    } else {
        loadNotificationsSection();
    }
}

function getSelectedNotificationIds() {
    return Array.from(document.querySelectorAll('.notification-select-checkbox:checked'))
        .map((cb) => Number(cb.value))
        .filter((id) => Number.isFinite(id) && id > 0);
}

function updateNotificationsSelectionState() {
    const selectedIds = getSelectedNotificationIds();
    const selectedCountEl = document.getElementById('notificationsSelectedCount');
    const deleteBtn = document.getElementById('btnDeleteSelectedNotifications');
    const selectAll = document.getElementById('notificationsSelectAll');
    const allCheckboxes = document.querySelectorAll('.notification-select-checkbox');

    if (selectedCountEl) {
        selectedCountEl.textContent = `${selectedIds.length} selecionada(s)`;
    }

    if (deleteBtn) {
        deleteBtn.disabled = selectedIds.length === 0;
    }

    if (selectAll) {
        selectAll.checked = allCheckboxes.length > 0 && selectedIds.length === allCheckboxes.length;
    }
}

function toggleAllNotifications(checked) {
    document.querySelectorAll('.notification-select-checkbox').forEach((cb) => {
        cb.checked = !!checked;
    });
    updateNotificationsSelectionState();
}

function filterNotificationsList() {
    const searchInput = document.getElementById('notificationsSearch');
    const query = String(searchInput?.value || '').trim().toLowerCase();
    const rows = document.querySelectorAll('#notificationsTableBody tr[data-row-type="notification"]');

    rows.forEach((row) => {
        const employee = (row.getAttribute('data-employee') || '').toLowerCase();
        const message = (row.getAttribute('data-message') || '').toLowerCase();
        const show = !query || employee.includes(query) || message.includes(query);
        row.style.display = show ? '' : 'none';
    });
}

function renderSentSMSOptions(history) {
    const container = document.getElementById('notificationsSentHistoryList');
    if (!container) return;

    renderNavbarSMSNotifications(history || []);

    if (!Array.isArray(history) || history.length === 0) {
        container.innerHTML = `
            <div style="border: 1px dashed var(--border-primary); border-radius: 10px; padding: 0.9rem; color: var(--text-secondary); text-align: center;">
                Sem SMS enviadas para exibir.
            </div>
        `;
        return;
    }

    container.innerHTML = history.map((item) => {
        const msgRaw = item.message || '';
        const msg = escapeSMSHistoryText(msgRaw);
        const when = formatSMSHistoryDate(item.sent_at);
        const recipients = Number(item.recipients || 0);
        const viewedCount = Number(item.viewed_count || 0);
        const pendingCount = Math.max(0, recipients - viewedCount);
        const source = String(item.source || 'app').toLowerCase();
        const sourceLabel = source === 'app' ? 'App' : (source === 'phone' ? 'Telefone' : 'App + Telefone');
        const canManage = !!item.can_manage && source === 'app';
        const jsMessage = JSON.stringify(msgRaw);
        const jsSentAt = JSON.stringify(item.sent_at || '');
        const jsSource = JSON.stringify(source);
        const manageActionsHtml = canManage
            ? `
                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.6rem;" onclick='useSMSHistoryMessage(${jsMessage}); editAdminMessageForAll();'>Editar para todos</button>
                    <button type="button" class="btn btn-danger" style="padding: 0.35rem 0.6rem;" onclick='deleteSentSMSBatch(${jsMessage}, ${jsSentAt}, ${jsSource});'>Apagar envio</button>
              `
            : `
                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.6rem; opacity: .6; cursor: not-allowed;" title="Apenas envios feitos no canal app podem ser editados." disabled>Editar para todos</button>
                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.6rem; opacity: .6; cursor: not-allowed;" title="Apenas envios feitos no canal app podem ser removidos." disabled>Apagar envio</button>
              `;

        return `
            <div style="border: 1px solid var(--border-primary); border-radius: 10px; padding: 0.75rem; background: rgba(148,163,184,0.08);">
                <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.35rem; white-space: normal; word-break: break-word;">${msg}</div>
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; color: var(--text-secondary); font-size: 0.8rem; margin-bottom: 0.55rem;">
                    <span>${recipients} destinatário(s)</span>
                    <span style="color:#10b981; font-weight:600;">${viewedCount} visualizaram</span>
                    <span style="color:#f59e0b; font-weight:600;">${pendingCount} pendente(s)</span>
                    <span style="color:#2563eb; font-weight:600;">Canal: ${sourceLabel}</span>
                    <span>${when}</span>
                </div>
                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
             
                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.6rem;" onclick='viewSentSMSRecipients(${jsMessage}, ${jsSentAt});'>Ver quem viu</button>
                    ${manageActionsHtml}
                </div>
            </div>
        `;
    }).join('');
}

async function viewSentSMSRecipients(message, sentAt) {
    const formData = new FormData();
    formData.append('action', 'sent_batch_status');
    formData.append('message', String(message || ''));
    formData.append('sent_at', String(sentAt || ''));

    try {
        const res = await fetch('../api/employees/manage_notifications.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Falha ao carregar status do envio.');
        }

        const rows = Array.isArray(data.notifications) ? data.notifications : [];
        if (!rows.length) {
            showWarning('Nenhum destinatário encontrado para este envio.');
            return;
        }

        const readRows = rows.filter((item) => item.read);
        const pendingRows = rows.filter((item) => !item.read);

        const readList = readRows.length
            ? readRows.map((item) => `<li>${escapeSMSHistoryText(item.employee_name || 'Funcionário')}</li>`).join('')
            : '<li>Ninguém visualizou ainda.</li>';
        const pendingList = pendingRows.length
            ? pendingRows.map((item) => `<li>${escapeSMSHistoryText(item.employee_name || 'Funcionário')}</li>`).join('')
            : '<li>Sem pendências.</li>';

        await Swal.fire({
            title: 'Status da SMS enviada',
            html: `
                <div style="text-align:left; font-size:0.92rem;">
                    <div style="margin-bottom:0.5rem;"><strong>Visualizaram (${readRows.length})</strong></div>
                    <ul style="margin:0 0 0.75rem 1rem; padding:0; max-height:120px; overflow:auto;">${readList}</ul>
                    <div style="margin-bottom:0.5rem;"><strong>Pendentes (${pendingRows.length})</strong></div>
                    <ul style="margin:0 0 0 1rem; padding:0; max-height:120px; overflow:auto;">${pendingList}</ul>
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'Fechar',
            customClass: {
                popup: 'custom-swal-popup'
            }
        });
    } catch (err) {
        console.error('Erro ao ver status da SMS enviada:', err);
        showError(err.message || 'Erro ao carregar status da SMS enviada.');
    }
}

async function deleteSentSMSBatch(message, sentAt, source = 'app') {
    if (String(source || '').toLowerCase() !== 'app') {
        showWarning('Só é possível remover para todos mensagens enviadas pelo canal app.');
        return;
    }

    const confirmed = await showConfirm(
        'Apagar envio de SMS',
        'Deseja apagar este envio para todos os destinatários?',
        'Sim, apagar envio',
        'Cancelar'
    );

    if (!confirmed) return;

    const formData = new FormData();
    formData.append('action', 'delete_sent_batch');
    formData.append('message', String(message || ''));
    formData.append('sent_at', String(sentAt || ''));
    formData.append('send_channel', String(source || 'app'));

    try {
        const res = await fetch('../api/employees/manage_notifications.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Falha ao apagar envio.');
        }

        showSuccess(data.message || 'Envio removido com sucesso.');
        loadSentSMSOptions();
        if (typeof loadSMSHistory === 'function') {
            loadSMSHistory();
        }
    } catch (err) {
        console.error('Erro ao apagar envio de SMS:', err);
        showError(err.message || 'Erro ao apagar envio de SMS.');
    }
}

async function loadSentSMSOptions() {
    const container = document.getElementById('notificationsSentHistoryList');
    if (container) {
        container.innerHTML = `
            <div style="border: 1px dashed var(--border-primary); border-radius: 10px; padding: 0.9rem; color: var(--text-secondary); text-align: center;">
                Carregando SMS enviadas...
            </div>
        `;
    }

    try {
        const res = await fetch('../api/employees/get_sms_history.php', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Falha ao carregar SMS enviadas.');
        }

        renderSentSMSOptions(data.history || []);
    } catch (err) {
        console.error('Erro ao carregar SMS enviadas:', err);
        if (container) {
            container.innerHTML = `
                <div style="border: 1px dashed #ef4444; border-radius: 10px; padding: 0.9rem; color: #ef4444; text-align: center;">
                    Não foi possível carregar as SMS enviadas.
                </div>
            `;
        }
    }
}

function renderNotificationsSection(notifications) {
    const body = document.getElementById('notificationsTableBody');
    const totalCount = document.getElementById('notificationsTotalCount');
    if (!body) return;

    if (totalCount) {
        totalCount.textContent = `${Array.isArray(notifications) ? notifications.length : 0} registro(s)`;
    }

    if (!Array.isArray(notifications) || notifications.length === 0) {
        body.innerHTML = `
            <tr>
                <td colspan="5" style="text-align:center; color: var(--text-secondary); padding: 1rem;">Sem notificações recebidas para exibir.</td>
            </tr>
        `;
        updateNotificationsSelectionState();
        return;
    }

    body.innerHTML = notifications.map((item) => {
        const id = Number(item.id || 0);
        const employeeName = escapeSMSHistoryText(item.employee_name || 'Funcionário');
        const message = escapeSMSHistoryText(item.message || '');
        const sentAt = formatSMSHistoryDate(item.sent_at);
        const statusLabel = item.read ? 'Lida' : 'Não lida';
        const statusColor = item.read ? '#10b981' : '#f59e0b';

        return `
            <tr data-row-type="notification" data-employee="${employeeName}" data-message="${message}">
                <td style="text-align:center;">
                    <input type="checkbox" class="notification-select-checkbox" value="${id}" onchange="updateNotificationsSelectionState()">
                </td>
                <td>${employeeName}</td>
                <td style="white-space: normal; word-break: break-word;">${message}</td>
                <td>${sentAt}</td>
                <td style="text-align:center;"><span style="font-weight:700; color:${statusColor};">${statusLabel}</span></td>
            </tr>
        `;
    }).join('');

    updateNotificationsSelectionState();
    filterNotificationsList();
}

async function loadNotificationsSection() {
    renderNotificationsReceivedPlaceholder();

    try {
        const formData = new FormData();
        formData.append('action', 'list_received');

        const res = await fetch('../api/employees/manage_notifications.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Falha ao carregar notificações.');
        }

        renderAdminNotificationsFeed(data.notifications || []);
        renderNavbarSMSNotifications(data.notifications || []);
    } catch (err) {
        console.error('Erro ao carregar notificações:', err);
        const feed = document.getElementById('adminNotificationsFeed');
        if (feed) {
            feed.innerHTML = `
                <div style="border: 1px dashed #ef4444; border-radius: 10px; padding: 0.9rem; color: #ef4444; text-align: center;">
                    Erro ao carregar notificações.
                </div>
            `;
        }
    }
}

async function deleteSelectedNotifications() {
    const ids = getSelectedNotificationIds();
    if (!ids.length) {
        showWarning('Selecione ao menos uma notificação para excluir.');
        return;
    }

    const confirmed = await showConfirm(
        'Excluir notificações',
        `Deseja excluir ${ids.length} notificação(ões) selecionada(s)?`,
        'Sim, excluir',
        'Cancelar'
    );

    if (!confirmed) return;

    try {
        const formData = new FormData();
        formData.append('action', 'delete_selected');
        formData.append('ids', JSON.stringify(ids));

        const res = await fetch('../api/employees/manage_notifications.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Falha ao excluir notificações selecionadas.');
        }

        showSuccess(data.message || 'Notificações removidas com sucesso.');
        loadNotificationsSection();
    } catch (err) {
        console.error('Erro ao excluir notificações selecionadas:', err);
        showError(err.message || 'Erro ao excluir notificações selecionadas.');
    }
}

async function deleteAllNotifications() {
    const confirmed = await showConfirm(
        'Excluir todas as notificações',
        'Tem certeza que deseja remover todas as SMS enviadas?',
        'Sim, excluir tudo',
        'Cancelar'
    );

    if (!confirmed) return;

    try {
        const formData = new FormData();
        formData.append('action', 'delete_all');

        const res = await fetch('../api/employees/manage_notifications.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Falha ao excluir todas as notificações.');
        }

        showSuccess(data.message || 'Todas as notificações foram removidas.');
        loadNotificationsSection();
    } catch (err) {
        console.error('Erro ao excluir todas as notificações:', err);
        showError(err.message || 'Erro ao excluir todas as notificações.');
    }
}

window.addEventListener('load', () => {
    loadNavbarSMSNotifications({ silent: true });
    if (!window.__navSmsPollingStarted) {
        window.__navSmsPollingStarted = true;
        setInterval(() => loadNavbarSMSNotifications({ silent: true }), 60000);
    }
});

function useSMSHistoryMessage(message, scope = 'client') {
    const textarea = document.getElementById('smsMessage');
    if (textarea) {
        textarea.value = message || '';
        textarea.dispatchEvent(new Event('input'));
        textarea.focus();
    }

    window.__lastSentSmsData = {
        ids: [],
        message: message || '',
        sentAt: Date.now(),
        scope
    };
}

function renderSMSHistory(history) {
    const listEl = document.getElementById('smsHistoryList');
    if (!listEl) return;

    if (!Array.isArray(history) || history.length === 0) {
        listEl.innerHTML = '<div class="sms-history-empty">Sem histórico de SMS por enquanto.</div>';
        return;
    }

    listEl.innerHTML = history.map((item) => {
        const message = escapeSMSHistoryText(item.message || '');
        const sentAt = formatSMSHistoryDate(item.sent_at);
        const recipients = Number(item.recipients || 0);
        const jsMessage = JSON.stringify(item.message || '');

        return `
            <div class="sms-history-item">
                <div class="sms-history-message">${message}</div>
                <div class="sms-history-meta">
                    <span>${recipients} destinatário(s) • ${sentAt}</span>
                    <div class="sms-history-actions">
                        <button type="button" onclick='useSMSHistoryMessage(${jsMessage})'>Usar</button>
                        <button type="button" onclick='useSMSHistoryMessage(${jsMessage}); editAdminMessageForAll()'>Editar para todos</button>
                        <button type="button" class="action-danger" onclick='useSMSHistoryMessage(${jsMessage}); deleteAdminMessageForAll()'>Apagar para todos</button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

async function loadSMSHistory() {
    const listEl = document.getElementById('smsHistoryList');
    if (listEl) {
        listEl.innerHTML = '<div class="sms-history-empty">Carregando histórico...</div>';
    }

    try {
        const res = await fetch('../api/employees/get_sms_history.php', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Falha ao carregar histórico de SMS.');
        }

        const history = data.history || [];
        renderSMSHistory(history);
        loadNavbarSMSNotifications({ silent: true });
    } catch (err) {
        console.error('Erro ao carregar histórico de SMS:', err);
        if (listEl) {
            listEl.innerHTML = '<div class="sms-history-empty">Não foi possível carregar o histórico agora.</div>';
        }
    }
}

function getSelectedSMSRecipients() {
    const selectedCheckboxes = document.querySelectorAll('#funcionarios-section .employee-row .employee-checkbox:checked');
    return Array.from(selectedCheckboxes)
        .map((cb) => ({
            id: Number(cb.getAttribute('data-employee-id')),
            name: cb.getAttribute('data-employee-name') || 'Funcionário'
        }))
        .filter((item) => Number.isFinite(item.id) && item.id > 0);
}

function updateNotifyAudience(selectedRecipients) {
    const countEl = document.getElementById('selectedCount') || document.getElementById('selectedCountText');
    const previewEl = document.getElementById('notifyRecipientPreview');

    if (countEl) {
        countEl.innerText = `${selectedRecipients.length} funcionário(s) selecionado(s)`;
    }

    if (!previewEl) return;

    if (!selectedRecipients.length) {
        previewEl.innerText = 'Nenhum destinatário selecionado.';
        return;
    }

    const previewNames = selectedRecipients.slice(0, 5).map((item) => item.name);
    const remaining = selectedRecipients.length - previewNames.length;
    previewEl.innerText = remaining > 0
        ? `${previewNames.join(', ')} e mais ${remaining}`
        : previewNames.join(', ');
}

function clearSMSDraft() {
    if (smsTextarea) {
        smsTextarea.value = '';
        smsTextarea.dispatchEvent(new Event('input'));
        smsTextarea.focus();
    }
}

async function editAdminMessageForAll() {
    if (!window.__lastSentSmsData || !window.__lastSentSmsData.message) {
        showWarning('Nenhuma mensagem enviada recentemente para editar em lote.');
        return;
    }

    let currentDraft = (smsTextarea?.value || '').trim();
    const fallbackMessage = window.__lastSentSmsData.message || '';
    let newMessage = currentDraft || fallbackMessage;
    const ids = Array.isArray(window.__lastSentSmsData.ids) ? window.__lastSentSmsData.ids : [];
    const scope = window.__lastSentSmsData.scope || (ids.length ? 'selected' : 'client');

    if (!newMessage) {
        showWarning('Digite uma nova mensagem antes de editar para todos.');
        return;
    }

    if (newMessage === fallbackMessage) {
        const promptResult = await Swal.fire({
            title: 'Editar para todos',
            text: 'Altere o texto da mensagem para aplicar a edição em lote.',
            input: 'textarea',
            inputValue: fallbackMessage,
            inputAttributes: {
                maxlength: '160'
            },
            showCancelButton: true,
            confirmButtonText: 'Continuar',
            cancelButtonText: 'Cancelar',
            customClass: {
                popup: 'custom-swal-popup'
            },
            inputValidator: (value) => {
                const normalized = String(value || '').trim();
                if (!normalized) return 'Digite a nova mensagem.';
                if (normalized === fallbackMessage) return 'A mensagem precisa ser diferente da anterior.';
                return null;
            }
        });

        if (!promptResult.isConfirmed) return;

        newMessage = String(promptResult.value || '').trim();
        currentDraft = newMessage;
        if (smsTextarea) {
            smsTextarea.value = newMessage;
            smsTextarea.dispatchEvent(new Event('input'));
        }
    }

    const confirmed = await showConfirm(
        'Editar para todos',
        'Substituir a última mensagem enviada para os mesmos funcionários?',
        'Sim, editar para todos',
        'Cancelar'
    );

    if (!confirmed) return;

    const formData = new FormData();
    formData.append('ids', JSON.stringify(ids));
    formData.append('old_message', fallbackMessage);
    formData.append('new_message', newMessage);
    formData.append('mode', 'replace');
    formData.append('scope', scope);

    try {
        const res = await fetch('../api/employees/update_bulk_sms.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Falha ao editar SMS em lote.');
        }

        window.__lastSentSmsData.message = newMessage;
        showSuccess(data.message || 'SMS atualizado para todos os selecionados.');
        loadSMSHistory();
    } catch (err) {
        console.error('Erro ao editar SMS em lote:', err);
        showError(err.message || 'Erro ao editar SMS em lote.');
    }
}

async function deleteAdminMessageForAll() {
    if (!window.__lastSentSmsData || !window.__lastSentSmsData.message) {
        showWarning('Nenhuma mensagem enviada recentemente para apagar em lote.');
        return;
    }

    const ids = Array.isArray(window.__lastSentSmsData.ids) ? window.__lastSentSmsData.ids : [];
    const scope = window.__lastSentSmsData.scope || (ids.length ? 'selected' : 'client');

    const confirmed = await showConfirm(
        'Apagar para todos',
        'Deseja apagar esta mensagem para todos os funcionários que a receberam agora?',
        'Sim, apagar para todos',
        'Cancelar'
    );

    if (!confirmed) return;

    const formData = new FormData();
    formData.append('ids', JSON.stringify(ids));
    formData.append('old_message', window.__lastSentSmsData.message);
    formData.append('mode', 'delete');
    formData.append('scope', scope);

    try {
        const res = await fetch('../api/employees/update_bulk_sms.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Falha ao apagar SMS em lote.');
        }

        window.__lastSentSmsData = null;
        showSuccess(data.message || 'Mensagem apagada para todos os destinatários.');
        loadSMSHistory();
    } catch (err) {
        console.error('Erro ao apagar SMS em lote:', err);
        showError(err.message || 'Erro ao apagar SMS em lote.');
    }
}

if (smsTextarea) {
    smsTextarea.addEventListener('input', () => {
        const remaining = MAX_CHARS - smsTextarea.value.length;
        if (smsCharCounter) {
            smsCharCounter.innerText = `${remaining} restantes`;
        }

        if (remaining <= 0) {
            charLimitMsg.innerText = "Você atingiu o limite de caracteres!";
            charLimitMsg.style.display = "block";
            smsTextarea.style.borderColor = 'red';
        } else {
            charLimitMsg.style.display = "none";
            smsTextarea.style.borderColor = '';
        }
    });
}

// note: closeBulkActionsBar is defined earlier inside initializeBulkActions
// to manage appearance and body class. Duplicate definition removed to avoid conflicts.




function editAdminMessage() {
    const textarea = document.getElementById('smsMessage');
    if (!textarea) return;

    const previewEl = document.getElementById('savedMessagePreview');
    const previewText = previewEl ? previewEl.innerText : '';
    
    // Coloca o texto de volta no textarea para editar
    textarea.value = previewText;
    textarea.focus();
    
    // Opcional: Esconde a prévia enquanto edita
    const containerEl = document.getElementById('adminMessageContainer');
    if (containerEl) {
        containerEl.style.display = 'none';
    }
}

async function deleteAdminMessage() {
    const confirmed = await showConfirm(
        'Excluir mensagem',
        'Deseja realmente excluir esta mensagem?',
        'Sim, excluir',
        'Cancelar'
    );

    if (!confirmed) return;

    const previewEl = document.getElementById('savedMessagePreview');
    const containerEl = document.getElementById('adminMessageContainer');
    if (previewEl) previewEl.innerText = '';
    if (containerEl) containerEl.style.display = 'none';
    clearSMSDraft();
    showSuccess('Mensagem removida.');
}

// Exemplo de como mostrar a div após o envio
async function sendBulkSMS() {
    const msg = document.getElementById('smsMessage').value;
    const selectedModeEl = document.querySelector('input[name="notifyDeliveryMode"]:checked');
    const deliveryMode = selectedModeEl ? selectedModeEl.value : 'both';
    
    // validar mensagem
    if (msg.trim() === "") {
        showError('Mensagem vazia!');
        return;
    }

    // obter IDs dos funcionários selecionados (vêm do atributo data-employee-id)
    const selectedRecipients = getSelectedSMSRecipients();
    const ids = selectedRecipients.map((item) => item.id);

    if (ids.length === 0) {
        showError('Nenhum funcionário selecionado!');
        return;
    }

    if (!['app', 'phone', 'both'].includes(deliveryMode)) {
        showError('Canal de envio inválido.');
        return;
    }

    // enviar para o endpoint
    const formData = new FormData();
    formData.append('ids', JSON.stringify(ids));
    formData.append('message', msg);
    formData.append('delivery_mode', deliveryMode);

    console.log('Enviando para ' + ids.length + ' funcionários:', ids);

    try {
        const res = await fetch('../api/employees/notify_employees.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        console.log('Response status:', res.status);
        const raw = await res.text();
        let data = null;
        try {
            data = raw ? JSON.parse(raw) : {};
        } catch (parseErr) {
            throw new Error(`Resposta inválida do servidor (HTTP ${res.status})`);
        }

        if (!res.ok) {
            throw new Error(data.message || data.error || `Falha HTTP ${res.status}`);
        }
        console.log('Resposta do servidor:', data);

        if (data.success) {
            window.__lastSentSmsData = {
                ids,
                message: msg,
                sentAt: Date.now(),
                scope: 'selected',
                deliveryMode
            };

            showSuccess(data.message || 'SMS enviado com sucesso!');

            // limpar textarea e fechar modal depois
            setTimeout(() => {
                clearSMSDraft();
                closeNotifyModal();
                // limpar seleções
                document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = false);
                if (typeof updateBulkActionsBar === 'function') {
                    updateBulkActionsBar();
                }
            }, 1200);

            if (typeof loadSentSMSOptions === 'function') {
                loadSentSMSOptions();
            }
        } else {
            console.error('Erro na resposta:', data.message);
            showError('Erro: ' + (data.message || 'Falha ao enviar SMS.'));
        }
    } catch (err) {
        console.error('Erro ao enviar SMS:', err);
        showError('Erro de rede: ' + err.message);
    }
}

function closeBulkActionsBar() {
    const bar = document.getElementById('bulkActionsBar');
    if (bar) {
        hideBulkBarSafely(bar);
    }
}


// (Handlers de Ferias/Status/Departamento/Exportar em lote consolidados em dashboard-05-utils-calendario.js — a copia duplicada que existia aqui foi removida por registar os mesmos listeners de submit duas vezes.)


