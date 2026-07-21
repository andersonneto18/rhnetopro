// SweetAlert2 helpers
function firePortalToast(icon, message, timer = 3000) {
    const popupClass = icon ? `swal-toast swal-toast-${icon}` : 'swal-toast';
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        title: message,
        customClass: {
            popup: popupClass,
            title: 'swal-toast-title'
        },
        showConfirmButton: false,
        timer,
        timerProgressBar: true
    });
}

function showSuccess(msg) {
    firePortalToast('success', msg, 3000);
}

function showError(msg) {
    firePortalToast('error', msg, 4000);
}

function showWarning(msg) {
    firePortalToast('warning', msg, 3500);
}

function showPortalSection(sectionId) {
    const sections = document.querySelectorAll('.portal-section');
    const navButtons = document.querySelectorAll('.nav-btn');

    sections.forEach((section) => {
        section.classList.toggle('active', section.id === sectionId);
    });

    navButtons.forEach((button) => {
        button.classList.toggle('active', button.dataset.section === sectionId);
    });
}

function closeMobilePortalNav() {
    const menuBtn = document.getElementById('portal-menu-btn');
    const menuIcon = document.getElementById('portal-menu-icon');
    document.body.classList.remove('portal-nav-open');
    if (menuBtn) {
        menuBtn.setAttribute('aria-expanded', 'false');
    }
    if (menuIcon) {
        menuIcon.className = 'fas fa-bars';
    }
}

function toggleMobilePortalNav() {
    const menuBtn = document.getElementById('portal-menu-btn');
    const menuIcon = document.getElementById('portal-menu-icon');
    const isOpen = document.body.classList.toggle('portal-nav-open');

    if (menuBtn) {
        menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
    if (menuIcon) {
        menuIcon.className = isOpen ? 'fas fa-times' : 'fas fa-bars';
    }
}

function toggleAttendanceHistory(button) {
    const panel     = document.getElementById('attendanceHistoryPanel');
    const filters   = document.getElementById('history-filters');
    if (!panel) return;

    const isOpen = panel.classList.toggle('open');
    if (filters) filters.style.display = isOpen ? '' : 'none';
    button.innerHTML = isOpen
        ? '<i class="fas fa-eye-slash"></i> Ocultar'
        : '<i class="fas fa-eye"></i> Ver Histórico';
}

function formatDateYmdToPt(value) {
    if (!value || typeof value !== 'string') return 'N/D';
    const parts = value.split('-');
    if (parts.length !== 3) return value;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function normalizeFeriasStatus(status) {
    const raw = String(status || 'pendente').toLowerCase().trim();
    if (raw === 'aprovado') return 'aprovada';
    if (['rejeitado', 'recusado', 'recusada'].includes(raw)) return 'rejeitada';
    return raw;
}

function renderFeriasRequestItem(request) {
    const feriasList = document.getElementById('feriasList');
    if (!feriasList || !request) return;

    const empty = document.getElementById('feriasEmptyState');
    if (empty) empty.remove();

    const status = normalizeFeriasStatus(request.status);
    const statusLabel = status === 'aprovada' ? 'Aprovada' : (status === 'rejeitada' ? 'Rejeitada' : 'Pendente');
    const badgeClass = status === 'aprovada' ? 'badge-success' : (status === 'rejeitada' ? 'badge-danger' : 'badge-warning');

    const row = document.createElement('div');
    row.className = 'message-item ferias-item-row';
    row.innerHTML = `
        <div style="flex:1;">
            <div class="message-meta" style="margin-bottom:.3rem;">
                <span class="status-badge ${badgeClass}">${escapeHTML(statusLabel)}</span>
                <small>${escapeHTML(formatDateYmdToPt(request.data_inicio))} - ${escapeHTML(formatDateYmdToPt(request.data_fim))}</small>
            </div>
            <p class="message-text" style="margin:0;">${escapeHTML(request.motivo || 'Sem motivo informado.')}</p>
        </div>
    `;

    feriasList.prepend(row);
}

async function submitFeriasRequest(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const data_inicio = String(form.querySelector('#ferias_data_inicio')?.value || '').trim();
    const data_fim = String(form.querySelector('#ferias_data_fim')?.value || '').trim();
    const motivo = String(form.querySelector('#ferias_motivo')?.value || '').trim();

    if (!data_inicio || !data_fim) {
        showWarning('Preencha as datas de início e término.');
        return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A enviar...';
    }

    try {
        const response = await fetch('solicitar_ferias.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ data_inicio, data_fim, motivo })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            showError(data.message || 'Não foi possível enviar o pedido de férias.');
            return;
        }

        showSuccess(data.message || 'Pedido de férias enviado com sucesso.');
        if (data.aviso_conflito) {
            showWarning(data.aviso_conflito);
        }
        renderFeriasRequestItem(data.request || { data_inicio, data_fim, motivo, status: 'pendente' });
        form.reset();
        _feriasLastHash = null;
        _pollFerias();
    } catch (error) {
        console.error(error);
        showError('Erro de comunicação ao enviar pedido de férias.');
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-send"></i> Enviar Pedido';
        }
    }
}

async function submitTurnoSwapRequest(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const requester_turno_id = parseInt(String(form.querySelector('#swap_requester_turno')?.value || '0'), 10);
    const target_turno_id = parseInt(String(form.querySelector('#swap_target_turno')?.value || '0'), 10);
    const requested_date = String(form.querySelector('#swap_requested_date')?.value || '').trim();
    const reason = String(form.querySelector('#swap_reason')?.value || '').trim();

    if (!Number.isInteger(requester_turno_id) || requester_turno_id <= 0 || !Number.isInteger(target_turno_id) || target_turno_id <= 0) {
        showWarning('Selecione os turnos para solicitar a troca.');
        return;
    }

    if (requester_turno_id === target_turno_id) {
        showWarning('Escolha turnos diferentes para solicitar a troca.');
        return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A enviar...';
    }

    try {
        const response = await fetch('solicitar_troca_turno.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ requester_turno_id, target_turno_id, requested_date, reason })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            showError(data.message || 'Não foi possível enviar o pedido de troca.');
            return;
        }

        showSuccess(data.message || 'Pedido de troca enviado com sucesso.');
        form.reset();
        setTimeout(() => window.location.reload(), 1200);
    } catch (error) {
        console.error(error);
        showError('Erro de comunicação ao enviar pedido de troca.');
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar pedido ao colega';
        }
    }
}

async function respondTurnoSwapRequest(requestId, decision, triggerButton) {
    const id = parseInt(String(requestId || '0'), 10);
    if (!Number.isInteger(id) || id <= 0) {
        showWarning('Solicitação inválida.');
        return;
    }

    const actionLabel = decision === 'accept' ? 'aceitar' : 'rejeitar';
    const confirmation = await Swal.fire({
        title: decision === 'accept' ? 'Aceitar troca?' : 'Rejeitar troca?',
        text: decision === 'accept'
            ? 'Ao aceitar, o pedido seguirá para aprovação final do administrador.'
            : 'Ao rejeitar, a troca será encerrada e não seguirá para o administrador.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: decision === 'accept' ? 'Sim, aceitar' : 'Sim, rejeitar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: decision === 'accept' ? '#16a34a' : '#dc2626',
        background: '#1e293b',
        color: '#e2e8f0'
    });

    if (!confirmation.isConfirmed) {
        return;
    }

    if (triggerButton) {
        triggerButton.disabled = true;
    }

    try {
        const response = await fetch('responder_troca_turno.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: id, decision })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            showError(data.message || `Não foi possível ${actionLabel} a solicitação.`);
            if (triggerButton) triggerButton.disabled = false;
            return;
        }

        showSuccess(data.message || 'Resposta registrada com sucesso.');
        setTimeout(() => window.location.reload(), 1100);
    } catch (error) {
        console.error(error);
        showError(`Erro de comunicação ao ${actionLabel} a solicitação.`);
        if (triggerButton) triggerButton.disabled = false;
    }
}

function getSelectedSMSIds() {
    return Array.from(document.querySelectorAll('.sms-checkbox:checked'))
        .map((checkbox) => parseInt(checkbox.value, 10))
        .filter((id) => Number.isInteger(id) && id > 0);
}

function onSMSItemCheckboxChange() {
    updateSMSSelectionState();
}

function escapeHTML(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatSMSDate(value) {
    if (!value) return '--/-- --:--';
    const parsed = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return '--/-- --:--';
    const dd = String(parsed.getDate()).padStart(2, '0');
    const mm = String(parsed.getMonth() + 1).padStart(2, '0');
    const hh = String(parsed.getHours()).padStart(2, '0');
    const ii = String(parsed.getMinutes()).padStart(2, '0');
    return `${dd}/${mm} ${hh}:${ii}`;
}

function ensureSMSActions(canDelete) {
    const smsSection = document.getElementById('sms-section');
    if (!smsSection) return;

    const card = smsSection.querySelector('.card');
    if (!card) return;

    let actions = smsSection.querySelector('.sms-actions');
    if (!canDelete) {
        if (actions) actions.remove();
        return;
    }

    if (actions) {
        return;
    }

    const actionsHtml = `
        <div class="sms-actions">
            <label class="sms-select-all">
                <input type="checkbox" id="smsSelectAll" data-role="sms-select-all" onchange="toggleAllSMS(this)">
                Selecionar todas
            </label>
            <button type="button" class="btn btn-secondary" onclick="markAllSMSRead()">
                <i class="fas fa-check-double"></i>
                Marcar todas lidas
            </button>
            <button type="button" class="btn btn-danger btn-sms-delete" id="deleteSelectedSmsBtn" onclick="deleteSelectedSMS()" disabled>
                <i class="fas fa-trash-alt"></i>
                Eliminar selecionadas
            </button>
            <button type="button" class="btn btn-secondary" onclick="deleteAllSMS()">
                <i class="fas fa-trash"></i>
                Limpar tudo
            </button>
        </div>
    `;

    const messageList = smsSection.querySelector('.message-list');
    if (messageList) {
        messageList.insertAdjacentHTML('beforebegin', actionsHtml);
    } else {
        card.insertAdjacentHTML('beforeend', actionsHtml);
    }
}

function renderSMSNotifications(notifications, canDelete, total = 0) {
    const smsSection = document.getElementById('sms-section');
    if (!smsSection) return;

    const messageList = smsSection.querySelector('.message-list');
    if (!messageList) return;

    ensureSMSActions(canDelete);

    if (!Array.isArray(notifications) || notifications.length === 0) {
        ensureSMSActions(false);
        messageList.innerHTML = '<p class="empty-state">Nenhuma mensagem nova.</p>';
        updateSMSSelectionState();
        updateSMSNavBadge(0);
        document.getElementById('sms-load-more-wrap')?.remove();
        return;
    }

    messageList.innerHTML = notifications.map(n => _buildSMSItemHtml(n, canDelete)).join('');
    _smsServerOffset = notifications.length; // repor offset sempre que a lista é substituída
    updateSMSSelectionState();

    // Botão "Ver mais" se houver mensagens além do LIMIT 20
    let moreWrap = document.getElementById('sms-load-more-wrap');
    if (total > notifications.length) {
        if (!moreWrap) {
            moreWrap = document.createElement('div');
            moreWrap.id = 'sms-load-more-wrap';
            moreWrap.style.cssText = 'text-align:center;padding:.6rem 1rem .9rem';
            moreWrap.innerHTML = '<button id="sms-load-more-btn" class="btn btn-secondary" onclick="loadMoreSMS()">Ver mais</button>';
            messageList.insertAdjacentElement('afterend', moreWrap);
        }
    } else {
        moreWrap?.remove();
    }

    // Re-aplicar filtros activos (texto + estado)
    if (_smsFilter || _smsStateFilter !== 'all') applySMSFilters();
}

let lastSMSFingerprint = '';
let smsRefreshInFlight = false;
let _smsUnreadCount   = 0;
let _smsFilter        = '';
let _smsStateFilter   = 'all'; // 'all' | 'unread' | 'read'
let _smsServerOffset  = 0; // offset real no servidor (independente de eliminações no DOM)

function _buildSMSItemHtml(item, canDelete) {
    const text     = String(item.mensagem ?? '');
    const isError  = text.toUpperCase().includes('ERRO');
    const isUnread = !item.lida;
    const id       = parseInt(item.id, 10) || 0;
    const checkboxHtml = canDelete
        ? `<input type="checkbox" class="sms-checkbox" value="${id}" onchange="onSMSItemCheckboxChange()" aria-label="Selecionar SMS">`
        : '';
    const unreadBtn = canDelete && !isUnread
        ? `<button class="btn-sms-unread" onclick="markSMSUnread(${id}, this)" title="Marcar como não lido" aria-label="Marcar como não lido"><i class="fas fa-envelope"></i></button>`
        : '';
    const deleteBtn = canDelete
        ? `<button class="btn-sms-item-del" onclick="deleteSingleSMS(${id}, this)" title="Eliminar mensagem" aria-label="Eliminar"><i class="fas fa-times"></i></button>`
        : '';

    const TRUNCATE_AT = 150;
    const truncate    = text.length > TRUNCATE_AT;
    const msgHtml     = truncate
        ? `<p class="message-text">${escapeHTML(text.slice(0, TRUNCATE_AT))}… <button class="msg-expand-btn" data-full="${escapeHTML(text)}" onclick="expandSMSMessage(this)">Ver mais</button></p>`
        : `<p class="message-text">${escapeHTML(text)}</p>`;

    const needsConfirm = item.requer_confirmacao && !item.confirmado_em;
    const confirmed    = item.requer_confirmacao && item.confirmado_em;
    const confirmHtml  = needsConfirm
        ? `<div class="sms-confirm-wrap"><button class="btn-sms-confirm" onclick="confirmRecepcao(${id}, this)"><i class="fas fa-check"></i> Confirmar leitura</button></div>`
        : confirmed
        ? `<div class="sms-confirm-wrap"><span class="sms-confirmed-badge"><i class="fas fa-check-circle"></i> Confirmado</span></div>`
        : '';

    return `
        <div class="message-item${isUnread ? ' message-item--unread' : ''}">
            <div class="message-check-wrap">${checkboxHtml}</div>
            <div class="message-meta">
                <span class="status-badge ${isError ? 'badge-error' : 'badge-success'}">${isError ? 'ALERTA / ERRO' : 'SMS RECEBIDO'}</span>
                <small>${formatSMSDate(item.data_envio)}</small>
                ${unreadBtn}${deleteBtn}
            </div>
            ${msgHtml}
            ${confirmHtml}
        </div>`;
}

async function deleteSingleSMS(id, el) {
    const confirmed = await Swal.fire({
        title: 'Eliminar esta mensagem?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        background: '#1e293b',
        color: '#e2e8f0',
        width: 360,
    });
    if (!confirmed.isConfirmed) return;

    try {
        const res  = await fetch('delete_sms.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: [id] })
        });
        const data = await res.json();
        if (!data.success) { showError(data.message || 'Erro ao eliminar mensagem.'); return; }

        const item = el.closest('.message-item');
        item?.remove();
        updateSMSSelectionState();

        const remaining = document.querySelectorAll('#sms-section .message-list .message-item');
        if (remaining.length === 0) {
            const ml = document.querySelector('#sms-section .message-list');
            if (ml) ml.innerHTML = '<p class="empty-state">Nenhuma mensagem nova.</p>';
            ensureSMSActions(false);
            document.getElementById('sms-load-more-wrap')?.remove();
        }

        const unread = document.querySelectorAll('#sms-section .message-item--unread').length;
        _smsUnreadCount = unread;
        updateSMSNavBadge(unread);
        lastSMSFingerprint = '';
    } catch (e) {
        showError('Erro de comunicação ao eliminar mensagem.');
    }
}

function applySMSFilters() {
    const textQ = _smsFilter.toLowerCase();
    document.querySelectorAll('#sms-section .message-list .message-item').forEach(item => {
        const text    = (item.querySelector('.message-text')?.textContent || '').toLowerCase();
        const unread  = item.classList.contains('message-item--unread');
        const textOk  = !textQ || text.includes(textQ);
        const stateOk = _smsStateFilter === 'all'
            || (_smsStateFilter === 'unread' && unread)
            || (_smsStateFilter === 'read'   && !unread);
        item.style.display = (textOk && stateOk) ? '' : 'none';
    });
}

function filterSMSMessages(query) {
    _smsFilter = (query || '').trim().toLowerCase();
    applySMSFilters();
}

async function markSMSUnread(id, el) {
    try {
        const res = await fetch('marcar_lida.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ ids: [id], lida: 0 }),
        });
        const data = await res.json();
        if (!data.success) return;

        const item = el.closest('.message-item');
        if (item) {
            item.classList.add('message-item--unread');
            el.remove(); // remover o botão "marcar não lido" (item está agora não lido)
        }
        _smsUnreadCount = Math.min(_smsUnreadCount + 1, 99);
        updateSMSNavBadge(_smsUnreadCount);
        lastSMSFingerprint = '';
        applySMSFilters();
    } catch { /* silencioso */ }
}

function expandSMSMessage(btn) {
    const p    = btn.closest('p');
    const full = btn.dataset.full || '';
    if (!p || !full) return;
    const collapsed = btn.textContent.trim() === 'Ver mais';
    if (collapsed) {
        p.innerHTML = escapeHTML(full)
            + ` <button class="msg-expand-btn" data-full="${escapeHTML(full)}" onclick="expandSMSMessage(this)">Ver menos</button>`;
    } else {
        p.innerHTML = escapeHTML(full.slice(0, 150))
            + `… <button class="msg-expand-btn" data-full="${escapeHTML(full)}" onclick="expandSMSMessage(this)">Ver mais</button>`;
    }
}

async function confirmRecepcao(id, el) {
    try {
        const res = await fetch('confirmar_recepcao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id }),
        });
        const data = await res.json();
        if (!data.success) return;

        const wrap = el.closest('.sms-confirm-wrap');
        if (wrap) {
            wrap.innerHTML = '<span class="sms-confirmed-badge"><i class="fas fa-check-circle"></i> Confirmado</span>';
        }
        lastSMSFingerprint = '';
    } catch { /* silencioso */ }
}

function setSMSStateFilter(val) {
    _smsStateFilter = val || 'all';
    document.querySelectorAll('.sms-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === _smsStateFilter);
    });
    applySMSFilters();
}

async function loadMoreSMS() {
    const smsSection  = document.getElementById('sms-section');
    const messageList = smsSection?.querySelector('.message-list');
    if (!messageList) return;

    const btn = document.getElementById('sms-load-more-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'A carregar…'; }

    try {
        const res     = await fetch(`get_notifications.php?offset=${_smsServerOffset}`, { credentials: 'same-origin', cache: 'no-store' });
        const payload = await res.json();

        if (!payload.success || !Array.isArray(payload.notifications) || payload.notifications.length === 0) {
            document.getElementById('sms-load-more-wrap')?.remove();
            return;
        }

        const canDelete = Boolean(payload.can_delete);
        const html = payload.notifications.map(n => _buildSMSItemHtml(n, canDelete)).join('');

        const moreWrap = document.getElementById('sms-load-more-wrap');
        if (moreWrap) moreWrap.insertAdjacentHTML('beforebegin', html);
        else messageList.insertAdjacentHTML('beforeend', html);

        _smsServerOffset += payload.notifications.length; // avançar offset real
        updateSMSSelectionState();

        if (!payload.total || _smsServerOffset >= payload.total) {
            document.getElementById('sms-load-more-wrap')?.remove();
        } else if (btn) {
            btn.disabled = false;
            btn.textContent = 'Ver mais';
        }

        if (_smsFilter || _smsStateFilter !== 'all') applySMSFilters();
    } catch (e) {
        if (btn) { btn.disabled = false; btn.textContent = 'Ver mais'; }
    }
}

function buildSMSFingerprint(payload) {
    const notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
    const compact = notifications.map((n) => `${n.id}|${n.data_envio}|${n.mensagem}|${n.lida}`).join('||');
    return `${payload.source || 'none'}::${payload.can_delete ? '1' : '0'}::${compact}`;
}

function updateSMSNavBadge(count) {
    const badge = document.getElementById('sms-nav-badge');
    if (badge) { badge.textContent = count; badge.style.display = count > 0 ? '' : 'none'; }
    // Sino do header
    const bell = document.getElementById('header-bell-badge');
    if (bell) { bell.textContent = count; bell.style.display = count > 0 ? '' : 'none'; }
}

async function markSMSAsRead() {
    try {
        // Apenas os IDs visíveis com classe não-lida — não marca mensagens fora do ecrã
        const unreadIds = Array.from(
            document.querySelectorAll('#sms-section .message-item--unread .sms-checkbox')
        ).map(cb => parseInt(cb.value, 10)).filter(id => id > 0);

        await fetch('marcar_lida.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(unreadIds.length ? { ids: unreadIds } : {}),
        });
        updateSMSNavBadge(0);
        document.querySelectorAll('.message-item--unread').forEach(el => el.classList.remove('message-item--unread'));
    } catch (e) {
        // não crítico
    }
}

async function markAllSMSRead() {
    const unreadItems = document.querySelectorAll('#sms-section .message-item--unread');
    if (unreadItems.length === 0) { showSuccess('Nenhuma mensagem não lida.'); return; }
    try {
        const ids = Array.from(document.querySelectorAll('#sms-section .message-item--unread .sms-checkbox'))
            .map(cb => parseInt(cb.value, 10)).filter(id => id > 0);
        await fetch('marcar_lida.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(ids.length ? { ids } : {}),
        });
        unreadItems.forEach(el => {
            el.classList.remove('message-item--unread');
            el.querySelector('.btn-sms-unread')?.remove();
        });
        updateSMSNavBadge(0);
        showSuccess(`${unreadItems.length} mensagem${unreadItems.length !== 1 ? 's' : ''} marcada${unreadItems.length !== 1 ? 's' : ''} como lida${unreadItems.length !== 1 ? 's' : ''}.`);
    } catch { /* silencioso */ }
}

function _updateResumoSalarial(gorjetaTotal) {
    const fmt = v => v.toFixed(2).replace('.', ',') + '€';
    const gorjEl = document.getElementById('resumo-gorjetas-mes');
    if (gorjEl) gorjEl.textContent = fmt(gorjetaTotal);
    const estimEl = document.getElementById('resumo-estimativa-total');
    if (estimEl) {
        const base = typeof window._salarioBase === 'number' ? window._salarioBase : null;
        estimEl.textContent = base !== null ? fmt(base + gorjetaTotal) : 'N/D';
    }
}

function playSMSSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [[880, 0, 0.18], [1100, 0.18, 0.36]].forEach(([freq, start, end]) => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = freq;
            osc.type = 'sine';
            gain.gain.setValueAtTime(0, ctx.currentTime + start);
            gain.gain.linearRampToValueAtTime(0.22, ctx.currentTime + start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + end);
            osc.start(ctx.currentTime + start);
            osc.stop(ctx.currentTime + end);
        });
        setTimeout(() => ctx.close(), 600);
    } catch { /* silencioso */ }
}

function showBrowserNotification(diff) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    try {
        const n = new Notification('RHNeto Pro — Nova mensagem', {
            body: `Recebeu ${diff} nova${diff > 1 ? 's' : ''} mensagem${diff > 1 ? 'ns' : ''}.`,
            icon: '/app-rhnetopro/admin/rh.png',
            tag: 'rhneto-sms',
            renotify: true,
        });
        setTimeout(() => n.close(), 6000);
    } catch { /* silencioso */ }
}

function _updateNotifPermBtn() {
    const btns = [
        document.getElementById('sms-notif-perm-btn'),
        document.getElementById('def-notif-perm-btn'),
    ].filter(Boolean);
    if (!btns.length) return;
    if (!('Notification' in window)) { btns.forEach(b => b.style.display = 'none'); return; }
    const perm = Notification.permission;
    btns.forEach(btn => {
        if (perm === 'granted') {
            btn.innerHTML = '<i class="fas fa-bell" style="color:#22c55e"></i> Notificações ativas';
            btn.title     = 'Notificações ativas';
            btn.disabled  = true;
        } else if (perm === 'denied') {
            btn.style.display = 'none';
        } else {
            btn.innerHTML     = '<i class="fas fa-bell-slash"></i> Ativar notificações';
            btn.disabled      = false;
            btn.style.display = '';
        }
    });
}

async function requestSMSNotificationPermission() {
    if (!('Notification' in window)) return;
    await Notification.requestPermission();
    _updateNotifPermBtn();
}

async function refreshSMSNotifications(forceRender = false) {
    const smsSection = document.getElementById('sms-section');
    if (!smsSection) return;

    if (smsRefreshInFlight) return;
    smsRefreshInFlight = true;

    try {
        const response = await fetch('get_notifications.php', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        });

        const payload = await response.json();
        if (!payload.success) return;

        // Actualizar badge sempre, independente de re-render
        const newUnread = typeof payload.unread_count === 'number' ? payload.unread_count : 0;
        updateSMSNavBadge(newUnread);

        // Toast + som + notificação push se chegaram novas mensagens enquanto noutra secção
        const smsActive = smsSection.classList.contains('active');
        if (!smsActive && newUnread > _smsUnreadCount) {
            const diff = newUnread - _smsUnreadCount;
            firePortalToast('info', `${diff} nova${diff > 1 ? 's' : ''} mensagem${diff > 1 ? 'ns' : ''} recebida${diff > 1 ? 's' : ''}`, 5000);
            playSMSSound();
            showBrowserNotification(diff);
        }
        _smsUnreadCount = newUnread;

        const nextFingerprint = buildSMSFingerprint(payload);
        if (!forceRender && nextFingerprint === lastSMSFingerprint) return;

        lastSMSFingerprint = nextFingerprint;
        renderSMSNotifications(payload.notifications || [], Boolean(payload.can_delete), payload.total || 0);
    } catch (error) {
        console.error('refreshSMSNotifications error', error);
    } finally {
        smsRefreshInFlight = false;
    }
}

function updateSMSSelectionState() {
    const allCheckboxes = Array.from(document.querySelectorAll('.sms-checkbox'));
    const checkedCount = allCheckboxes.filter((checkbox) => checkbox.checked).length;
    const selectAll = document.getElementById('smsSelectAll');
    const deleteButton = document.getElementById('deleteSelectedSmsBtn');

    if (selectAll) {
        selectAll.checked = allCheckboxes.length > 0 && checkedCount === allCheckboxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
    }

    if (deleteButton) {
        deleteButton.disabled = checkedCount === 0;
    }
}

function toggleAllSMS(source) {
    if (!source || source.id !== 'smsSelectAll') {
        return;
    }

    const allCheckboxes = document.querySelectorAll('.sms-checkbox');
    allCheckboxes.forEach((checkbox) => {
        checkbox.checked = source.checked;
    });
    updateSMSSelectionState();
}

async function deleteAllSMS() {
    const confirmation = await Swal.fire({
        title: 'Limpar todas as mensagens?',
        text: 'Esta ação vai remover todas as mensagens permanentemente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, limpar tudo',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        background: '#1e293b',
        color: '#e2e8f0'
    });

    if (!confirmation.isConfirmed) return;

    try {
        const response = await fetch('delete_sms.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ all: true })
        });
        const data = await response.json();
        if (data.success) {
            showSuccess(data.message || 'Mensagens eliminadas.');
            await refreshSMSNotifications(true);
        } else {
            showError(data.message || 'Não foi possível eliminar as mensagens.');
        }
    } catch (error) {
        console.error(error);
        showError('Erro de comunicação ao eliminar mensagens.');
    }
}

async function deleteSelectedSMS() {
    const ids = getSelectedSMSIds();
    if (ids.length === 0) {
        showWarning('Selecione pelo menos uma SMS.');
        return;
    }

    const confirmation = await Swal.fire({
        title: 'Eliminar SMS selecionadas?',
        text: `Esta ação vai remover ${ids.length} mensagem(ns).`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        background: '#1e293b',
        color: '#e2e8f0'
    });

    if (!confirmation.isConfirmed) {
        return;
    }

    try {
        const response = await fetch('delete_sms.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids })
        });

        const data = await response.json();
        if (data.success) {
            showSuccess(data.message || 'SMS eliminadas com sucesso.');
            await refreshSMSNotifications(true);
            return;
        }

        showError(data.message || 'Não foi possível eliminar as SMS.');
    } catch (error) {
        console.error(error);
        showError('Erro de comunicação ao eliminar SMS.');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const menuBtn = document.getElementById('portal-menu-btn');
    const portalNav = document.getElementById('portal-nav');

    if (menuBtn) {
        menuBtn.addEventListener('click', function () {
            toggleMobilePortalNav();
        });
    }

    const navButtons = document.querySelectorAll('.nav-btn');
    navButtons.forEach((button) => {
        button.addEventListener('click', function () {
            const sectionId = this.dataset.section;
            showPortalSection(sectionId);
            if (window.innerWidth <= 768) {
                closeMobilePortalNav();
            }
            if (sectionId === 'sms-section') {
                refreshSMSNotifications(true);
                markSMSAsRead();
            }
        });
    });

    document.addEventListener('click', function (event) {
        if (window.innerWidth > 768) {
            return;
        }
        if (!document.body.classList.contains('portal-nav-open')) {
            return;
        }
        const clickedInsideMenu = portalNav && portalNav.contains(event.target);
        const clickedMenuBtn = menuBtn && menuBtn.contains(event.target);
        if (!clickedInsideMenu && !clickedMenuBtn) {
            closeMobilePortalNav();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            closeMobilePortalNav();
        }
    });

    updateSMSSelectionState();
    _updateNotifPermBtn();
    refreshSMSNotifications(true);

    // Iniciar contador com a entrada do período actual (não necessariamente a primeira do dia)
    const entradaAtualEl = document.getElementById('periodo-entrada-atual');
    const timerRow = document.getElementById('live-timer-row');
    if (timerRow && timerRow.style.display !== 'none' && entradaAtualEl && entradaAtualEl.value) {
        _iniciarContadorPonto(entradaAtualEl.value.trim());
    }

    // Filtros do histórico
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const dateInput = document.getElementById('history-date-search');
            if (dateInput) dateInput.value = '';
            const clearBtn = document.getElementById('history-clear-btn');
            if (clearBtn) clearBtn.style.display = 'none';
            _filtrarTabelaPresencas(btn.dataset.period, null);
        });
    });

    const histDateInput = document.getElementById('history-date-search');
    if (histDateInput) {
        histDateInput.addEventListener('change', () => {
            const val = histDateInput.value;
            const clearBtn = document.getElementById('history-clear-btn');
            if (clearBtn) clearBtn.style.display = val ? '' : 'none';
            document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
            _filtrarTabelaPresencas(null, val);
        });
    }

    const historyClearBtn = document.getElementById('history-clear-btn');
    if (historyClearBtn) {
        historyClearBtn.addEventListener('click', () => {
            const dateInput = document.getElementById('history-date-search');
            if (dateInput) dateInput.value = '';
            historyClearBtn.style.display = 'none';
            const allBtn = document.querySelector('.period-btn[data-period="all"]');
            if (allBtn) allBtn.classList.add('active');
            _filtrarTabelaPresencas('all', null);
        });
    }

    const feriasForm = document.getElementById('feriasForm');
    if (feriasForm) feriasForm.addEventListener('submit', submitFeriasRequest);

    const turnoSwapRequestForm = document.getElementById('turnoSwapRequestForm');
    if (turnoSwapRequestForm) turnoSwapRequestForm.addEventListener('submit', submitTurnoSwapRequest);

    // Modal de justificativas
    const justModalForm = document.getElementById('justificativaModalForm');
    if (justModalForm) justModalForm.addEventListener('submit', _submitJustificativaModal);

    // Fechar modal ao clicar fora
    const justModal = document.getElementById('justificativaModal');
    if (justModal) {
        justModal.addEventListener('click', (e) => {
            if (e.target === justModal) closeJustificativaModal();
        });
    }

    // Navegação por mês no histórico
    _initMonthNav();
    _pgReset(); // Paginação inicial da tabela PHP

    document.querySelectorAll('.shortcut-btn, .shortcut-card').forEach((btn) => {
        btn.addEventListener('click', () => {
            const sectionId = btn.dataset.section;
            showPortalSection(sectionId);
            if (sectionId === 'sms-section') {
                refreshSMSNotifications(true);
                markSMSAsRead();
            }
        });
    });

    document.querySelectorAll('.btn-turno-swap-decision').forEach((button) => {
        button.addEventListener('click', () => {
            const requestId = button.getAttribute('data-id');
            const decision = String(button.getAttribute('data-decision') || '').trim().toLowerCase();
            if (!['accept', 'reject'].includes(decision)) {
                showWarning('Ação inválida para esta solicitação.');
                return;
            }
            respondTurnoSwapRequest(requestId, decision, button);
        });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshSMSNotifications(true);
            const smsActive = document.getElementById('sms-section')?.classList.contains('active');
            if (smsActive) markSMSAsRead();
        }
    });

    // Polling activo: 3s quando a secção SMS está aberta
    setInterval(() => {
        const smsActive = document.getElementById('sms-section')?.classList.contains('active');
        if (document.visibilityState === 'visible' && smsActive) {
            refreshSMSNotifications(false);
        }
    }, 3000);

    // Polling background: 30s para badge + toast mesmo noutras secções
    setInterval(() => {
        const smsActive = document.getElementById('sms-section')?.classList.contains('active');
        if (document.visibilityState === 'visible' && !smsActive) {
            refreshSMSNotifications(false);
        }
    }, 30000);

    // Cancelar férias — botões renderizados pelo PHP inicial
    _registerFeriasCancel(document);

    // Polling ferias (30s)
    _pollFerias();
    setInterval(() => { if (document.visibilityState === 'visible') _pollFerias(); }, 30000);

    // Cancelar troca — botões renderizados pelo PHP inicial
    document.querySelectorAll('.btn-cancel-swap').forEach((btn) => {
        btn.addEventListener('click', () => {
            const rid = parseInt(String(btn.getAttribute('data-id') || '0'), 10);
            if (rid > 0) cancelTurnoSwap(rid, btn);
        });
    });

    // Polling inicial de trocas + intervalo de 30s
    _pollTrocas();
    setInterval(() => {
        if (document.visibilityState === 'visible') _pollTrocas();
    }, 30000);

    // Cancelar gorjetas — botões do PHP inicial
    _registerGorjetaCancel(document);

    // Polling gorjetas (30s)
    _pollGorjetas();
    setInterval(() => { if (document.visibilityState === 'visible') _pollGorjetas(); }, 30000);

    // Sino do header → vai para notificações
    document.getElementById('header-bell-btn')?.addEventListener('click', () => {
        showPortalSection('sms-section');
        refreshSMSNotifications(true);
        markSMSAsRead();
    });

    // Carregar recibos quando a secção ficar activa
    document.querySelectorAll('.nav-btn[data-section="recibos-section"]').forEach(btn => {
        btn.addEventListener('click', () => { _loadRecibos(); }, { once: false });
    });

    // Definições
    _initDefinicoes();
});

// ── Férias ───────────────────────────────────────────────────────────

let _feriasLastHash = null;

function onFeriasDatesChange() {
    const ini = document.getElementById('ferias_data_inicio')?.value || '';
    const fim = document.getElementById('ferias_data_fim')?.value || '';
    const counter = document.getElementById('ferias-dias-counter');
    if (!counter) return;

    const fimInput = document.getElementById('ferias_data_fim');
    if (fimInput && ini) fimInput.min = ini;

    if (ini && fim && fim >= ini) {
        const ms   = new Date(fim) - new Date(ini);
        const dias = Math.round(ms / 86400000) + 1;
        const disp = typeof window._diasFeriasDisp !== 'undefined' ? window._diasFeriasDisp : null;
        const tot  = typeof window._diasFeriasTotal !== 'undefined' ? window._diasFeriasTotal : 22;

        if (disp !== null && dias > disp) {
            counter.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${dias} dia${dias !== 1 ? 's' : ''} pedidos — saldo insuficiente (${disp}/${tot} disponíveis)`;
            counter.className = 'ferias-dias-counter ferias-dias-counter--over';
        } else {
            counter.innerHTML = `<i class="fas fa-check-circle"></i> ${dias} dia${dias !== 1 ? 's' : ''}${disp !== null ? ` — ficam ${disp - dias} de ${tot}` : ''}`;
            counter.className = 'ferias-dias-counter ferias-dias-counter--ok';
        }
        counter.style.display = '';
    } else {
        counter.style.display = 'none';
    }
}

async function cancelFerias(id, btn) {
    const conf = await Swal.fire({
        title: 'Cancelar pedido de férias?',
        text: 'O pedido será cancelado e terá de submeter um novo se necessário.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Não',
        confirmButtonColor: '#dc2626',
        background: '#1e293b',
        color: '#e2e8f0'
    });
    if (!conf.isConfirmed) return;

    if (btn) btn.disabled = true;
    try {
        const res  = await fetch('cancelar_ferias.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ferias_id: id })
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            showError(data.message || 'Não foi possível cancelar o pedido.');
            if (btn) btn.disabled = false;
            return;
        }
        showSuccess(data.message || 'Pedido cancelado.');
        btn?.closest('.message-item')?.remove();
        const list = document.getElementById('feriasList');
        if (list && !list.querySelector('.message-item')) {
            list.innerHTML = '<p class="empty-state" id="feriasEmptyState">Sem pedidos de férias no momento.</p>';
        }
        _feriasLastHash = null;
    } catch (err) {
        console.error(err);
        showError('Erro de comunicação ao cancelar pedido.');
        if (btn) btn.disabled = false;
    }
}

function _buildFeriasItemHtml(f) {
    let st = (f.status || 'pendente').toLowerCase();
    if (st === 'aprovado') st = 'aprovada';
    if (['rejeitado', 'recusado', 'recusada'].includes(st)) st = 'rejeitada';

    let label = 'Pendente', cls = 'badge-warning';
    const today = new Date().toISOString().slice(0, 10);
    if (st === 'aprovada') {
        label = 'Em curso'; cls = 'badge-success';
        if (f.data_inicio && today < f.data_inicio) { label = 'Agendada'; cls = 'badge-warning'; }
        else if (f.data_fim && today > f.data_fim)  { label = 'Terminada'; cls = 'badge-neutral'; }
    } else if (st === 'rejeitada') { label = 'Rejeitada'; cls = 'badge-danger'; }
    else if (st === 'cancelada')   { label = 'Cancelada'; cls = 'badge-neutral'; }

    const iniFmt = formatDateYmdToPt(f.data_inicio);
    const fimFmt = formatDateYmdToPt(f.data_fim);
    let dias = 0;
    if (f.data_inicio && f.data_fim) {
        dias = Math.round((new Date(f.data_fim) - new Date(f.data_inicio)) / 86400000) + 1;
    }
    const diasStr = dias > 0 ? ` (${dias} dia${dias !== 1 ? 's' : ''})` : '';
    const motivoRejHtml = f.motivo_rejeicao
        ? `<p class="ferias-motivo-rej"><i class="fas fa-comment-slash"></i> ${escapeHTML(f.motivo_rejeicao)}</p>`
        : '';
    const cancelBtn = st === 'pendente'
        ? `<button type="button" class="btn btn-danger btn-cancel-ferias" data-id="${escapeHTML(String(f.id))}"
               style="margin-top:.4rem;font-size:.8rem;padding:.3rem .7rem;">
               <i class="fas fa-times"></i> Cancelar pedido
           </button>`
        : '';

    return `<div class="message-item ferias-item-row" data-ferias-id="${escapeHTML(String(f.id))}" data-ferias-status="${escapeHTML(st)}">
        <div style="flex:1;">
            <div class="message-meta" style="margin-bottom:.3rem;">
                <span class="status-badge ${cls}">${label}</span>
                <small>${escapeHTML(iniFmt)} – ${escapeHTML(fimFmt)}${escapeHTML(diasStr)}</small>
            </div>
            <p class="message-text" style="margin:0 0 .3rem;">${escapeHTML(f.motivo || 'Sem motivo informado.')}</p>
            ${motivoRejHtml}
            ${cancelBtn}
        </div>
    </div>`;
}

function _registerFeriasCancel(container) {
    (container || document).querySelectorAll('.btn-cancel-ferias').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = parseInt(String(btn.getAttribute('data-id') || '0'), 10);
            if (id > 0) cancelFerias(id, btn);
        });
    });
}

async function _pollFerias() {
    try {
        const res = await fetch('get_ferias_employee.php', { credentials: 'same-origin' });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.success || data.hash === _feriasLastHash) return;
        _feriasLastHash = data.hash;

        const list = document.getElementById('feriasList');
        if (list) {
            if (!data.ferias || data.ferias.length === 0) {
                list.innerHTML = '<p class="empty-state" id="feriasEmptyState">Sem pedidos de férias no momento.</p>';
            } else {
                list.innerHTML = data.ferias.map(_buildFeriasItemHtml).join('');
                _registerFeriasCancel(list);
            }
        }

        // Actualizar contador de dias usados
        const saldoEl = document.querySelector('.ferias-saldo-bar span');
        if (saldoEl && data.diasUsados !== undefined) {
            const ano = new Date().getFullYear();
            saldoEl.textContent = `${data.diasUsados} dias aprovados em ${ano}`;
        }

        // Actualizar saldo disponível (KPI + contador do formulário) sem recarregar a página
        if (data.diasDisponiveis !== undefined) {
            window._diasFeriasDisp = data.diasDisponiveis;
        }
        if (data.vacationDays !== undefined) {
            window._diasFeriasTotal = data.vacationDays;
        }
        const kpiDisp = document.getElementById('kpi-ferias-disp');
        const kpiSub = document.getElementById('kpi-ferias-sub');
        if (kpiDisp && data.diasDisponiveis !== undefined) kpiDisp.textContent = data.diasDisponiveis;
        if (kpiSub && data.diasUsados !== undefined && data.vacationDays !== undefined) {
            kpiSub.textContent = `${data.diasUsados}/${data.vacationDays} usados`;
        }
        if (typeof onFeriasDatesChange === 'function') onFeriasDatesChange();
    } catch (_) {
        // silent
    }
}

// ── Trocas de turno ──────────────────────────────────────────────────

let _swapLastHash = null;

async function cancelTurnoSwap(id, btn) {
    const confirmation = await Swal.fire({
        title: 'Cancelar pedido?',
        text: 'O pedido de troca será cancelado e o colega não precisará responder.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Não',
        confirmButtonColor: '#dc2626',
        background: '#1e293b',
        color: '#e2e8f0'
    });
    if (!confirmation.isConfirmed) return;

    if (btn) btn.disabled = true;
    try {
        const res = await fetch('cancelar_troca_turno.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: id })
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            showError(data.message || 'Não foi possível cancelar o pedido.');
            if (btn) btn.disabled = false;
            return;
        }
        showSuccess(data.message || 'Pedido cancelado.');
        btn?.closest('.message-item')?.remove();
        const outList = document.getElementById('swapSentList');
        if (outList && !outList.querySelector('.message-item')) {
            outList.innerHTML = '<p class="empty-state" id="swapSentEmptyState">Você ainda não enviou pedidos de troca.</p>';
        }
        _swapLastHash = null; // forçar re-render no próximo poll
    } catch (err) {
        console.error(err);
        showError('Erro de comunicação ao cancelar pedido.');
        if (btn) btn.disabled = false;
    }
}

function _buildSwapIncomingHtml(s) {
    const rTipo = escapeHTML((s.requester_turno_tipo || '-') + ' ' + (s.requester_horario_inicio || '').slice(0, 5) + '-' + (s.requester_horario_fim || '').slice(0, 5));
    const tTipo = escapeHTML((s.target_turno_tipo || '-') + ' ' + (s.target_horario_inicio || '').slice(0, 5) + '-' + (s.target_horario_fim || '').slice(0, 5));
    return `<div class="message-item">
        <div class="message-meta" style="margin-bottom:.35rem;">
            <span class="status-badge badge-warning">Pendente colega</span>
            <small>${escapeHTML(s.requester_name || 'Funcionário')}</small>
        </div>
        <p class="message-text" style="margin:0 0 .45rem 0;">
            <strong>Turno solicitante:</strong> ${rTipo}<br>
            <strong>Seu turno:</strong> ${tTipo}
        </p>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <button type="button" class="btn btn-success btn-turno-swap-decision"
                data-id="${escapeHTML(String(s.id))}" data-decision="accept">
                <i class="fas fa-check"></i> Aceitar
            </button>
            <button type="button" class="btn btn-danger btn-turno-swap-decision"
                data-id="${escapeHTML(String(s.id))}" data-decision="reject">
                <i class="fas fa-times"></i> Rejeitar
            </button>
        </div>
    </div>`;
}

function _buildSwapSentHtml(s) {
    const st = (s.status || 'pendente_colega').toLowerCase();
    let badgeCls = 'badge-warning';
    let label = 'Pendente colega';
    if (st === 'pendente_admin') { label = 'Pendente admin'; }
    else if (['aprovada', 'aprovado'].includes(st)) { label = 'Aprovada'; badgeCls = 'badge-success'; }
    else if (st === 'cancelada') { label = 'Cancelada'; badgeCls = 'badge-danger'; }
    else if (['rejeitada', 'rejeitado', 'rejeitada_colega'].includes(st)) { label = 'Rejeitada'; badgeCls = 'badge-danger'; }

    const rTipo = escapeHTML((s.requester_turno_tipo || '-') + ' ' + (s.requester_horario_inicio || '').slice(0, 5) + '-' + (s.requester_horario_fim || '').slice(0, 5));
    const tTipo = escapeHTML((s.target_turno_tipo || '-') + ' ' + (s.target_horario_inicio || '').slice(0, 5) + '-' + (s.target_horario_fim || '').slice(0, 5));
    const noteHtml = s.review_note
        ? `<p class="message-text" style="margin:.3rem 0 0;font-size:.8rem;opacity:.7"><i class="fas fa-comment"></i> ${escapeHTML(s.review_note)}</p>`
        : '';
    const cancelBtn = st === 'pendente_colega'
        ? `<button type="button" class="btn btn-danger btn-cancel-swap"
               data-id="${escapeHTML(String(s.id))}"
               style="margin-top:.5rem;font-size:.8rem;padding:.35rem .75rem;">
               <i class="fas fa-times"></i> Cancelar pedido
           </button>`
        : '';

    return `<div class="message-item">
        <div class="message-meta" style="margin-bottom:.35rem;">
            <span class="status-badge ${badgeCls}">${label}</span>
            <small>${escapeHTML(s.target_name || 'Colega')}</small>
        </div>
        <p class="message-text" style="margin:0;">${rTipo} ↔ ${tTipo}</p>
        ${noteHtml}
        ${cancelBtn}
    </div>`;
}

async function _pollTrocas() {
    try {
        const res = await fetch('get_trocas_turno.php', { credentials: 'same-origin' });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.success || data.hash === _swapLastHash) return;
        _swapLastHash = data.hash;

        // Actualizar lista de pedidos recebidos
        const inList = document.getElementById('swapIncomingList');
        if (inList) {
            if (data.incoming.length === 0) {
                inList.innerHTML = '<p class="empty-state" id="swapIncomingEmptyState">Nenhum pedido pendente para você aprovar.</p>';
            } else {
                inList.innerHTML = data.incoming.map(_buildSwapIncomingHtml).join('');
                inList.querySelectorAll('.btn-turno-swap-decision').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const rid = btn.getAttribute('data-id');
                        const dec = String(btn.getAttribute('data-decision') || '').trim().toLowerCase();
                        if (['accept', 'reject'].includes(dec)) respondTurnoSwapRequest(rid, dec, btn);
                    });
                });
            }
        }

        // Actualizar lista de pedidos enviados
        const outList = document.getElementById('swapSentList');
        if (outList) {
            if (data.outgoing.length === 0) {
                outList.innerHTML = '<p class="empty-state" id="swapSentEmptyState">Você ainda não enviou pedidos de troca.</p>';
            } else {
                outList.innerHTML = data.outgoing.map(_buildSwapSentHtml).join('');
                outList.querySelectorAll('.btn-cancel-swap').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const rid = parseInt(String(btn.getAttribute('data-id') || '0'), 10);
                        if (rid > 0) cancelTurnoSwap(rid, btn);
                    });
                });
            }
        }

        // Actualizar badge KPI de trocas pendentes
        const kpiCard = document.querySelector('.kpi-orange');
        if (kpiCard) {
            const valEl = kpiCard.querySelector('.kpi-value');
            if (valEl) valEl.textContent = String(data.incoming.length);
            kpiCard.classList.toggle('kpi-alert', data.incoming.length > 0);
        }
    } catch (_) {
        // silent — sem ligação ou servidor em manutenção
    }
}

        async function registrarPonto(tipo) {
            await _executarRegistoPonto(tipo, '');
        }

        async function registrarPausa() {
            const { value: tipoPausa, isConfirmed } = await Swal.fire({
                title: 'Tipo de pausa',
                input: 'radio',
                inputOptions: {
                    'Pausa Almoço':  '🍽️  Almoço',
                    'Pausa Cigarro': '🚬  Cigarro',
                    'Pausa Outra':   '⏸️  Outra',
                },
                inputValue: 'Pausa Almoço',
                showCancelButton: true,
                confirmButtonText: 'Registar Pausa',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#f59e0b',
                background: '#1e293b',
                color: '#e2e8f0',
                customClass: { input: 'pausa-radio-group' },
                inputValidator: (v) => !v ? 'Selecione o tipo de pausa' : null,
            });
            if (!isConfirmed || !tipoPausa) return;
            await _executarRegistoPonto('saida', tipoPausa);
        }

        async function registrarRegresso() {
            await _executarRegistoPonto('entrada', 'Regresso');
        }

        // Tenta obter a localização atual do funcionário; nunca rejeita — se o
        // navegador não suportar, o funcionário recusar a permissão, ou demorar
        // demasiado, resolve com null e a marcação de ponto segue em frente na
        // mesma, só sem dados de localização.
        function _obterLocalizacaoAtual() {
            return new Promise((resolve) => {
                if (!navigator.geolocation) { resolve(null); return; }
                navigator.geolocation.getCurrentPosition(
                    (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
                    () => resolve(null),
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 30000 }
                );
            });
        }

        async function _executarRegistoPonto(tipo, observacao) {
            const allPontoBtns = document.querySelectorAll('.btn-ponto-action');
            allPontoBtns.forEach(b => { b.disabled = true; });

            try {
                const localizacao = await _obterLocalizacaoAtual();
                const res = await fetch('registrar_ponto_session.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        tipo,
                        observacao,
                        lat: localizacao ? localizacao.lat : null,
                        lng: localizacao ? localizacao.lng : null
                    })
                });

                const data = await res.json();

                if (data.success) {
                    const hora = String(data.hora || '').substring(0, 5) ||
                        new Date().toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });

                    showSuccess(data.message || `Ponto de ${tipo} registado às ${hora}`);
                    _atualizarStatusPonto(tipo, hora, observacao);
                    setTimeout(() => location.reload(), 1500);

                } else {
                    showError(data.message || 'Erro ao registar ponto');
                    allPontoBtns.forEach(b => { b.disabled = false; });
                }
            } catch (e) {
                console.error(e);
                showError('Erro de comunicação com o servidor');
                allPontoBtns.forEach(b => { b.disabled = false; });
            }
        }

        function _atualizarStatusPonto(tipo, hora, observacao) {
            const isPausa    = tipo === 'saida'   && observacao.toLowerCase().includes('pausa');
            const isRegresso = tipo === 'entrada' && observacao === 'Regresso';
            const isEntrada  = tipo === 'entrada';

            // Mapa de acção → { icon, label, statusLabel, badgeClass }
            let icon, label, statusLabel, badgeCls;
            if (isPausa) {
                icon = 'fa-pause-circle'; label = observacao + ' registada';
                statusLabel = 'Em pausa'; badgeCls = 'badge-warning';
            } else if (isRegresso) {
                icon = 'fa-undo-alt'; label = 'Regresso registado';
                statusLabel = 'Em serviço'; badgeCls = 'badge-success';
            } else if (isEntrada) {
                icon = 'fa-sign-in-alt'; label = 'Entrada registada';
                statusLabel = 'Em serviço'; badgeCls = 'badge-success';
            } else {
                icon = 'fa-sign-out-alt'; label = 'Saída registada';
                statusLabel = 'Dia concluído'; badgeCls = 'badge-secondary';
            }

            // Actualizar display de último registo
            const el = document.getElementById('ponto-status-display');
            if (el) {
                el.className = `ponto-status ${isEntrada || isRegresso ? 'ponto-status--in' : 'ponto-status--out'}`;
                el.innerHTML = `
                    <i class="fas ${icon}"></i>
                    <div>
                        <span>${label}</span>
                        <strong>${escapeHTML(hora)}</strong>
                    </div>`;
            }

            // Actualizar badge de estado no card "Presença Hoje"
            const badge = document.getElementById('presenca-estado-badge');
            if (badge) {
                badge.className = `status-badge ${badgeCls}`;
                badge.textContent = statusLabel;
            }

            // Actualizar badge de estado no card de ponto (info-row)
            const estadoBadge = document.querySelector('.info-row .status-badge');
            if (estadoBadge) {
                estadoBadge.className = `status-badge ${badgeCls}`;
                estadoBadge.textContent = statusLabel;
            }
        }

        // ── Actualização dinâmica do card "Presença Hoje" ──────────────────
        function _atualizarCardPresenca(tipo, hora) {
            const badgeEl    = document.getElementById('presenca-estado-badge');
            const entradaRow = document.getElementById('presenca-entrada-row');
            const entradaVal = document.getElementById('presenca-entrada-valor');
            const saidaRow   = document.getElementById('presenca-saida-row');
            const saidaVal   = document.getElementById('presenca-saida-valor');
            const horasRow   = document.getElementById('presenca-horas-row');
            const horasVal   = document.getElementById('presenca-horas-valor');
            const timerRow   = document.getElementById('live-timer-row');

            if (tipo === 'entrada') {
                if (badgeEl)    { badgeEl.className = 'status-badge badge-success'; badgeEl.textContent = 'Presente'; }
                if (entradaVal) entradaVal.textContent = hora;
                if (entradaRow) entradaRow.style.display = '';
                if (timerRow)   timerRow.style.display = '';
                _iniciarContadorPonto(hora);
            } else {
                if (badgeEl)    { badgeEl.className = 'status-badge badge-warning'; badgeEl.textContent = 'Pendente'; }
                if (saidaVal)   saidaVal.textContent = hora;
                if (saidaRow)   saidaRow.style.display = '';
                if (timerRow)   timerRow.style.display = 'none';
                _pararContadorPonto();

                // Calcular horas trabalhadas a partir da entrada visível
                const entradaStr = entradaVal ? entradaVal.textContent.trim() : '';
                if (entradaStr && hora) {
                    const [h1, m1] = entradaStr.split(':').map(Number);
                    const [h2, m2] = hora.split(':').map(Number);
                    const diffMin = (h2 * 60 + m2) - (h1 * 60 + m1);
                    if (diffMin > 0) {
                        const label = `${Math.floor(diffMin / 60)}h${String(diffMin % 60).padStart(2, '0')}m`;
                        if (horasVal)  horasVal.textContent = label;
                        if (horasRow)  horasRow.style.display = '';
                    }
                }
            }
        }

        // ── Contador em Tempo Real ─────────────────────────────────────────
        let _timerInterval = null;

        function _fmtSegs(segs) {
            const h = Math.floor(segs / 3600);
            const m = Math.floor((segs % 3600) / 60);
            return `${h}h ${String(m).padStart(2, '0')}min`;
        }

        function _iniciarContadorPonto(horaEntrada) {
            _pararContadorPonto();
            const el = document.getElementById('live-timer');
            if (!el || !horaEntrada) return;

            // Segundos já trabalhados em períodos anteriores fechados
            const baseEl   = document.getElementById('horas-base-hoje');
            const baseSegs = baseEl ? parseInt(baseEl.value || '0', 10) : 0;

            // Esconder a row estática para evitar duplicação
            const horasRow = document.getElementById('presenca-horas-row');
            if (horasRow) horasRow.style.display = 'none';

            const [hh, mm] = horaEntrada.split(':').map(Number);

            function _tick() {
                const agora = new Date();
                const base  = new Date();
                base.setHours(hh, mm, 0, 0);
                const diffMs = agora - base;
                if (diffMs < 0) { el.textContent = _fmtSegs(baseSegs); return; }
                el.textContent = _fmtSegs(baseSegs + Math.floor(diffMs / 1000));
            }
            _tick();
            _timerInterval = setInterval(_tick, 1000);
        }

        function _pararContadorPonto() {
            if (_timerInterval) { clearInterval(_timerInterval); _timerInterval = null; }
        }

        // Marcar presença
        async function marcarPresenca(status) {
            try {
                const res = await fetch('salvar_presenca_session.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ status })
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showSuccess(data.message || 'Presença registrada com sucesso!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(data.message || 'Erro ao marcar presença');
                }
            } catch(e) {
                console.error(e);
                showError('Erro de comunicação com o servidor');
            }
        }

        // ── Gorjetas: filtros, paginação e AJAX ──────────────────────────────
        let _gorjetaMes          = document.getElementById('gorjeta-mes-select')?.value || new Date().toISOString().slice(0, 7);
        let _gorjetaStatusFilter = 'all';
        let _gorjetaOffset       = parseInt(document.getElementById('gorjeta-list')?.dataset.offset || '0', 10);
        let _gorjetaTotal        = parseInt(document.getElementById('gorjeta-list')?.dataset.total  || '0', 10);

        function _buildGorjetaItemHtml(g) {
            const gs     = String(g.status || '').toLowerCase().trim();
            const gCls   = gs === 'pago' ? 'badge-success' : (gs === 'pendente' ? 'badge-warning' : 'badge-danger');
            const gLbl   = gs === 'pago' ? 'Pago' : (gs === 'pendente' ? 'Pendente' : (gs.charAt(0).toUpperCase() + gs.slice(1)));
            const fp     = String(g.forma_pagamento || '').trim();
            const orig   = String(g.origem || '').trim();
            const obs    = String(g.observacoes || g.observacao || '').trim();
            const motivo = String(g.motivo_rejeicao || '').trim();
            const dc     = g.data_registro || g.data || '';
            const dateStr = dc ? new Date(dc + 'T00:00:00').toLocaleDateString('pt-PT') : '--';
            let details  = '';
            if (orig)                    details += `<div class="gorjeta-detail"><i class="fas fa-map-marker-alt"></i> ${escapeHTML(orig)}</div>`;
            if (obs)                     details += `<div class="gorjeta-detail"><i class="fas fa-comment"></i> ${escapeHTML(obs)}</div>`;
            if (gs === 'rejeitado' && motivo) details += `<div class="gorjeta-motivo"><i class="fas fa-times-circle"></i> ${escapeHTML(motivo)}</div>`;
            const cancelBtn = gs === 'pendente'
                ? `<button class="btn-cancel-gorjeta" data-id="${parseInt(g.id||0,10)}" title="Cancelar gorjeta"><i class="fas fa-times"></i> Cancelar</button>`
                : '';
            return `<div class="gorjeta-item" data-status="${escapeHTML(gs)}" data-id="${parseInt(g.id||0,10)}">
                <div style="flex:1;min-width:0">
                    <div class="gorjeta-valor">${parseFloat(g.valor||0).toFixed(2).replace('.',',')}€</div>
                    <div class="gorjeta-data">${dateStr}${g.turno ? ' • '+escapeHTML(g.turno) : ''}${fp ? ' • '+escapeHTML(fp) : ''}</div>
                    ${details}
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;flex-shrink:0">
                    <span class="status-badge ${gCls}">${gLbl}</span>
                    ${cancelBtn}
                </div>
            </div>`;
        }

        function _applyGorjetaClientFilter() {
            document.querySelectorAll('#gorjeta-list .gorjeta-item').forEach(item => {
                const s = item.dataset.status || '';
                item.style.display = (_gorjetaStatusFilter === 'all' || s === _gorjetaStatusFilter) ? '' : 'none';
            });
        }

        function _updateGorjetaPaymentBreakdown(totais) {
            const bd = document.getElementById('gorjeta-payment-breakdown');
            if (!bd) return;
            const entries = Object.entries(totais || {});
            bd.innerHTML = entries.length
                ? entries.map(([fp, v]) => `<span>${parseFloat(v).toFixed(2).replace('.',',')}€ ${escapeHTML(fp)}</span>`).join('')
                : '';
        }

        async function loadGorjetas(reset = false) {
            const list = document.getElementById('gorjeta-list');
            if (!list) return;
            if (reset) { _gorjetaOffset = 0; list.innerHTML = '<p class="empty-state">A carregar…</p>'; }
            const btn = document.getElementById('gorjeta-load-more-btn');
            if (btn) btn.disabled = true;
            try {
                const res  = await fetch(`get_gorjetas_employee.php?mes=${_gorjetaMes}&status=all&offset=${_gorjetaOffset}`, { credentials: 'same-origin', cache: 'no-store' });
                const data = await res.json();
                if (!data.success) return;
                if (reset) {
                    list.innerHTML = data.gorjetas.length
                        ? data.gorjetas.map(_buildGorjetaItemHtml).join('')
                        : '<div class="empty-state"><i class="fas fa-receipt"></i><p>Sem gorjetas neste período.</p></div>';
                } else {
                    if (data.gorjetas.length) list.insertAdjacentHTML('beforeend', data.gorjetas.map(_buildGorjetaItemHtml).join(''));
                }
                _gorjetaTotal   = data.total || 0;
                _gorjetaOffset += data.gorjetas.length;
                _updateGorjetaPaymentBreakdown(data.totaisPorPagamento || {});
                if (reset) _updateResumoSalarial(parseFloat(data.totalValor || 0));
                const wrap = document.getElementById('gorjeta-load-more-wrap');
                if (wrap) wrap.style.display = _gorjetaOffset < _gorjetaTotal ? '' : 'none';
                _applyGorjetaClientFilter();
                _registerGorjetaCancel(list);
            } catch (e) { console.error('loadGorjetas', e); }
            finally { if (btn) { btn.disabled = false; } }
        }

        window._loadGorjetas = loadGorjetas;

        function setGorjetaMes(val) {
            _gorjetaMes = val;
            loadGorjetas(true);
            const expBtn = document.getElementById('gorjeta-export-btn');
            if (expBtn) expBtn.href = `exportar_gorjetas.php?mes=${val}`;
        }
        window.setGorjetaMes = setGorjetaMes;

        function setGorjetaStatusFilter(val) {
            _gorjetaStatusFilter = val || 'all';
            document.querySelectorAll('.gorjeta-tab').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.filter === _gorjetaStatusFilter);
            });
            _applyGorjetaClientFilter();
        }
        window.setGorjetaStatusFilter = setGorjetaStatusFilter;

        function loadMoreGorjetas() { loadGorjetas(false); }
        window.loadMoreGorjetas = loadMoreGorjetas;
        // ─────────────────────────────────────────────────────────────────────

        // Modal Gorjeta
        function openGorjetaModal() {
            document.getElementById('gorjetaModal').style.display = 'block';
            // Garantir que a data mostra sempre hoje ao abrir
            const dateField = document.getElementById('gorjeta_data');
            if (dateField && !dateField.value) {
                dateField.value = new Date().toISOString().substring(0, 10);
            }
        }

        function closeGorjetaModal() {
            document.getElementById('gorjetaModal').style.display = 'none';
            document.getElementById('gorjetaForm').reset();
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('gorjetaModal');
            if (event.target === modal) {
                closeGorjetaModal();
            }
        }

        // Registrar gorjeta
        document.getElementById('gorjetaForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A enviar...';
            
            try {
                const res = await fetch('../api/gorjetas/add_gorjeta_employee.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showSuccess('Gorjeta registrada com sucesso!');
                    closeGorjetaModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(data.message || 'Erro ao registar gorjeta');
                }
            } catch(e) {
                console.error(e);
                showError('Erro de comunicação com o servidor');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Registrar';
            }
        });

// ── Navegação por mês no histórico ────────────────────────────────────
let _mesCurrent = new Date().toISOString().substring(0, 7); // YYYY-MM
const _mesMin   = '2024-01';

function _mesLabel(mesStr) {
    const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    const [y, m] = mesStr.split('-');
    return `${meses[parseInt(m, 10) - 1]} ${y}`;
}

function _addMes(mesStr, delta) {
    const [y, m] = mesStr.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

// ── Paginação da tabela de presenças ──────────────────────────────────
const _PG_SIZE = 7;
let   _pgCurPage = 1;

function _pgGetTbody() {
    const tw = document.getElementById('attendance-table-wrap');
    const al = document.getElementById('history-ajax-list');
    if (tw && tw.style.display !== 'none') return tw.querySelector('tbody');
    if (al && al.style.display !== 'none') return al.querySelector('tbody');
    return null;
}

function _pgRender() {
    const tbody = _pgGetTbody();
    const bar   = document.getElementById('presence-pagination');
    if (!bar) return;

    if (!tbody) { bar.innerHTML = ''; return; }

    // Linhas que passam o filtro (display não forçado a 'none' pelo filtro)
    const all    = Array.from(tbody.querySelectorAll('tr.presence-row'));
    const visible = all.filter(r => r.style.display !== 'none');
    const total  = visible.length;
    const pages  = Math.max(1, Math.ceil(total / _PG_SIZE));

    if (_pgCurPage > pages) _pgCurPage = pages;

    const start = (_pgCurPage - 1) * _PG_SIZE;
    const end   = start + _PG_SIZE;

    // Aplicar/remover pg-hidden nas linhas que passam o filtro
    visible.forEach((r, i) => {
        r.classList.toggle('pg-hidden', i < start || i >= end);
    });

    // Construir barra de paginação
    if (pages <= 1) { bar.innerHTML = ''; return; }

    const btnPrev = `<button class="pg-btn" onclick="_pgGo(${_pgCurPage - 1})" ${_pgCurPage <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;
    const btnNext = `<button class="pg-btn" onclick="_pgGo(${_pgCurPage + 1})" ${_pgCurPage >= pages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;

    let nums = '';
    for (let p = 1; p <= pages; p++) {
        const gap = Math.abs(p - _pgCurPage);
        if (p !== 1 && p !== pages && gap > 1) {
            if (p === 2 || p === pages - 1) nums += '<span class="pg-ellipsis">…</span>';
            continue;
        }
        nums += `<button class="pg-btn ${p === _pgCurPage ? 'pg-active' : ''}" onclick="_pgGo(${p})">${p}</button>`;
    }

    const from = Math.min(start + 1, total);
    const to   = Math.min(end, total);
    bar.innerHTML = `<div class="pg-bar">${btnPrev}${nums}${btnNext}<span class="pg-info">${from}–${to} de ${total}</span></div>`;
}

function _pgGo(page) {
    _pgCurPage = page;
    _pgRender();
    // Scroll suave para o topo da tabela
    document.getElementById('attendanceHistoryPanel')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function _pgReset() { _pgCurPage = 1; _pgRender(); }

function _initMonthNav() {
    const btnAnterior  = document.getElementById('btn-mes-anterior');
    const btnSeguinte  = document.getElementById('btn-mes-seguinte');
    if (!btnAnterior || !btnSeguinte) return;

    btnAnterior.addEventListener('click', () => {
        const prev = _addMes(_mesCurrent, -1);
        if (prev < _mesMin) return;
        _mesCurrent = prev;
        _carregarMes(_mesCurrent);
    });

    btnSeguinte.addEventListener('click', () => {
        const next = _addMes(_mesCurrent, 1);
        const hoje = new Date().toISOString().substring(0, 7);
        if (next > hoje) return;
        _mesCurrent = next;
        _carregarMes(_mesCurrent);
    });
}

async function _carregarMes(mes) {
    const btnAnterior  = document.getElementById('btn-mes-anterior');
    const btnSeguinte  = document.getElementById('btn-mes-seguinte');
    const labelEl      = document.getElementById('history-mes-label');
    const hoje         = new Date().toISOString().substring(0, 7);
    const exportBtn    = document.querySelector('.btn-export');

    if (labelEl)     labelEl.textContent = _mesLabel(mes);
    if (btnSeguinte) btnSeguinte.disabled = mes >= hoje;
    if (btnAnterior) btnAnterior.disabled = mes <= _mesMin;
    if (exportBtn)   exportBtn.href = `exportar_historico.php?mes=${mes}`;

    // Abrir painel se fechado
    const panel = document.getElementById('attendanceHistoryPanel');
    const filters = document.getElementById('history-filters');
    if (panel && !panel.classList.contains('open')) {
        panel.classList.add('open');
        if (filters) filters.style.display = '';
        const toggleBtn = document.querySelector('.btn-history-toggle');
        if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> Ocultar';
    }

    try {
        const res  = await fetch(`get_historico_ponto.php?mes=${mes}`, { credentials: 'same-origin', cache: 'no-store' });
        const data = await res.json();
        if (!data.success) { showError(data.message || 'Erro ao carregar histórico'); return; }
        _renderHistoricoAjax(data.registros);
        // Keep calendar data in sync so toggling calendar later shows correct month
        window._attendanceData = _normalizeRegistrosForCalendar(data.registros, mes);
        window._attendanceMes  = mes;
        if (_calActive) _renderCalendar(window._attendanceData, mes);
    } catch (e) {
        showError('Erro de comunicação ao carregar histórico');
    }
}

function _renderHistoricoAjax(registros) {
    const tableWrap = document.getElementById('attendance-table-wrap');
    const ajaxList  = document.getElementById('history-ajax-list');
    if (!ajaxList) return;

    if (tableWrap) tableWrap.style.display = 'none';
    ajaxList.style.display = '';

    if (!registros || registros.length === 0) {
        ajaxList.innerHTML = '<div class="empty-state"><i class="fas fa-calendar-minus"></i><p>Sem registos para este período.</p></div>';
        _atualizarContadorFaltas(0);
        return;
    }

    let faltasCount = 0;

    const rows = registros.map(r => {
        const isFalta  = !r.entrada || r.entrada === '--:--';
        const isIncomp = r.entrada && r.entrada !== '--:--' && (!r.saida || r.saida === '--:--');
        const pStatus  = isFalta ? 'falta' : isIncomp ? 'incompleto' : 'presente';

        // Estado composto com justificativa (espelha lógica PHP)
        let displayStatus, badgeCls, badgeLbl;
        if (pStatus === 'presente') {
            displayStatus = 'presente';     badgeCls = 'badge-success'; badgeLbl = 'Presente';
        } else if (pStatus === 'incompleto') {
            displayStatus = 'incompleto';   badgeCls = 'badge-warning'; badgeLbl = 'Incompleto';
        } else if (r.just_status === 'aprovada') {
            displayStatus = 'just-aprovada';  badgeCls = 'badge-success'; badgeLbl = 'Aprovada';
        } else if (r.just_status === 'rejeitada') {
            displayStatus = 'just-rejeitada'; badgeCls = 'badge-danger';  badgeLbl = 'Rejeitada';
        } else if (r.just_status) {
            displayStatus = 'just-pendente';  badgeCls = 'badge-warning'; badgeLbl = 'Pendente';
        } else {
            displayStatus = 'falta';          badgeCls = 'badge-danger';  badgeLbl = 'Falta';
            faltasCount++;
        }

        const rowJson = escapeHTML(JSON.stringify({
            date: r.data_raw, date_fmt: r.data_fmt, entrada: r.entrada,
            saida: r.saida, horas: r.total, comp_lbl: r.comp, comp_cls: r.comp_class,
            obs: r.obs, confirm: r.status, periodos: r.periodos || [],
        }));

        const justJson = escapeHTML(JSON.stringify({
            data_fmt: r.data_fmt, tipo: r.just_tipo || '', motivo: r.just_motivo || '',
            status: r.just_status || '', obs: r.just_obs || '',
            doc: r.just_doc || '', enviado_em: r.just_at || '',
        }));

        let actionHtml;
        if (pStatus === 'presente' || pStatus === 'incompleto') {
            actionHtml = `<button class="btn-action btn-ver" data-row='${rowJson}' onclick="verDetalhePresenca(this)"><i class="fas fa-eye"></i> Ver</button>`;
        } else if (displayStatus === 'falta') {
            actionHtml = `<button class="btn-action btn-justificar" data-date="${escapeHTML(r.data_raw)}" data-fmt="${escapeHTML(r.data_fmt)}" onclick="justificarFalta(this)"><i class="fas fa-file-alt"></i> Justificar</button>`;
        } else if (displayStatus === 'just-rejeitada') {
            actionHtml = `<div style="display:flex;gap:.4rem;flex-wrap:wrap">
                <button class="btn-action btn-justificar" data-date="${escapeHTML(r.data_raw)}" data-fmt="${escapeHTML(r.data_fmt)}" onclick="justificarFalta(this)"><i class="fas fa-redo"></i> Re-enviar</button>
                <button class="btn-action btn-ver-just" data-just='${justJson}' onclick="verJustificacao(this)"><i class="fas fa-eye"></i> Ver</button>
            </div>`;
        } else if (displayStatus === 'just-pendente') {
            actionHtml = `<div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
                <span class="btn-action btn-just-sent"><i class="fas fa-clock"></i> Aguarda</span>
                <button class="btn-action btn-ver-just" data-just='${justJson}' onclick="verJustificacao(this)"><i class="fas fa-eye"></i> Ver</button>
            </div>`;
        } else {
            // just-aprovada
            actionHtml = `<div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
                <span class="btn-action btn-just-ok"><i class="fas fa-check-circle"></i> Aprovada</span>
                <button class="btn-action btn-ver-just" data-just='${justJson}' onclick="verJustificacao(this)"><i class="fas fa-eye"></i> Ver</button>
            </div>`;
        }

        const horasHtml = r.total
            ? `<span style="font-weight:700;color:var(--accent-500)">${escapeHTML(r.total)}</span>`
            : `<span style="color:var(--neutral-500)">—</span>`;

        return `
        <tr class="presence-row" data-status="${displayStatus}" data-date="${escapeHTML(r.data_raw)}">
            <td class="presence-date">
                <span class="presence-date-day">${escapeHTML(r.data_fmt)}</span>
            </td>
            <td><span class="status-badge ${badgeCls}">${badgeLbl}</span></td>
            <td class="presence-horas">${horasHtml}</td>
            <td class="presence-action">${actionHtml}</td>
        </tr>`;
    }).join('');

    ajaxList.innerHTML = `
        <table class="presence-table">
            <thead><tr><th>Data</th><th>Estado</th><th>Horas</th><th>Ação</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>`;
    const _activeBtn = document.querySelector('.period-btn.active');
    _filtrarTabelaPresencas(_activeBtn ? (_activeBtn.dataset.period || 'all') : 'all', null);
    _atualizarContadorFaltas(faltasCount);
}

function _atualizarContadorFaltas(count) {
    const el = document.getElementById('stat-faltas-value');
    if (!el) return;
    el.textContent = count;
    const pill = el.closest('.stat-pill');
    if (pill) pill.classList.toggle('stat-pill--alert', count > 0);
}

// ── Justificativas de Ausência ─────────────────────────────────────────
async function _submeterJustificativa(e) {
    e.preventDefault();
    const form      = e.currentTarget;
    const dataAus   = (form.querySelector('#just_data')?.value  || '').trim();
    const motivo    = (form.querySelector('#just_motivo')?.value || '').trim();
    const submitBtn = form.querySelector('button[type="submit"]');

    if (!dataAus || !motivo) { showWarning('Preencha todos os campos obrigatórios.'); return; }
    if (motivo.length < 5)   { showWarning('Motivo demasiado curto.'); return; }

    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A enviar…'; }

    try {
        const res  = await fetch('justificar_ausencia.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ data_ausencia: dataAus, motivo }),
        });
        const data = await res.json();
        if (!data.success) { showError(data.message || 'Erro ao enviar justificativa'); return; }

        showSuccess(data.message || 'Justificativa enviada com sucesso.');
        form.reset();

        // Adicionar ao topo da lista sem reload
        const list = document.querySelector('.justificativas-list') || _criarListaJustificativas();
        const item = document.createElement('div');
        item.className = 'message-item';
        item.innerHTML = `
            <div class="message-meta">
                <span class="status-badge badge-warning">Pendente</span>
                <small>${escapeHTML(data.item?.data_fmt || dataAus)}</small>
            </div>
            <p class="message-text">${escapeHTML(motivo)}</p>`;
        list.prepend(item);

        // Remover "sem justificativas" se existir
        document.querySelector('.justificativas-list + .empty-hint')?.remove();
    } catch (err) {
        showError('Erro de comunicação ao enviar justificativa');
    } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Justificativa'; }
    }
}

function _criarListaJustificativas() {
    const form = document.getElementById('justificativaForm');
    if (!form) return document.body;
    const div = document.createElement('div');
    div.className = 'justificativas-list';
    div.style.marginTop = '1rem';
    form.parentNode.insertBefore(div, form.nextSibling);
    return div;
}

// ── Modal "Ver" detalhe de presença ───────────────────────────────────
function verDetalhePresenca(btn) {
    const row = JSON.parse(btn.dataset.row || '{}');

    const compHtml = row.comp_lbl
        ? `<span class="comp-badge ${escapeHTML(row.comp_cls)}" style="font-size:.8rem">${escapeHTML(row.comp_lbl)}</span>`
        : '—';
    const confirmHtml = row.confirm === 'confirmado'
        ? '<span style="color:#059669;font-weight:700">Confirmado</span>'
        : row.confirm === 'invalidado'
            ? '<span style="color:#dc2626;font-weight:700">Invalidado</span>'
            : '<span style="color:#b45309">Pendente</span>';
    const obsHtml = row.obs
        ? `<div style="margin-top:.75rem;padding:.6rem;background:rgba(255,255,255,.05);border-radius:8px;font-size:.85rem;color:#94a3b8"><i class="fas fa-comment-alt" style="color:#3b82f6"></i> ${escapeHTML(row.obs)}</div>`
        : '';

    // Breakdown de períodos (quando há pausa/regresso)
    const periodos = Array.isArray(row.periodos) ? row.periodos : [];
    let periodosHtml = '';
    if (periodos.length > 1) {
        const periodoRows = periodos.map((p, i) => {
            const eMs = p.entrada ? new Date('1970-01-01T' + p.entrada + ':00').getTime() : 0;
            const sMs = p.saida   ? new Date('1970-01-01T' + p.saida   + ':00').getTime() : 0;
            const secs = (eMs && sMs && sMs > eMs) ? (sMs - eMs) / 1000 : 0;
            const dur  = secs > 0
                ? Math.floor(secs / 3600) + 'h' + String(Math.floor((secs % 3600) / 60)).padStart(2, '0') + 'm'
                : '—';
            const obsTag = p.obs
                ? ` <span style="color:#64748b;font-size:.75rem">(${escapeHTML(p.obs)})</span>`
                : '';
            return `<tr>
                <td style="padding:.3rem .4rem;color:#94a3b8;font-weight:700;width:1.2rem">${i + 1}</td>
                <td style="padding:.3rem .4rem">${escapeHTML(p.entrada || '--:--')} → ${escapeHTML(p.saida || '—')}${obsTag}</td>
                <td style="padding:.3rem .4rem;text-align:right;color:#10b981;font-weight:600">${dur}</td>
            </tr>`;
        }).join('');
        periodosHtml = `
            <div style="margin-top:.85rem;border-top:1px solid rgba(255,255,255,.1);padding-top:.75rem">
                <div style="font-size:.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem">Períodos</div>
                <table style="width:100%;border-collapse:collapse;font-size:.85rem">${periodoRows}</table>
            </div>`;
    }

    Swal.fire({
        title: `${escapeHTML(row.date_fmt)} — ${escapeHTML(row.weekday)}`,
        html: `
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;text-align:left">
                <tr><td style="padding:.45rem .5rem;color:#94a3b8;width:45%">1.ª Entrada</td><td style="font-weight:700">${escapeHTML(row.entrada || '--:--')}</td></tr>
                <tr><td style="padding:.45rem .5rem;color:#94a3b8">Última Saída</td><td style="font-weight:700">${escapeHTML(row.saida || '--:--')}</td></tr>
                <tr><td style="padding:.45rem .5rem;color:#94a3b8">Horas trabalhadas</td><td style="font-weight:700;color:#10b981">${escapeHTML(row.horas || '—')}</td></tr>
                <tr><td style="padding:.45rem .5rem;color:#94a3b8">Pontualidade</td><td>${compHtml}</td></tr>
                <tr><td style="padding:.45rem .5rem;color:#94a3b8">Confirmação</td><td>${confirmHtml}</td></tr>
            </table>${periodosHtml}${obsHtml}`,
        background: '#1e293b',
        color: '#e2e8f0',
        confirmButtonText: 'Fechar',
        confirmButtonColor: '#2563eb',
        width: 400,
    });
}

// ── Modal de Justificação ──────────────────────────────────────────────
let _justSourceBtn = null;

function openJustificativaModal(date, dateFmt, sourceBtn) {
    const modal    = document.getElementById('justificativaModal');
    const form     = document.getElementById('justificativaModalForm');
    const hiddenEl = document.getElementById('just_modal_data');
    const labelEl  = document.getElementById('just_modal_date_label');

    if (!modal) return;

    form.reset();
    _resetJustFile();
    _justSourceBtn = sourceBtn || null;

    if (hiddenEl) hiddenEl.value = date || '';
    if (labelEl)  labelEl.textContent = dateFmt ? `📅 ${dateFmt}` : '';

    modal.style.display = 'block';
    setTimeout(() => document.getElementById('just_modal_tipo')?.focus(), 100);
}

function closeJustificativaModal() {
    const modal = document.getElementById('justificativaModal');
    if (modal) modal.style.display = 'none';
    _justSourceBtn = null;
}

function onJustFileChange(input) {
    const file        = input.files[0];
    const placeholder = document.getElementById('just_file_placeholder');
    const preview     = document.getElementById('just_file_preview');
    const nameEl      = document.getElementById('just_file_name');
    if (!file) { _resetJustFile(); return; }

    const maxMB = 5;
    if (file.size > maxMB * 1024 * 1024) {
        showError(`Ficheiro demasiado grande (máximo ${maxMB} MB).`);
        input.value = '';
        return;
    }

    if (placeholder) placeholder.style.display = 'none';
    if (preview)     preview.style.display = 'flex';
    if (nameEl)      nameEl.textContent = file.name;
    document.getElementById('just_file_area')?.classList.add('file-upload-area--has-file');
}

function removeJustFile(e) {
    e.preventDefault();
    e.stopPropagation();
    const input = document.getElementById('just_modal_doc');
    if (input) input.value = '';
    _resetJustFile();
}

function _resetJustFile() {
    document.getElementById('just_file_placeholder')?.style.setProperty('display', 'flex');
    const preview = document.getElementById('just_file_preview');
    if (preview) preview.style.display = 'none';
    document.getElementById('just_file_area')?.classList.remove('file-upload-area--has-file');
}

// Botão "Justificar" na tabela — abre o modal
function justificarFalta(btn) {
    const date    = btn.dataset.date;
    const dateFmt = btn.dataset.fmt;
    openJustificativaModal(date, dateFmt, btn);
}

// Botão "Ver" na tabela — mostra detalhe da justificação enviada
function verJustificacao(btn) {
    const just = JSON.parse(btn.dataset.just || '{}');
    const tiposMap = {
        doenca:'Doença', consulta_medica:'Consulta Médica',
        assistencia_familiar:'Assistência a Familiar', falecimento_familiar:'Falecimento de Familiar',
        casamento:'Casamento', maternidade_paternidade:'Maternidade / Paternidade',
        formacao_profissional:'Formação Profissional', convocacao_judicial:'Convocação Judicial',
        acidente:'Acidente', transporte:'Problema de Transporte',
        motivo_pessoal:'Motivo Pessoal', outro:'Outro',
    };
    const statusStyles = {
        pendente:  'background:#fef3c7;color:#92400e',
        aprovada:  'background:#d1fae5;color:#065f46',
        rejeitada: 'background:#fee2e2;color:#991b1b',
    };
    const statusLabels = { pendente:'Pendente', aprovada:'Aprovada', rejeitada:'Rejeitada' };
    const statusSty = statusStyles[just.status] || '';
    const statusLbl = statusLabels[just.status] || just.status || '';
    const tipoLabel = tiposMap[just.tipo] || just.tipo || '—';
    const docHtml   = just.doc
        ? `<a href="uploads/justificativas/${escapeHTML(just.doc)}" target="_blank" style="color:#60a5fa;text-decoration:none"><i class="fas fa-paperclip"></i> Ver documento</a>`
        : '<span style="color:#64748b">Sem documento</span>';
    const adminObsHtml = just.obs
        ? `<tr><td style="padding:.4rem 0;color:#94a3b8;vertical-align:top">Nota admin</td><td style="color:#fca5a5">${escapeHTML(just.obs)}</td></tr>`
        : '';
    const enviadoHtml = just.enviado_em
        ? `<tr><td style="padding:.4rem 0;color:#94a3b8">Enviado em</td><td>${escapeHTML(just.enviado_em)}</td></tr>`
        : '';

    Swal.fire({
        title: `Justificação — ${escapeHTML(just.data_fmt || '')}`,
        html: `
            <div style="text-align:left;font-size:.9rem;line-height:1.6">
                <div style="margin-bottom:1rem">
                    <span style="${statusSty};padding:3px 12px;border-radius:99px;font-weight:700;font-size:.8rem">${escapeHTML(statusLbl)}</span>
                </div>
                <table style="width:100%;border-collapse:collapse">
                    <tr><td style="padding:.4rem 0;color:#94a3b8;width:38%;vertical-align:top">Tipo</td><td style="font-weight:600">${escapeHTML(tipoLabel)}</td></tr>
                    <tr><td style="padding:.4rem 0;color:#94a3b8;vertical-align:top">Motivo</td><td>${escapeHTML(just.motivo || '—')}</td></tr>
                    <tr><td style="padding:.4rem 0;color:#94a3b8">Documento</td><td>${docHtml}</td></tr>
                    ${adminObsHtml}
                    ${enviadoHtml}
                </table>
            </div>`,
        background: '#1e293b',
        color: '#e2e8f0',
        confirmButtonText: 'Fechar',
        confirmButtonColor: '#3b82f6',
        showCancelButton: false,
        width: 440,
    });
}

// Submit do modal
async function _submitJustificativaModal(e) {
    e.preventDefault();
    const form     = e.currentTarget;
    const submitEl = document.getElementById('just_submit_btn');

    const date   = (document.getElementById('just_modal_data')?.value    || '').trim();
    const tipo   = (document.getElementById('just_modal_tipo')?.value    || '').trim();
    const motivo = (document.getElementById('just_modal_motivo')?.value  || '').trim();

    if (!date)             { showWarning('Data da ausência em falta.');                return; }
    if (!tipo)             { showWarning('Seleccione o tipo de justificação.');         return; }
    if (motivo.length < 5) { showWarning('Motivo demasiado curto (mínimo 5 chars).'); return; }

    if (submitEl) { submitEl.disabled = true; submitEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A enviar…'; }

    try {
        const fd = new FormData(form);
        // Garantir que data_ausencia tem o valor correcto
        fd.set('data_ausencia', date);

        const res  = await fetch('justificar_ausencia.php', { method: 'POST', credentials: 'same-origin', body: fd });
        const data = await res.json();

        if (!data.success) { showError(data.message || 'Erro ao enviar justificação'); return; }

        showSuccess('Justificação enviada. Aguarda aprovação do administrador.');
        closeJustificativaModal();

        // Actualizar botão da tabela
        if (_justSourceBtn) {
            _justSourceBtn.disabled = true;
            _justSourceBtn.className = 'btn-action btn-just-sent';
            _justSourceBtn.innerHTML = '<i class="fas fa-check"></i> Enviada';
        }

        // Adicionar item à lista
        const list = document.getElementById('justificativas-list');
        if (list) {
            const tiposMap = {
                doenca:'Doença',consulta_medica:'Consulta Médica',
                assistencia_familiar:'Assistência a Familiar',falecimento_familiar:'Falecimento de Familiar',
                casamento:'Casamento',maternidade_paternidade:'Maternidade / Paternidade',
                formacao_profissional:'Formação Profissional',convocacao_judicial:'Convocação Judicial',
                acidente:'Acidente',transporte:'Problema de Transporte',
                motivo_pessoal:'Motivo Pessoal',outro:'Outro',
            };
            const tipoLabel = tiposMap[tipo] || tipo;
            const docHtml = data.item?.documento
                ? `<a href="uploads/justificativas/${escapeHTML(data.item.documento)}" target="_blank" class="just-doc-link" title="Ver documento"><i class="fas fa-paperclip"></i></a>`
                : '';

            const item = document.createElement('div');
            item.className = 'just-item';
            item.innerHTML = `
                <div class="just-item-header">
                    <div class="just-item-meta">
                        <span class="status-badge badge-warning">Pendente</span>
                        <strong>${escapeHTML(data.item?.data_fmt || date)}</strong>
                        <span class="just-tipo-tag">${escapeHTML(tipoLabel)}</span>
                    </div>${docHtml}
                </div>
                <p class="just-item-motivo">${escapeHTML(motivo)}</p>`;
            list.prepend(item);
            document.getElementById('just-empty-hint')?.remove();
        }
    } catch (err) {
        showError('Erro de comunicação ao enviar justificativa');
    } finally {
        if (submitEl) { submitEl.disabled = false; submitEl.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Justificação'; }
    }
}

// ── Filtros da tabela de presenças ────────────────────────────────────
function _filtrarTabelaPresencas(periodo, dataEspecifica) {
    const rows = document.querySelectorAll('.presence-row');
    let visiveis = 0;
    rows.forEach(row => {
        const status  = row.dataset.status || '';
        const dateStr = row.dataset.date   || '';
        let mostrar = true;

        if (dataEspecifica) {
            mostrar = dateStr === dataEspecifica;
        } else if (periodo === 'present') {
            mostrar = status === 'presente';
        } else if (periodo === 'incomplete') {
            mostrar = status === 'incompleto';
        } else if (periodo === 'falta') {
            // faltas: sem justificativa + com justificativa (pendente/aprovada/rejeitada)
            mostrar = ['falta','just-pendente','just-aprovada','just-rejeitada'].includes(status);
        }

        // Repor pg-hidden ao filtrar (paginação vai re-aplicar a seguir)
        row.classList.remove('pg-hidden');
        row.style.display = mostrar ? '' : 'none';
        if (mostrar) visiveis++;
    });

    let emptyEl = document.getElementById('table-empty-filter');
    if (visiveis === 0 && rows.length > 0) {
        if (!emptyEl) {
            emptyEl = document.createElement('tr');
            emptyEl.id = 'table-empty-filter';
            emptyEl.innerHTML = `<td colspan="4" style="text-align:center;padding:1.5rem;color:#64748b">Nenhum registo para o filtro seleccionado.</td>`;
            document.querySelector('#presence-table tbody')?.appendChild(emptyEl);
        }
        emptyEl.style.display = '';
    } else if (emptyEl) {
        emptyEl.style.display = 'none';
    }

    // Repor à página 1 com o novo filtro
    _pgReset();
}

// ── Definições ───────────────────────────────────────────────────────

function _initDefinicoes() {
    // Avatar upload
    const avatarInput = document.getElementById('defAvatarInput');
    if (avatarInput) {
        avatarInput.addEventListener('change', async () => {
            if (!avatarInput.files || !avatarInput.files[0]) return;
            const file = avatarInput.files[0];
            if (file.size > 2 * 1024 * 1024) {
                showError('Ficheiro demasiado grande (máx. 2 MB).');
                avatarInput.value = '';
                return;
            }
            const fd = new FormData();
            fd.append('avatar', file);
            try {
                const res = await fetch('update_profile.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showSuccess('Foto actualizada.');
                    if (data.avatar_url) {
                        const cacheBust = '?v=' + Date.now();

                        // Atualizar avatar nas Definições
                        const img = document.getElementById('defAvatarImg');
                        const ini = document.getElementById('defAvatarInitials');
                        if (img) {
                            img.src = data.avatar_url + cacheBust;
                        } else if (ini) {
                            const newImg = document.createElement('img');
                            newImg.id = 'defAvatarImg';
                            newImg.alt = 'Avatar';
                            newImg.className = 'def-avatar-img';
                            newImg.src = data.avatar_url + cacheBust;
                            ini.replaceWith(newImg);
                        }

                        // Atualizar avatar da nav bar
                        const navAvatar = document.getElementById('navAvatar');
                        if (navAvatar) {
                            const navSrc = '../' + data.avatar_url + cacheBust;
                            const navImg = navAvatar.querySelector('img');
                            if (navImg) {
                                navImg.src = navSrc;
                            } else {
                                navAvatar.textContent = '';
                                const newNavImg = document.createElement('img');
                                newNavImg.alt = 'Avatar';
                                newNavImg.src = navSrc;
                                newNavImg.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;';
                                navAvatar.appendChild(newNavImg);
                            }
                        }
                    }
                } else {
                    showError(data.message || 'Erro ao actualizar foto.');
                }
            } catch (e) {
                showError('Erro de comunicação.');
            } finally {
                avatarInput.value = '';
            }
        });
    }

    // Phone edit toggle
    const phoneEditBtn   = document.getElementById('defPhoneEditBtn');
    const phoneCancelBtn = document.getElementById('defPhoneCancelBtn');
    const phoneSaveBtn   = document.getElementById('defPhoneSaveBtn');
    const phoneForm      = document.getElementById('defPhoneForm');
    const phoneDisplay   = document.getElementById('defPhoneDisplay');
    const phoneInput     = document.getElementById('defPhoneInput');

    if (phoneEditBtn && phoneForm) {
        phoneEditBtn.addEventListener('click', () => {
            phoneForm.style.display = '';
            phoneEditBtn.style.display = 'none';
            phoneInput?.focus();
        });
    }
    if (phoneCancelBtn && phoneForm) {
        phoneCancelBtn.addEventListener('click', () => {
            phoneForm.style.display = 'none';
            if (phoneEditBtn) phoneEditBtn.style.display = '';
        });
    }
    if (phoneSaveBtn && phoneInput) {
        phoneSaveBtn.addEventListener('click', async () => {
            const val = phoneInput.value.trim();
            phoneSaveBtn.disabled = true;
            try {
                const res = await fetch('update_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'phone=' + encodeURIComponent(val)
                });
                const data = await res.json();
                if (data.success) {
                    showSuccess('Telefone actualizado.');
                    if (phoneDisplay) {
                        phoneDisplay.textContent = val || '';
                        if (!val) phoneDisplay.innerHTML = '<em style="opacity:.5">Não definido</em>';
                    }
                    phoneForm.style.display = 'none';
                    if (phoneEditBtn) phoneEditBtn.style.display = '';
                } else {
                    showError(data.message || 'Erro ao actualizar telefone.');
                }
            } catch (e) {
                showError('Erro de comunicação.');
            } finally {
                phoneSaveBtn.disabled = false;
            }
        });
    }

    // PIN change form
    const pinForm = document.getElementById('defPinForm');
    if (pinForm) {
        pinForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const currentPin = document.getElementById('defPinCurrent')?.value.trim() || '';
            const newPin     = document.getElementById('defPinNew')?.value.trim() || '';
            const confirmPin = document.getElementById('defPinConfirm')?.value.trim() || '';

            if (!currentPin || !newPin || !confirmPin) {
                showWarning('Preencha todos os campos.');
                return;
            }
            if (newPin.length < 4) {
                showWarning('O novo PIN deve ter pelo menos 4 caracteres.');
                return;
            }
            if (newPin !== confirmPin) {
                showWarning('Os PINs não coincidem.');
                return;
            }

            const submitBtn = pinForm.querySelector('[type=submit]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A guardar...'; }

            try {
                const res = await fetch('change_pin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ current_pin: currentPin, new_pin: newPin, confirm_pin: confirmPin })
                });
                const data = await res.json();
                if (data.success) {
                    showSuccess(data.message || 'PIN alterado com sucesso.');
                    pinForm.reset();
                } else {
                    showError(data.message || 'Erro ao alterar PIN.');
                }
            } catch (err) {
                showError('Erro de comunicação.');
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar PIN'; }
            }
        });
    }
}

// ── Gorjetas: cancelar + polling ─────────────────────────────────────

function _registerGorjetaCancel(container) {
    container.querySelectorAll('.btn-cancel-gorjeta').forEach(btn => {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => {
            const gid = parseInt(String(btn.dataset.id || '0'), 10);
            if (gid > 0) cancelGorjeta(gid, btn);
        });
    });
}

async function cancelGorjeta(gorjetaId, triggerEl) {
    const ok = await Swal.fire({
        title: 'Cancelar gorjeta?',
        text: 'O registo será removido do histórico pendente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Voltar'
    });
    if (!ok.isConfirmed) return;

    if (triggerEl) { triggerEl.disabled = true; triggerEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
    try {
        const res  = await fetch('cancelar_gorjeta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ gorjeta_id: gorjetaId })
        });
        const data = await res.json();
        if (data.success) {
            showSuccess('Gorjeta cancelada.');
            // Remove item from DOM
            const item = document.querySelector(`.gorjeta-item[data-id="${gorjetaId}"]`);
            if (item) item.remove();
            // Refresh via polling immediately
            _gorjetasLastHash = null;
            await _pollGorjetas();
        } else {
            showError(data.message || 'Erro ao cancelar gorjeta.');
            if (triggerEl) { triggerEl.disabled = false; triggerEl.innerHTML = '<i class="fas fa-times"></i> Cancelar'; }
        }
    } catch (e) {
        showError('Erro de comunicação.');
        if (triggerEl) { triggerEl.disabled = false; triggerEl.innerHTML = '<i class="fas fa-times"></i> Cancelar'; }
    }
}

let _gorjetasLastHash = null;

async function _pollGorjetas() {
    const mes = document.getElementById('gorjeta-mes-select')?.value || new Date().toISOString().slice(0, 7);
    try {
        const res  = await fetch(`get_gorjetas_employee.php?mes=${mes}&status=all&offset=0`, { credentials: 'same-origin', cache: 'no-store' });
        const data = await res.json();
        if (!data.success) return;

        if (data.hash === _gorjetasLastHash) return;
        _gorjetasLastHash = data.hash;

        // Update KPI gorjetas no dashboard
        const totalValor = parseFloat(data.totalValor || 0);
        const kpiVal = document.querySelector('.kpi-card.kpi-green .kpi-value');
        if (kpiVal) kpiVal.textContent = totalValor.toFixed(2).replace('.', ',') + '€';
        const kpiSub = document.querySelector('.kpi-card.kpi-green .kpi-sub');
        if (kpiSub) {
            const n = (data.gorjetas || []).length;
            kpiSub.textContent = `${n} ${n === 1 ? 'registo' : 'registos'}`;
        }

        // Re-render lista completa
        if (typeof window._loadGorjetas === 'function') {
            window._loadGorjetas(true);
        }
    } catch (e) { /* silent */ }
}

// ── Recibos de Vencimento ─────────────────────────────────────────────

let _recibosLoaded = false;

async function _loadRecibos() {
    if (_recibosLoaded) return;
    const list = document.getElementById('recibos-list');
    if (!list) return;
    list.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>A carregar…</p></div>';
    try {
        const res  = await fetch('get_recibos_employee.php', { credentials: 'same-origin', cache: 'no-store' });
        const data = await res.json();
        if (!data.success) { list.innerHTML = '<div class="empty-state"><p>Erro ao carregar recibos.</p></div>'; return; }
        if (!data.recibos || data.recibos.length === 0) {
            list.innerHTML = '<div class="empty-state"><i class="fas fa-file-invoice"></i><p>Sem recibos disponíveis.</p></div>';
            _recibosLoaded = true;
            return;
        }
        list.innerHTML = data.recibos.map(_buildReciboItemHtml).join('');
        _recibosLoaded = true;
    } catch (e) {
        list.innerHTML = '<div class="empty-state"><p>Erro de comunicação.</p></div>';
    }
}

function _buildReciboItemHtml(r) {
    const statusMap = { pago:'Pago', pendente:'Pendente', processado:'Processado', gerado:'Gerado' };
    const statusCls = r.status === 'pago' || r.status === 'processado' ? 'badge-success' : 'badge-warning';
    const statusLbl = statusMap[r.status] || (r.status ? r.status.charAt(0).toUpperCase() + r.status.slice(1) : 'Pendente');
    const liquido   = parseFloat(r.salario_liquido || 0).toFixed(2).replace('.', ',');
    return `<div class="recibo-item" onclick="openReciboModal('${escapeHTML(r.periodo_key)}')">
        <div class="recibo-item-left">
            <i class="fas fa-file-invoice-dollar recibo-item-icon"></i>
            <div>
                <div class="recibo-item-periodo">${escapeHTML(r.periodo_label)}</div>
                <div class="recibo-item-valor">${liquido} €</div>
            </div>
        </div>
        <span class="status-badge ${statusCls}">${statusLbl}</span>
    </div>`;
}

async function openReciboModal(periodoKey) {
    const modal = document.getElementById('reciboModal');
    const body  = document.getElementById('reciboModalBody');
    const titulo = document.getElementById('reciboModalPeriodo');
    const statusBadge = document.getElementById('reciboModalStatus');
    if (!modal || !body) return;

    body.innerHTML = '<div style="text-align:center;padding:2rem"><i class="fas fa-spinner fa-spin fa-2x" style="color:#3b82f6"></i></div>';
    modal.style.display = 'flex';

    try {
        const res  = await fetch(`get_recibos_employee.php?detail=${encodeURIComponent(periodoKey)}`, { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success || !data.recibo) { body.innerHTML = '<p style="padding:1rem;color:#ef4444">Recibo não encontrado.</p>'; return; }
        const r = data.recibo;

        if (titulo) titulo.textContent = r.periodo_label;
        if (statusBadge) {
            const sCls = r.status === 'pago' || r.status === 'processado' ? 'badge-success' : 'badge-warning';
            statusBadge.className = 'status-badge ' + sCls;
            statusBadge.textContent = r.status ? (r.status.charAt(0).toUpperCase() + r.status.slice(1)) : 'Pendente';
        }

        const fmt = v => parseFloat(v || 0).toFixed(2).replace('.', ',') + ' €';
        const row = (lbl, val, bold) => `<tr${bold ? ' class="recibo-row-total"' : ''}>
            <td class="recibo-td-lbl">${lbl}</td>
            <td class="recibo-td-val">${val}</td></tr>`;

        let remuneracoes = row('Salário Base', fmt(r.salario_base));
        if (parseFloat(r.subsidio_alimentacao || 0) > 0) remuneracoes += row('Subsídio de Alimentação', fmt(r.subsidio_alimentacao));
        if (parseFloat(r.horas_extra || 0) > 0)          remuneracoes += row('Horas Extra', fmt(r.horas_extra));
        if (parseFloat(r.bonus || 0) > 0)                remuneracoes += row('Bónus', fmt(r.bonus));
        if (parseFloat(r.subsidios_extra || 0) > 0)      remuneracoes += row('Subsídios Extra', fmt(r.subsidios_extra));
        if (parseFloat(r.gorjetas || 0) > 0)             remuneracoes += row('Gorjetas', fmt(r.gorjetas));
        remuneracoes += row('Salário Bruto', fmt(r.salario_bruto), true);

        let descontos = '';
        if (parseFloat(r.faltas_dias || 0) > 0) descontos += row(`Faltas (${r.faltas_dias} dias)`, '—');
        if (parseFloat(r.outros_descontos || 0) > 0) descontos += row('Outros Descontos', fmt(r.outros_descontos));

        body.innerHTML = `
            <div class="recibo-section-title"><i class="fas fa-plus-circle" style="color:#22c55e"></i> Remunerações</div>
            <table class="recibo-table">${remuneracoes}</table>
            ${descontos ? `<div class="recibo-section-title"><i class="fas fa-minus-circle" style="color:#ef4444"></i> Descontos</div><table class="recibo-table">${descontos}</table>` : ''}
            <div class="recibo-liquido-box">
                <div><div class="recibo-liquido-label">Salário Líquido</div><div class="recibo-liquido-sub">a receber pelo trabalhador</div></div>
                <div class="recibo-liquido-valor">${fmt(r.salario_liquido)}</div>
            </div>
            ${r.data_pagamento ? `<div style="font-size:.8rem;color:var(--neutral-500);margin-top:.75rem"><i class="fas fa-calendar-check"></i> Pago em ${new Date(r.data_pagamento).toLocaleDateString('pt-PT')}</div>` : ''}`;
    } catch (e) {
        body.innerHTML = '<p style="padding:1rem;color:#ef4444">Erro ao carregar detalhe.</p>';
    }
}

function closeReciboModal() {
    const modal = document.getElementById('reciboModal');
    if (modal) modal.style.display = 'none';
}

function imprimirRecibo() {
    const body = document.getElementById('reciboModalBody');
    const titulo = document.getElementById('reciboModalPeriodo')?.textContent || '';
    if (!body) return;
    const w = window.open('', '_blank', 'width=750,height=900');
    w.document.write(`<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>Recibo — ${titulo}</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:Arial,sans-serif; font-size:12px; color:#1e293b; padding:24px; }
        h1 { font-size:18px; color:#2563eb; margin-bottom:4px; }
        .sub { font-size:11px; color:#64748b; margin-bottom:16px; }
        table { width:100%; border-collapse:collapse; margin-bottom:14px; }
        td { padding:5px 8px; border-bottom:1px solid #e2e8f0; }
        td:last-child { text-align:right; font-weight:500; }
        .recibo-row-total td { font-weight:700; border-top:2px solid #334155; }
        .sec { font-size:10px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#64748b; margin:12px 0 6px; border-bottom:1px solid #e2e8f0; padding-bottom:3px; }
        .liquido { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; margin-top:12px; }
        .liquido-val { font-size:1.5rem; font-weight:800; color:#16a34a; }
        @media print { body{padding:10px} @page{margin:1cm} }
    </style></head><body>
    <h1>Recibo de Vencimento</h1><p class="sub">${titulo}</p>
    ${body.innerHTML}
    <script>window.onload=()=>window.print()<\/script>
    </body></html>`);
    w.document.close();
}

// Close recibo modal on backdrop click
document.addEventListener('click', e => {
    const modal = document.getElementById('reciboModal');
    if (modal && e.target === modal) closeReciboModal();
});

// ── Vista Calendário de Presenças ─────────────────────────────────────────

var _calActive = false;

function _toggleCalView() {
    _calActive = !_calActive;
    const calWrap   = document.getElementById('attendance-calendar-wrap');
    const tableWrap = document.getElementById('attendance-table-wrap');
    const ajaxList  = document.getElementById('history-ajax-list');
    const btn       = document.getElementById('btn-cal-toggle');
    const panel     = document.getElementById('attendanceHistoryPanel');
    const filters   = document.getElementById('history-filters');

    if (_calActive) {
        // Ensure panel is open
        if (panel && !panel.classList.contains('open')) {
            panel.classList.add('open');
            if (filters) filters.style.display = '';
            const toggleBtn = document.querySelector('.btn-history-toggle');
            if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> Ocultar';
        }
        if (tableWrap) tableWrap.style.display = 'none';
        if (ajaxList)  ajaxList.style.display  = 'none';
        if (calWrap)   calWrap.style.display    = '';
        if (btn) btn.innerHTML = '<i class="fas fa-table"></i> Tabela';

        // _attendanceData is already calendar-ready (PHP format on load; normalized AJAX after month nav)
        _renderCalendar(window._attendanceData || [], window._attendanceMes || _mesCurrent);
    } else {
        if (calWrap) calWrap.style.display = 'none';
        // Restore the correct sub-view
        const isAjax = ajaxList && ajaxList.innerHTML.trim() !== '';
        if (isAjax) {
            if (ajaxList)  ajaxList.style.display  = '';
        } else {
            if (tableWrap) tableWrap.style.display = '';
        }
        if (btn) btn.innerHTML = '<i class="fas fa-calendar-alt"></i> Calendário';
    }
}

function _normalizeRegistrosForCalendar(registros, mes) {
    // Convert AJAX registros (only days with records) to the attendanceGrid format.
    // Returns an array of {date, status, comp_cls, comp_lbl, horas, just_status} per day.
    const byDate = {};
    (registros || []).forEach(r => {
        const isFalta  = !r.entrada || r.entrada === '--:--';
        const isIncomp = r.entrada && r.entrada !== '--:--' && (!r.saida || r.saida === '--:--');
        byDate[r.data_raw] = {
            date:        r.data_raw,
            status:      isFalta ? 'falta' : isIncomp ? 'incompleto' : 'presente',
            horas:       r.total  || '',
            comp_lbl:    r.comp   || '',
            comp_cls:    r.comp_class || '',
            just_status: r.just_status || '',
        };
    });

    // Build full-month day list (workdays up to today)
    const [year, month] = mes.split('-').map(Number);
    const today    = new Date().toISOString().substring(0, 10);
    const lastDay  = new Date(year, month, 0).getDate();
    const result   = [];
    for (let d = 1; d <= lastDay; d++) {
        const ds  = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const dow = new Date(ds).getDay(); // 0=Sun,6=Sat
        if (dow === 0 || dow === 6) continue; // skip weekends (not in grid)
        if (ds > today) continue;            // skip future
        result.push(byDate[ds] || { date: ds, status: 'falta', horas: '', comp_lbl: '', comp_cls: '', just_status: '' });
    }
    return result;
}

function _renderCalendar(days, mes) {
    const wrap = document.getElementById('attendance-calendar-wrap');
    if (!wrap) return;

    const [year, month] = mes.split('-').map(Number);
    const firstDay = new Date(year, month - 1, 1);
    const lastDay  = new Date(year, month, 0).getDate();
    const today    = new Date().toISOString().substring(0, 10);

    const byDate = {};
    days.forEach(d => { byDate[d.date] = d; });

    // Mon=0..Sun=6 offset
    let startDow = firstDay.getDay(); // 0=Sun
    startDow = startDow === 0 ? 6 : startDow - 1;

    const diasSem = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

    let html = '<div class="cal-grid">';
    diasSem.forEach(d => { html += `<div class="cal-header">${d}</div>`; });
    for (let i = 0; i < startDow; i++) html += '<div class="cal-day cal-day--empty"></div>';

    for (let d = 1; d <= lastDay; d++) {
        const ds  = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const dow = new Date(ds).getDay(); // 0=Sun,6=Sat
        const isWeekend = dow === 0 || dow === 6;
        const isFuture  = ds > today;
        const dayData   = byDate[ds];

        let cls     = 'cal-day';
        let tooltip = '';
        let inner   = `<span class="cal-day-num">${d}</span>`;

        if (isWeekend) {
            cls += ' cal-day--weekend';
        } else if (isFuture) {
            cls += ' cal-day--future';
        } else if (dayData) {
            const s  = dayData.status || 'falta';
            const js = dayData.just_status || '';
            if (s === 'presente') {
                if (dayData.comp_cls === 'comp-badge--late') {
                    cls     += ' cal-day--late';
                    tooltip  = dayData.comp_lbl || 'Atraso';
                } else {
                    cls     += ' cal-day--present';
                    tooltip  = dayData.horas || 'Presente';
                }
            } else if (s === 'incompleto') {
                cls     += ' cal-day--incomplete';
                tooltip  = 'Incompleto';
            } else if (js === 'aprovada') {
                cls     += ' cal-day--justified';
                tooltip  = 'Justificada';
            } else if (js === 'pendente') {
                cls     += ' cal-day--just-pending';
                tooltip  = 'Just. pendente';
            } else if (js === 'rejeitada') {
                cls     += ' cal-day--absent';
                tooltip  = 'Falta (rejeitada)';
            } else {
                cls     += ' cal-day--absent';
                tooltip  = 'Falta';
            }
            if (dayData.horas) inner += `<span class="cal-day-hrs">${escapeHTML(dayData.horas)}</span>`;
        } else {
            // Workday, past, no data = falta
            cls     += ' cal-day--absent';
            tooltip  = 'Falta';
        }

        html += `<div class="${cls}" title="${tooltip}">${inner}</div>`;
    }

    html += '</div>';
    html += `<div class="cal-legend">
        <span class="cal-legend-item"><span class="cal-dot cal-dot--present"></span>Presente</span>
        <span class="cal-legend-item"><span class="cal-dot cal-dot--late"></span>Atraso</span>
        <span class="cal-legend-item"><span class="cal-dot cal-dot--absent"></span>Falta</span>
        <span class="cal-legend-item"><span class="cal-dot cal-dot--incomplete"></span>Incompleto</span>
        <span class="cal-legend-item"><span class="cal-dot cal-dot--justified"></span>Justificada</span>
        <span class="cal-legend-item"><span class="cal-dot cal-dot--just-pending"></span>Just. pendente</span>
    </div>`;

    wrap.innerHTML = html;
}
