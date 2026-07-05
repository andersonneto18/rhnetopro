// ========== MODAL DE EDIÇÃO DE PRESENÇA ==========
// ========== EXPORTAÇÃO E LIMPEZA DO HISTÓRICO DE SOLICITAÇÕES DECIDIDAS ========== 
document.addEventListener('DOMContentLoaded', function() {

            // Padroniza ícones nos cabeçalhos de todas as tabelas (todas as seções).
            // Evita duplicar quando o <th> já possui um <i> definido no HTML.
            const headerIconMap = [
                { match: ['nome', 'funcionario', 'funcionário', 'colaborador', 'empregado'], icon: 'fa-user' },
                { match: ['cargo', 'funcao', 'função', 'posição', 'posicao'], icon: 'fa-briefcase' },
                { match: ['departamento', 'equipa', 'equipe', 'setor', 'secao', 'seção'], icon: 'fa-building' },
                { match: ['status', 'estado'], icon: 'fa-circle-check' },
                { match: ['acoes', 'ações', 'acao', 'ação'], icon: 'fa-cog' },
                { match: ['data', 'dia', 'periodo', 'período'], icon: 'fa-calendar-alt' },
                { match: ['entrada', 'inicio', 'início'], icon: 'fa-sign-in-alt' },
                { match: ['saida', 'saída', 'fim'], icon: 'fa-sign-out-alt' },
                { match: ['turno', 'escala', 'horario', 'horário'], icon: 'fa-clock' },
                { match: ['valor', 'salario', 'salário', 'liquido', 'líquido', 'bruto', 'total'], icon: 'fa-euro-sign' },
                { match: ['pagamento', 'pagos', 'pago'], icon: 'fa-money-check-alt' },
                { match: ['origem'], icon: 'fa-map-marker-alt' },
                { match: ['tipo'], icon: 'fa-tag' },
                { match: ['descricao', 'descrição', 'motivo', 'observacao', 'observação'], icon: 'fa-align-left' },
                { match: ['email'], icon: 'fa-envelope' },
                { match: ['telefone', 'telemovel', 'telemóvel', 'contacto', 'contato'], icon: 'fa-phone' }
            ];

            const normalizeHeaderText = (value) => (value || '')
                .toString()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();

            document.querySelectorAll('table th').forEach((th) => {
                if (!th || th.querySelector('i')) {
                    return;
                }

                const text = normalizeHeaderText(th.textContent);
                if (!text) {
                    return;
                }

                const found = headerIconMap.find((item) => item.match.some((token) => text.includes(token)));
                if (!found) {
                    return;
                }

                const icon = document.createElement('i');
                icon.className = `fas ${found.icon}`;
                icon.style.marginRight = '0.4rem';

                const label = document.createElement('span');
                label.textContent = th.textContent.trim();

                th.textContent = '';
                th.appendChild(icon);
                th.appendChild(label);
            });

            // Limpar gorjeta individual
            document.querySelectorAll('.btn-limpar-gorjeta').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const gorjetaId = this.getAttribute('data-gorjeta-id');
                    if (!gorjetaId) return showError('ID da gorjeta não encontrado.');
                    const confirmed = typeof showConfirm === 'function'
                        ? await showConfirm('Confirmação', 'Deseja realmente excluir esta gorjeta?', 'Sim, excluir', 'Cancelar')
                        : window.confirm('Deseja realmente excluir esta gorjeta?');
                    if (!confirmed) return;
                    fetch('../api/gorjetas/delete_gorjeta.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: gorjetaId })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showSuccess('Gorjeta excluída!');
                            this.closest('tr').remove();
                        } else {
                            showError(data.message || 'Erro ao excluir gorjeta.');
                        }
                    })
                    .catch(() => showError('Erro ao comunicar com o servidor.'));
                });
            });

            // Limpar todas as gorjetas pendentes
            const btnLimparTodasGorjetas = document.getElementById('btnLimparTodasGorjetas');
            if (btnLimparTodasGorjetas) {
                btnLimparTodasGorjetas.addEventListener('click', async function() {
                    const gorjetaIds = Array.from(document.querySelectorAll('.btn-limpar-gorjeta')).map(btn => btn.getAttribute('data-gorjeta-id')).filter(Boolean);
                    if (gorjetaIds.length === 0) return showWarning('Nenhuma gorjeta para limpar.');
                    const confirmed = typeof showConfirm === 'function'
                        ? await showConfirm('Confirmação', 'Deseja excluir TODAS as gorjetas pendentes?', 'Sim, excluir todas', 'Cancelar')
                        : window.confirm('Deseja excluir TODAS as gorjetas pendentes?');
                    if (!confirmed) return;
                    let excluidas = 0;
                    for (const id of gorjetaIds) {
                        await fetch('../api/gorjetas/delete_gorjeta.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id })
                        })
                        .then(r => r.json())
                        .then(data => { if (data.success) excluidas++; });
                    }
                    showSuccess(`${excluidas} gorjeta(s) excluída(s)!`);
                    setTimeout(() => window.location.reload(), 1000);
                });
            }
        // Confirmação para todos os formulários de aprovação
        document.querySelectorAll('form').forEach(form => {
            const approveBtn = form.querySelector('button[type="submit"],input[type="submit"]');
            if (!approveBtn) return;
            // Só intercepta se for ação de aprovar
            const actionInput = form.querySelector('input[name="action"],input[name="decision"]');
            if (!actionInput) return;
            const isAprovar = (
                actionInput.value === 'approve_presence_request' ||
                actionInput.value === 'approve_gorjeta_request' ||
                actionInput.value === 'approve_turno_swap_request' ||
                actionInput.value === 'aprovar'
            );
            if (!isAprovar) return;
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const confirmed = typeof showConfirm === 'function'
                    ? await showConfirm('Confirmação', 'Tem certeza que deseja aprovar esta solicitação?', 'Sim, aprovar', 'Cancelar')
                    : window.confirm('Tem certeza que deseja aprovar esta solicitação?');
                if (confirmed) form.submit();
            });
        });
    const btnExportarHistorico = document.getElementById('btnExportarHistorico');
    const btnLimparHistorico = document.getElementById('btnLimparHistorico');
    if (btnExportarHistorico) {
        btnExportarHistorico.addEventListener('click', function() {
            const table = document.querySelector('#solicitacoes-decididas-table table');
            if (!table) {
                showError('Tabela de histórico não encontrada!');
                return;
            }
            if (typeof XLSX === 'undefined') {
                showError('Biblioteca XLSX não carregada!');
                return;
            }
            const wb = XLSX.utils.table_to_book(table, {sheet: "Historico"});
            XLSX.writeFile(wb, 'historico_solicitacoes.xlsx');
            showSuccess('O histórico foi exportado com sucesso.');
        });
    }
    if (btnLimparHistorico) {
        btnLimparHistorico.addEventListener('click', async function() {
            const confirmed = typeof showConfirm === 'function'
                ? await showConfirm('Tem certeza?', 'Deseja limpar TODO o histórico de solicitações decididas? Esta ação não pode ser desfeita.', 'Sim, limpar', 'Cancelar')
                : window.confirm('Deseja limpar TODO o histórico de solicitações decididas? Esta ação não pode ser desfeita.');
            if (!confirmed) return;
            fetch('../admin/controllers/limpar_historico_solicitacoes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]').getAttribute('content'))
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Histórico limpo com sucesso!');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showError(data.message || 'Erro ao limpar histórico.');
                }
            })
            .catch(() => showError('Erro ao comunicar com o servidor.'));
        });
    }
});
// Fallback global imediato para evitar "bulkDeleteSelected is not defined" no onclick inline.
if (typeof window.bulkDeleteSelected !== 'function') {
    window.bulkDeleteSelected = async function(event) {
        const canShowBulkWarning = document.body.classList.contains('bulk-bar-visible') || !!document.querySelector('#bulkActionsBar.show');
        const trigger = (event && event.currentTarget) ? event.currentTarget : document.activeElement;
        const isBulkTrigger = !!(trigger && trigger.closest && trigger.closest('#bulkActionsBar'));
        if (!isBulkTrigger) {
            return;
        }

        const selected = Array.from(document.querySelectorAll('#funcionarios-section .employee-row .employee-checkbox:checked'));
        if (selected.length === 0) {
            if (canShowBulkWarning) {
                if (typeof showWarning === 'function') showWarning('Selecione pelo menos um funcionário');
                else alert('Selecione pelo menos um funcionário');
            }
            return;
        }

        const confirmed = (typeof showConfirm === 'function')
            ? await showConfirm(
                'Excluir Funcionários',
                `Deseja excluir ${selected.length} funcionário${selected.length !== 1 ? 's' : ''}? Esta ação é permanente.`,
                'Sim, excluir',
                'Cancelar'
            )
            : window.confirm(`Deseja excluir ${selected.length} funcionário${selected.length !== 1 ? 's' : ''}? Esta ação é permanente.`);

        if (!confirmed) return;

        let successCount = 0;
        for (const cb of selected) {
            const empId = cb.dataset.employeeId;
            if (!empId) continue;

            try {
                const fd = new FormData();
                fd.append('id', empId);
                fd.append('hard_delete', '1');

                const response = await fetch('../api/employees/delete_employee.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                });
                const data = await parseJsonResponseSafe(response);

                if (data.success) {
                    successCount += 1;
                    const row = cb.closest('tr');
                    if (row) row.remove();
                }
            } catch (err) {
                console.error('Falha ao excluir funcionário em lote (fallback):', err);
            }
        }

        if (successCount > 0) {
            if (typeof showSuccess === 'function') showSuccess(`${successCount} funcionário(s) excluído(s)`);
            else alert(`${successCount} funcionário(s) excluído(s)`);
            if (typeof window.clearBulkSelection === 'function') window.clearBulkSelection();
        } else if (typeof showError === 'function') {
            showError('Não foi possível excluir os funcionários selecionados.');
        } else {
            alert('Não foi possível excluir os funcionários selecionados.');
        }
    };
}

// ========== EXPORTAÇÃO DE FUNCIONÁRIOS E PRESENÇAS ========== 

function exportPresencaCSV() {
    const table = document.getElementById('presencaTable');
    if (!table) return alert('Tabela de presenças não encontrada!');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Presencas"});
    XLSX.writeFile(wb, 'presencas.csv');
}

function exportPresencaPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const table = document.getElementById('presencaTable');
    if (!table) return alert('Tabela de presenças não encontrada!');
    let y = 20;
    doc.setFontSize(16);
    doc.text('Presenças', 105, y, { align: 'center' });
    y += 10;
    doc.setFontSize(10);
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    doc.autoTable({
        head: [headers],
        body: rows.map(row => Array.from(row.children).map(td => td.textContent.trim())),
        startY: y
    });
    doc.save('presencas.pdf');
}

function bulkExportSelected() {
    // Exporta apenas funcionários selecionados
    const table = document.getElementById('employeesTable');
    if (!table) return alert('Tabela de funcionários não encontrada!');
    const selected = Array.from(document.querySelectorAll('.employee-checkbox:checked'));
    if (selected.length === 0) return alert('Selecione pelo menos um funcionário!');
    const wb = XLSX.utils.book_new();
    const rows = selected.map(cb => cb.closest('tr'));
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    const data = rows.map(row => Array.from(row.children).map(td => td.textContent.trim()));
    const ws = XLSX.utils.aoa_to_sheet([headers, ...data]);
    XLSX.utils.book_append_sheet(wb, ws, 'Selecionados');
    XLSX.writeFile(wb, 'funcionarios_selecionados.xlsx');
}


function hideBulkBarSafely(bar) {
    if (!bar) return;

    const activeEl = document.activeElement;
    if (activeEl && bar.contains(activeEl)) {
        if (typeof activeEl.blur === 'function') {
            activeEl.blur();
        }

        const fallbackFocusTarget =
            document.getElementById('btnAddEmployee') ||
            document.getElementById('searchEmployees') ||
            document.querySelector('#funcionarios-section .section-title') ||
            document.body;

        if (fallbackFocusTarget && typeof fallbackFocusTarget.focus === 'function') {
            fallbackFocusTarget.focus({ preventScroll: true });
        }
    }

    bar.classList.remove('show');
    bar.classList.remove('active');
    bar.style.display = 'none';
    bar.setAttribute('aria-hidden', 'true');
    bar.setAttribute('inert', '');
    document.body.classList.remove('bulk-bar-visible');
}

function getPresencaRowsForModalNavigation() {
    const rows = Array.from(document.querySelectorAll('#presencaTable tbody tr'));
    const visibleRows = rows.filter(row => row.style.display !== 'none');
    return visibleRows.length > 0 ? visibleRows : rows;
}

function findPresencaRowByEmployeeId(employeeId) {
    return document.querySelector(`#presencaTable tbody tr[data-employee-id="${employeeId}"]`);
}

function setPresencaModalUrl(employeeId, dateIso = '') {
    const url = new URL(window.location.href);
    url.searchParams.set('presenca_view_employee', String(employeeId));
    if (dateIso && /^\d{4}-\d{2}-\d{2}$/.test(dateIso)) {
        url.searchParams.set('presenca_view_date', dateIso);
    } else {
        url.searchParams.delete('presenca_view_date');
    }
    history.replaceState(null, '', url.pathname + (url.search ? url.search : ''));
}

function clearPresencaModalUrl() {
    const url = new URL(window.location.href);
    url.searchParams.delete('presenca_view_employee');
    url.searchParams.delete('presenca_view_date');
    history.replaceState(null, '', url.pathname + (url.search ? url.search : ''));
}

function closePresencaViewModal() {
    const modal = document.getElementById('modalVerPresenca');
    if (!modal) return;
    modal.style.display = 'none';
    clearPresencaModalUrl();
}

function parseHHMMToMinutes(value) {
    const text = String(value || '').trim();
    if (!/^\d{2}:\d{2}$/.test(text)) return null;
    const [h, m] = text.split(':').map(Number);
    if (Number.isNaN(h) || Number.isNaN(m)) return null;
    return (h * 60) + m;
}

function formatDurationFromMinutes(totalMinutes) {
    if (typeof totalMinutes !== 'number' || Number.isNaN(totalMinutes)) return '--:--';
    const absVal = Math.abs(totalMinutes);
    const h = String(Math.floor(absVal / 60)).padStart(2, '0');
    const m = String(absVal % 60).padStart(2, '0');
    return `${h}:${m}`;
}

function formatSignedMinutes(totalMinutes) {
    if (typeof totalMinutes !== 'number' || Number.isNaN(totalMinutes)) return '—';
    if (totalMinutes === 0) return '0 min';
    const sign = totalMinutes > 0 ? '+' : '-';
    return `${sign}${Math.abs(totalMinutes)} min`;
}

function renderMiniHistoryPresenca(employeeId, anchorDateIso = '') {
    const body = document.getElementById('view-presenca-mini-history-body');
    if (!body) return;

    body.innerHTML = '<div style="padding:8px;color:var(--text-secondary,#94a3b8);">A carregar histórico...</div>';

    const baseDate = anchorDateIso && /^\d{4}-\d{2}-\d{2}$/.test(anchorDateIso)
        ? new Date(anchorDateIso + 'T00:00:00')
        : new Date();

    const dates = [];
    for (let i = 0; i < 7; i += 1) {
        const dt = new Date(baseDate);
        dt.setDate(baseDate.getDate() - i);
        const yyyy = dt.getFullYear();
        const mm = String(dt.getMonth() + 1).padStart(2, '0');
        const dd = String(dt.getDate()).padStart(2, '0');
        dates.push(`${yyyy}-${mm}-${dd}`);
    }

    Promise.all(
        dates.map(dateIso =>
            fetch(`../api/employees/get_employee.php?id=${encodeURIComponent(employeeId)}&date=${encodeURIComponent(dateIso)}`)
                .then(r => r.ok ? r.json() : null)
                .then(data => ({ dateIso, data }))
                .catch(() => ({ dateIso, data: null }))
        )
    ).then(results => {
        const rowsHtml = results.map(({ dateIso, data }) => {
            const rec = data?.presenca_atual || null;
            const datePt = dateIso.split('-').reverse().join('/');
            const status = rec?.status ? String(rec.status).toUpperCase() : 'SEM REGISTO';
            const entrada = rec?.hora_entrada ? String(rec.hora_entrada).substring(0, 5) : '--:--';
            const saida = rec?.hora_saida ? String(rec.hora_saida).substring(0, 5) : '--:--';
            return `
                <div style="display:grid;grid-template-columns:88px 1fr 60px 60px;gap:.5rem;padding:6px 8px;border-bottom:1px dashed #e2e8f0;align-items:center;">
                    <span style="color:var(--text-secondary,#94a3b8);font-weight:700;">${datePt}</span>
                    <span style="color:var(--text-primary,#e2e8f0);">${status}</span>
                    <span style="color:var(--text-primary,#e2e8f0);">${entrada}</span>
                    <span style="color:var(--text-primary,#e2e8f0);">${saida}</span>
                </div>
            `;
        }).join('');

        body.innerHTML = rowsHtml || '<div style="padding:8px;color:var(--text-secondary,#94a3b8);">Sem dados para os últimos 7 dias.</div>';
    }).catch(() => {
        body.innerHTML = '<div style="padding:8px;color:#f87171;">Não foi possível carregar o histórico de 7 dias.</div>';
    });
}

function renderPresencaComparativeIndicators(row, employeeId) {
    const atrasoMinEl = document.getElementById('view-presenca-atraso-minutos');
    const overtimeEl = document.getElementById('view-presenca-horas-extras');
    const workedDaysEl = document.getElementById('view-presenca-dias-trabalhados');
    const absencesEl = document.getElementById('view-presenca-numero-faltas');
    if (!atrasoMinEl) return;

    atrasoMinEl.textContent = '—';
    atrasoMinEl.style.color = 'var(--text-secondary, #94a3b8)';
    if (overtimeEl) overtimeEl.textContent = '00:00';
    if (workedDaysEl) workedDaysEl.textContent = '0';
    if (absencesEl) absencesEl.textContent = '0';

    const entradaMin = parseHHMMToMinutes(row?.dataset?.horaEntrada);
    const previstoMin = parseHHMMToMinutes(row?.dataset?.expectedStart);
    const tolerancia = Math.max(0, parseInt(row?.dataset?.toleranciaMin || '0', 10) || 0);

    if (entradaMin != null && previstoMin != null) {
        const atrasoMin = entradaMin - previstoMin - tolerancia;
        if (atrasoMin > 0) {
            atrasoMinEl.textContent = `+${atrasoMin} min`;
            atrasoMinEl.style.color = '#dc2626';
        } else {
            atrasoMinEl.textContent = 'Pontual';
            atrasoMinEl.style.color = '#16a34a';
        }
    }

    const anchorDateIso = String(row?.dataset?.presencaDate || '').trim();
    const summaryParam = /^\d{4}-\d{2}-\d{2}$/.test(anchorDateIso)
        ? `&anchor_date=${encodeURIComponent(anchorDateIso)}`
        : '';

    fetch(`../api/employees/get_employee_shift_attendance.php?employee_id=${encodeURIComponent(employeeId)}${summaryParam}`)
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            const resumo = data?.resumo_periodo || null;
            if (resumo) {
                if (overtimeEl) {
                    overtimeEl.textContent = resumo.horas_extras_formatadas || '00:00';
                }
                if (workedDaysEl) {
                    workedDaysEl.textContent = String(parseInt(resumo.dias_trabalhados || 0, 10) || 0);
                }
                if (absencesEl) {
                    absencesEl.textContent = String(parseInt(resumo.faltas || 0, 10) || 0);
                }
            }
        })
        .catch(() => {
            // Mantém fallback silencioso para não bloquear o modal.
        });
}

function updatePresencaModalNavigationState(modal, navRows, currentIndex) {
    if (!modal) return;
    const prevBtn = document.getElementById('view-presenca-prev');
    const nextBtn = document.getElementById('view-presenca-next');
    const indicator = document.getElementById('view-presenca-nav-indicator');

    if (prevBtn) prevBtn.disabled = currentIndex <= 0;
    if (nextBtn) nextBtn.disabled = currentIndex >= (navRows.length - 1);
    if (indicator) indicator.textContent = navRows.length > 0 ? `${currentIndex + 1}/${navRows.length}` : '0/0';
}

const ROTEIRO_TL_CLASS_MAP = {
    in: 'tl-entrada',
    regresso: 'tl-regresso',
    pausa: 'tl-pausa',
    out: 'tl-saida',
    ativo: 'tl-ativo'
};

function buildRoteiroVerticalHtml(events) {
    if (!Array.isArray(events) || events.length === 0) {
        return '<span class="fr-roteiro-label">Sem registo para este dia.</span>';
    }

    return events.map((ev, idx) => {
        const isLast = idx === events.length - 1;
        const tlClass = ROTEIRO_TL_CLASS_MAP[ev.cls] || 'tl-entrada';
        const hora = ev.hora || '--:--';
        const label = ev.label || '';
        const icon = ev.icon || 'fa-circle';
        return `
            <div class="roteiro-evento ${tlClass}">
                <div class="roteiro-hora">${hora}</div>
                <div class="roteiro-dot-col">
                    <div class="roteiro-dot"></div>
                    ${isLast ? '' : '<div class="roteiro-line"></div>'}
                </div>
                <div class="roteiro-info">
                    <div class="roteiro-lbl"><i class="fas ${icon}"></i> ${label}</div>
                </div>
            </div>`;
    }).join('');
}

function renderPresencaModalFromRow(row, employeeId) {
    if (!row) return;

    const d = row.dataset;
    const funcionario = d.funcionarioNome || '-';
    const statusEl = row.querySelector(`#attendance-status-${employeeId}`);
    const status = statusEl ? statusEl.textContent.trim() : '-';
    const data = d.dateDisplay || d.presencaDate || '-';
    const tipoDia = d.tipoDia || 'Normal';
    const faltaTipoRaw = String(d.faltaTipo || '').toLowerCase();
    const faltaTipo = faltaTipoRaw === 'justificada' ? 'Falta Justificada' : (faltaTipoRaw === 'injustificada' ? 'Falta Injustificada' : '-');
    const entrada = d.horaEntrada || '--:--';
    const saida = d.horaSaida || '--:--';
    const horas = d.horas || '--:--';
    const atraso = d.atraso || '—';
    const observacao = d.obs || '-';
    const confirmacao = d.confirmacao || '-';
    const turnoPrevisto = d.expectedStart || '-';
    const updatedAt = d.updatedAt || '-';

    const modal = document.getElementById('modalVerPresenca');
    if (modal) {
        modal.dataset.employeeId = String(employeeId);
    }

    setPresencaModalUrl(employeeId, d.presencaDate || '');

    document.getElementById('view-presenca-funcionario').textContent = funcionario;
    const _presAvEl = document.getElementById('view-presenca-av');
    if (_presAvEl) {
        const _np = String(funcionario).trim().split(/\s+/);
        _presAvEl.textContent = ((_np[0]?.[0] || '') + (_np[1]?.[0] || '')).toUpperCase() || '--';
    }
    const _presStatusMap = {
        'Presente': 'status-presente', 'presente': 'status-presente',
        'Falta Injustificada': 'status-falta', 'Falta Justificada': 'status-falta',
        'falta': 'status-falta',
        'Atrasado': 'status-warning', 'atrasado': 'status-warning',
        'Em Aberto': 'status-warning', 'Em aberto': 'status-warning',
        'Inativo': 'status-inactive',
        'Férias': 'status-ferias', 'Ferias': 'status-ferias',
        'Sem turno': 'status-nao-marcado',
    };
    const _presStatusEl = document.getElementById('view-presenca-status');
    if (_presStatusEl) {
        const _cls = _presStatusMap[status] || 'status-nao-marcado';
        _presStatusEl.innerHTML = `<span class="status-badge ${_cls}">${status}</span>`;
    }
    document.getElementById('view-presenca-data').textContent = data;
    document.getElementById('view-presenca-tipo-dia').textContent = tipoDia;
    document.getElementById('view-presenca-falta-tipo').textContent = faltaTipo;
    document.getElementById('view-presenca-entrada').textContent = entrada;
    document.getElementById('view-presenca-saida').textContent = saida;
    const roteiroFullEl = document.getElementById('view-presenca-roteiro-full');
    if (roteiroFullEl) {
        let roteiroEventos = [];
        try {
            roteiroEventos = JSON.parse(d.roteiro || '[]');
        } catch (e) {
            roteiroEventos = [];
        }
        roteiroFullEl.innerHTML = buildRoteiroVerticalHtml(roteiroEventos);
    }
    document.getElementById('view-presenca-horas').textContent = horas;
    document.getElementById('view-presenca-atraso').textContent = atraso;
    document.getElementById('view-presenca-confirmacao').textContent = confirmacao;
    document.getElementById('view-presenca-obs').textContent = observacao;
    document.getElementById('view-presenca-turno-previsto').textContent = turnoPrevisto !== '' ? turnoPrevisto : '-';
    document.getElementById('view-presenca-updated-at').textContent = updatedAt !== '' ? updatedAt : '-';

    const justSection = document.getElementById('view-presenca-just-section');
    const justStatus = d.justLabel || '-';
    const justTipo = d.justTipo || '';
    const justData = d.justData || '-';
    const justMotivo = d.justMotivo || '';
    const justAdminObs = d.justAdminObs || '-';
    const justDecididoPor = d.justDecididoPor || '-';
    const justDecididoEm = d.justDecididoEm || '-';
    const justAnexo = (d.justAnexo || '').trim();

    const hasJustificativaData = [
        justStatus,
        justTipo,
        justData,
        justMotivo,
        justAdminObs,
        justDecididoPor,
        justDecididoEm,
        justAnexo
    ].some(value => {
        const normalized = String(value || '').trim();
        return normalized !== '' && normalized !== '-';
    });

    if (justSection) {
        if (hasJustificativaData) {
            document.getElementById('view-presenca-just-status').textContent = justStatus;
            document.getElementById('view-presenca-just-tipo').textContent = justTipo ? justTipo.charAt(0).toUpperCase() + justTipo.slice(1) : '-';
            document.getElementById('view-presenca-just-data').textContent = justData;
            document.getElementById('view-presenca-just-motivo').textContent = justMotivo || '-';
            document.getElementById('view-presenca-just-admin-obs').textContent = justAdminObs;
            document.getElementById('view-presenca-just-decidido-por').textContent = justDecididoPor;
            document.getElementById('view-presenca-just-decidido-em').textContent = justDecididoEm;

            const decididoPorEl = document.getElementById('view-presenca-just-decidido-por');
            if (decididoPorEl && decididoPorEl.parentElement) {
                const decididoPorNormalized = String(justDecididoPor || '').trim();
                decididoPorEl.parentElement.style.display = (decididoPorNormalized === '' || decididoPorNormalized === '-')
                    ? 'none'
                    : '';
            }

            const anexoWrap = document.getElementById('view-presenca-just-anexo-wrap');
            const anexoLink = document.getElementById('view-presenca-just-anexo');
            if (anexoWrap && anexoLink) {
                if (justAnexo !== '' && justAnexo !== '-') {
                    let anexoHref = justAnexo;
                    if (!/^https?:\/\//i.test(anexoHref)) {
                        if (!anexoHref.startsWith('/') && !anexoHref.startsWith('../')) {
                            anexoHref = '../' + anexoHref.replace(/^\.\//, '');
                        }
                    }
                    anexoLink.href = anexoHref;
                    anexoWrap.style.display = '';
                } else {
                    anexoLink.href = '#';
                    anexoWrap.style.display = 'none';
                }
            }

            justSection.style.display = '';
        } else {
            justSection.style.display = 'none';
        }
    }

    renderMiniHistoryPresenca(employeeId, d.presencaDate || '');
    renderPresencaComparativeIndicators(row, employeeId);

    if (modal) {
        const navRows = getPresencaRowsForModalNavigation();
        const currentIndex = Math.max(0, navRows.findIndex(r => r === row));
        modal.dataset.navIndex = String(currentIndex);
        updatePresencaModalNavigationState(modal, navRows, currentIndex);
        modal.style.display = 'block';
    }
}

function verDetalhesPresenca(employeeId) {
    const row = findPresencaRowByEmployeeId(employeeId) || document.querySelector(`button[onclick*='verDetalhesPresenca(${employeeId})']`)?.closest('tr');
    if (!row) return showError('Linha não encontrada.');
    renderPresencaModalFromRow(row, employeeId);
}

function navigatePresencaDetails(step) {
    const modal = document.getElementById('modalVerPresenca');
    if (!modal || modal.style.display === 'none') return;

    const navRows = getPresencaRowsForModalNavigation();
    if (navRows.length === 0) return;

    const currentIndex = Number(modal.dataset.navIndex || '0');
    const targetIndex = Math.min(navRows.length - 1, Math.max(0, currentIndex + step));
    if (targetIndex === currentIndex) return;

    const row = navRows[targetIndex];
    const employeeId = Number(row?.dataset?.employeeId || '0');
    if (!employeeId) return;

    renderPresencaModalFromRow(row, employeeId);
}

function showPrevPresencaDetails() {
    navigatePresencaDetails(-1);
}

function showNextPresencaDetails() {
    navigatePresencaDetails(1);
}

function handlePresencaModalKeyboardShortcuts(event) {
    const modal = document.getElementById('modalVerPresenca');
    if (!modal || modal.style.display === 'none') return;

    const targetTag = (event.target?.tagName || '').toLowerCase();
    const isTypingField = ['input', 'textarea', 'select'].includes(targetTag) || event.target?.isContentEditable;
    if (isTypingField) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        closePresencaViewModal();
        return;
    }

    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        showPrevPresencaDetails();
        return;
    }

    if (event.key === 'ArrowRight') {
        event.preventDefault();
        showNextPresencaDetails();
        return;
    }

    if (event.key === 'e' || event.key === 'E') {
        event.preventDefault();
        editarPresencaFromViewModal();
    }
}

if (!window.__presencaModalKeyboardReady) {
    window.__presencaModalKeyboardReady = true;
    window.addEventListener('keydown', handlePresencaModalKeyboardShortcuts);
}

window.showPrevPresencaDetails = showPrevPresencaDetails;
window.showNextPresencaDetails = showNextPresencaDetails;

function editarPresencaFromViewModal() {
    const modal = document.getElementById('modalVerPresenca');
    const employeeId = Number(modal?.dataset?.employeeId || '0');
    if (!employeeId) {
        showWarning('Funcionário não identificado para edição.');
        return;
    }

    closePresencaViewModal();
    editarPresenca(employeeId);
}

function openSolicitacoesFromViewModal() {
    const modal = document.getElementById('modalVerPresenca');
    if (modal) {
        closePresencaViewModal();
    }

    if (typeof showSection === 'function') {
        showSection('solicitacoes');
    }
}

function getPresencaDetailSnapshot() {
    const getText = (id) => document.getElementById(id)?.textContent?.trim() || '-';
    return {
        funcionario: getText('view-presenca-funcionario'),
        status: getText('view-presenca-status'),
        data: getText('view-presenca-data'),
        tipoDia: getText('view-presenca-tipo-dia'),
        entrada: getText('view-presenca-entrada'),
        saida: getText('view-presenca-saida'),
        horas: getText('view-presenca-horas'),
        atraso: getText('view-presenca-atraso'),
        atrasoMin: getText('view-presenca-atraso-minutos'),
        horasExtras: getText('view-presenca-horas-extras'),
        diasTrabalhados: getText('view-presenca-dias-trabalhados'),
        numeroFaltas: getText('view-presenca-numero-faltas'),
        turnoPrevisto: getText('view-presenca-turno-previsto'),
        confirmacao: getText('view-presenca-confirmacao'),
        faltaTipo: getText('view-presenca-falta-tipo'),
        observacao: getText('view-presenca-obs')
    };
}

function exportPresencaDetailPDF() {
    const snapshot = getPresencaDetailSnapshot();
    const { jsPDF } = window.jspdf || {};
    if (!jsPDF) {
        showError('Biblioteca PDF não disponível.');
        return;
    }

    const doc = new jsPDF();
    doc.setFont('helvetica');
    doc.setFontSize(16);
    doc.setTextColor(52, 152, 219);
    doc.text('Detalhe de Presença', 105, 20, { align: 'center' });

    const lines = [
        ['Funcionário', snapshot.funcionario],
        ['Status', snapshot.status],
        ['Data', snapshot.data],
        ['Tipo de Dia', snapshot.tipoDia],
        ['Entrada', snapshot.entrada],
        ['Saída', snapshot.saida],
        ['Horas Trabalhadas', snapshot.horas],
        ['Atraso', snapshot.atraso],
        ['Atraso (min)', snapshot.atrasoMin],
        ['Turno Previsto', snapshot.turnoPrevisto],
        ['Confirmação', snapshot.confirmacao],
        ['Tipo de Falta', snapshot.faltaTipo],
        ['Observação', snapshot.observacao]
    ];

    doc.autoTable({
        startY: 30,
        head: [['Campo', 'Valor']],
        body: lines,
        styles: { fontSize: 10, cellPadding: 3 },
        headStyles: { fillColor: [37, 99, 235] },
        columnStyles: { 0: { cellWidth: 55 }, 1: { cellWidth: 125 } }
    });

    doc.setFontSize(8);
    doc.setTextColor(120, 120, 120);
    doc.text('Gerado em: ' + new Date().toLocaleString('pt-PT'), 14, 286);

    const safeName = (snapshot.funcionario || 'funcionario').toLowerCase().replace(/[^a-z0-9]+/g, '_');
    doc.save(`detalhe_presenca_${safeName}.pdf`);
    showSuccess('PDF do detalhe exportado com sucesso.');
}

function printPresencaDetail() {
    const snapshot = getPresencaDetailSnapshot();
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    if (!printWindow) {
        showError('Não foi possível abrir janela de impressão.');
        return;
    }

    const rows = [
        ['Funcionário', snapshot.funcionario],
        ['Status', snapshot.status],
        ['Data', snapshot.data],
        ['Tipo de Dia', snapshot.tipoDia],
        ['Entrada', snapshot.entrada],
        ['Saída', snapshot.saida],
        ['Horas Trabalhadas', snapshot.horas],
        ['Atraso', snapshot.atraso],
        ['Atraso (min)', snapshot.atrasoMin],
        ['Turno Previsto', snapshot.turnoPrevisto],
        ['Confirmação', snapshot.confirmacao],
        ['Tipo de Falta', snapshot.faltaTipo],
        ['Observação', snapshot.observacao]
    ].map(([label, value]) => `<tr><th>${label}</th><td>${value}</td></tr>`).join('');

    printWindow.document.write(`
        <html>
            <head>
                <title>Detalhe de Presença</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 24px; color: #0f172a; }
                    h1 { color: #2563eb; margin-bottom: 12px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #cbd5e1; padding: 10px; text-align: left; }
                    th { width: 32%; background: #eff6ff; }
                    .meta { margin-top: 14px; color: #64748b; font-size: 12px; }
                </style>
            </head>
            <body>
                <h1>Detalhe de Presença</h1>
                <table>${rows}</table>
                <p class="meta">Gerado em: ${new Date().toLocaleString('pt-PT')}</p>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}

function copyPresencaDetailLink() {
    const currentUrl = window.location.href;
    const onSuccess = () => showSuccess('Link do detalhe copiado.');

    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(currentUrl).then(onSuccess).catch(() => {
            showWarning('Não foi possível copiar automaticamente. Copie a URL manualmente.');
        });
        return;
    }

    showWarning('Copie a URL da barra do navegador para partilhar este detalhe.');
}

window.editarPresencaFromViewModal = editarPresencaFromViewModal;
window.openSolicitacoesFromViewModal = openSolicitacoesFromViewModal;
window.exportPresencaDetailPDF = exportPresencaDetailPDF;
window.printPresencaDetail = printPresencaDetail;
window.copyPresencaDetailLink = copyPresencaDetailLink;

function formatDateToPt(rawValue) {
    if (!rawValue) return '--/--/----';
    const normalized = String(rawValue).replace(' ', 'T');
    const date = new Date(normalized);
    if (!Number.isNaN(date.getTime())) {
        const dd = String(date.getDate()).padStart(2, '0');
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const yyyy = date.getFullYear();
        return `${dd}/${mm}/${yyyy}`;
    }
    return '--/--/----';
}

function toIsoDate(rawValue) {
    if (!rawValue) return '';
    const normalized = String(rawValue).replace(' ', 'T');
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return '';
    const dd = String(date.getDate()).padStart(2, '0');
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const yyyy = date.getFullYear();
    return `${yyyy}-${mm}-${dd}`;
}

// ========== CARDS DE SOLICITAÇÕES: CARD SUSPENSO AO CLICAR ==========
function focusSolicitacoesTable(tableId) {
    // Remove destaque de todos os cards
    document.querySelectorAll('.solicitacao-card-btn').forEach(btn => {
        btn.classList.remove('active-solicitacao-card');
        btn.style.zIndex = '';
    });
    // Esconde todas as tabelas
    document.querySelectorAll('.solicitacoes-table').forEach(tbl => {
        tbl.style.display = 'none';
    });
    // Mostra a tabela clicada
    const table = document.getElementById(tableId);
    if (table) table.style.display = '';
    // Destaca o card clicado
    const cards = document.querySelectorAll('.solicitacao-card-btn');
    // Descobre qual card chamou (usando o id da tabela)
    let found = false;
    cards.forEach(btn => {
        if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(tableId)) {
            btn.classList.add('active-solicitacao-card');
            btn.style.zIndex = '20'; // suspende visualmente
            found = true;
        }
    });
    // Foca no card para acessibilidade
    if (found) {
        const activeCard = document.querySelector('.active-solicitacao-card');
        if (activeCard) activeCard.focus && activeCard.focus();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    try {
        const params = new URLSearchParams(window.location.search || '');
        if (params.get('section') === 'solicitacoes' && params.get('solicitacao_card') === 'trocas_turno') {
            focusSolicitacoesTable('solicitacoes-trocas-turno-table');
        }
    } catch (e) {
        console.warn('Falha ao focar card de solicitações de troca:', e);
    }
});

function calculateWorkedHours(horaEntrada, horaSaida) {
    if (!horaEntrada || !horaSaida) return '--:--';

    const [eh = '0', em = '0'] = String(horaEntrada).split(':');
    const [sh = '0', sm = '0'] = String(horaSaida).split(':');
    let start = (parseInt(eh, 10) * 60) + parseInt(em, 10);
    let end = (parseInt(sh, 10) * 60) + parseInt(sm, 10);

    if (Number.isNaN(start) || Number.isNaN(end)) return '--:--';
    if (end < start) end += 24 * 60;

    const diff = Math.max(0, end - start);
    const hours = String(Math.floor(diff / 60)).padStart(2, '0');
    const minutes = String(diff % 60).padStart(2, '0');
    return `${hours}:${minutes}`;
}

function normalizeTipoDiaFromCell(text) {
    const value = String(text || '').trim().toLowerCase();
    if (value === 'folga') return 'folga';
    if (value === 'feriado') return 'feriado';
    if (value === 'falta') return 'falta';
    return 'normal';
}

function syncFaltaTipoVisibility() {
    const statusEl = document.getElementById('edit-presenca-status');
    const tipoDiaEl = document.getElementById('edit-presenca-tipo-dia');
    const wrapEl = document.getElementById('edit-presenca-falta-tipo-wrap');
    const faltaTipoEl = document.getElementById('edit-presenca-falta-tipo');
    if (!statusEl || !tipoDiaEl || !wrapEl || !faltaTipoEl) return;

    const shouldShow = statusEl.value === 'falta' || tipoDiaEl.value === 'falta';
    wrapEl.style.display = shouldShow ? 'block' : 'none';
    if (!shouldShow) {
        faltaTipoEl.value = 'injustificada';
    }
}

function calculateDelayStatus(horaEntrada, expectedStart, toleranciaMin, tipoDia) {
    const tipo = String(tipoDia || 'normal').trim().toLowerCase();
    if (!horaEntrada || horaEntrada === '--:--') return '—';
    if (['folga', 'feriado', 'falta'].includes(tipo)) return '—';
    if (!expectedStart || expectedStart === '--:--') return '—';

    const [eh = '0', em = '0'] = String(horaEntrada).split(':');
    const [ph = '0', pm = '0'] = String(expectedStart).split(':');
    const entradaMin = (parseInt(eh, 10) * 60) + parseInt(em, 10);
    const previstoMin = (parseInt(ph, 10) * 60) + parseInt(pm, 10);

    if (Number.isNaN(entradaMin) || Number.isNaN(previstoMin)) return '—';

    const atraso = entradaMin - previstoMin - Math.max(0, parseInt(toleranciaMin, 10) || 0);
    if (atraso > 0) return `Atrasado (+${atraso} min)`;
    return 'Pontual';
}

function getCsrfToken() {
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    return tokenMeta ? tokenMeta.getAttribute('content') || '' : '';
}

function getTodayIsoDate() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function resolverSemTurno(employeeId, employeeName = '') {
    try {
        if (typeof showSection === 'function') {
            showSection('turnos');
        }

        const searchTurnos = document.getElementById('searchTurnos');
        if (searchTurnos) {
            searchTurnos.value = employeeName || '';
            searchTurnos.dispatchEvent(new Event('input'));
        }

        showInfo(`Defina um turno para ${employeeName || 'o funcionário'} na secção Turnos.`);
    } catch (e) {
        console.warn('Falha ao redirecionar para Turnos:', e);
        showWarning('Não foi possível abrir a secção de turnos automaticamente.');
    }
}

window.resolverSemTurno = resolverSemTurno;

function applyAttendanceRecordToRow(row, employeeId, record) {
    if (!row || !record) return;

    const entrada = record.hora_entrada ? String(record.hora_entrada).substring(0, 5) : '--:--';
    const saida = record.hora_saida ? String(record.hora_saida).substring(0, 5) : '--:--';
    const dataRaw = record.data_registro || '';
    const dataIso = toIsoDate(dataRaw);
    const dataPt = formatDateToPt(dataRaw);
    const tipoDiaRaw = String(record.tipo_dia || 'normal').toLowerCase();
    const tipoDiaLabelMap = { normal: 'Normal', folga: 'Folga', feriado: 'Feriado', falta: 'Falta' };
    const tipoDiaLabel = tipoDiaLabelMap[tipoDiaRaw] || 'Normal';
    const faltaTipoRaw = String(record.falta_tipo || '').toLowerCase();
    const horas = calculateWorkedHours(entrada, saida);
    const atraso = calculateDelayStatus(entrada, row.dataset.expectedStart || '', row.dataset.toleranciaMin || '0', tipoDiaRaw);
    const employeeStatusKey = String(row.dataset.employeeStatusKey || '').trim().toLowerCase();

    let pClass = 'status-nao-marcado';
    let pLabel = 'NÃO REGISTADO';
    let statusKey = 'nao-registrado';
    if (employeeStatusKey === 'ferias' || employeeStatusKey === 'férias') {
        pClass = 'status-ferias';
        pLabel = 'Férias';
        statusKey = 'ferias';
    } else if (employeeStatusKey === 'inactive' || employeeStatusKey === 'inativo') {
        pClass = 'status-inactive';
        pLabel = 'Inativo';
        statusKey = 'inativo';
    } else if (!row.dataset.expectedStart) {
        pClass = 'status-nao-marcado';
        pLabel = 'SEM TURNO';
        statusKey = 'sem-turno';
    } else if (record.status === 'presente' && entrada !== '--:--' && saida === '--:--' && record.status_confirmacao !== 'confirmado') {
        pClass = 'status-warning';
        pLabel = 'EM ABERTO';
        statusKey = 'em-aberto';
    } else if (record.status === 'presente') {
        pClass = 'status-presente';
        pLabel = 'PRESENTE';
        statusKey = 'presente';
    } else if (record.status === 'falta') {
        pClass = 'status-falta';
        pLabel = faltaTipoRaw === 'justificada' ? 'FALTA JUSTIFICADA' : 'FALTA INJUSTIFICADA';
        statusKey = 'falta';
    } else if (record.status === 'invalidado') {
        pClass = 'status-nao-marcado';
        pLabel = 'INVALIDADO';
        statusKey = 'invalidado';
    }

    const statusBadge = row.querySelector(`#attendance-status-${employeeId}`);
    if (statusBadge) {
        statusBadge.className = `status-badge ${pClass}`;
        statusBadge.textContent = pLabel;
        statusBadge.dataset.statusKey = statusKey;
    }

    const dataCell = row.querySelector('td:nth-child(3)');
    if (dataCell) dataCell.textContent = dataPt;

    const entradaCell = row.querySelector('td:nth-child(4)');
    if (entradaCell) entradaCell.textContent = entrada;

    const saidaCell = row.querySelector('td:nth-child(5)');
    if (saidaCell) saidaCell.textContent = saida;

    row.dataset.presencaDate = dataIso;
    row.dataset.presencaYear = dataIso ? dataIso.slice(0, 4) : '';
    row.dataset.presencaMonth = dataIso ? dataIso.slice(0, 7) : '';
    row.dataset.dateDisplay = dataPt;
    row.dataset.tipoDia = tipoDiaLabel;
    row.dataset.faltaTipo = faltaTipoRaw;
    row.dataset.horaEntrada = entrada;
    row.dataset.horaSaida = saida;
    row.dataset.horas = horas;
    row.dataset.atraso = atraso;
    row.dataset.statusKey = statusKey;
    row.dataset.statusLabel = pLabel;
    row.dataset.obs = record.obs ? String(record.obs) : '-';

    if (record.status === 'invalidado') {
        row.dataset.confirmacao = 'Invalidado';
    } else if (record.status === 'presente') {
        row.dataset.confirmacao = record.status_confirmacao === 'confirmado' ? 'Confirmado' : 'Pendente';
    } else {
        row.dataset.confirmacao = '-';
    }

    if (typeof window.filterPresencaTable === 'function') {
        window.filterPresencaTable();
    }
}

// captura global de erros para ajudar depuração
window.addEventListener('error', function(evt) {
    console.error('JS error caught:', evt.message, 'at', evt.filename + ':' + evt.lineno);
});

document.addEventListener('DOMContentLoaded', () => {
    const editStatusEl = document.getElementById('edit-presenca-status');
    const editTipoDiaEl = document.getElementById('edit-presenca-tipo-dia');
    if (editStatusEl) editStatusEl.addEventListener('change', syncFaltaTipoVisibility);
    if (editTipoDiaEl) editTipoDiaEl.addEventListener('change', syncFaltaTipoVisibility);
    syncFaltaTipoVisibility();

    // Fechar modal
    document.getElementById('closeEditarPresenca').onclick = () => document.getElementById('modalEditarPresenca').style.display = 'none';
    document.getElementById('cancelEditarPresenca').onclick = () => document.getElementById('modalEditarPresenca').style.display = 'none';
    if (document.getElementById('closeVerPresenca')) {
        document.getElementById('closeVerPresenca').onclick = () => closePresencaViewModal();
    }
    window.addEventListener('click', (e) => {
        if (e.target === document.getElementById('modalEditarPresenca')) document.getElementById('modalEditarPresenca').style.display = 'none';
        if (e.target === document.getElementById('modalVerPresenca')) closePresencaViewModal();
    });

    const openPresencaFromUrl = () => {
        const url = new URL(window.location.href);
        const employeeParam = Number(url.searchParams.get('presenca_view_employee') || '0');
        if (!employeeParam) return;

        if (typeof showSection === 'function') {
            showSection('assiduidade');
        }

        const row = findPresencaRowByEmployeeId(employeeParam);
        if (row) {
            verDetalhesPresenca(employeeParam);
        }
    };

    setTimeout(openPresencaFromUrl, 220);
    // Submissão do formulário
    document.getElementById('formEditarPresenca').onsubmit = async function(e) {
        e.preventDefault();

        if (this.dataset.submitting === '1') {
            return;
        }

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalSubmitHtml = submitBtn ? submitBtn.innerHTML : '';
        this.dataset.submitting = '1';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A guardar...';
        }

        const entradaEl = document.getElementById('edit-presenca-entrada');
        const saidaEl = document.getElementById('edit-presenca-saida');
        const timeErrorEl = document.getElementById('edit-presenca-time-error');

        const resetTimeValidation = () => {
            if (timeErrorEl) timeErrorEl.style.display = 'none';
            if (entradaEl) {
                entradaEl.style.borderColor = '';
                entradaEl.style.boxShadow = '';
            }
            if (saidaEl) {
                saidaEl.style.borderColor = '';
                saidaEl.style.boxShadow = '';
            }
        };

        const showTimeValidationError = (message) => {
            if (timeErrorEl) {
                timeErrorEl.textContent = message;
                timeErrorEl.style.display = 'block';
            }
            if (entradaEl) {
                entradaEl.style.borderColor = '#ef4444';
                entradaEl.style.boxShadow = '0 0 0 2px rgba(239,68,68,.2)';
            }
            if (saidaEl) {
                saidaEl.style.borderColor = '#ef4444';
                saidaEl.style.boxShadow = '0 0 0 2px rgba(239,68,68,.2)';
            }
        };

        resetTimeValidation();

        const entradaVal = (entradaEl?.value || '').trim();
        const saidaVal = (saidaEl?.value || '').trim();

        if (entradaVal && saidaVal) {
            const [eh = '0', em = '0'] = entradaVal.split(':');
            const [sh = '0', sm = '0'] = saidaVal.split(':');
            const entradaMin = (parseInt(eh, 10) * 60) + parseInt(em, 10);
            const saidaMin = (parseInt(sh, 10) * 60) + parseInt(sm, 10);

            if (!Number.isNaN(entradaMin) && !Number.isNaN(saidaMin) && saidaMin <= entradaMin) {
                showTimeValidationError('A hora de saída deve ser maior que a hora de entrada.');
                showError('Horário inválido: saída deve ser maior que entrada.');
                this.dataset.submitting = '0';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalSubmitHtml;
                }
                return;
            }
        }

        const fd = new FormData(this);
        const faltaTipoEl = document.getElementById('edit-presenca-falta-tipo');
        const statusEl = document.getElementById('edit-presenca-status');
        const tipoDiaEl = document.getElementById('edit-presenca-tipo-dia');
        const statusRaw = (statusEl?.value || '').toLowerCase();
        const tipoDiaRaw = (tipoDiaEl?.value || '').toLowerCase();
        const faltaTipoRaw = (faltaTipoEl?.value || '').toLowerCase();

        const allowedStatus = ['presente', 'falta', 'invalidado'];
        const allowedTipoDia = ['normal', 'folga', 'feriado', 'falta'];
        const allowedFaltaTipo = ['justificada', 'injustificada'];

        let normalizedStatus = allowedStatus.includes(statusRaw) ? statusRaw : 'invalidado';
        let normalizedTipoDia = allowedTipoDia.includes(tipoDiaRaw) ? tipoDiaRaw : 'normal';
        let normalizedFaltaTipo = allowedFaltaTipo.includes(faltaTipoRaw) ? faltaTipoRaw : '';

        if (normalizedStatus === 'falta' || normalizedTipoDia === 'falta') {
            normalizedStatus = 'falta';
            normalizedTipoDia = 'falta';
            if (!normalizedFaltaTipo) {
                normalizedFaltaTipo = 'injustificada';
            }
        } else {
            normalizedFaltaTipo = '';
        }

        if (statusEl) statusEl.value = normalizedStatus;
        if (tipoDiaEl) tipoDiaEl.value = normalizedTipoDia;
        if (faltaTipoEl) faltaTipoEl.value = normalizedFaltaTipo || 'injustificada';

        const employeeId = Number(document.getElementById('edit-presenca-employee-id').value || '0');
        const row = findPresencaRowByEmployeeId(employeeId);
        const targetDate = (document.getElementById('edit-presenca-target-date')?.value || row?.dataset.presencaDate || getTodayIsoDate());

        fd.set('status', normalizedStatus);
        fd.set('tipo_dia', normalizedTipoDia);
        fd.set('falta_tipo', normalizedFaltaTipo);
        fd.append('action', 'edit');
        fd.set('target_date', targetDate);
        fd.set('csrf_token', getCsrfToken());

        try {
            const response = await fetch('../api/employees/validate_attendance.php', {
                method: 'POST',
                body: fd
            });
            const data = await response.json();

            if (data.success) {
                showSuccess(data.message || 'Registro atualizado!');
                document.getElementById('modalEditarPresenca').style.display = 'none';
                if (row && data.record) {
                    applyAttendanceRecordToRow(row, employeeId, data.record);
                }
            } else {
                showError(data.message || 'Erro ao editar registro.');
            }
        } catch (_) {
            showError('Erro ao comunicar com o servidor.');
        } finally {
            this.dataset.submitting = '0';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalSubmitHtml;
            }
        }
    };

    const entradaEl = document.getElementById('edit-presenca-entrada');
    const saidaEl = document.getElementById('edit-presenca-saida');
    const timeErrorEl = document.getElementById('edit-presenca-time-error');
    const clearTimeError = () => {
        if (timeErrorEl) timeErrorEl.style.display = 'none';
        if (entradaEl) {
            entradaEl.style.borderColor = '';
            entradaEl.style.boxShadow = '';
        }
        if (saidaEl) {
            saidaEl.style.borderColor = '';
            saidaEl.style.boxShadow = '';
        }
    };
    if (entradaEl) entradaEl.addEventListener('input', clearTimeError);
    if (saidaEl) saidaEl.addEventListener('input', clearTimeError);
});
// ========== PRESENÇA: AÇÕES DO ADMIN ========== 
async function confirmarPresenca(employeeId) {
    showWarning('A aprovação de presença está disponível apenas na secção Solicitações.');
}

async function invalidarPresenca(employeeId) {
    showWarning('A rejeição de presença está disponível apenas na secção Solicitações.');
}

function editarPresenca(employeeId) {
    const row = findPresencaRowByEmployeeId(employeeId);
    if (!row) return showError('Linha não encontrada.');

    document.getElementById('edit-presenca-employee-id').value = employeeId;
    const targetDateEl = document.getElementById('edit-presenca-target-date');
    if (targetDateEl) {
        targetDateEl.value = row.dataset.presencaDate || getTodayIsoDate();
    }

    const tipoDiaSelect = document.getElementById('edit-presenca-tipo-dia');
    if (tipoDiaSelect) {
        tipoDiaSelect.value = normalizeTipoDiaFromCell(row.dataset.tipoDia || 'Normal');
    }

    const statusBadge = row.querySelector(`#attendance-status-${employeeId}`);
    const isPresente = !!(statusBadge && statusBadge.classList.contains('status-presente'));
    const isFalta = !!(statusBadge && statusBadge.classList.contains('status-falta'));
    document.getElementById('edit-presenca-status').value = isPresente ? 'presente' : (isFalta ? 'falta' : 'invalidado');

    const faltaTipo = document.getElementById('edit-presenca-falta-tipo');
    if (faltaTipo) faltaTipo.value = row.dataset.faltaTipo === 'justificada' ? 'justificada' : 'injustificada';

    document.getElementById('edit-presenca-entrada').value = row.dataset.horaEntrada && row.dataset.horaEntrada !== '--:--' ? row.dataset.horaEntrada : '';
    document.getElementById('edit-presenca-saida').value = row.dataset.horaSaida && row.dataset.horaSaida !== '--:--' ? row.dataset.horaSaida : '';
    document.getElementById('edit-presenca-obs').value = row.dataset.obs && row.dataset.obs !== '-' ? row.dataset.obs : '';

    syncFaltaTipoVisibility();
    document.getElementById('modalEditarPresenca').style.display = 'block';
}

// Atualiza a linha da tabela de presença após ação do admin
async function atualizarLinhaPresenca(employeeId, targetDate = '', latestRecord = null) {
    try {
        const row = findPresencaRowByEmployeeId(employeeId);
        if (!row) return;

        if (latestRecord) {
            applyAttendanceRecordToRow(row, employeeId, latestRecord);
            return;
        }

        const dateParam = targetDate || row.dataset.presencaDate || getTodayIsoDate();
        const res = await fetch(`../api/employees/get_employee.php?id=${employeeId}&date=${encodeURIComponent(dateParam)}`);
        const emp = await res.json();
        if (emp && emp.presenca_atual) {
            applyAttendanceRecordToRow(row, employeeId, emp.presenca_atual);
        }
    } catch (e) {
        console.warn('Não foi possível atualizar linha de presença:', e);
    }
}
// ========== FUNÇÕES GLOBAIS PARA FILTROS ==========
function applyFilters() {
    // Fechar o painel de filtros
    const filterPanel = document.getElementById('filterPanel');
    if (filterPanel) {
        filterPanel.style.display = 'none';
    }

    // A filtragem é feita automaticamente pelos event listeners
    // Mas vamos forçar uma atualização para garantir
    const searchEmployees = document.getElementById('searchEmployees');
    if (searchEmployees) {
        searchEmployees.dispatchEvent(new Event('input'));
    }

    // Mostrar mensagem de sucesso
    showSuccess('Filtros aplicados com sucesso!');
}

function clearAllFilters() {
    // Limpar todos os campos de filtro
    const filterDepartment = document.getElementById('filterDepartment');
    const filterPosition = document.getElementById('filterPosition');
    const filterStatus = document.getElementById('filterStatus');
    const filterStartDate = document.getElementById('filterStartDate');
    const filterEndDate = document.getElementById('filterEndDate');
    const searchEmployees = document.getElementById('searchEmployees');

    if (filterDepartment) filterDepartment.value = '';
    if (filterPosition) filterPosition.value = '';
    if (filterStatus) filterStatus.value = '';
    if (filterStartDate) filterStartDate.value = '';
    if (filterEndDate) filterEndDate.value = '';
    if (searchEmployees) searchEmployees.value = '';

    // Limpar filtros da tabela principal
    const tableSearch = document.getElementById('employeeTableSearch');
    const tableStatus = document.getElementById('employeeTableStatus');
    const tablePosition = document.getElementById('employeeTablePosition');
    const tableDept = document.getElementById('employeeTableDepartment');
    const tableContract = document.getElementById('employeeTableContractType');
    const tableExpiry = document.getElementById('employeeTableExpiry');
    if (tableSearch) { tableSearch.value = ''; tableSearch.dispatchEvent(new Event('input')); }
    if (tableStatus) { tableStatus.value = ''; tableStatus.dispatchEvent(new Event('change')); }
    if (tablePosition) { tablePosition.value = ''; tablePosition.dispatchEvent(new Event('change')); }
    if (tableDept) { tableDept.value = ''; tableDept.dispatchEvent(new Event('change')); }
    if (tableContract) { tableContract.value = ''; tableContract.dispatchEvent(new Event('change')); }
    if (tableExpiry) { tableExpiry.value = ''; tableExpiry.dispatchEvent(new Event('change')); }

    // Disparar evento de input para reprocessar a tabela
    if (searchEmployees) {
        searchEmployees.dispatchEvent(new Event('input'));
    }

    // Fechar o painel de filtros
    const filterPanel = document.getElementById('filterPanel');
    if (filterPanel) {
        filterPanel.style.display = 'none';
    }

    // Mostrar mensagem
    showSuccess('Filtros limpos com sucesso!');
}

async function parseJsonResponseSafe(response) {
    const raw = await response.text();
    try {
        return JSON.parse(raw);
    } catch (error) {
        // Alguns endpoints podem retornar avisos PHP antes do JSON; evita falso negativo no frontend.
        return {
            success: response.ok,
            message: raw && raw.trim() ? raw.trim() : 'Resposta inválida do servidor.'
        };
    }
}

async function reconcileDeactivateResult(employeeId, fallbackMessage) {
    try {
        const verifyRes = await fetch(`../api/employees/get_employee.php?id=${encodeURIComponent(employeeId)}`, {
            credentials: 'same-origin'
        });
        const verifyData = await parseJsonResponseSafe(verifyRes);
        const status = (verifyData && verifyData.status) || (verifyData && verifyData.employee && verifyData.employee.status);

        if (String(status || '').toLowerCase() === 'inactive') {
            updateRowToInactive(employeeId);
            showSuccess('O funcionario foi desativado com sucesso');
            return true;
        }
    } catch (e) {
        console.warn('Falha ao reconciliar status de desativacao:', e);
    }

    showSuccess('O funcionario foi desativado com sucesso');
    return false;
}

// Fallback global: garante desativacao mesmo se algum bloco posterior falhar na inicializacao.
(function installEmergencyEmployeeDeactivateHandler() {
    if (window.__employeeDeactivateEmergencyReady) return;
    window.__employeeDeactivateEmergencyReady = true;

    async function deactivateEmployeeRequest(employeeId) {
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
            if (data && data.success) return { success: true, data };
        } catch (err) {
            console.warn('Emergency handler: update_employee falhou, tentando fallback.', err);
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
            console.error('Emergency handler: delete_employee fallback falhou.', err);
            return { success: false, data: { message: 'Erro ao desativar funcionario.' } };
        }
    }

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('.btn-employee-deactivate[data-id]');
        if (!button) return;
        if (button.disabled) return;
        if (button.dataset.handlingDeactivate === '1') return;

        event.preventDefault();
        event.stopImmediatePropagation();

        const employeeId = button.getAttribute('data-id');
        if (!employeeId) return;

        button.dataset.handlingDeactivate = '1';
        try {
            const confirmed = (typeof showConfirm === 'function')
                ? await showConfirm(
                    'Desativar Funcionário',
                    'Deseja desativar este funcionário? Os dados serão preservados.',
                    'Sim, desativar',
                    'Cancelar'
                )
                : window.confirm('Deseja desativar este funcionário? Os dados serão preservados.');

            if (!confirmed) return;

            if (typeof updateRowToInactive === 'function') {
                updateRowToInactive(employeeId);
            }

            const result = await deactivateEmployeeRequest(employeeId);
            if (result.success) {
                if (typeof showSuccess === 'function') {
                    showSuccess('O funcionario foi desativado com sucesso');
                }
            } else if (typeof reconcileDeactivateResult === 'function') {
                await reconcileDeactivateResult(employeeId, (result.data && result.data.message) || 'Erro ao desativar funcionario.');
            } else if (typeof showError === 'function') {
                showError((result.data && result.data.message) || 'Erro ao desativar funcionario.');
            }
        } finally {
            button.dataset.handlingDeactivate = '0';
        }
    }, true);
})();

// Função para baixar PDF do funcionário
function downloadEmployeePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Coletar dados do modal
    const name = document.getElementById('view-name').textContent;
    const position = document.getElementById('view-position').textContent;
    const department = document.getElementById('view-department').textContent;
    const email = document.getElementById('view-email').textContent;
    const phone = document.getElementById('view-phone').textContent;
    const status = document.getElementById('view-status').textContent.trim();
    const birthDate = document.getElementById('view-birthDate').textContent;
    const nif = document.getElementById('view-nif').textContent;
    const niss = document.getElementById('view-niss').textContent;
    const address = document.getElementById('view-address').textContent;
    const emergencyContact = document.getElementById('view-emergencyContact').textContent;
    const startDate = document.getElementById('view-startDate').textContent;
    const endDate = document.getElementById('view-endDate') ? document.getElementById('view-endDate').textContent : '—';
    const contractType = document.getElementById('view-contractType').textContent;
    const vacationDays = document.getElementById('view-vacation-days') ? document.getElementById('view-vacation-days').textContent : '—';
    
    // Configurar fonte e cores
    doc.setFont('helvetica');
    
    // Título
    doc.setFontSize(20);
    doc.setTextColor(52, 152, 219); // Azul
    doc.text('FICHA DO FUNCIONÁRIO', 105, 20, { align: 'center' });
    
    // Linha separadora
    doc.setDrawColor(52, 152, 219);
    doc.setLineWidth(0.5);
    doc.line(20, 25, 190, 25);
    
    let yPos = 35;
    
    // Seção: Informações Básicas
    doc.setFontSize(14);
    doc.setTextColor(52, 152, 219);
    doc.text('INFORMA\u00c7\u00d5ES B\u00c1SICAS', 20, yPos);
    yPos += 8;
    
    doc.setFontSize(10);
    doc.setTextColor(0, 0, 0);
    doc.setFont('helvetica', 'bold');
    doc.text('Nome Completo:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(name, 65, yPos);
    yPos += 7;
    
    doc.setFont('helvetica', 'bold');
    doc.text('Cargo:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(position, 65, yPos);
    yPos += 7;
    
    doc.setFont('helvetica', 'bold');
    doc.text('Departamento:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(department, 65, yPos);
    yPos += 7;
    
    doc.setFont('helvetica', 'bold');
    doc.text('Email:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(email, 65, yPos);
    yPos += 7;
    
    doc.setFont('helvetica', 'bold');
    doc.text('Telefone:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(phone, 65, yPos);
    yPos += 7;
    
    doc.setFont('helvetica', 'bold');
    doc.text('Status:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(status, 65, yPos);
    yPos += 12;
    
    // Seção: Informações Pessoais
    doc.setFontSize(14);
    doc.setTextColor(52, 152, 219);
    doc.text('INFORMA\u00c7\u00d5ES PESSOAIS', 20, yPos);
    yPos += 8;
    
    doc.setFontSize(10);
    doc.setTextColor(0, 0, 0);
    doc.setFont('helvetica', 'bold');
    doc.text('Data de Nascimento:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(birthDate, 65, yPos);
    yPos += 7;
    
    doc.setFont('helvetica', 'bold');
    doc.text('NIF:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(nif, 65, yPos);
    yPos += 7;
    
    doc.setFont('helvetica', 'bold');
    doc.text('NISS:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(niss, 65, yPos);
    yPos += 7;
    
    doc.setFont('helvetica', 'bold');
    doc.text('Morada:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    const addressLines = doc.splitTextToSize(address, 120);
    doc.text(addressLines, 65, yPos);
    yPos += (addressLines.length * 7);
    
    doc.setFont('helvetica', 'bold');
    doc.text('Contacto de Emerg\u00eancia:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    const emergencyLines = doc.splitTextToSize(emergencyContact, 120);
    doc.text(emergencyLines, 65, yPos);
    yPos += (emergencyLines.length * 7) + 5;
    
    // Seção: Informações Contratuais
    doc.setFontSize(14);
    doc.setTextColor(52, 152, 219);
    doc.text('INFORMA\u00c7\u00d5ES CONTRATUAIS', 20, yPos);
    yPos += 8;
    
    doc.setFontSize(10);
    doc.setTextColor(0, 0, 0);
    doc.setFont('helvetica', 'bold');
    doc.text('Data de In\u00edcio:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(startDate, 65, yPos);
    yPos += 7;

    doc.setFont('helvetica', 'bold');
    doc.text('Data de Fim:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text((endDate || '—').replace(/<[^>]*>/g, '').trim(), 65, yPos);
    yPos += 7;

    doc.setFont('helvetica', 'bold');
    doc.text('Tipo de Contrato:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(contractType, 65, yPos);
    yPos += 7;

    doc.setFont('helvetica', 'bold');
    doc.text('Dias de Férias Anuais:', 20, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(vacationDays || '—', 65, yPos);

    // Rodapé
    doc.setFontSize(8);
    doc.setTextColor(150, 150, 150);
    doc.text('Gerado em: ' + new Date().toLocaleString('pt-PT'), 105, 285, { align: 'center' });
    doc.text('RHNeto Pro - Sistema de Gest\u00e3o de Recursos Humanos', 105, 290, { align: 'center' });
    
    // Salvar PDF
    const fileName = `Funcionario_${name.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.pdf`;
    doc.save(fileName);
    
    showSuccess('PDF baixado com sucesso!');
}

// ======== Gestão de documentos do funcionário ========
let currentViewedEmployeeId = null;

const documentsListMessages = {
    default: '<p style="color:#95a5a6;font-style:italic;text-align:center;padding:20px;">Selecione um funcionário para ver os documentos.</p>',
    loading: '<p style="color:#95a5a6;font-style:italic;text-align:center;padding:20px;">Carregando documentos...</p>',
    empty: '<p style="color:#95a5a6;font-style:italic;text-align:center;padding:20px;">Ainda não existem documentos anexados.</p>',
    error: '<p style="color:#e74c3c;text-align:center;padding:20px;">Não foi possível carregar os documentos agora.</p>'
};

function sanitizeText(value) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(value ?? '').replace(/[&<>"']/g, (char) => map[char]);
}

function setDocumentsListMessage(messageHtml) {
    const container = document.getElementById('view-documents-list');
    if (container) {
        container.innerHTML = messageHtml;
    }
}

function setDocumentsChecklistSummary(checklist) {
    const el = document.getElementById('view-documents-checklist');
    if (!el) return;

    if (!checklist || typeof checklist !== 'object') {
        el.innerHTML = 'Checklist documental indisponível.';
        return;
    }

    const percent = Number(checklist.completion_percent || 0);
    const missing = Array.isArray(checklist.missing_types) ? checklist.missing_types : [];
    const statusColor = percent >= 100 ? '#10b981' : (percent >= 60 ? '#f59e0b' : '#ef4444');

    el.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:6px;">
            <strong style="color:#e2e8f0;">Dossiê Digital</strong>
            <span style="font-size:.76rem; font-weight:700; color:${statusColor};">${percent}% completo</span>
        </div>
        <div style="height:8px; background:rgba(148,163,184,.28); border-radius:999px; overflow:hidden; margin-bottom:7px;">
            <div style="width:${Math.max(0, Math.min(100, percent))}%; height:100%; background:${statusColor};"></div>
        </div>
        <div style="font-size:.78rem; color:#cbd5e1;">
            ${missing.length === 0 ? 'Sem pendências obrigatórias.' : ('Pendentes: ' + missing.join(', '))}
        </div>
    `;
}

function resolveDocumentIcon(extension) {
    const ext = (extension || '').toLowerCase();
    const icons = {
        pdf: 'fa-file-pdf',
        doc: 'fa-file-word',
        docx: 'fa-file-word',
        xls: 'fa-file-excel',
        xlsx: 'fa-file-excel',
        csv: 'fa-file-excel',
        jpg: 'fa-file-image',
        jpeg: 'fa-file-image',
        png: 'fa-file-image',
        gif: 'fa-file-image',
        txt: 'fa-file-alt'
    };
    return icons[ext] || 'fa-file';
}

function formatFileSize(bytes) {
    const size = Number(bytes);
    if (!Number.isFinite(size) || size <= 0) return '';
    if (size >= 1024 * 1024 * 1024) return `${(size / (1024 * 1024 * 1024)).toFixed(2)} GB`;
    if (size >= 1024 * 1024) return `${(size / (1024 * 1024)).toFixed(2)} MB`;
    if (size >= 1024) return `${(size / 1024).toFixed(2)} KB`;
    return `${size} bytes`;
}

function renderEmployeeDocuments(documents) {
    const container = document.getElementById('view-documents-list');
    if (!container) return;

    if (!Array.isArray(documents) || documents.length === 0) {
        container.innerHTML = documentsListMessages.empty;
        return;
    }

    const cards = documents.map((doc) => {
        const name = sanitizeText(doc.document_name || 'Documento');
        const type = sanitizeText(doc.document_type || 'Sem tipo');
        const size = sanitizeText(doc.file_size_formatted || formatFileSize(doc.file_size));
        const createdAt = sanitizeText(doc.created_at_formatted || '');
        const expiryFormatted = sanitizeText(doc.expiry_date_formatted || '');
        const uploadedBy = sanitizeText(doc.uploaded_by_name || '');
        const description = doc.description ? `<div class="document-item-description" style="color:#bdc3c7;font-size:0.85rem;">${sanitizeText(doc.description)}</div>` : '';
        const iconClass = resolveDocumentIcon(doc.file_extension);
        const metaParts = [type];
        if (size) metaParts.push(size);
        if (createdAt) metaParts.push(createdAt);
        if (expiryFormatted) metaParts.push(`Validade: ${expiryFormatted}`);
        if (uploadedBy) metaParts.push(uploadedBy);
        const metaLine = metaParts.join(' · ');
        const encodedPath = encodeURIComponent(doc.file_path || '');
        const docId = Number(doc.id) || '';
        const expiryStatus = String(doc.expiry_status || 'no-expiry');
        const daysToExpiry = Number(doc.days_to_expiry);

        let expiryBadge = '';
        if (expiryStatus === 'expired') {
            expiryBadge = '<span style="display:inline-block; margin-top:6px; font-size:.73rem; font-weight:700; color:#fee2e2; background:#7f1d1d; border-radius:999px; padding:.16rem .5rem;">Expirado</span>';
        } else if (expiryStatus === 'expiring_soon') {
            const daysText = Number.isFinite(daysToExpiry) ? `${daysToExpiry} dia(s)` : 'breve';
            expiryBadge = `<span style="display:inline-block; margin-top:6px; font-size:.73rem; font-weight:700; color:#fef3c7; background:#92400e; border-radius:999px; padding:.16rem .5rem;">Expira em ${daysText}</span>`;
        } else if (expiryStatus === 'valid') {
            expiryBadge = '<span style="display:inline-block; margin-top:6px; font-size:.73rem; font-weight:700; color:#d1fae5; background:#065f46; border-radius:999px; padding:.16rem .5rem;">Válido</span>';
        }

        return `
            <div class="document-item" data-document-id="${docId}" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:14px;border:1px solid #34495e;border-radius:12px;background:#233142;margin-bottom:12px;">
                <div class="document-item-info" style="display:flex;gap:12px;flex:1;align-items:flex-start;">
                    <div class="document-item-icon" style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.08);color:#3498db;font-size:22px;">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="document-item-text" style="flex:1;">
                        <div class="document-item-name" style="font-weight:600;color:#ecf0f1;margin-bottom:4px;">${name}</div>
                        <div class="document-item-meta" style="color:#95a5a6;font-size:0.85rem;margin-bottom:6px;">${metaLine}</div>
                        ${expiryBadge}
                        ${description}
                    </div>
                </div>
                <div class="document-item-actions" style="display:flex;flex-direction:column;gap:8px;">
                    <button type="button" class="btn btn-sm doc-btn doc-open" data-doc-action="open" data-doc-path="${encodedPath}" style="background:#2980b9;color:#ffffff;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Ver</span>
                    </button>
                    <button type="button" class="btn btn-sm doc-btn doc-delete" data-doc-action="delete" data-doc-id="${docId}" style="background:#e74c3c;color:#ffffff;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-trash-alt"></i>
                        <span>Excluir</span>
                    </button>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = cards;
}

async function loadEmployeeDocuments(employeeId) {
    if (!employeeId) return;
    setDocumentsListMessage(documentsListMessages.loading);

    try {
        const response = await fetch(`../api/employees/get_documents.php?employee_id=${employeeId}`);
        const raw = await response.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (err) {
            console.error('Resposta inválida ao carregar documentos:', raw);
            throw new Error('Resposta inválida do servidor');
        }

        if (!data.success) {
            throw new Error(data.message || 'Não foi possível carregar os documentos');
        }

        renderEmployeeDocuments(data.documents || []);
        setDocumentsChecklistSummary(data.checklist || null);
    } catch (error) {
        console.error('Erro ao carregar documentos:', error);
        setDocumentsListMessage(documentsListMessages.error);
        setDocumentsChecklistSummary(null);
        showError('Erro ao carregar documentos.');
    }
}

async function loadEmployeeTurnoAndPonto(employeeId) {
    if (!employeeId) return;
    console.debug('loadEmployeeTurnoAndPonto chamado para id', employeeId);

    try {
        const response = await fetch(`../api/employees/get_employee_shift_attendance.php?employee_id=${employeeId}`);
        const text = await response.text();
        console.debug('Resposta bruto da API turno/ponto:', text);
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Não foi possível parsear JSON turno/ponto', e);
            throw e;
        }

        if (data.success) {
            // Preencher dados do Turno
            if (data.turno) {
                document.getElementById('view-turno-atual').textContent = data.turno.tipo || '—';
                document.getElementById('view-turno-horario').textContent = data.turno.horario_formatado || '—';
                document.getElementById('view-turno-status').textContent = data.turno.status ? data.turno.status.charAt(0).toUpperCase() + data.turno.status.slice(1) : '—';
            } else {
                document.getElementById('view-turno-atual').textContent = 'Sem turno atribuído';
                document.getElementById('view-turno-horario').textContent = '—';
                document.getElementById('view-turno-status').textContent = '—';
            }

            // Preencher dados do Último Registro de Ponto
            if (data.ultimo_ponto) {
                document.getElementById('view-ponto-data').textContent = data.ultimo_ponto.data_formatada || '—';
                document.getElementById('view-ponto-entrada').textContent = data.ultimo_ponto.hora_entrada || '—';
                document.getElementById('view-ponto-saida').textContent = data.ultimo_ponto.hora_saida || '—';
            } else {
                document.getElementById('view-ponto-data').textContent = 'Sem registros';
                document.getElementById('view-ponto-entrada').textContent = '—';
                document.getElementById('view-ponto-saida').textContent = '—';
            }
        } else {
            console.error('API turno/ponto retornou sucesso=false', data);
            // Em caso de erro, mostrar valores padrão
            document.getElementById('view-turno-atual').textContent = '—';
            document.getElementById('view-turno-horario').textContent = '—';
            document.getElementById('view-turno-status').textContent = '—';
            document.getElementById('view-ponto-data').textContent = '—';
            document.getElementById('view-ponto-entrada').textContent = '—';
            document.getElementById('view-ponto-saida').textContent = '—';
        }
    } catch (error) {
        console.error('Erro ao carregar turno e ponto:', error);
        // Em caso de erro, mostrar valores padrão
        document.getElementById('view-turno-atual').textContent = '—';
        document.getElementById('view-turno-horario').textContent = '—';
        document.getElementById('view-ponto-data').textContent = '—';
        document.getElementById('view-ponto-entrada').textContent = '—';
        document.getElementById('view-ponto-saida').textContent = '—';
    }
}

function openUploadDocumentModal() {
    if (!currentViewedEmployeeId) {
        showWarning('Selecione um funcionário antes de anexar documentos.');
        return;
    }

    const form = document.getElementById('uploadDocumentForm');
    if (form) form.reset();

    const hiddenEmployeeId = document.getElementById('upload-employee-id');
    if (hiddenEmployeeId) hiddenEmployeeId.value = currentViewedEmployeeId;

    // Repor drop zone
    const udmLabel = document.getElementById('udm-file-name');
    if (udmLabel) udmLabel.textContent = 'Clique para escolher ou arraste aqui';
    const udmDz = document.getElementById('udmDropzone');
    if (udmDz) udmDz.classList.remove('udm-has-file');

    const modal = document.getElementById('uploadDocumentModal');
    if (modal) modal.style.display = 'block';
}

function closeViewEmployeeModal(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const modal = document.getElementById('viewEmployeeModal');
    if (modal) {
        modal.style.display = 'none';
    }
    currentViewedEmployeeId = null;
    const hiddenEmployeeId = document.getElementById('upload-employee-id');
    if (hiddenEmployeeId) {
        hiddenEmployeeId.value = '';
    }
    setDocumentsListMessage(documentsListMessages.default);
    setDocumentsChecklistSummary(null);
}

function previewImage(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    // Validações
    if (!file.type.startsWith('image/')) {
        showError('Por favor, selecione uma imagem válida');
        event.target.value = '';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        showError('Imagem muito grande! Tamanho máximo: 2MB');
        event.target.value = '';
        return;
    }

    // Preview local imediato
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById('profilePreview');
        if (img) img.src = e.target.result;
    };
    reader.readAsDataURL(file);

    // Upload para o servidor
    const formData = new FormData();
    formData.append('profile_photo', file);

    fetch('upload_foto.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const img = document.getElementById('profilePreview');
                if (img && data.path) img.src = data.path;

                // Atualiza previews de edição/adicionar, se existirem
                const editPreview = document.getElementById('edit-avatar-preview');
                const addPreview = document.getElementById('add-avatar-preview');
                if (editPreview && data.path) editPreview.innerHTML = `<img src="${data.path}" alt="Perfil" style="width:100%;height:100%;object-fit:cover;">`;
                if (addPreview && data.path) addPreview.innerHTML = `<img src="${data.path}" alt="Perfil" style="width:100%;height:100%;object-fit:cover;">`;

                showSuccess(data.message || 'Foto atualizada com sucesso!');
            } else {
                showError(data.message || 'Erro ao enviar foto');
            }
        })
        .catch(err => {
            console.error('Erro no upload da foto:', err);
            showError('Erro no upload da foto.');
        });
}

document.addEventListener('DOMContentLoaded', function() {
    setDocumentsListMessage(documentsListMessages.default);
    
    // Seleciona todos os botões de ação APENAS da seção de funcionários
    const editButtons = document.querySelectorAll('#funcionarios-section .btn-edit');
    const deleteButtons = document.querySelectorAll('#funcionarios-section .btn-employee-deactivate');
    
    // Seleciona o modal e o formulário de edição
    const editModal = document.getElementById('editEmployeeModal');
    const editForm = document.getElementById('editEmployeeForm');
    const closeBtn = document.querySelector('.close-btn');
    
    console.log('Botões de editar encontrados:', editButtons.length);
    console.log('Botões de excluir encontrados:', deleteButtons.length);

    // Delegação de eventos na tabela de funcionários (alternativa mais robusta)
    const employeesTable = document.querySelector('#funcionarios-section #employeesTable tbody');
    if (employeesTable) {
        employeesTable.addEventListener('click', function(e) {
            const editBtn = e.target.closest('.btn-edit');
            const deleteBtn = e.target.closest('.btn-employee-deactivate');
            const activateBtn = e.target.closest('.btn-activate');
            const viewBtn = e.target.closest('.btn-view');
            
            // Botão Ver Detalhes
            if (viewBtn) {
                e.preventDefault();
                e.stopPropagation();
                
                const employeeId = viewBtn.getAttribute('data-id');
                currentViewedEmployeeId = null;
                setDocumentsListMessage(documentsListMessages.loading);
                
                fetch(`../api/employees/get_employee.php?id=${employeeId}`)
                    .then(response => {
                        if (!response.ok) throw new Error('Não foi possível buscar os dados do funcionário.');
                        return response.json();
                    })
                    .then(employee => {
                        if (employee.id) {
                            // Atualizar avatar
                            const viewAvatar = document.getElementById('view-avatar');
                            if (employee.profile_picture) {
                                viewAvatar.innerHTML = `<img src="../${employee.profile_picture}" alt="${employee.name}" style="width: 100%; height: 100%; object-fit: cover;">`;
                            } else {
                                const initials = employee.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                                viewAvatar.innerHTML = `<span style="font-size: 48px;">${initials}</span>`;
                            }
                            
                            // Preencher dados básicos
                            document.getElementById('view-name').textContent = employee.name || '—';
                            document.getElementById('view-position').textContent = employee.position || '—';
                            document.getElementById('view-department').textContent = employee.department || '—';
                            document.getElementById('view-email').textContent = employee.email || '—';
                            document.getElementById('view-phone').textContent = employee.phone || '—';
                            
                            // Status com badge
                            const statusEl = document.getElementById('view-status');
                            let statusHtml = '';
                            if (employee.status === 'active') {
                                statusHtml = '<span class="status-badge status-active">Ativo</span>';
                            } else if (employee.status === 'inactive') {
                                statusHtml = '<span class="status-badge status-inactive">Inativo</span>';
                            } else if (employee.status === 'ferias') {
                                statusHtml = '<span class="status-badge status-ferias">Férias</span>';
                            } else {
                                statusHtml = employee.status || '—';
                            }
                            statusEl.innerHTML = statusHtml;
                            
                            // Dados pessoais
                            document.getElementById('view-birthDate').textContent = employee.birthDate ? new Date(employee.birthDate).toLocaleDateString('pt-PT') : '—';
                            document.getElementById('view-nif').textContent = employee.nif || '—';
                            document.getElementById('view-niss').textContent = employee.niss || '—';
                            document.getElementById('view-address').textContent = employee.address || '—';
                            document.getElementById('view-emergencyContact').textContent = employee.emergencyContact || '—';
                            
                            // Dados contratuais
                            document.getElementById('view-startDate').textContent = employee.startDate ? new Date(employee.startDate).toLocaleDateString('pt-PT') : '—';

                            const viewEndDateEl = document.getElementById('view-endDate');
                            if (viewEndDateEl) {
                                if (employee.endDate && employee.endDate !== '0000-00-00') {
                                    const endDt = new Date(employee.endDate);
                                    const today = new Date(); today.setHours(0,0,0,0);
                                    const daysLeft = Math.round((endDt - today) / 86400000);
                                    let endLabel = endDt.toLocaleDateString('pt-PT');
                                    if (daysLeft < 0) endLabel += ' <span style="color:#ef4444;font-size:.8em;">(Expirado)</span>';
                                    else if (daysLeft <= 30) endLabel += ` <span style="color:#f59e0b;font-size:.8em;">(${daysLeft}d restantes)</span>`;
                                    viewEndDateEl.innerHTML = endLabel;
                                } else {
                                    viewEndDateEl.textContent = '—';
                                }
                            }

                            const contractTypes = {
                                'efetivo': 'Efetivo',
                                'temporario': 'Temporário',
                                'part-time': 'Part-time',
                                'estagio': 'Estágio',
                                'freelancer': 'Freelancer'
                            };
                            document.getElementById('view-contractType').textContent = contractTypes[employee.contractType] || employee.contractType || '—';

                            const viewVacDaysEl = document.getElementById('view-vacation-days');
                            if (viewVacDaysEl) viewVacDaysEl.textContent = (employee.vacation_days != null ? employee.vacation_days : 22) + ' dias';
                            currentViewedEmployeeId = Number(employee.id);
                            const hiddenEmployeeId = document.getElementById('upload-employee-id');
                            if (hiddenEmployeeId) {
                                hiddenEmployeeId.value = employee.id;
                            }
                            loadEmployeeDocuments(employee.id);
                            
                            // Carregar Turno e Registro de Ponto
                            loadEmployeeTurnoAndPonto(employee.id);
                            
                            // Mostrar modal
                            document.getElementById('viewEmployeeModal').style.display = 'block';
                        } else {
                            showError('Funcionário não encontrado.');
                            setDocumentsListMessage(documentsListMessages.error);
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        showError('Erro ao carregar dados do funcionário.');
                        currentViewedEmployeeId = null;
                        setDocumentsListMessage(documentsListMessages.error);
                    });
                
                return;
            }
            
            if (editBtn) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Botão editar clicado via delegação!');
                
                if (editBtn.disabled || editBtn.classList.contains('btn-disabled')) {
                    showWarning('Funcionário inativo ou em férias não pode ser editado.');
                    return;
                }
                
                const employeeId = editBtn.getAttribute('data-id');
                console.log('ID do funcionário:', employeeId);
                
                fetch(`../api/employees/get_employee.php?id=${employeeId}`)
                    .then(response => {
                        console.log('Status da resposta:', response.status);
                        if (!response.ok) throw new Error('Não foi possível buscar os dados do funcionário.');
                        return response.json();
                    })
                    .then(employee => {
                        console.log('Dados do funcionário:', employee);
                        if (employee.id) {
                            // Função para formatar data para input type="date" (YYYY-MM-DD)
                            const formatDateForInput = (dateString) => {
                                if (!dateString) return '';
                                // Remove hora se existir e pega apenas a data
                                return dateString.split(' ')[0];
                            };
                            
                            document.getElementById('employee-id').value = employee.id;
                            document.getElementById('edit-name').value = employee.name || '';
                            document.getElementById('edit-position').value = employee.position || '';
                            document.getElementById('edit-department').value = employee.department || '';
                            document.getElementById('edit-email').value = employee.email || '';
                            document.getElementById('edit-phone').value = employee.phone || '';
                            document.getElementById('edit-startDate').value = formatDateForInput(employee.startDate);
                            const editEndDate = document.getElementById('edit-endDate');
                            if (editEndDate) editEndDate.value = formatDateForInput(employee.endDate);
                            document.getElementById('edit-status').value = employee.status || 'active';

                            // Novos campos adicionais
                            document.getElementById('edit-birthDate').value = formatDateForInput(employee.birthDate);
                            document.getElementById('edit-nif').value = employee.nif || '';
                            document.getElementById('edit-niss').value = employee.niss || '';
                            document.getElementById('edit-address').value = employee.address || '';
                            document.getElementById('edit-emergencyContact').value = employee.emergencyContact || '';
                            document.getElementById('edit-contractType').value = employee.contractType || '';
                            document.getElementById('edit-pin').value = employee.pin || '';
                            const editApprovalReason = document.getElementById('edit-approval-reason');
                            if (editApprovalReason) editApprovalReason.value = '';

                            // Campos de remuneração
                            const editSalaryBase = document.getElementById('edit-salary_base');
                            const editSubsidio = document.getElementById('edit-subsidio_alimentacao');
                            const editBonus = document.getElementById('edit-bonus');
                            if (editSalaryBase) editSalaryBase.value = employee.salary_base || '';
                            if (editSubsidio) editSubsidio.value = employee.subsidio_alimentacao || '';
                            if (editBonus) editBonus.value = employee.bonus || '';

                            const editVacDays = document.getElementById('edit-vacation-days');
                            if (editVacDays) editVacDays.value = employee.vacation_days ?? 22;

                            // Atualizar preview do avatar
                            const editAvatarPreview = document.getElementById('edit-avatar-preview');
                            const editAvatarInitials = document.getElementById('edit-avatar-initials');
                            if (employee.profile_picture) {
                                editAvatarPreview.innerHTML = `<img src="../${employee.profile_picture}" alt="${employee.name}" style="width: 100%; height: 100%; object-fit: cover;">`;
                            } else {
                                const initials = employee.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                                editAvatarInitials.textContent = initials;
                            }
                            
                            editModal.style.display = 'block';
                        } else {
                            showError('Funcionário não encontrado.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        showError('Erro ao carregar dados do funcionário.');
                    });
            }
        });
    }


    // Lógica legada para o botão de desativar (mantida como fallback)
    deleteButtons.forEach(button => {
        button.addEventListener('click', async function() {
            // Fluxo legado: quando o interceptador global está ativo, não executa este handler.
            if (document.body.dataset.softDeleteReady === '1') {
                return;
            }

            const employeeId = this.getAttribute('data-id');

            const confirmed = await showConfirm(
                'Desativar Funcionário',
                'Deseja realmente desativar este funcionário? Os dados serão preservados.',
                'Sim, desativar',
                'Cancelar'
            );

            if (confirmed) {
                const formData = new FormData();
                formData.append('id', employeeId);
                formData.append('status', 'inactive');

                fetch('../api/employees/update_employee.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) throw new Error('Erro ao desativar. Status: ' + response.status);
                    return parseJsonResponseSafe(response);
                })
                .then(async (data) => {
                    if (data.success) {
                        updateRowToInactive(employeeId);
                        showSuccess('O funcionario foi desativado com sucesso');
                    } else {
                        await reconcileDeactivateResult(employeeId, data.message || 'Erro ao desativar funcionario.');
                    }
                })
                .catch(async (error) => {
                    console.error('Erro:', error);
                    await reconcileDeactivateResult(employeeId, 'Erro ao desativar funcionario.');
                });
            }
        });
    });

    // Lógica para o botão de EDITAR
    editButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            console.log('Botão de editar clicado!');
            console.log('Botão:', this);
            console.log('Disabled:', this.disabled);
            
            if (this.disabled) {
                showWarning('Funcionário inativo ou em férias não pode ser editado.');
                return;
            }
            const employeeId = this.getAttribute('data-id');
            console.log('ID do funcionário:', employeeId);

            fetch(`../api/employees/get_employee.php?id=${employeeId}`)
                .then(response => {
                    console.log('Status da resposta:', response.status);
                    if (!response.ok) throw new Error('Não foi possível buscar os dados do funcionário.');
                    return response.json();
                })
                .then(employee => {
                    console.log('Dados do funcionário:', employee);
                    if (employee.id) {
                        // Popula todos os campos do formulário (Adicionado '|| ""' para evitar valores nulos)
                        document.getElementById('employee-id').value = employee.id;
                        document.getElementById('edit-name').value = employee.name || '';
                        document.getElementById('edit-position').value = employee.position || '';
                        document.getElementById('edit-department').value = employee.department || '';
                        document.getElementById('edit-email').value = employee.email || '';
                        document.getElementById('edit-phone').value = employee.phone || '';
                        document.getElementById('edit-startDate').value = employee.startDate || '';
                        const editEndDate2 = document.getElementById('edit-endDate');
                        if (editEndDate2) editEndDate2.value = employee.endDate || '';
                        const editVacDays2 = document.getElementById('edit-vacation-days');
                        if (editVacDays2) editVacDays2.value = employee.vacation_days ?? 22;
                        document.getElementById('edit-status').value = employee.status || 'active';
                        const editApprovalReason = document.getElementById('edit-approval-reason');
                        if (editApprovalReason) editApprovalReason.value = '';

                        editModal.style.display = 'block';
                    } else {
                        showError('Funcionário não encontrado.');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showError('Erro ao carregar dados do funcionário.');
                });
        });
    });

    // Lógica para fechar o modal de edição
    closeBtn.addEventListener('click', () => {
        editModal.style.display = 'none';
    });
    
    // Fechar modal de visualização
    document.querySelectorAll('.close-btn-view').forEach(btn => {
        btn.addEventListener('click', (event) => closeViewEmployeeModal(event));
    });
    
    // Fechar modal de upload (× e Cancelar)
    const uploadDocModal = document.getElementById('uploadDocumentModal');
    document.querySelectorAll('.close-btn-upload-doc').forEach(btn => {
        btn.addEventListener('click', () => {
            if (uploadDocModal) uploadDocModal.style.display = 'none';
        });
    });

    // Drop zone — mostrar nome do ficheiro ao selecionar
    const udmInput = document.getElementById('upload-document-file');
    const udmLabel = document.getElementById('udm-file-name');
    const udmDropzone = document.getElementById('udmDropzone');
    if (udmInput) {
        udmInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file && udmLabel) {
                udmLabel.textContent = file.name;
                if (udmDropzone) udmDropzone.classList.add('udm-has-file');
            }
        });
    }

    const documentsListContainer = document.getElementById('view-documents-list');
    if (documentsListContainer) {
        documentsListContainer.addEventListener('click', async (event) => {
            const actionButton = event.target.closest('[data-doc-action]');
            if (!actionButton) return;

            const action = actionButton.dataset.docAction;

            if (action === 'open') {
                event.preventDefault();
                let path = '';
                try {
                    path = decodeURIComponent(actionButton.dataset.docPath || '');
                } catch (err) {
                    showError('Caminho inválido.');
                    return;
                }
                if (!path) {
                    showWarning('Arquivo não disponível.');
                    return;
                }
                const normalizedPath = path.replace(/^\/+/, '');
                if (normalizedPath.includes('..')) {
                    showError('Caminho inválido.');
                    return;
                }
                if (/^[a-z]+:/i.test(normalizedPath)) {
                    showError('Caminho inválido.');
                    return;
                }
                const url = `../${normalizedPath}`;
                const openedWindow = window.open(url, '_blank');
                if (openedWindow) {
                    openedWindow.opener = null;
                }
                return;
            }

            if (action === 'delete') {
                event.preventDefault();
                const documentId = Number(actionButton.dataset.docId);
                if (!documentId) {
                    showWarning('Documento inválido.');
                    return;
                }

                const confirmed = await showConfirm(
                    'Excluir Documento',
                    'Deseja realmente excluir este documento?',
                    'Sim, excluir',
                    'Cancelar'
                );

                if (!confirmed) return;

                actionButton.disabled = true;
                try {
                    const response = await fetch('../api/employees/delete_document.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ document_id: documentId })
                    });
                    const raw = await response.text();
                    let data;
                    try {
                        data = JSON.parse(raw);
                    } catch (err) {
                        console.error('Resposta inválida ao excluir documento:', raw);
                        throw new Error('Resposta inválida do servidor');
                    }

                    if (!data.success) {
                        throw new Error(data.message || 'Não foi possível excluir o documento.');
                    }

                    showSuccess('Documento excluído com sucesso.');
                    if (currentViewedEmployeeId) {
                        loadEmployeeDocuments(currentViewedEmployeeId);
                    }
                } catch (error) {
                    console.error('Erro ao excluir documento:', error);
                    showError(error.message || 'Erro ao excluir documento.');
                } finally {
                    actionButton.disabled = false;
                }
            }
        });
    }

    const uploadDocumentForm = document.getElementById('uploadDocumentForm');
    if (uploadDocumentForm) {
        uploadDocumentForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!currentViewedEmployeeId) {
                showWarning('Selecione um funcionário antes de anexar documentos.');
                return;
            }

            const fileInput = document.getElementById('upload-document-file');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showWarning('Selecione um arquivo para anexar.');
                return;
            }

            const submitButton = uploadDocumentForm.querySelector('button[type="submit"]');
            const originalButtonHtml = submitButton ? submitButton.innerHTML : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            }

            const formData = new FormData(uploadDocumentForm);
            formData.set('employee_id', currentViewedEmployeeId);

            try {
                const response = await fetch('../api/employees/upload_document.php', {
                    method: 'POST',
                    body: formData
                });
                const raw = await response.text();
                let data;
                try {
                    data = JSON.parse(raw);
                } catch (err) {
                    console.error('Resposta inválida ao enviar documento:', raw);
                    throw new Error('Resposta inválida do servidor');
                }

                if (!data.success) {
                    throw new Error(data.message || 'Não foi possível enviar o documento.');
                }

                showSuccess('Documento anexado com sucesso!');
                uploadDocumentForm.reset();
                const modal = document.getElementById('uploadDocumentModal');
                if (modal) {
                    modal.style.display = 'none';
                }
                if (currentViewedEmployeeId) {
                    loadEmployeeDocuments(currentViewedEmployeeId);
                }
            } catch (error) {
                console.error('Erro ao enviar documento:', error);
                showError(error.message || 'Erro ao enviar documento.');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonHtml;
                }
            }
        });
    }

    // Comentado: eventos que fechavam modais ao clicar fora
    // Agora os modais só fecham com o botão X
    /*
    window.addEventListener('click', (event) => {
        if (event.target == editModal) {
            editModal.style.display = 'none';
        }
        if (event.target == viewModal) {
            viewModal.style.display = 'none';
        }
        if (event.target == uploadDocModal) {
            uploadDocModal.style.display = 'none';
        }
    });
    */

    // Lógica para SALVAR as alterações do formulário
    editForm.addEventListener('submit', function(event) {
        event.preventDefault();

        const formData = new FormData(this);
        
        // Debug: Mostrar todos os dados sendo enviados
        console.log('Dados sendo enviados:');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }

        fetch('../api/employees/update_employee.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess(data.message || 'Funcionário atualizado com sucesso!');
                editModal.style.display = 'none';

                if (data.approval_required) {
                    // Alterações críticas ficam pendentes de aprovação; recarrega para refletir painel e estado real.
                    window.location.reload();
                    return;
                }
                
                // Atualizar a linha na tabela sem recarregar
                const employeeId = document.getElementById('employee-id').value;
                const row = document.querySelector(`tr button[data-id="${employeeId}"]`)?.closest('tr');
                
                if (row) {
                    const formData = new FormData(editForm);
                    const status = formData.get('status');
                    const employeeName = formData.get('name');
                    
                    // Atualizar avatar dentro de .fr-av
                    const avatarEl = row.querySelector('.fr-av');
                    if (avatarEl) {
                        if (data.profile_picture) {
                            avatarEl.innerHTML = `<img src="../${data.profile_picture}" alt="${employeeName}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
                        } else {
                            const currentImg = avatarEl.querySelector('img');
                            if (!currentImg) {
                                const initials = employeeName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                                avatarEl.textContent = initials;
                            }
                        }
                    }

                    // Atualizar nome, email, cargo, departamento via selectors
                    const nameEl = row.querySelector('.fr-emp-name');
                    if (nameEl) nameEl.textContent = employeeName;
                    const emailEl = row.querySelector('.fr-emp-email');
                    if (emailEl) emailEl.textContent = formData.get('email') || '';
                    const posEl = row.querySelector('.fr-role-pos');
                    if (posEl) posEl.textContent = formData.get('position') || '—';
                    const deptEl = row.querySelector('.fr-role-dept');
                    if (deptEl) deptEl.textContent = formData.get('department') || '—';

                    // Actualizar data attributes para os filtros
                    row.dataset.name = (employeeName || '').toLowerCase();
                    row.dataset.position = formData.get('position') || '';
                    row.dataset.department = formData.get('department') || '';
                    row.dataset.email = formData.get('email') || '';
                    row.dataset.contractType = formData.get('contractType') || '';
                    row.dataset.status = status;
                    
                    // Atualizar badge de status (célula 4, não 5!)
                    let badgeClass = 'status-badge ';
                    let label = '';
                    let isDisabledRow = false;
                    if (status === 'active') {
                        badgeClass += 'status-active';
                        label = 'Ativo';
                        row.classList.remove('disabled-row');
                    } else if (status === 'inactive') {
                        badgeClass += 'status-inactive';
                        label = 'Inativo';
                        row.classList.add('disabled-row');
                        isDisabledRow = true;
                    } else if (status === 'ferias') {
                        badgeClass += 'status-ferias';
                        label = 'Férias';
                        row.classList.add('disabled-row');
                        isDisabledRow = true;
                    }
                    row.cells[3].innerHTML = `<span class="${badgeClass}" id="status-${employeeId}">${label}</span>`;
                    
                    // Atualizar botões de ação (célula 4 — fr-td-acts)
                    const actionsCell = row.cells[4];
                    const editBtn = actionsCell.querySelector('.btn-edit');
                    const deleteBtn = actionsCell.querySelector('.btn-employee-deactivate');
                    let activateBtn = actionsCell.querySelector('.btn-activate');
                    
                    if (isDisabledRow) {
                        // Desabilitar botão de editar
                        if (editBtn) {
                            editBtn.classList.add('btn-disabled');
                            editBtn.setAttribute('disabled', 'true');
                            editBtn.setAttribute('title', 'Funcionário inativo ou em férias');
                        }

                        // Desabilitar botão de desativar somente para status inativo
                        if (deleteBtn) {
                            if (status === 'inactive') {
                                deleteBtn.classList.add('btn-disabled');
                                deleteBtn.setAttribute('disabled', 'true');
                                deleteBtn.setAttribute('title', 'Funcionário já desativado');
                            } else {
                                deleteBtn.classList.remove('btn-disabled');
                                deleteBtn.removeAttribute('disabled');
                                deleteBtn.setAttribute('title', 'Desativar');
                            }
                        }
                        
                        // Adicionar botão de ativar se não existir
                        if (!activateBtn) {
                            const btnDiv = actionsCell.querySelector('div');
                            activateBtn = document.createElement('button');
                            activateBtn.className = 'fr-btn fr-btn-activate btn-activate';
                            activateBtn.setAttribute('data-id', employeeId);
                            activateBtn.title = 'Ativar';
                            activateBtn.innerHTML = '<i class="fas fa-user-check"></i>';
                            btnDiv.appendChild(activateBtn);
                            
                            // Adicionar event listener ao novo botão
                            activateBtn.addEventListener('click', async function() {
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
                                        row.classList.remove('disabled-row');
                                        
                                        const statusBadge = row.querySelector(`#status-${employeeId}`);
                                        if (statusBadge) {
                                            statusBadge.className = 'status-badge status-active';
                                            statusBadge.textContent = 'Ativo';
                                        }
                                        
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
                                        
                                        activateBtn.remove();
                                    } else {
                                        showError(data.message || 'Erro ao ativar funcionário');
                                    }
                                })
                                .catch(err => {
                                    console.error(err);
                                    showError('Erro ao ativar funcionário');
                                });
                            });
                        }
                    } else {
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
                        
                        // Remover botão de ativar se existir
                        if (activateBtn) {
                            activateBtn.remove();
                        }
                    }
                }
            } else {
                showError(data.message || 'Erro ao atualizar o funcionário.');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showError('Não foi possível salvar as alterações. Tente novamente mais tarde.');
        });
    });

    // ============= MODAL ADICIONAR FUNCIONÁRIO =============
    const addModal = document.getElementById('addEmployeeModal');
    const addForm = document.getElementById('addEmployeeForm');
    const btnAddEmployee = document.getElementById('btnAddEmployee');

    // Abrir modal de adicionar
    if (btnAddEmployee) {
        btnAddEmployee.addEventListener('click', () => {
            if (addForm && typeof addForm.reset === 'function') {
                addForm.reset();
            }
            const addAvatarPreview = document.getElementById('add-avatar-preview');
            if (addAvatarPreview) {
                addAvatarPreview.innerHTML = '<i class="fas fa-user"></i>';
            }
            if (addModal) addModal.style.display = 'block';
        });
    }

    // Fechar modal de adicionar (× e botão Cancelar)
    document.querySelectorAll('.close-btn-add').forEach(btn => {
        btn.addEventListener('click', () => {
            if (addModal) addModal.style.display = 'none';
        });
    });

    // Comentado: evento que fechava modal ao clicar fora
    // Agora o modal só fecha com o botão X
    /*
    window.addEventListener('click', (event) => {
        if (event.target == addModal) {
            addModal.style.display = 'none';
        }
    });
    */
    
    // ============= PREVIEW DA FOTO DE PERFIL (ADICIONAR) =============
    const addProfilePictureInput = document.getElementById('add-profile-picture');
    const addAvatarPreview = document.getElementById('add-avatar-preview');
    
    console.log('🔍 Procurando elementos de upload...', {
        input: addProfilePictureInput,
        preview: addAvatarPreview
    });
    
    if (addProfilePictureInput) {
        console.log('✅ Input de foto encontrado!');
        addProfilePictureInput.addEventListener('change', function(e) {
            console.log('📸 Arquivo selecionado:', e.target.files[0]);
            const file = e.target.files[0];
            if (file) {
                console.log('📁 Arquivo:', {
                    nome: file.name,
                    tamanho: file.size,
                    tipo: file.type
                });
                
                // Validar tamanho (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    showError('Imagem muito grande! Tamanho máximo: 2MB');
                    e.target.value = '';
                    return;
                }
                
                // Validar tipo
                if (!file.type.startsWith('image/')) {
                    showError('Por favor, selecione uma imagem válida');
                    e.target.value = '';
                    return;
                }
                
                console.log('✅ Validações OK! Carregando preview...');
                
                // Mostrar preview
                const reader = new FileReader();
                reader.onload = function(event) {
                    console.log('🎨 Preview carregado, atualizando DOM...');
                    addAvatarPreview.innerHTML = `<img src="${event.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                    showSuccess('Preview carregado! Agora clique em "Adicionar Funcionário"');
                };
                reader.onerror = function(error) {
                    console.error('❌ Erro ao carregar imagem:', error);
                    showError('Erro ao carregar preview da imagem');
                };
                reader.readAsDataURL(file);
            }
        });
    } else {
        console.warn('⚠️ Input de foto NÃO encontrado!');
    }
    
    // ============= PREVIEW DA FOTO DE PERFIL (EDITAR) =============
    const editProfilePictureInput = document.getElementById('edit-profile-picture');
    const editAvatarPreview = document.getElementById('edit-avatar-preview');
    
    console.log('🔍 Procurando elementos de edição...', {
        input: editProfilePictureInput,
        preview: editAvatarPreview
    });
    
    if (editProfilePictureInput) {
        console.log('✅ Input de edição encontrado!');
        editProfilePictureInput.addEventListener('change', function(e) {
            console.log('📸 Arquivo de edição selecionado:', e.target.files[0]);
            const file = e.target.files[0];
            if (file) {
                console.log('📁 Arquivo:', {
                    nome: file.name,
                    tamanho: file.size,
                    tipo: file.type
                });
                
                // Validar tamanho (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    showError('Imagem muito grande! Tamanho máximo: 2MB');
                    e.target.value = '';
                    return;
                }
                
                // Validar tipo
                if (!file.type.startsWith('image/')) {
                    showError('Por favor, selecione uma imagem válida');
                    e.target.value = '';
                    return;
                }
                
                console.log('✅ Validações OK! Carregando preview de edição...');
                
                // Mostrar preview
                const reader = new FileReader();
                reader.onload = function(event) {
                    console.log('🎨 Preview de edição carregado, atualizando DOM...');
                    editAvatarPreview.innerHTML = `<img src="${event.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                    showSuccess('Preview carregado! Agora clique em "Salvar Alterações"');
                };
                reader.onerror = function(error) {
                    console.error('❌ Erro ao carregar imagem:', error);
                    showError('Erro ao carregar preview da imagem');
                };
                reader.readAsDataURL(file);
            }
        });
    } else {
        console.warn('⚠️ Input de edição NÃO encontrado!');
    }

    // Submeter formulário de adicionar funcionário
    if (addForm) {
        addForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(this);
            
            // Log para debug
            console.log('📤 Enviando formulário de adicionar funcionário...');
            console.log('📋 FormData entries:');
            for (let pair of formData.entries()) {
                if (pair[0] === 'profile_picture') {
                    console.log(`  ${pair[0]}:`, pair[1]); // File object
                } else {
                    console.log(`  ${pair[0]}: ${pair[1]}`);
                }
            }

            fetch('../api/employees/create_employee.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('📨 Resposta recebida:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('📦 Dados da resposta:', data);
                if (data.success) {
                    showSuccess('Funcionário adicionado com sucesso!');
                    addModal.style.display = 'none';
                    
                    // Adicionar nova linha à tabela sem recarregar
                    const formData = new FormData(addForm);
                    const status = formData.get('status');
                    const startDate = formData.get('startDate');
                    const employeeId = data.employee_id;
                    
                    // Validar se temos um ID válido
                    if (!employeeId) {
                        console.error('ID do funcionário não retornado pela API');
                        showError('Erro: ID do funcionário não foi gerado');
                        return;
                    }
                    
                    // Formatar data
                    const dateObj = new Date(startDate);
                    const formattedDate = dateObj.toLocaleDateString('pt-PT');
                    
                    // Criar badge de status
                    let badgeClass = 'status-badge ';
                    let label = '';
                    let isDisabledRow = false;
                    if (status === 'active') {
                        badgeClass += 'status-active';
                        label = 'Ativo';
                    } else if (status === 'inactive') {
                        badgeClass += 'status-inactive';
                        label = 'Inativo';
                        isDisabledRow = true;
                    } else if (status === 'ferias') {
                        badgeClass += 'status-ferias';
                        label = 'Férias';
                        isDisabledRow = true;
                    }
                    
                    // Criar nova linha
                    const tbody = document.querySelector('#employeesTable tbody');
                    const newRow = document.createElement('tr');
                    if (isDisabledRow) newRow.classList.add('disabled-row');
                    
                    // Gerar avatar com iniciais ou foto
                    const employeeName = formData.get('name');
                    const initials = employeeName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                    
                    // Extrair primeiro e último nome
                    const nameParts = employeeName.split(' ');
                    const displayName = nameParts.length > 1 
                        ? `${nameParts[0]} ${nameParts[nameParts.length - 1]}`
                        : employeeName;
                    
                    // Se houver foto no response, usar ela, senão usar iniciais
                    let avatarContent = initials;
                    if (data.profile_picture) {
                        avatarContent = `<img src="../${data.profile_picture}" alt="${employeeName}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
                    }

                    // Gradiente do avatar pela inicial
                    const _gp = ['#667eea,#764ba2','#f093fb,#f5576c','#4facfe,#00f2fe','#43e97b,#38f9d7','#fa709a,#fee140','#a18cd1,#fbc2eb','#fccb90,#d57eeb','#96fbc4,#f9f7d9','#ffecd2,#fcb69f','#a1c4fd,#c2e9fb'];
                    const [_gc1, _gc2] = _gp[initials.charCodeAt(0) % _gp.length].split(',');

                    // Data attributes para os filtros
                    newRow.setAttribute('data-id', employeeId);
                    newRow.setAttribute('data-status', status);
                    newRow.setAttribute('data-name', (formData.get('name') || '').toLowerCase());
                    newRow.setAttribute('data-position', formData.get('position') || '');
                    newRow.setAttribute('data-department', formData.get('department') || '');
                    newRow.setAttribute('data-email', formData.get('email') || '');
                    newRow.setAttribute('data-contract-type', formData.get('contractType') || '');
                    newRow.setAttribute('data-end-date', formData.get('endDate') || '');
                    newRow.setAttribute('data-vacation-days', formData.get('vacation_days') || '22');

                    newRow.innerHTML = `
                        <td class="fr-td-chk"><input type="checkbox" class="fr-row-check" data-id="${employeeId}"></td>
                        <td class="fr-td-emp">
                            <div class="fr-emp-cell">
                                <div class="fr-av" style="background:linear-gradient(135deg,${_gc1},${_gc2});">${avatarContent}</div>
                                <div class="fr-emp-info">
                                    <span class="fr-emp-name">${displayName}</span>
                                    <span class="fr-emp-email">${formData.get('email') || ''}</span>
                                </div>
                            </div>
                        </td>
                        <td class="fr-td-role">
                            <span class="fr-role-pos">${formData.get('position') || '—'}</span>
                            <span class="fr-role-dept">${formData.get('department') || '—'}</span>
                        </td>
                        <td class="fr-td-status"><span class="${badgeClass}" id="status-${employeeId}">${label}</span></td>
                        <td class="fr-td-acts">
                            <div class="fr-acts">
                                <button class="fr-btn fr-btn-view btn-view" data-id="${employeeId}" title="Ver"><i class="fas fa-eye"></i></button>
                                <button class="fr-btn fr-btn-edit btn-edit" data-id="${employeeId}" title="Editar"${isDisabledRow ? ' disabled' : ''}><i class="fas fa-pen"></i></button>
                                ${status === 'inactive'
                                    ? `<button class="fr-btn fr-btn-activate btn-activate" data-id="${employeeId}" title="Ativar"><i class="fas fa-user-check"></i></button>`
                                    : `<button class="fr-btn fr-btn-deact btn-employee-deactivate" data-id="${employeeId}" title="Desativar"><i class="fas fa-ban"></i></button>`}
                            </div>
                        </td>
                    `;
                    
                    tbody.insertBefore(newRow, tbody.firstChild);
                    
                    // Adicionar event listeners aos novos botões
                    const viewBtn = newRow.querySelector('.btn-view');
                    if (viewBtn) {
                        viewBtn.addEventListener('click', function() {
                            const empId = this.getAttribute('data-id');
                            fetch(`../api/employees/get_employee.php?id=${empId}`)
                                .then(response => {
                                    if (!response.ok) throw new Error('Não foi possível buscar os dados do funcionário.');
                                    return response.json();
                                })
                                .then(employee => {
                                    if (employee.id) {
                                        // Atualizar avatar
                                        const viewAvatar = document.getElementById('view-avatar');
                                        if (employee.profile_picture) {
                                            viewAvatar.innerHTML = `<img src="../${employee.profile_picture}" alt="${employee.name}" style="width: 100%; height: 100%; object-fit: cover;">`;
                                        } else {
                                            const initials = employee.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                                            viewAvatar.innerHTML = `<span style="font-size: 48px;">${initials}</span>`;
                                        }
                                        
                                        // Preencher dados
                                        document.getElementById('view-name').textContent = employee.name || '—';
                                        document.getElementById('view-position').textContent = employee.position || '—';
                                        document.getElementById('view-department').textContent = employee.department || '—';
                                        document.getElementById('view-email').textContent = employee.email || '—';
                                        document.getElementById('view-phone').textContent = employee.phone || '—';
                                        
                                        const statusEl = document.getElementById('view-status');
                                        let statusHtml = '';
                                        if (employee.status === 'active') {
                                            statusHtml = '<span class="status-badge status-active">Ativo</span>';
                                        } else if (employee.status === 'inactive') {
                                            statusHtml = '<span class="status-badge status-inactive">Inativo</span>';
                                        } else if (employee.status === 'ferias') {
                                            statusHtml = '<span class="status-badge status-ferias">Férias</span>';
                                        } else {
                                            statusHtml = employee.status || '—';
                                        }
                                        statusEl.innerHTML = statusHtml;
                                        
                                        document.getElementById('view-birthDate').textContent = employee.birthDate ? new Date(employee.birthDate).toLocaleDateString('pt-PT') : '—';
                                        document.getElementById('view-nif').textContent = employee.nif || '—';
                                        document.getElementById('view-niss').textContent = employee.niss || '—';
                                        document.getElementById('view-address').textContent = employee.address || '—';
                                        document.getElementById('view-emergencyContact').textContent = employee.emergencyContact || '—';
                                        document.getElementById('view-startDate').textContent = employee.startDate ? new Date(employee.startDate).toLocaleDateString('pt-PT') : '—';

                                        const viewEndDateEl2 = document.getElementById('view-endDate');
                                        if (viewEndDateEl2) {
                                            if (employee.endDate && employee.endDate !== '0000-00-00') {
                                                const endDt = new Date(employee.endDate);
                                                const today = new Date(); today.setHours(0,0,0,0);
                                                const daysLeft = Math.round((endDt - today) / 86400000);
                                                let endLabel = endDt.toLocaleDateString('pt-PT');
                                                if (daysLeft < 0) endLabel += ' <span style="color:#ef4444;font-size:.8em;">(Expirado)</span>';
                                                else if (daysLeft <= 30) endLabel += ` <span style="color:#f59e0b;font-size:.8em;">(${daysLeft}d restantes)</span>`;
                                                viewEndDateEl2.innerHTML = endLabel;
                                            } else {
                                                viewEndDateEl2.textContent = '—';
                                            }
                                        }

                                        const contractTypes = {
                                            'efetivo': 'Efetivo',
                                            'temporario': 'Temporário',
                                            'part-time': 'Part-time',
                                            'estagio': 'Estágio',
                                            'freelancer': 'Freelancer'
                                        };
                                        document.getElementById('view-contractType').textContent = contractTypes[employee.contractType] || employee.contractType || '—';

                                        const viewVacDaysEl2 = document.getElementById('view-vacation-days');
                                        if (viewVacDaysEl2) viewVacDaysEl2.textContent = (employee.vacation_days != null ? employee.vacation_days : 22) + ' dias';

                                        // Sincronizar estado global do modal (mesmo fluxo das linhas já existentes)
                                        currentViewedEmployeeId = Number(employee.id);
                                        const hiddenEmployeeId = document.getElementById('upload-employee-id');
                                        if (hiddenEmployeeId) {
                                            hiddenEmployeeId.value = employee.id;
                                        }

                                        // Carregar dados complementares do modal
                                        loadEmployeeDocuments(employee.id);
                                        loadEmployeeTurnoAndPonto(employee.id);
                                        
                                        document.getElementById('viewEmployeeModal').style.display = 'block';
                                    } else {
                                        showError('Funcionário não encontrado.');
                                    }
                                })
                                .catch(error => {
                                    console.error('Erro:', error);
                                    showError('Erro ao carregar dados do funcionário.');
                                });
                        });
                    }
                    
                    const editBtn = newRow.querySelector('.btn-edit');
                    if (editBtn && !isDisabledRow) {
                        editBtn.addEventListener('click', function() {
                            const empId = this.getAttribute('data-id');
                            fetch(`../api/employees/get_employee.php?id=${empId}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.employee) {
                                        const employee = data.employee;
                                        document.getElementById('employee-id').value = employee.id;
                                        document.getElementById('edit-name').value = employee.name || '';
                                        document.getElementById('edit-position').value = employee.position || '';
                                        document.getElementById('edit-department').value = employee.department || '';
                                        document.getElementById('edit-email').value = employee.email || '';
                                        document.getElementById('edit-phone').value = employee.phone || '';
                                        document.getElementById('edit-startDate').value = employee.startDate || '';
                                        const editEndDate3 = document.getElementById('edit-endDate');
                                        if (editEndDate3) editEndDate3.value = employee.endDate || '';
                                        const editVacDays3 = document.getElementById('edit-vacation-days');
                                        if (editVacDays3) editVacDays3.value = employee.vacation_days ?? 22;
                                        document.getElementById('edit-status').value = employee.status || 'active';
                                        editModal.style.display = 'block';
                                    }
                                });
                        });
                    }
                    
                    const deleteBtn = newRow.querySelector('.btn-employee-deactivate');
                    if (deleteBtn) {
                        deleteBtn.addEventListener('click', async function() {
                            // Fluxo legado para linhas dinâmicas: usa apenas o interceptador global.
                            if (document.body.dataset.softDeleteReady === '1') {
                                return;
                            }

                            const empId = this.getAttribute('data-id');
                            if (confirm('Tem certeza que deseja desativar este funcionário?')) {
                                try {
                                    const response = await fetch('../api/employees/delete_employee.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ id: empId })
                                    });
                                    const data = await response.json();
                                    if (data.success) {
                                        showSuccess('O funcionario foi desativado com sucesso');
                                        updateRowToInactive(empId);
                                    } else {
                                        showSuccess('O funcionario foi desativado com sucesso');
                                    }
                                } catch (error) {
                                    showError('Erro ao processar a solicitação.');
                                }
                            }
                        });
                    }
                    
                    // Adicionar event listener ao botão de ativar (se existir)
                    const activateBtn = newRow.querySelector('.btn-activate');
                    if (activateBtn) {
                        activateBtn.addEventListener('click', async function() {
                            const empId = this.getAttribute('data-id');
                            const confirmed = await showConfirm(
                                'Ativar Funcionário',
                                'Deseja mudar o status deste funcionário para Ativo?',
                                'Sim, ativar',
                                'Cancelar'
                            );
                            
                            if (!confirmed) return;

                            const fd = new FormData();
                            fd.append('id', empId);
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
                                    if (newRow) {
                                        // Remover classe disabled-row
                                        newRow.classList.remove('disabled-row');
                                        
                                        // Atualizar badge de status
                                        const statusBadge = newRow.querySelector(`#status-${empId}`);
                                        if (statusBadge) {
                                            statusBadge.className = 'status-badge status-active';
                                            statusBadge.textContent = 'Ativo';
                                        }
                                        
                                        // Atualizar botões de ação
                                        const actionsCell = newRow.cells[newRow.cells.length - 1];
                                        const editBtnToUpdate = actionsCell.querySelector('.btn-edit');
                                        
                                        // Habilitar botão de editar
                                        if (editBtnToUpdate) {
                                            editBtnToUpdate.classList.remove('btn-disabled');
                                            editBtnToUpdate.removeAttribute('disabled');
                                            editBtnToUpdate.setAttribute('title', 'Editar');
                                        }
                                        
                                        // Remover botão de ativar
                                        activateBtn.remove();
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
                    }
                } else {
                    showError(data.message || 'Erro ao adicionar o funcionário.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showError('Não foi possível adicionar o funcionário. Tente novamente mais tarde.');
            });
        });
    }
});



function registrarPonto(tipo, funcionarioId) {
    // Bloqueia tentativa para funcionários inativos ou em férias
    const statusEl = document.getElementById('status-' + funcionarioId);
    if (statusEl) {
        const cls = statusEl.className || '';
        if (cls.includes('status-ferias') || cls.includes('status-inactive')) {
            showError('Funcionário inativo ou em férias não pode registar ponto.');
            return;
        }
        // também checa texto caso classe não esteja presente
        const txt = statusEl.textContent ? statusEl.textContent.trim().toLowerCase() : '';
        if (txt === 'ferias' || txt === 'férias' || txt === 'inativo' || txt === 'inactive') {
            showError('Funcionário inativo ou em férias não pode registar ponto.');
            return;
        }
    }
    fetch('../api/gorjetas/presenca/registrar_ponto.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            tipo: tipo,
            funcionario_id: funcionarioId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Ponto registrado com sucesso!');
            
            // Atualizar a tabela de presença dinamicamente SEM recarregar
            const row = document.querySelector(`#assiduidade-section button[onclick*="${funcionarioId}"]`)?.closest('tr');
            if (row) {
                const currentTime = data.hora || new Date().toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
                const cells = row.cells;
                
                if (tipo === 'entrada') {
                    // Atualizar data e coluna de entrada
                    const now = new Date();
                    const dd = String(now.getDate()).padStart(2, '0');
                    const mm = String(now.getMonth() + 1).padStart(2, '0');
                    const yyyy = now.getFullYear();
                    const iso = `${yyyy}-${mm}-${dd}`;

                    const dataCell = cells[2];
                    const entradaCell = cells[4];
                    if (entradaCell) {
                        entradaCell.innerHTML = `<span style="font-weight: 500; color: #10b981;">${currentTime}</span>`;
                    }
                    if (dataCell) {
                        dataCell.textContent = `${dd}/${mm}/${yyyy}`;
                    }

                    row.dataset.presencaDate = iso;
                    row.dataset.presencaYear = String(yyyy);
                    row.dataset.presencaMonth = `${yyyy}-${mm}`;

                    const atrasoCell = cells[7];
                    if (atrasoCell) {
                        const tipoDia = normalizeTipoDiaFromCell(row.dataset.tipoDia || 'Normal');
                        atrasoCell.textContent = calculateDelayStatus(currentTime, row.dataset.expectedStart || '', row.dataset.toleranciaMin || '0', tipoDia);
                    }
                    
                    // Desabilitar botão de entrada e habilitar botão de saída
                    const entradaBtn = row.querySelector('.btn-entrada');
                    const saidaBtn = row.querySelector('.btn-saida');
                    if (entradaBtn) {
                        entradaBtn.disabled = true;
                        entradaBtn.title = 'Entrada já registrada';
                    }
                    if (saidaBtn) {
                        saidaBtn.disabled = false;
                        saidaBtn.title = 'Marcar Saída de Ponto';
                    }
                } else if (tipo === 'saida') {
                    // Atualizar coluna de saída
                    const saidaCell = cells[5];
                    if (saidaCell) {
                        saidaCell.innerHTML = `<span style="font-weight: 500; color: #f59e0b;">${currentTime}</span>`;
                    }

                    const entradaAtual = cells[4] ? cells[4].textContent.trim() : '--:--';
                    const horasCell = cells[6];
                    if (horasCell) {
                        horasCell.textContent = calculateWorkedHours(entradaAtual, currentTime);
                    }
                    
                    // Desabilitar botão de saída
                    const saidaBtn = row.querySelector('.btn-saida');
                    if (saidaBtn) {
                        saidaBtn.disabled = true;
                        saidaBtn.title = 'Saída já registrada';
                    }
                }
            }
        } else {
            showError(data.message || 'Erro ao registrar ponto');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showError('Erro ao registrar ponto');
    });
}




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





document.addEventListener('DOMContentLoaded', () => {
  const gorjetaModal = document.getElementById('gorjetaModal');
  const closeModal = document.getElementById('closeGorjetaModal');
    const gorjetaViewModal = document.getElementById('gorjetaViewModal');
    const closeGorjetaViewModal = document.getElementById('closeGorjetaViewModal');
    const closeGorjetaViewFooter = document.getElementById('closeGorjetaViewFooter');

    function closeGorjetaViewDetails() {
        if (gorjetaViewModal) {
            gorjetaViewModal.style.display = 'none';
        }
    }

    function getGorjetaStatusView(statusRaw) {
        const status = (statusRaw || 'pendente').toString().toLowerCase();
        if (status === 'pago') {
            return { label: 'Pago', color: '#4ade80', bg: 'rgba(22,163,74,.18)', border: 'rgba(74,222,128,.35)' };
        }
        if (status === 'rejeitado' || status === 'rejeitada' || status === 'cancelado' || status === 'cancelada') {
            return { label: 'Rejeitado', color: '#fca5a5', bg: 'rgba(239,68,68,.15)', border: 'rgba(252,165,165,.35)' };
        }
        return { label: 'Pendente', color: '#fbbf24', bg: 'rgba(245,158,11,.15)', border: 'rgba(251,191,36,.35)' };
    }

    function openGorjetaViewDetails(data) {
        if (!gorjetaViewModal) return;

        const nome = (data.funcionario_nome || '-').toString();
        const valor = Number(data.valor || 0);
        const statusView = getGorjetaStatusView(data.status);
        const dataFmt = (() => {
            const raw = (data.data || data.data_registro || '').toString();
            if (!raw) return '-';
            if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
                const parts = raw.split('-');
                return parts[2] + '/' + parts[1] + '/' + parts[0];
            }
            return raw;
        })();

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };

        const avatarEl = document.getElementById('gv-avatar');
        if (avatarEl) {
            avatarEl.textContent = nome ? nome.slice(0, 2).toUpperCase() : '--';
        }

        const statusEl = document.getElementById('gv-status');
        if (statusEl) {
            statusEl.textContent = statusView.label;
            statusEl.style.color = statusView.color;
            statusEl.style.background = statusView.bg;
            statusEl.style.borderColor = statusView.border;
        }

        setText('gv-nome', nome);
        setText('gv-data', dataFmt);
        setText('gv-turno', (data.turno || '-').toString());
        setText('gv-valor', 'EUR ' + valor.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        setText('gv-pagamento', (data.forma_pagamento || '-').toString());
        setText('gv-origem', (data.origem || '-').toString());

        gorjetaViewModal.style.display = 'block';
    }
  const gorjetaForm = document.getElementById('gorjetaForm');
  const tbody = document.querySelector('#gorjetas-section tbody');

    const mapGorjetaStatus = (rawStatus) => {
        const normalized = (rawStatus || '').toString().trim().toLowerCase();
        const meta = {
            className: 'status-pendente',
            label: 'Pendente',
            showConfirm: false,
            showReject: false
        };

        if (normalized === 'pago') {
            meta.className = 'status-active';
            meta.label = 'Pago';
        } else if (normalized === 'rejeitado' || normalized === 'rejeitada') {
            meta.className = 'status-rejeitado';
            meta.label = 'Rejeitado';
        } else if (normalized === 'cancelado' || normalized === 'cancelada') {
            meta.className = 'status-rejeitado';
            meta.label = 'Cancelado';
        }

        return meta;
    };

    const createConfirmButton = (id) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-success btn-sm btn-confirm-gorjeta';
        btn.dataset.id = id;
        btn.title = 'Confirmar pagamento';
        btn.innerHTML = '<i class="fas fa-check"></i> Confirmar';
        return btn;
    };

    const createRejectButton = (id) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-danger btn-sm btn-reject-gorjeta';
        btn.dataset.id = id;
        btn.title = 'Rejeitar';
        btn.innerHTML = '<i class="fas fa-ban"></i> Rejeitar';
        return btn;
    };

  // ✅ VERIFICAR SE OS ELEMENTOS EXISTEM
  if (!gorjetaModal || !gorjetaForm) {
    console.warn('⚠️ Elementos de gorjetas não encontrados. Seção de gorjetas pode não estar visível.');
    return;
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

  // Fechar modal
  if (closeModal) {
    closeModal.addEventListener('click', () => {
      gorjetaModal.style.display = 'none';
      gorjetaForm.reset();
    });
  }

    if (closeGorjetaViewModal) {
        closeGorjetaViewModal.addEventListener('click', closeGorjetaViewDetails);
    }
    if (closeGorjetaViewFooter) {
        closeGorjetaViewFooter.addEventListener('click', closeGorjetaViewDetails);
    }
  
    window.addEventListener('click', (e) => { 
        if (e.target === gorjetaModal) {
            gorjetaModal.style.display = 'none';
            gorjetaForm.reset();
        }
        if (e.target === gorjetaViewModal) {
            closeGorjetaViewDetails();
        }
    });

  // SUBMIT - Criar / Atualizar gorjeta
  if (gorjetaForm) {
    gorjetaForm.addEventListener('submit', (e) => {
      e.preventDefault();
      e.stopImmediatePropagation(); // ✅ Previne múltiplos disparos
      
      console.log('💰 Form gorjeta submit iniciado');
      
      // ✅ Proteção contra duplo envio
      const submitBtn = gorjetaForm.querySelector('button[type="submit"]');
      if (submitBtn && submitBtn.disabled) {
        console.log('⚠️ Envio já em andamento, ignorando...');
        return;
      }
      
      // Desabilita botão durante o envio
      if (submitBtn) submitBtn.disabled = true;
      
      const formData = new FormData(gorjetaForm);
      const rawData = Object.fromEntries(formData);
      
      // Mapear campos para o formato esperado pela API
            const data = {
                id: rawData.id || undefined,
                funcionario_id: rawData.funcionario_id,
                data: rawData.data,
                data_registro: rawData.data,
        turno_id: null, // Manter null por enquanto (campo turno_id na tabela)
        turno: rawData.turno, // Guardar o texto do turno também
        valor: rawData.valor,
        forma_pagamento: rawData.forma_pagamento,
        origem: rawData.origem,
        status: rawData.status
      };
      
      const url = data.id ? '../api/gorjetas/update_gorjeta.php' : '../api/gorjetas/create_gorjeta.php';
      const isEdit = !!data.id;
      
      console.log(isEdit ? '✏️ Modo EDIÇÃO' : '➕ Modo CRIAÇÃO');
      console.log('🚀 Enviando para:', url);

      fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })
      .then(response => {
        console.log('📡 Resposta recebida, status:', response.status);
        return parseJsonSafe(response);
      })
      .then(result => {
        console.log('📦 Resultado da API:', result);
        
        // ✅ Reabilita botão de submit
        const submitBtn = gorjetaForm.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = false;
        
        if (result.success) {
          console.log('✅ Sucesso! Fechando modal...');
          showSuccess('Gorjeta salva com sucesso!');
          
          // ✅ FECHAR O MODAL E RESETAR FORMULÁRIO
          gorjetaModal.style.display = 'none';
          gorjetaForm.reset();
          console.log('🔒 Modal fechado e formulário resetado');

                    // Mantém contexto: novo registo volta para Gorjetas; edição direciona para Folha para refletir totais.
                    const targetUrl = new URL(window.location.href);
                    if (isEdit) {
                        targetUrl.searchParams.set('section', 'folha-pagamento');
                    } else {
                        targetUrl.searchParams.set('section', 'gorjetas');
                        targetUrl.searchParams.delete('folha_ano');
                        targetUrl.searchParams.delete('folha_mes');
                    }

                    const dataRegistro = (data.data_registro || '').toString();
                    if (isEdit && /^\d{4}-\d{2}-\d{2}$/.test(dataRegistro)) {
                        const parts = dataRegistro.split('-');
                        const year = parseInt(parts[0], 10);
                        const month = parseInt(parts[1], 10);
                        if (!Number.isNaN(year) && year > 0) {
                            targetUrl.searchParams.set('folha_ano', String(year));
                        }
                        if (!Number.isNaN(month) && month >= 1 && month <= 12) {
                            targetUrl.searchParams.set('folha_mes', String(month));
                        }
                    }

                    setTimeout(() => {
                        window.location.assign(targetUrl.toString());
                    }, 550);
        } else {
          console.log('❌ Erro retornado pelo servidor:', result.message);
          showError(result.message || 'Erro ao salvar gorjeta.');
        }
      })
      .catch(err => {
        console.error('💥 ERRO CATCH (gorjetas):', err);
        showError('Erro ao comunicar com o servidor.');
        // ✅ Reabilita botão em caso de erro
        const submitBtn = gorjetaForm.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = false;
      });
    });
  }

    // Event listener para ações de gorjetas
  if (tbody) {
    tbody.addEventListener('click', (e) => {
            const confirmBtn = e.target.closest('.btn-confirm-gorjeta');
                        const viewBtn = e.target.closest('.btn-view-gorjeta');
            const editBtn = e.target.closest('.btn-edit');
            const rejectBtn = e.target.closest('.btn-reject-gorjeta');

            if (confirmBtn) {
                e.preventDefault();
                e.stopImmediatePropagation();

                showWarning('A aprovação de gorjetas está disponível apenas na secção Solicitações.');

                return;
            }

            // Ver detalhes da gorjeta
            if (viewBtn) {
                e.preventDefault();
                e.stopImmediatePropagation();

                const id = viewBtn.dataset.id;
                if (!id) {
                    console.error('ID da gorjeta não encontrado para visualização');
                    return;
                }

                fetch(`../api/gorjetas/get_gorjeta.php?id=${id}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data || !data.id) {
                            showError(data?.message || 'Não foi possível carregar os detalhes da gorjeta.');
                            return;
                        }

                        openGorjetaViewDetails(data);
                    })
                    .catch(err => {
                        console.error('Erro ao carregar detalhes da gorjeta:', err);
                        showError('Erro ao comunicar com o servidor');
                    });

                return;
            }

      // Editar gorjeta
      if (editBtn) {
                e.preventDefault();
                e.stopImmediatePropagation();
        const id = editBtn.dataset.id;
        if (!id) {
          console.error('ID da gorjeta não encontrado');
          return;
        }

        console.log('Carregando gorjeta ID:', id);

        fetch(`../api/gorjetas/get_gorjeta.php?id=${id}`)
          .then(r => {
            console.log('Status resposta:', r.status);
            return r.json();
          })
          .then(data => {
            console.log('Dados recebidos:', data);
            
            if (data.id) {
              document.getElementById('gorjetaId').value = data.id;
              document.getElementById('gorjetaFuncionario').value = data.funcionario_id;
              document.getElementById('gorjetaData').value = data.data;
              document.getElementById('gorjetaTurno').value = data.turno || '';
              document.getElementById('gorjetaValor').value = data.valor;
              document.getElementById('gorjetaPagamento').value = data.forma_pagamento || 'Dinheiro';
              document.getElementById('gorjetaOrigem').value = data.origem || '';
                            const statusField = document.getElementById('gorjetaStatus');
                            const statusValue = (data.status || 'pendente').toString().toLowerCase();
                            statusField.value = statusValue === 'rejeitada' ? 'rejeitado' : statusValue;

              document.getElementById('gorjetaModalTitle').textContent = 'Editar Gorjeta';
              gorjetaModal.style.display = 'block';
            } else {
              showError(data.message || 'Erro ao carregar dados da gorjeta');
            }
          })
          .catch(err => {
            console.error('Erro ao carregar gorjeta:', err);
            showError('Erro ao comunicar com o servidor');
          });
      }

            // Rejeitar gorjeta
            if (rejectBtn) {
                e.preventDefault();
                e.stopImmediatePropagation();

                                showWarning('A rejeição de gorjetas está disponível apenas na secção Solicitações.');
                                return;
      }
    });
  }
});








        // Current section management
        let currentSection = 'inicio';

        function toggleProfileMenu(forceState) {
            const profileLink = document.querySelector('.profile-link');
            if (!profileLink) return;

            const shouldOpen = typeof forceState === 'boolean'
                ? forceState
                : !profileLink.classList.contains('profile-open');

            profileLink.classList.toggle('profile-open', shouldOpen);
        }

        function triggerAdminProfilePhotoPicker() {
            const input = document.getElementById('admin-profile-photo-input');
            if (!input) {
                showError('Não foi possível abrir o seletor de imagem.');
                return;
            }
            input.click();
        }

        function updateAdminProfileAvatars(path) {
            if (!path) return;
            document.querySelectorAll('.admin-profile-avatar').forEach((img) => {
                img.src = path;
            });
        }

        function handleAdminProfilePhotoSelected(event) {
            const input = event && event.target ? event.target : document.getElementById('admin-profile-photo-input');
            const file = input && input.files ? input.files[0] : null;

            if (!file) return;

            if (!file.type.startsWith('image/')) {
                showError('Por favor, selecione uma imagem válida.');
                input.value = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                showError('Imagem muito grande. Tamanho máximo: 2MB.');
                input.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('profile_photo', file);

            fetch('controllers/upload_foto.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    if (data.path) {
                        const cacheBustedPath = `${data.path}${data.path.includes('?') ? '&' : '?'}v=${Date.now()}`;
                        updateAdminProfileAvatars(cacheBustedPath);
                    }
                    showSuccess(data.message || 'Foto de perfil atualizada com sucesso!');
                    toggleProfileMenu(false);
                } else {
                    showError((data && data.message) || 'Erro ao atualizar foto de perfil.');
                }
            })
            .catch((err) => {
                console.error('Erro no upload da foto de perfil do admin:', err);
                showError('Erro no upload da foto de perfil.');
            })
            .finally(() => {
                input.value = '';
            });
        }

        // Show section function
        function showSection(sectionName) {

            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
                // Esconde também se for a seção de férias
                if (section.id === 'ferias-section') {
                    section.style.display = 'none';
                }
            });

            // Show selected section
            const targetSection = document.getElementById(sectionName + '-section');
            if (targetSection) {
                targetSection.classList.add('active');
                // Se for a seção de férias, exibe com display:block
                if (targetSection.id === 'ferias-section') {
                    targetSection.style.display = 'block';
                }
            }

            // Update navigation active state
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });

            document.querySelectorAll(`[data-section="${sectionName}"]`).forEach(link => {
                link.classList.add('active');
            });

            currentSection = sectionName;

            // Update page title
            const sectionTitles = {
                'inicio': 'Painel RH',
                'funcionarios': 'Funcionários',
                'notificacoes': 'Notificações',
                'assiduidade': 'Assiduidade',
                'solicitacoes': 'Solicitações',
                'turnos': 'Turnos',
                'ferias': 'Férias',
                'folha-pagamento': 'Folha de Pagamento',
                'gorjetas': 'Gorjetas',
                'relatorios': 'Relatórios',
                'definicoes': 'Definições'
            };

            if (sectionName === 'notificacoes') {
                if (typeof setNotificationsView === 'function') {
                    setNotificationsView(window.__notificationsView || 'received');
                } else if (typeof loadNotificationsSection === 'function') {
                    loadNotificationsSection();
                }
            }

            document.title = `${sectionTitles[sectionName]} - RHNeto Pro - <?php echo htmlspecialchars($fullname); ?>`;
        }

        // Theme Toggle Functionality (disabled — application forces dark mode)
        function toggleTheme() {
            // No-op: theme is fixed to dark mode
            console.log('toggleTheme called but theme is fixed to dark mode.');
            document.body.classList.add('dark-theme');
            const themeIcon = document.getElementById('theme-icon');
            if (themeIcon) themeIcon.textContent = '☀️';
        }

        // Mobile Menu Toggle
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobile-menu');
            const menuIcon = document.getElementById('mobile-menu-icon');
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');

            if (!mobileMenu || !menuIcon) {
                return;
            }

            mobileMenu.classList.toggle('active');
            const isOpen = mobileMenu.classList.contains('active');

            if (mobileMenuBtn) {
                mobileMenuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            document.body.classList.toggle('mobile-menu-open', isOpen);

            if (isOpen) {
                menuIcon.className = 'fas fa-times';
            } else {
                menuIcon.className = 'fas fa-bars';
            }
        }

        // Initialize theme (force dark mode)
        document.addEventListener('DOMContentLoaded', function() {
            const themeIcon = document.getElementById('theme-icon');
            document.body.classList.add('dark-theme');
            if (themeIcon) themeIcon.textContent = '☀️';

            // Restaurar seção ativa após reload (para manter usuário na mesma seção)
            const activeSection = sessionStorage.getItem('activeSection');
            if (activeSection) {
                console.log('Restaurando seção:', activeSection);
                // Usar setTimeout para garantir que o DOM está pronto
                setTimeout(() => {
                    showSection(activeSection);
                    sessionStorage.removeItem('activeSection');
                }, 100);
            }

            const shouldOpenPresencaHistory = sessionStorage.getItem('openPresencaHistory') === '1';
            if (shouldOpenPresencaHistory) {
                setTimeout(() => {
                    const historyPanel = document.getElementById('presencaHistoryPanel');
                    const historyButton = document.getElementById('togglePresencaHistoryBtn');
                    if (historyPanel && historyPanel.dataset.open !== 'true') {
                        togglePresencaHistoryPanel(historyButton || null);
                    }
                    sessionStorage.removeItem('openPresencaHistory');
                }, 220);
            }

            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                const mobileMenu = document.getElementById('mobile-menu');
                const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
                const profileLink = document.querySelector('.profile-link');

                // guard clauses to avoid null pointers
                if (mobileMenu && mobileMenuBtn) {
                    if (!mobileMenu.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
                        mobileMenu.classList.remove('active');
                        document.body.classList.remove('mobile-menu-open');
                        mobileMenuBtn.setAttribute('aria-expanded', 'false');
                        const icon = document.getElementById('mobile-menu-icon');
                        if (icon) icon.className = 'fas fa-bars';
                    }
                }

                if (profileLink && !profileLink.contains(event.target)) {
                    profileLink.classList.remove('profile-open');
                }
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    const mobileMenu = document.getElementById('mobile-menu');
                    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
                    const icon = document.getElementById('mobile-menu-icon');
                    if (mobileMenu) {
                        mobileMenu.classList.remove('active');
                    }
                    if (mobileMenuBtn) {
                        mobileMenuBtn.setAttribute('aria-expanded', 'false');
                    }
                    if (icon) {
                        icon.className = 'fas fa-bars';
                    }
                    document.body.classList.remove('mobile-menu-open');
                }
            });

            // Add smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Add intersection observer for animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe animated elements
            document.querySelectorAll('.animate-fade-in-up, .animate-fade-in-left').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                observer.observe(el);
            });
        });

        // Add loading states for buttons
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('#')) {
                    this.style.opacity = '0.7';
                    this.style.pointerEvents = 'none';
                    
                    setTimeout(() => {
                        this.style.opacity = '1';
                        this.style.pointerEvents = 'auto';
                    }, 2000);
                }
            });
        });

        // Add ripple effect to cards
        document.querySelectorAll('.stat-card, .activity-item, .info-card').forEach(card => {
            card.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(59, 130, 246, 0.3)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.pointerEvents = 'none';
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Search functionality
        document.querySelectorAll('.search-input').forEach(input => {
            if (input.placeholder && input.placeholder.includes('Pesquisar')) {
                input.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const table = this.closest('.data-table').querySelector('tbody');
                    
                    if (table) {
                        const rows = table.querySelectorAll('tr');
                        rows.forEach(row => {
                            const text = row.textContent.toLowerCase();
                            row.style.display = text.includes(searchTerm) ? '' : 'none';
                        });
                    }
                });
            }
        });
       
// Toggle dropdown de exportação
function toggleExportDropdown() {
    const dropdown = document.getElementById('exportDropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

function toggleExportTurnosDropdown() {
    const dropdown = document.getElementById('exportTurnosDropdown');
    if (!dropdown) return;
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

function togglePresencaHistoryPanel(button) {
    const panel = document.getElementById('presencaHistoryPanel');
    if (!panel) return;

    const isOpen = panel.dataset.open === 'true';
    const nextOpen = !isOpen;

    if (nextOpen) {
        panel.style.marginTop = '1.25rem';
        panel.style.maxHeight = panel.scrollHeight + 'px';
        panel.style.opacity = '1';
        panel.dataset.open = 'true';
    } else {
        panel.style.maxHeight = '0';
        panel.style.opacity = '0';
        panel.style.marginTop = '0';
        panel.dataset.open = 'false';
    }

    if (button) {
        button.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
        button.classList.toggle('history-active', nextOpen);
        button.innerHTML = nextOpen
            ? '<i class="fas fa-history"></i><span>Ocultar Histórico</span><i class="fas fa-chevron-up" style="margin-left: .35rem; font-size: 0.8em;"></i>'
            : '<i class="fas fa-history"></i><span>Histórico</span><i class="fas fa-chevron-down" style="margin-left: .35rem; font-size: 0.8em;"></i>';
    }
}

// Fechar dropdown ao clicar fora
window.onclick = function(event) {
    if (!event.target.matches('.btn-accent') && !event.target.closest('.btn-accent')) {
        const dropdown = document.getElementById('exportDropdown');
        if (dropdown && dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        }
        const turnosDropdown = document.getElementById('exportTurnosDropdown');
        if (turnosDropdown && turnosDropdown.style.display === 'block') {
            turnosDropdown.style.display = 'none';
        }
        const presencaDropdown = document.getElementById('exportPresencaDropdown');
        if (presencaDropdown && presencaDropdown.style.display === 'block') {
            presencaDropdown.style.display = 'none';
        }
        const historyPresencaDropdown = document.getElementById('exportHistoryPresencaDropdown');
        if (historyPresencaDropdown && historyPresencaDropdown.style.display === 'block') {
            historyPresencaDropdown.style.display = 'none';
        }
    }
}

// Função para obter funcionários visíveis (respeitando filtros)
function getVisibleEmployees() {
    const table = document.getElementById('employeesTable');
    if (!table) {
        return [];
    }

    const rows = table.querySelectorAll('tbody tr');
    const employees = [];

    rows.forEach(row => {
        if (row.style.display === 'none') {
            return;
        }

        const cells = row.querySelectorAll('td');
        const data = row.dataset || {};
        const statusLabel = data.statusLabel || cells[4]?.textContent.trim() || '';
        const statusRaw = (data.status || '').toLowerCase();
        const phone = (data.phone || '').trim();

        employees.push({
            id: data.employeeId || '',
            nome: data.fullname || data.name || cells[1]?.textContent.trim() || '',
            cargo: data.position || cells[2]?.textContent.trim() || '',
            departamento: data.department || cells[3]?.textContent.trim() || '',
            telefone: phone !== '' ? phone : '—',
            email: data.email || '',
            startDate: data.startDate || '',
            status: statusLabel,
            statusRaw: statusRaw || statusLabel.toLowerCase()
        });
    });

    return employees;
}

function initEmployeeTableFilters() {
    const table = document.getElementById('employeesTable');
    const searchInput = document.getElementById('employeeTableSearch');
    const statusSelect = document.getElementById('employeeTableStatus');
    const positionSelect = document.getElementById('employeeTablePosition');
    const departmentSelect = document.getElementById('employeeTableDepartment');
    const contractTypeSelect = document.getElementById('employeeTableContractType');
    const expirySelect = document.getElementById('employeeTableExpiry');

    if (!table || !searchInput || !statusSelect || !positionSelect || !departmentSelect) {
        return;
    }

    const rows = Array.from(table.querySelectorAll('tbody .employee-row'));
    const today = new Date(); today.setHours(0, 0, 0, 0);

    function normalizeStatus(value) {
        const v = (value || '').toString().trim().toLowerCase();
        if (v === 'ativo') return 'active';
        if (v === 'inativo') return 'inactive';
        if (v === 'férias') return 'ferias';
        return v;
    }

    function normalizeText(value) {
        return (value || '').toString().trim().toLowerCase();
    }

    function populateSelectFromRows(selectEl, attrName, defaultLabel) {
        const uniqueValues = new Set();

        rows.forEach((row) => {
            const raw = row.getAttribute(attrName) || '';
            const value = raw.toString().trim();
            if (value !== '') {
                uniqueValues.add(value);
            }
        });

        const options = Array.from(uniqueValues).sort((a, b) => a.localeCompare(b, 'pt', { sensitivity: 'base' }));
        const previousValue = selectEl.value;

        selectEl.innerHTML = '';
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = defaultLabel;
        selectEl.appendChild(defaultOption);

        options.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            selectEl.appendChild(option);
        });

        if (previousValue && options.includes(previousValue)) {
            selectEl.value = previousValue;
        }
    }

    populateSelectFromRows(positionSelect, 'data-position', 'Todos os cargos');
    populateSelectFromRows(departmentSelect, 'data-department', 'Todos os departamentos');

    function applyEmployeeFilters() {
        const searchTerm = (searchInput.value || '').trim().toLowerCase();
        const selectedStatus = normalizeStatus(statusSelect.value);
        const selectedPosition = normalizeText(positionSelect.value);
        const selectedDepartment = normalizeText(departmentSelect.value);
        const selectedContractType = normalizeText(contractTypeSelect ? contractTypeSelect.value : '');
        const selectedExpiry = expirySelect ? expirySelect.value : '';

        rows.forEach((row) => {
            const text = (row.textContent || '').toLowerCase();
            const rowStatus = normalizeStatus(row.getAttribute('data-status') || row.getAttribute('data-status-label'));
            const rowPosition = normalizeText(row.getAttribute('data-position'));
            const rowDepartment = normalizeText(row.getAttribute('data-department'));
            const rowContractType = normalizeText(row.getAttribute('data-contract-type'));

            const matchesSearch = searchTerm === '' || text.includes(searchTerm);
            const matchesStatus = selectedStatus === '' || rowStatus === selectedStatus;
            const matchesPosition = selectedPosition === '' || rowPosition === selectedPosition;
            const matchesDepartment = selectedDepartment === '' || rowDepartment === selectedDepartment;
            const matchesContractType = selectedContractType === '' || rowContractType === selectedContractType;

            let matchesExpiry = true;
            if (selectedExpiry !== '') {
                const rawEndDate = (row.getAttribute('data-end-date') || '').trim();
                if (selectedExpiry === 'active') {
                    matchesExpiry = rawEndDate === '' || rawEndDate === '0000-00-00';
                } else if (rawEndDate && rawEndDate !== '0000-00-00') {
                    const endTs = new Date(rawEndDate); endTs.setHours(0, 0, 0, 0);
                    const daysLeft = Math.round((endTs - today) / 86400000);
                    if (selectedExpiry === 'expiring') matchesExpiry = daysLeft >= 0 && daysLeft <= 30;
                    else if (selectedExpiry === 'expired') matchesExpiry = daysLeft < 0;
                } else {
                    matchesExpiry = false;
                }
            }

            const visible = matchesSearch && matchesStatus && matchesPosition && matchesDepartment && matchesContractType && matchesExpiry;
            row.style.display = visible ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', applyEmployeeFilters);
    statusSelect.addEventListener('change', applyEmployeeFilters);
    positionSelect.addEventListener('change', applyEmployeeFilters);
    departmentSelect.addEventListener('change', applyEmployeeFilters);
    if (contractTypeSelect) contractTypeSelect.addEventListener('change', applyEmployeeFilters);
    if (expirySelect) expirySelect.addEventListener('change', applyEmployeeFilters);

    applyEmployeeFilters();
}

function initEmployeeStatusChips() {
    const chips = document.querySelectorAll('.fr-chip[data-chip-status]');
    const statusSelect = document.getElementById('employeeTableStatus');
    if (!chips.length || !statusSelect) return;

    chips.forEach(chip => {
        chip.addEventListener('click', () => {
            chips.forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            statusSelect.value = chip.dataset.chipStatus;
            statusSelect.dispatchEvent(new Event('change'));
        });
    });

    // Sync chips when select changes externally
    statusSelect.addEventListener('change', () => {
        chips.forEach(c => {
            c.classList.toggle('active', c.dataset.chipStatus === statusSelect.value);
        });
    });

    // Count filter badge
    const filterInputs = ['employeeTablePosition','employeeTableDepartment','employeeTableContractType','employeeTableExpiry'];
    const badge = document.getElementById('frFilterBadge');
    function updateFilterBadge() {
        if (!badge) return;
        const active = filterInputs.filter(id => { const el = document.getElementById(id); return el && el.value !== ''; }).length;
        badge.textContent = active;
        badge.style.display = active > 0 ? 'flex' : 'none';
    }
    filterInputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', updateFilterBadge);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { initEmployeeTableFilters(); initEmployeeStatusChips(); });
} else {
    initEmployeeTableFilters();
    initEmployeeStatusChips();
}

function normalizeEmployeeStatus(employee) {
    return (employee.statusRaw || employee.status || '').toLowerCase();
}

function getVisibleGorjetas() {
    const rows = document.querySelectorAll('#gorjetas-section tbody tr');
    const registros = [];

    rows.forEach((row) => {
        if (!row || row.cells.length < 7) return;
        const style = window.getComputedStyle(row);
        if (style.display === 'none') return;

        const cells = row.cells;
        const valorTexto = (cells[3]?.textContent || '').trim();
        const valorNumerico = parseFloat(
            valorTexto
                .replace(/[€\s]/g, '')
                .replace(/\.(?=\d{3}(\D|$))/g, '')
                .replace(',', '.')
        );

        registros.push({
            data: (cells[0]?.textContent || '').trim(),
            funcionario: (cells[1]?.textContent || '').trim(),
            turno: (cells[2]?.textContent || '').trim(),
            valorTexto,
            valorNumerico: Number.isNaN(valorNumerico) ? null : valorNumerico,
            formaPagamento: (cells[4]?.textContent || '').trim(),
            origem: (cells[5]?.textContent || '').trim(),
            status: (cells[6]?.textContent || '').trim()
        });
    });

    return registros;
}

function toCSVCell(value) {
    const text = value == null ? '' : String(value);
    return '"' + text.replace(/"/g, '""') + '"';
}

function exportGorjetasCSV() {
    const gorjetas = getVisibleGorjetas();

    if (!gorjetas.length) {
        showError('Nenhuma gorjeta encontrada para exportação');
        return;
    }

    const header = ['Data', 'Funcionário', 'Turno', 'Valor (€)', 'Forma de Pagamento', 'Origem', 'Status'];
    const linhas = gorjetas.map((item) => {
        const valor = item.valorNumerico != null
            ? item.valorNumerico.toFixed(2)
            : item.valorTexto.replace(/[€\s]/g, '');

        return [
            item.data,
            item.funcionario,
            item.turno,
            valor,
            item.formaPagamento,
            item.origem,
            item.status
        ].map(toCSVCell).join(';');
    });

    const conteudo = [header.map(toCSVCell).join(';'), ...linhas].join('\r\n');
    const blob = new Blob([conteudo], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `gorjetas_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    showSuccess(`Exportámos ${gorjetas.length} gorjeta(s).`);
}

// Exportar para PDF com estatísticas
function exportEmployeesPDF() {
    document.getElementById('exportDropdown').style.display = 'none';
    
    const employees = getVisibleEmployees();
    
    if (employees.length === 0) {
        showError('Nenhum funcionário encontrado para exportar');
        return;
    }
    
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Calcular estatísticas
    const stats = {
        total: employees.length,
        ativos: employees.filter(e => {
            const status = normalizeEmployeeStatus(e);
            return status.includes('active') || status.includes('ativo');
        }).length,
        inativos: employees.filter(e => {
            const status = normalizeEmployeeStatus(e);
            return status.includes('inactive') || status.includes('inativo');
        }).length,
        ferias: employees.filter(e => {
            const status = normalizeEmployeeStatus(e);
            return status.includes('ferias') || status.includes('férias');
        }).length
    };
    
    // Departamentos únicos
    const departamentos = {};
    employees.forEach(e => {
        const dept = e.departamento || 'Sem Departamento';
        departamentos[dept] = (departamentos[dept] || 0) + 1;
    });
    
    // Título
    doc.setFontSize(18);
    doc.setTextColor(52, 152, 219);
    doc.text('RELATORIO DE FUNCIONARIOS', 105, 20, { align: 'center' });
    
    doc.setFontSize(10);
    doc.setTextColor(100, 100, 100);
    doc.text(`Gerado em: ${new Date().toLocaleString('pt-PT')}`, 105, 28, { align: 'center' });
    
    // Linha separadora
    doc.setDrawColor(52, 152, 219);
    doc.setLineWidth(0.5);
    doc.line(20, 32, 190, 32);
    
    let yPos = 42;
    
    // ESTATISTICAS
    doc.setFontSize(14);
    doc.setTextColor(52, 152, 219);
    doc.text('ESTATISTICAS', 20, yPos);
    yPos += 8;
    
    doc.setFontSize(10);
    doc.setTextColor(0, 0, 0);
    doc.setFont('helvetica', 'bold');
    doc.text(`Total de Funcionarios:`, 25, yPos);
    doc.setFont('helvetica', 'normal');
    doc.text(`${stats.total}`, 75, yPos);
    yPos += 6;
    
    doc.setFont('helvetica', 'bold');
    doc.text(`Ativos:`, 25, yPos);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(39, 174, 96);
    doc.text(`${stats.ativos}`, 75, yPos);
    yPos += 6;
    
    doc.setTextColor(0, 0, 0);
    doc.setFont('helvetica', 'bold');
    doc.text(`Inativos:`, 25, yPos);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(231, 76, 60);
    doc.text(`${stats.inativos}`, 75, yPos);
    yPos += 6;
    
    doc.setTextColor(0, 0, 0);
    doc.setFont('helvetica', 'bold');
    doc.text(`Em Ferias:`, 25, yPos);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(243, 156, 18);
    doc.text(`${stats.ferias}`, 75, yPos);
    yPos += 10;
    
    // Por Departamento
    doc.setTextColor(0, 0, 0);
    doc.setFontSize(11);
    doc.setFont('helvetica', 'bold');
    doc.text('Por Departamento:', 25, yPos);
    yPos += 6;
    
    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    Object.entries(departamentos).forEach(([dept, count]) => {
        doc.text(`- ${dept}: ${count}`, 30, yPos);
        yPos += 5;
    });
    
    yPos += 8;
    
    // LISTA DE FUNCIONARIOS
    doc.setFontSize(14);
    doc.setTextColor(52, 152, 219);
    doc.text('LISTA DE FUNCIONARIOS', 20, yPos);
    yPos += 8;
    
    doc.setFontSize(8);
    doc.setTextColor(0, 0, 0);
    
    employees.forEach((emp, index) => {
        if (yPos > 270) { // Nova página se necessário
            doc.addPage();
            yPos = 20;
        }
        
        doc.setFont('helvetica', 'bold');
        doc.text(`${index + 1}. ${emp.nome}`, 20, yPos);
        doc.setFont('helvetica', 'normal');
        yPos += 4;
        
        doc.text(`   Cargo: ${emp.cargo} | Depto: ${emp.departamento}`, 20, yPos);
        yPos += 4;
        doc.text(`   Tel: ${emp.telefone} | Status: ${emp.status}`, 20, yPos);
        yPos += 6;
    });
    
    // Rodapé
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(150, 150, 150);
        doc.text(`Pagina ${i} de ${pageCount}`, 105, 290, { align: 'center' });
    }
    
    const fileName = `Relatorio_Funcionarios_${new Date().toISOString().split('T')[0]}.pdf`;
    doc.save(fileName);
    
    showSuccess(`PDF gerado com ${employees.length} funcionario(s)!`);
}

// Exportar para Excel (XLSX real com formatação)
function exportEmployeesExcel() {
    document.getElementById('exportDropdown').style.display = 'none';
    
    const employees = getVisibleEmployees();
    
    if (employees.length === 0) {
        showError('Nenhum funcionário encontrado para exportar');
        return;
    }
    
    // Calcular estatísticas
    const stats = {
        total: employees.length,
        ativos: employees.filter(e => {
            const status = normalizeEmployeeStatus(e);
            return status.includes('active') || status.includes('ativo');
        }).length,
        inativos: employees.filter(e => {
            const status = normalizeEmployeeStatus(e);
            return status.includes('inactive') || status.includes('inativo');
        }).length,
        ferias: employees.filter(e => {
            const status = normalizeEmployeeStatus(e);
            return status.includes('ferias') || status.includes('férias');
        }).length
    };
    
    // Departamentos únicos
    const departamentos = {};
    employees.forEach(e => {
        const dept = e.departamento || 'Sem Departamento';
        departamentos[dept] = (departamentos[dept] || 0) + 1;
    });
    
    // Criar array de dados para a planilha
    const wsData = [];
    
    // CABEÇALHO
    wsData.push(['RELATORIO DE FUNCIONARIOS']);
    wsData.push(['Gerado em: ' + new Date().toLocaleString('pt-PT')]);
    wsData.push([]);
    
    // ESTATÍSTICAS
    wsData.push(['RESUMO ESTATISTICO']);
    wsData.push(['Total de Funcionarios: ' + stats.total]);
    wsData.push(['Ativos: ' + stats.ativos]);
    wsData.push(['Inativos: ' + stats.inativos]);
    wsData.push(['Em Ferias: ' + stats.ferias]);
    wsData.push([]);
    
    // DEPARTAMENTOS
    wsData.push(['DISTRIBUICAO POR DEPARTAMENTO']);
    Object.entries(departamentos).forEach(([dept, count]) => {
        wsData.push([dept + ': ' + count]);
    });
    wsData.push([]);
    wsData.push([]);
    
    // LISTA DE FUNCIONÁRIOS
    wsData.push(['LISTA COMPLETA DE FUNCIONARIOS']);
    wsData.push(['Nome', 'Cargo', 'Departamento', 'Telefone', 'Status']);
    
    employees.forEach(emp => {
        wsData.push([emp.nome, emp.cargo, emp.departamento, emp.telefone, emp.status]);
    });
    
    // RODAPÉ
    wsData.push([]);
    wsData.push(['TOTAL: ' + employees.length + ' funcionario(s)']);
    
    // Criar workbook e worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(wsData);
    
    // Definir largura das colunas (em caracteres)
    ws['!cols'] = [
        { wch: 35 },  // Nome - largo
        { wch: 30 },  // Cargo - médio-largo
        { wch: 25 },  // Departamento - médio
        { wch: 15 },  // Telefone - pequeno
        { wch: 12 }   // Status - pequeno
    ];
    
    // Adicionar worksheet ao workbook
    XLSX.utils.book_append_sheet(wb, ws, 'Funcionarios');
    
    // Gerar e baixar arquivo
    const fileName = `Relatorio_Funcionarios_${new Date().toISOString().split('T')[0]}.xlsx`;
    XLSX.writeFile(wb, fileName);
    
    showSuccess(`Excel gerado com ${employees.length} funcionario(s)!`);
}

function getVisibleTurnos() {
    const rows = document.querySelectorAll('#turnosTable tbody tr');
    const turnos = [];

    rows.forEach((row) => {
        if (!row || row.style.display === 'none') return;

        const cells = row.querySelectorAll('td');
        const statusBadge = row.querySelector('.status-badge');
        const status = (statusBadge?.textContent || cells[5]?.textContent || '').trim();

        turnos.push({
            funcionario: (cells[0]?.textContent || '').trim(),
            turno: (cells[1]?.textContent || '').trim(),
            horario: (cells[2]?.textContent || '').trim(),
            diasSemana: (cells[3]?.textContent || '').trim(),
            escala: (cells[4]?.textContent || '').trim(),
            status
        });
    });

    return turnos;
}

function exportTurnosPDF() {
    const dropdown = document.getElementById('exportTurnosDropdown');
    if (dropdown) dropdown.style.display = 'none';

    const turnos = getVisibleTurnos();
    if (turnos.length === 0) {
        showError('Nenhum turno encontrado para exportar');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.setFontSize(18);
    doc.setTextColor(52, 152, 219);
    doc.text('RELATORIO DE TURNOS', 105, 20, { align: 'center' });

    doc.setFontSize(10);
    doc.setTextColor(100, 100, 100);
    doc.text(`Gerado em: ${new Date().toLocaleString('pt-PT')}`, 105, 28, { align: 'center' });

    doc.setDrawColor(52, 152, 219);
    doc.setLineWidth(0.5);
    doc.line(20, 32, 190, 32);

    let yPos = 42;
    doc.setFontSize(11);
    doc.setTextColor(0, 0, 0);

    turnos.forEach((item, index) => {
        if (yPos > 270) {
            doc.addPage();
            yPos = 20;
        }

        doc.setFont('helvetica', 'bold');
        doc.text(`${index + 1}. ${item.funcionario}`, 20, yPos);
        doc.setFont('helvetica', 'normal');
        yPos += 4;

        doc.text(`   Turno: ${item.turno} | Horario: ${item.horario}`, 20, yPos);
        yPos += 4;
        doc.text(`   Dias: ${item.diasSemana} | Escala: ${item.escala} | Status: ${item.status}`, 20, yPos);
        yPos += 6;
    });

    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(150, 150, 150);
        doc.text(`Pagina ${i} de ${pageCount}`, 105, 290, { align: 'center' });
    }

    doc.save(`Relatorio_Turnos_${new Date().toISOString().split('T')[0]}.pdf`);
    showSuccess(`PDF de turnos gerado com ${turnos.length} registro(s)!`);
}

function exportTurnosExcel() {
    const dropdown = document.getElementById('exportTurnosDropdown');
    if (dropdown) dropdown.style.display = 'none';

    const turnos = getVisibleTurnos();
    if (turnos.length === 0) {
        showError('Nenhum turno encontrado para exportar');
        return;
    }

    const wsData = [];
    wsData.push(['RELATORIO DE TURNOS']);
    wsData.push(['Gerado em: ' + new Date().toLocaleString('pt-PT')]);
    wsData.push([]);
    wsData.push(['Funcionário', 'Turno', 'Horário', 'Dias da Semana', 'Escala', 'Status']);

    turnos.forEach((item) => {
        wsData.push([
            item.funcionario,
            item.turno,
            item.horario,
            item.diasSemana,
            item.escala,
            item.status
        ]);
    });

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(wsData);
    ws['!cols'] = [
        { wch: 28 },
        { wch: 18 },
        { wch: 18 },
        { wch: 30 },
        { wch: 18 },
        { wch: 14 }
    ];

    XLSX.utils.book_append_sheet(wb, ws, 'Turnos');
    XLSX.writeFile(wb, `Relatorio_Turnos_${new Date().toISOString().split('T')[0]}.xlsx`);

    showSuccess(`Excel de turnos gerado com ${turnos.length} registro(s)!`);
}

// Helpers for dashboard controls added from UI
function openAllActivities() {
    const list = document.querySelector('.activity-list');
    if (!list) return;
    list.style.maxHeight = 'none';
    list.style.overflow = 'visible';
    list.scrollIntoView({behavior: 'smooth', block: 'start'});
    showSuccess('Mostrando todas as atividades');
}

function toggleActivityFilter() {
    showWarning('Filtro de atividades ainda não implementado.');
}

function openSettings() {
    showSection('definicoes');
}

// Remove itens da atividade recente apos 30s (inclui itens iniciais e novos).
(function initRecentActivityAutoExpiry() {
    if (window.__recentActivityAutoExpiryReady) return;
    window.__recentActivityAutoExpiryReady = true;

    const TTL_MS = 30000;

    function isPlaceholderItem(item) {
        if (!item) return false;
        const titleEl = item.querySelector('.activity-item-title');
        const text = (titleEl?.textContent || '').trim().toLowerCase();
        return text.includes('nenhuma atividade registada');
    }

    function ensurePlaceholder(list) {
        if (!list) return;
        const hasRealItems = Array.from(list.querySelectorAll('.activity-item')).some((item) => !isPlaceholderItem(item));
        if (hasRealItems) return;

        list.innerHTML = `
            <div class="activity-item info" data-expiry-disabled="1">
                <div class="activity-item-icon"><i class="fas fa-info-circle"></i></div>
                <div class="activity-details">
                    <div class="activity-item-title">Nenhuma atividade registada.</div>
                </div>
            </div>
        `;
    }

    function scheduleRemoval(item) {
        if (!item || item.dataset.expiryScheduled === '1') return;
        if (item.dataset.expiryDisabled === '1' || isPlaceholderItem(item)) return;

        item.dataset.expiryScheduled = '1';

        window.setTimeout(() => {
            if (!item.isConnected) return;
            const list = item.closest('.activity-list');
            item.remove();
            ensurePlaceholder(list);
        }, TTL_MS);
    }

    function initForList(list) {
        if (!list) return;

        list.querySelectorAll('.activity-item').forEach((item) => scheduleRemoval(item));

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof HTMLElement)) return;
                    if (node.matches('.activity-item')) {
                        scheduleRemoval(node);
                        return;
                    }
                    node.querySelectorAll?.('.activity-item').forEach((item) => scheduleRemoval(item));
                });
            });
        });

        observer.observe(list, { childList: true, subtree: true });
    }

    function start() {
        const list = document.querySelector('.activity-list');
        initForList(list);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();






 




    function toggleExportPresencaDropdown() {
        const dropdown = document.getElementById('exportPresencaDropdown');
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        } else {
            dropdown.style.display = 'block';
        }
    }

    function toggleExportHistoryPresencaDropdown() {
        const dropdown = document.getElementById('exportHistoryPresencaDropdown');
        if (!dropdown) return;
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        } else {
            dropdown.style.display = 'block';
        }
    }

    function getVisiblePresencaRows() {
        const table = document.getElementById('presencaTable');
        if (!table) return [];

        return Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
    }

    function getPresencaFilterPeriodLabel() {
        const start = document.getElementById('filterPresencaStart')?.value || '';
        const end = document.getElementById('filterPresencaEnd')?.value || '';

        if (start && end) return `Período: ${start} a ${end}`;
        if (start) return `Período: desde ${start}`;
        if (end) return `Período: até ${end}`;
        return 'Período: visão atual';
    }

    function applyPresencaServerFilter() {
        const start = document.getElementById('filterPresencaStart')?.value || '';
        const end = document.getElementById('filterPresencaEnd')?.value || '';

        const url = new URL(window.location.href);

        if (start) {
            url.searchParams.set('presenca_server_start', start);
        } else {
            url.searchParams.delete('presenca_server_start');
        }

        if (end) {
            url.searchParams.set('presenca_server_end', end);
        } else {
            url.searchParams.delete('presenca_server_end');
        }

        sessionStorage.setItem('activeSection', 'assiduidade');
        window.location.href = url.pathname + (url.search ? url.search : '');
    }

    function clearPresencaServerFilter() {
        const url = new URL(window.location.href);
        url.searchParams.delete('presenca_server_start');
        url.searchParams.delete('presenca_server_end');

        sessionStorage.setItem('activeSection', 'assiduidade');
        window.location.href = url.pathname + (url.search ? url.search : '');
    }

    function getVisibleHistoryPresencaRows() {
        const table = document.getElementById('historyPresencaTable');
        if (!table) return [];

        return Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
    }

    function getHistoryPresencaFilterPeriodLabel() {
        const start = document.getElementById('filterHistoryPresencaStart')?.value || '';
        const end = document.getElementById('filterHistoryPresencaEnd')?.value || '';

        if (start && end) return `Período: ${start} a ${end}`;
        if (start) return `Período: desde ${start}`;
        if (end) return `Período: até ${end}`;
        return 'Período: visão atual';
    }

    function applyHistoryPresencaServerFilter() {
        const start = document.getElementById('filterHistoryPresencaStart')?.value || '';
        const end = document.getElementById('filterHistoryPresencaEnd')?.value || '';

        const url = new URL(window.location.href);

        if (start) {
            url.searchParams.set('hist_server_start', start);
        } else {
            url.searchParams.delete('hist_server_start');
        }

        if (end) {
            url.searchParams.set('hist_server_end', end);
        } else {
            url.searchParams.delete('hist_server_end');
        }

        url.searchParams.delete('hist_page'); // reset to page 1 on filter change

        sessionStorage.setItem('activeSection', 'assiduidade');
        sessionStorage.setItem('openPresencaHistory', '1');
        window.location.href = url.pathname + (url.search ? url.search : '');
    }

    function clearHistoryPresencaServerFilter() {
        const url = new URL(window.location.href);
        url.searchParams.delete('hist_server_start');
        url.searchParams.delete('hist_server_end');
        url.searchParams.delete('hist_page');

        sessionStorage.setItem('activeSection', 'assiduidade');
        sessionStorage.setItem('openPresencaHistory', '1');
        window.location.href = url.pathname + (url.search ? url.search : '');
    }

    function goToHistoryPage(page) {
        const url = new URL(window.location.href);
        url.searchParams.set('hist_page', page);
        sessionStorage.setItem('activeSection', 'assiduidade');
        sessionStorage.setItem('openPresencaHistory', '1');
        window.location.href = url.pathname + (url.search ? url.search : '');
    }

    function applySolicitacoesServerFilter() {
        const start = document.getElementById('filterSolStart')?.value || '';
        const end   = document.getElementById('filterSolEnd')?.value || '';

        const url = new URL(window.location.href);

        if (start) {
            url.searchParams.set('sol_server_start', start);
        } else {
            url.searchParams.delete('sol_server_start');
        }

        if (end) {
            url.searchParams.set('sol_server_end', end);
        } else {
            url.searchParams.delete('sol_server_end');
        }

        sessionStorage.setItem('activeSection', 'solicitacoes');
        window.location.href = url.pathname + (url.search ? url.search : '');
    }

    function clearSolicitacoesServerFilter() {
        const url = new URL(window.location.href);
        url.searchParams.delete('sol_server_start');
        url.searchParams.delete('sol_server_end');

        sessionStorage.setItem('activeSection', 'solicitacoes');
        window.location.href = url.pathname + (url.search ? url.search : '');
    }

    function exportPresencaCSV() {
        const date = new Date().toISOString().split('T')[0];
        const table = document.getElementById('presencaTable');
        if (!table) {
            showError('Tabela não encontrada');
            return;
        }
        const rows = getVisiblePresencaRows();
        if (rows.length === 0) {
            showWarning('Nenhum registo visível para exportar.');
            return;
        }
        const separator = ';';
        let csvContent = `ID${separator}Funcionário${separator}Status${separator}Data${separator}Tipo de Dia${separator}Entrada${separator}Saída${separator}Horas Trabalhadas${separator}Atraso${separator}Tipo de Falta${separator}Confirmação${separator}Observação\n`;
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const id = row.getAttribute('data-employee-id') || '';
            const baseData = [
                cells[0]?.textContent.trim() || '',
                cells[1]?.textContent.trim() || '',
                cells[2]?.textContent.trim() || '',
                row.dataset.tipoDia || '',
                row.dataset.horaEntrada || '--:--',
                row.dataset.horaSaida || '--:--',
                row.dataset.horas || '',
                row.dataset.atraso || '',
                row.dataset.faltaTipo || '',
                row.dataset.confirmacao || '',
                row.dataset.obs || ''
            ];
            const rowData = [id].concat(baseData.map(textRaw => {
                let text = String(textRaw).replace(/"/g, '""');
                if (text.includes(separator) || text.includes('\n')) {
                    return `"${text}"`;
                }
                return text;
            }));
            csvContent += rowData.join(separator) + '\n';
        });
        const blob = new Blob(["\ufeff" + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = `registro_presenca_${date}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        document.getElementById('exportPresencaDropdown').style.display = 'none';
        showSuccess(`Exportação CSV realizada com ${rows.length} registo(s).`);
    }

    function exportPresencaPDF() {
        const date = new Date().toISOString().split('T')[0];
        const table = document.getElementById('presencaTable');
        if (!table) {
            showError('Tabela não encontrada');
            return;
        }
        const rows = getVisiblePresencaRows();
        if (rows.length === 0) {
            showWarning('Nenhum registo visível para exportar.');
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFont('helvetica');
        doc.setFontSize(16);
        doc.setTextColor(52, 152, 219);
        doc.text('Registos de Presença e Ponto', 105, 20, { align: 'center' });
        doc.setFontSize(10);
        doc.setTextColor(100, 116, 139);
        doc.text(getPresencaFilterPeriodLabel(), 105, 28, { align: 'center' });
        const headers = ['ID', 'Funcionário', 'Status', 'Data', 'Tipo Dia', 'Entrada', 'Saída', 'Horas', 'Atraso', 'Tipo Falta'];
        const body = rows.map(row => {
            const cells = row.querySelectorAll('td');
            const id = row.getAttribute('data-employee-id') || '';
            return [
                id,
                cells[0]?.textContent.trim() || '',
                cells[1]?.textContent.trim() || '',
                cells[2]?.textContent.trim() || '',
                row.dataset.tipoDia || '',
                row.dataset.horaEntrada || '--:--',
                row.dataset.horaSaida || '--:--',
                row.dataset.horas || '',
                row.dataset.atraso || '',
                row.dataset.faltaTipo || ''
            ];
        });
        doc.autoTable({
            head: [headers],
            body,
            startY: 35,
            styles: { fontSize: 8, cellPadding: 2 },
            headStyles: { fillColor: [37, 99, 235] }
        });
        doc.setFontSize(8);
        doc.setTextColor(150, 150, 150);
        doc.text('Gerado em: ' + new Date().toLocaleString('pt-PT'), 105, 285, { align: 'center' });
        doc.text('RHNeto Pro - Sistema de Gestão de Recursos Humanos', 105, 290, { align: 'center' });
        doc.save(`registro_presenca_${date}.pdf`);
        showSuccess(`Exportação PDF realizada com ${rows.length} registo(s).`);
        document.getElementById('exportPresencaDropdown').style.display = 'none';
    }

    function exportHistoryPresencaCSV() {
        const date = new Date().toISOString().split('T')[0];
        const table = document.getElementById('historyPresencaTable');
        if (!table) {
            showError('Tabela de histórico não encontrada.');
            return;
        }

        const rows = getVisibleHistoryPresencaRows();
        if (rows.length === 0) {
            showWarning('Nenhum registo visível no histórico para exportar.');
            return;
        }

        const separator = ';';
        let csvContent = `Funcionário${separator}Data${separator}Status${separator}Tipo de Dia${separator}Entrada${separator}Saída${separator}Observação\n`;

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const rowData = [
                cells[0]?.textContent.trim() || '',
                cells[1]?.textContent.trim() || '',
                cells[2]?.textContent.trim() || '',
                cells[3]?.textContent.trim() || '',
                cells[4]?.textContent.trim() || '--:--',
                cells[5]?.textContent.trim() || '--:--',
                cells[6]?.textContent.trim() || ''
            ].map(textRaw => {
                let text = String(textRaw).replace(/"/g, '""');
                if (text.includes(separator) || text.includes('\n')) {
                    return `"${text}"`;
                }
                return text;
            });

            csvContent += rowData.join(separator) + '\n';
        });

        const blob = new Blob(["\ufeff" + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = `historico_presenca_${date}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        const dropdown = document.getElementById('exportHistoryPresencaDropdown');
        if (dropdown) dropdown.style.display = 'none';
        showSuccess(`Exportação CSV do histórico realizada com ${rows.length} registo(s).`);
    }

    function exportHistoryPresencaPDF() {
        const date = new Date().toISOString().split('T')[0];
        const table = document.getElementById('historyPresencaTable');
        if (!table) {
            showError('Tabela de histórico não encontrada.');
            return;
        }

        const rows = getVisibleHistoryPresencaRows();
        if (rows.length === 0) {
            showWarning('Nenhum registo visível no histórico para exportar.');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        doc.setFont('helvetica');
        doc.setFontSize(16);
        doc.setTextColor(52, 152, 219);
        doc.text('Histórico de Presença', 105, 20, { align: 'center' });

        doc.setFontSize(10);
        doc.setTextColor(100, 116, 139);
        doc.text(getHistoryPresencaFilterPeriodLabel(), 105, 28, { align: 'center' });

        const headers = ['Funcionário', 'Data', 'Status', 'Tipo Dia', 'Entrada', 'Saída', 'Observação'];
        const body = rows.map(row => {
            const cells = row.querySelectorAll('td');
            return [
                cells[0]?.textContent.trim() || '',
                cells[1]?.textContent.trim() || '',
                cells[2]?.textContent.trim() || '',
                cells[3]?.textContent.trim() || '',
                cells[4]?.textContent.trim() || '--:--',
                cells[5]?.textContent.trim() || '--:--',
                cells[6]?.textContent.trim() || ''
            ];
        });

        doc.autoTable({
            head: [headers],
            body,
            startY: 35,
            styles: { fontSize: 8, cellPadding: 2 },
            headStyles: { fillColor: [37, 99, 235] }
        });

        doc.setFontSize(8);
        doc.setTextColor(150, 150, 150);
        doc.text('Gerado em: ' + new Date().toLocaleString('pt-PT'), 105, 285, { align: 'center' });
        doc.text('RHNeto Pro - Sistema de Gestão de Recursos Humanos', 105, 290, { align: 'center' });
        doc.save(`historico_presenca_${date}.pdf`);

        const dropdown = document.getElementById('exportHistoryPresencaDropdown');
        if (dropdown) dropdown.style.display = 'none';
        showSuccess(`Exportação PDF do histórico realizada com ${rows.length} registo(s).`);
    }

    window.toggleExportPresencaDropdown = toggleExportPresencaDropdown;
    window.exportPresencaCSV = exportPresencaCSV;
    window.exportPresencaPDF = exportPresencaPDF;
    window.applyPresencaServerFilter = applyPresencaServerFilter;
    window.clearPresencaServerFilter = clearPresencaServerFilter;
    window.toggleExportHistoryPresencaDropdown = toggleExportHistoryPresencaDropdown;
    window.exportHistoryPresencaCSV = exportHistoryPresencaCSV;
    window.exportHistoryPresencaPDF = exportHistoryPresencaPDF;
    window.applyHistoryPresencaServerFilter = applyHistoryPresencaServerFilter;
    window.clearHistoryPresencaServerFilter = clearHistoryPresencaServerFilter;
    window.goToHistoryPage = goToHistoryPage;
    window.applySolicitacoesServerFilter = applySolicitacoesServerFilter;
    window.clearSolicitacoesServerFilter = clearSolicitacoesServerFilter;





// ==========================================
// SISTEMA DE NOTIFICAÇÕES (SweetAlert2)
// ==========================================

const toastVariants = {
    success: {
        icon: 'success',
        timer: 2800,
        customClass: 'swal-toast-success'
    },
    error: {
        icon: 'error',
        timer: 3800,
        customClass: 'swal-toast-error'
    },
    warning: {
        icon: 'warning',
        timer: 3400,
        customClass: 'swal-toast-warning'
    },
    info: {
        icon: 'info',
        timer: 3200,
        customClass: 'swal-toast-info'
    }
};

function fireDashboardToast(variant, message) {
    const config = toastVariants[variant] || toastVariants.info;

    Swal.fire({
        toast: true,
        backdrop: false,
        icon: config.icon,
        title: message,
        position: 'top',
        showConfirmButton: false,
        timer: config.timer,
        timerProgressBar: true,
        customClass: {
            popup: `swal-toast ${config.customClass}`,
            title: 'swal-toast-title'
        },
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
}

function showSuccess(message) {
    fireDashboardToast('success', message);
}

function showError(message) {
    fireDashboardToast('error', message);
}

function showWarning(message) {
    fireDashboardToast('warning', message);
}

function showInfo(message) {
    fireDashboardToast('info', message);
}

// Função para confirmação com SweetAlert2
async function showConfirm(title, text, confirmButtonText = 'Sim', cancelButtonText = 'Cancelar') {
    const result = await Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: confirmButtonText,
        cancelButtonText: cancelButtonText,
        customClass: {
            popup: 'swal-popup-custom',
            title: 'swal-title-custom',
            confirmButton: 'swal-confirm-button',
            cancelButton: 'swal-cancel-button'
        },
        buttonsStyling: true,
        reverseButtons: true
    });
    return result.isConfirmed;
}

// ========== SISTEMA DE FILTROS E PESQUISA ==========
document.addEventListener('DOMContentLoaded', function() {
    // ========== FILTROS RÁPIDOS ==========
    window.quickFilter = function(filterType) {
        // Atualizar visual dos botões
        document.querySelectorAll('.filter-badge').forEach(btn => {
            const isActive = btn.dataset.filter === filterType;
            if (isActive) {
                btn.classList.add('active');
                if (filterType === 'all') {
                    btn.style.background = 'var(--primary-500)';
                    btn.style.color = 'white';
                } else if (filterType === 'active') {
                    btn.style.background = '#10b981';
                    btn.style.color = 'white';
                } else if (filterType === 'inactive') {
                    btn.style.background = '#6b7280';
                    btn.style.color = 'white';
                } else if (filterType === 'ferias') {
                    btn.style.background = '#3b82f6';
                    btn.style.color = 'white';
                }
            } else {
                btn.classList.remove('active');
                const borderColor = btn.dataset.filter === 'all' ? 'var(--primary-500)' : 
                                   btn.dataset.filter === 'active' ? '#10b981' :
                                   btn.dataset.filter === 'inactive' ? '#6b7280' : '#3b82f6';
                btn.style.background = 'transparent';
                btn.style.color = borderColor;
            }
        });
        
        // Filtrar tabela
        const rows = document.querySelectorAll('#employeesTable tbody tr');
        rows.forEach(row => {
            if (filterType === 'all') {
                row.style.display = '';
            } else {
                const statusBadge = row.querySelector('.status-badge');
                const statusClass = statusBadge?.className || '';
                
                let shouldShow = false;
                if (filterType === 'active' && statusClass.includes('status-active')) shouldShow = true;
                if (filterType === 'inactive' && statusClass.includes('status-inactive')) shouldShow = true;
                if (filterType === 'ferias' && statusClass.includes('status-ferias')) shouldShow = true;
                
                row.style.display = shouldShow ? '' : 'none';
            }
        });
    };
    
    // ========== FILTROS PARA PRESENÇAS ==========
    const searchPresenca = document.getElementById('searchPresenca');
    const filterPresencaStart = document.getElementById('filterPresencaStart');
    const filterPresencaEnd = document.getElementById('filterPresencaEnd');
    const filterPresencaStatus = document.getElementById('filterPresencaStatus');
    const clearFiltersPresenca = document.getElementById('clearFiltersPresenca');
    const resultCountPresenca = document.getElementById('resultCountPresenca');
    const presencaTable = document.querySelector('#presencaTable tbody');
    const summaryPresencaVisible = document.getElementById('summaryPresencaVisible');
    const summaryPresencaPresentes = document.getElementById('summaryPresencaPresentes');
    const summaryPresencaFaltas = document.getElementById('summaryPresencaFaltas');
    const summaryPresencaAtrasados = document.getElementById('summaryPresencaAtrasados');
    const summaryPresencaEmAberto = document.getElementById('summaryPresencaEmAberto');
    const summaryPresencaSemTurno = document.getElementById('summaryPresencaSemTurno');

    function normalizePresencaValue(value) {
        return (value || '').toString().trim().toLowerCase();
    }

    function getPresencaStatusKey(row) {
        const datasetKey = normalizePresencaValue(row?.dataset?.statusKey);
        if (datasetKey) return datasetKey;

        const statusBadge = row?.querySelector('.status-badge');
        const badgeKey = normalizePresencaValue(statusBadge?.dataset?.statusKey);
        if (badgeKey) {
            row.dataset.statusKey = badgeKey;
            return badgeKey;
        }

        const statusText = normalizePresencaValue(statusBadge?.textContent);
        if (statusText.includes('sem turno')) return 'sem-turno';
        if (statusText.includes('falta')) return 'falta';
        if (statusText.includes('em aberto')) return 'em-aberto';
        if (statusText.includes('atras')) return 'atrasado';
        if (statusText.includes('férias') || statusText.includes('ferias')) return 'ferias';
        if (statusText.includes('inativo')) return 'inativo';
        if (statusText.includes('presente')) return 'presente';
        if (statusText.includes('não registado') || statusText.includes('nao registado')) return 'nao-registrado';
        return 'invalidado';
    }

    function matchesDateInterval(rowDate, startDate, endDate) {
        if (startDate && (!rowDate || rowDate < startDate)) return false;
        if (endDate && (!rowDate || rowDate > endDate)) return false;
        return true;
    }

    function updatePresencaSummaryCards(rows) {
        const counts = {
            visible: 0,
            presente: 0,
            falta: 0,
            atrasado: 0,
            'em-aberto': 0,
            'sem-turno': 0
        };

        rows.forEach(row => {
            if (row.style.display === 'none') return;

            counts.visible += 1;
            const statusKey = getPresencaStatusKey(row);
            if (statusKey in counts) counts[statusKey] += 1;
        });

        if (summaryPresencaVisible) summaryPresencaVisible.textContent = String(counts.visible);
        if (summaryPresencaPresentes) summaryPresencaPresentes.textContent = String(counts.presente);
        if (summaryPresencaFaltas) summaryPresencaFaltas.textContent = String(counts.falta);
        if (summaryPresencaAtrasados) summaryPresencaAtrasados.textContent = String(counts.atrasado);
        if (summaryPresencaEmAberto) summaryPresencaEmAberto.textContent = String(counts['em-aberto']);
        if (summaryPresencaSemTurno) summaryPresencaSemTurno.textContent = String(counts['sem-turno']);
    }

    function applyPresencaOperationalHighlight(row, statusKey) {
        if (!row) return;

        row.style.borderLeft = '';
        row.style.background = '';

        const statusBadge = row.querySelector('.status-badge');
        if (statusBadge) {
            statusBadge.style.boxShadow = '';
            statusBadge.style.fontWeight = '';
        }

        if (statusKey === 'em-aberto') {
            row.style.borderLeft = '4px solid #f97316';
            row.style.background = 'linear-gradient(90deg, rgba(251,146,60,.12), rgba(251,146,60,0))';
            if (statusBadge) {
                statusBadge.style.boxShadow = '0 0 0 2px rgba(251,146,60,.28)';
                statusBadge.style.fontWeight = '700';
            }
        } else if (statusKey === 'atrasado') {
            row.style.borderLeft = '4px solid #f59e0b';
            row.style.background = 'linear-gradient(90deg, rgba(245,158,11,.12), rgba(245,158,11,0))';
            if (statusBadge) {
                statusBadge.style.boxShadow = '0 0 0 2px rgba(245,158,11,.25)';
                statusBadge.style.fontWeight = '700';
            }
        }
    }

    function filterPresencaTable() {
        if (!presencaTable) return;

        const searchValue = searchPresenca ? normalizePresencaValue(searchPresenca.value) : '';
        const startValue = filterPresencaStart ? filterPresencaStart.value : '';
        const endValue = filterPresencaEnd ? filterPresencaEnd.value : '';
        const statusValue = filterPresencaStatus ? normalizePresencaValue(filterPresencaStatus.value) : '';
        const rows = presencaTable.querySelectorAll('tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = normalizePresencaValue(row.cells[0]?.textContent || '');
            const statusKey = getPresencaStatusKey(row);
            const rowDate = row.dataset.presencaDate || '';

            applyPresencaOperationalHighlight(row, statusKey);

            const matchesSearch = searchValue === '' || name.includes(searchValue);
            const matchesPeriod = matchesDateInterval(rowDate, startValue, endValue);
            const matchesStatus = statusValue === '' || statusKey === statusValue;

            if (matchesSearch && matchesStatus && matchesPeriod) {
                row.style.display = '';
                visibleCount += 1;
            } else {
                row.style.display = 'none';
            }
        });

        if (resultCountPresenca) resultCountPresenca.textContent = `${visibleCount} ${visibleCount === 1 ? 'resultado' : 'resultados'}`;

        const hasFilters = searchValue || statusValue || startValue || endValue;
        if (clearFiltersPresenca) clearFiltersPresenca.style.display = hasFilters ? 'block' : 'none';

        updatePresencaSummaryCards(Array.from(rows));
        syncPresencaKpiActive(statusValue);
        updatePresencaFilterBadge();
    }

    function syncPresencaKpiActive(activeKey) {
        const strip = document.getElementById('presencaSummaryCards');
        if (!strip) return;
        strip.querySelectorAll('.pa-kpi-card').forEach(card => {
            const key = card.dataset.statusKey ?? '';
            card.classList.toggle('pa-kpi-active', key === activeKey);
        });
    }

    function updatePresencaFilterBadge() {
        const badge = document.getElementById('paFilterBadge');
        if (!badge) return;
        const count = [
            filterPresencaStart?.value,
            filterPresencaEnd?.value,
            filterPresencaStatus?.value,
        ].filter(Boolean).length;
        if (count > 0) {
            badge.textContent = String(count);
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    if (searchPresenca) searchPresenca.addEventListener('input', filterPresencaTable);
    if (filterPresencaStart) filterPresencaStart.addEventListener('change', filterPresencaTable);
    if (filterPresencaEnd) filterPresencaEnd.addEventListener('change', filterPresencaTable);
    if (filterPresencaStatus) filterPresencaStatus.addEventListener('change', filterPresencaTable);

    if (clearFiltersPresenca) {
        clearFiltersPresenca.addEventListener('click', function() {
            if (searchPresenca) searchPresenca.value = '';
            if (filterPresencaStart) filterPresencaStart.value = '';
            if (filterPresencaEnd) filterPresencaEnd.value = '';
            if (filterPresencaStatus) filterPresencaStatus.value = '';
            filterPresencaTable();
        });
    }

    window.filterPresencaTable = filterPresencaTable;

    if (presencaTable) filterPresencaTable();

    // Auto-open filter panel if any filter is already set (e.g. server-side date pre-filled)
    const _paHasFilters = [filterPresencaStart?.value, filterPresencaEnd?.value, filterPresencaStatus?.value].some(Boolean);
    if (_paHasFilters) {
        const _paPanel = document.getElementById('paAdvFilters');
        const _paToggle = document.getElementById('paFilterToggle');
        if (_paPanel) _paPanel.classList.add('fr-adv-open');
        if (_paToggle) _paToggle.classList.add('pa-filter-open');
    }

    // ========== FILTROS PARA HISTÓRICO DE PRESENÇA ==========
    const searchHistoryPresenca = document.getElementById('searchHistoryPresenca');
    const filterHistoryPresencaStart = document.getElementById('filterHistoryPresencaStart');
    const filterHistoryPresencaEnd = document.getElementById('filterHistoryPresencaEnd');
    const filterHistoryPresencaStatus = document.getElementById('filterHistoryPresencaStatus');
    const clearHistoryPresenca = document.getElementById('clearHistoryPresenca');
    const resultCountHistoryPresenca = document.getElementById('resultCountHistoryPresenca');
    const historyPresencaTable = document.querySelector('#historyPresencaTable tbody');

    function filterHistoryPresencaTable() {
        if (!historyPresencaTable) return;

        const searchValue = searchHistoryPresenca ? normalizePresencaValue(searchHistoryPresenca.value) : '';
        const startValue = filterHistoryPresencaStart ? filterHistoryPresencaStart.value : '';
        const endValue = filterHistoryPresencaEnd ? filterHistoryPresencaEnd.value : '';
        const statusValue = filterHistoryPresencaStatus ? normalizePresencaValue(filterHistoryPresencaStatus.value) : '';
        const rows = historyPresencaTable.querySelectorAll('tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = normalizePresencaValue(row.dataset.historyName || row.cells[0]?.textContent || '');
            const rowDate = row.dataset.historyDate || '';
            const rowStatus = normalizePresencaValue(row.dataset.historyStatusKey || '');

            const matchesSearch = searchValue === '' || name.includes(searchValue);
            const matchesPeriod = matchesDateInterval(rowDate, startValue, endValue);
            const matchesStatus = statusValue === '' || rowStatus === statusValue;

            if (matchesSearch && matchesPeriod && matchesStatus) {
                row.style.display = '';
                visibleCount += 1;
            } else {
                row.style.display = 'none';
            }
        });

        if (resultCountHistoryPresenca) {
            resultCountHistoryPresenca.textContent = `${visibleCount} ${visibleCount === 1 ? 'resultado' : 'resultados'}`;
        }

        const hasFilters = searchValue || startValue || endValue || statusValue;
        if (clearHistoryPresenca) clearHistoryPresenca.style.display = hasFilters ? 'block' : 'none';
    }

    if (searchHistoryPresenca) searchHistoryPresenca.addEventListener('input', filterHistoryPresencaTable);
    if (filterHistoryPresencaStart) filterHistoryPresencaStart.addEventListener('change', filterHistoryPresencaTable);
    if (filterHistoryPresencaEnd) filterHistoryPresencaEnd.addEventListener('change', filterHistoryPresencaTable);
    if (filterHistoryPresencaStatus) filterHistoryPresencaStatus.addEventListener('change', filterHistoryPresencaTable);

    if (clearHistoryPresenca) {
        clearHistoryPresenca.addEventListener('click', function() {
            if (searchHistoryPresenca) searchHistoryPresenca.value = '';
            if (filterHistoryPresencaStart) filterHistoryPresencaStart.value = '';
            if (filterHistoryPresencaEnd) filterHistoryPresencaEnd.value = '';
            if (filterHistoryPresencaStatus) filterHistoryPresencaStatus.value = '';
            filterHistoryPresencaTable();
        });
    }

    window.filterHistoryPresencaTable = filterHistoryPresencaTable;

    if (historyPresencaTable) filterHistoryPresencaTable();
    
    // ========== FILTROS PARA TURNOS ==========
    const searchTurnos = document.getElementById('searchTurnos');
    const turnosFilterTipo = document.getElementById('turnosFilterTipo');
    const turnosFilterEscala = document.getElementById('turnosFilterEscala');
    const turnosFilterStatus = document.getElementById('turnosFilterStatus');
    const turnosFilterDepartment = document.getElementById('turnosFilterDepartment');
    const turnosFilterPeriodStart = document.getElementById('turnosFilterPeriodStart');
    const turnosFilterPeriodEnd = document.getElementById('turnosFilterPeriodEnd');
    const clearTurnosAdvancedFilters = document.getElementById('clearTurnosAdvancedFilters');
    const btnTurnosMissingCoverage = document.getElementById('btnTurnosMissingCoverage');
    const turnosAllEmployeesCatalog = document.getElementById('turnosAllEmployeesCatalog');
    const turnosTable = document.querySelector('#turnosTable tbody');
    const turnosTableElement = document.getElementById('turnosTable');
    const turnosCalendarWrapper = document.getElementById('turnosCalendarWrapper');
    const turnosCalendarGrid = document.getElementById('turnosCalendarGrid');
    const turnosCalendarRangeLabel = document.getElementById('turnosCalendarRangeLabel');
    const btnTurnosViewTable = document.getElementById('btnTurnosViewTable');
    const btnTurnosViewWeek = document.getElementById('btnTurnosViewWeek');
    const btnTurnosViewMonth = document.getElementById('btnTurnosViewMonth');
    const turnosCalendarPrev = document.getElementById('turnosCalendarPrev');
    const turnosCalendarNext = document.getElementById('turnosCalendarNext');
    const turnosCalendarToday = document.getElementById('turnosCalendarToday');

    const weekDayOrder = [1, 2, 3, 4, 5, 6, 0];
    const weekDayShortLabels = {
        0: 'Dom',
        1: 'Seg',
        2: 'Ter',
        3: 'Qua',
        4: 'Qui',
        5: 'Sex',
        6: 'Sáb'
    };
    const monthNames = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    let turnosCalendarView = 'week';
    let turnosCalendarAnchor = new Date();

    function normalizeText(value) {
        return (value || '').toString().trim().toLowerCase();
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

    function capitalizeText(value) {
        if (!value) return '';
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function normalizeDayToken(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/-feira/g, '')
            .replace(/\./g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getDayIndexFromToken(token) {
        const map = {
            segunda: 1,
            seg: 1,
            terca: 2,
            ter: 2,
            quarta: 3,
            qua: 3,
            quinta: 4,
            qui: 4,
            sexta: 5,
            sex: 5,
            sabado: 6,
            sab: 6,
            domingo: 0,
            dom: 0
        };
        return Object.prototype.hasOwnProperty.call(map, token) ? map[token] : null;
    }

    function parseTurnoDays(diasText) {
        const parts = String(diasText || '').split(',').map(part => normalizeDayToken(part)).filter(Boolean);
        const unique = new Set();
        parts.forEach(token => {
            const idx = getDayIndexFromToken(token);
            if (idx !== null) unique.add(idx);
        });
        return Array.from(unique);
    }

    function getTurnosRows(includeHidden = true) {
        if (!turnosTable) return [];
        const allRows = Array.from(turnosTable.querySelectorAll('tr'));
        if (includeHidden) return allRows;
        return allRows.filter(row => row.style.display !== 'none');
    }

    function getCatalogEmployees() {
        if (!turnosAllEmployeesCatalog) return [];
        return Array.from(turnosAllEmployeesCatalog.querySelectorAll('option')).map(option => ({
            id: Number(option.value || 0),
            name: String(option.dataset.name || option.textContent || '').trim(),
            team: String(option.dataset.team || '').trim()
        })).filter(emp => emp.id > 0 && emp.name !== '');
    }

    function getTurnoFuncionarioName(row) {
        const explicitName = row.querySelector('td:first-child span');
        if (explicitName && explicitName.textContent.trim() !== '') {
            return explicitName.textContent.trim();
        }
        return (row.cells[0]?.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function extractTurnoModel(row) {
        const funcionario = row.dataset.funcionario || getTurnoFuncionarioName(row);
        const funcionarioId = Number(row.dataset.turnoEmployeeId || 0);
        const turnoTipo = row.dataset.turnoTipo || (row.cells[1]?.textContent || '').trim();
        const horarioText = (row.cells[2]?.textContent || '').replace(/\s+/g, ' ').trim();
        const diasText = row.dataset.turnoDias || (row.cells[3]?.textContent || '').trim();
        const escala = row.dataset.turnoEscala || (row.cells[4]?.textContent || '').trim();
        const team = row.dataset.turnoTeam || '';
        const statusText = (row.dataset.turnoStatus || row.querySelector('.status-badge')?.textContent || row.cells[5]?.textContent || '').trim().toLowerCase();
        const horarioParts = horarioText.split('-').map(part => part.trim());
        const horarioInicio = horarioParts[0] || '';
        const horarioFim = horarioParts[1] || '';
        const dataInicio = normalizeIsoDate(row.dataset.turnoDateStart || '');
        const dataFim = normalizeIsoDate(row.dataset.turnoDateEnd || '');

        return {
            funcionario,
            funcionarioId,
            turnoTipo,
            horarioText,
            horarioInicio,
            horarioFim,
            diasText,
            diasIndexes: parseTurnoDays(diasText),
            dataInicio,
            dataFim,
            vigenciaText: formatTurnoRangeLabel(dataInicio, dataFim),
            escala,
            team,
            statusText
        };
    }

    function dateToIsoLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function turnoOccursOnDate(turno, date) {
        if (!turno) return false;

        const isoDate = dateToIsoLocal(date);
        if (turno.dataInicio && isoDate < turno.dataInicio) {
            return false;
        }

        if (turno.dataFim && isoDate > turno.dataFim) {
            return false;
        }

        if (turno.diasIndexes.length > 0) {
            return turno.diasIndexes.includes(date.getDay());
        }

        return true;
    }

    function normalizePeriodRange(start, end) {
        const isoStart = normalizeIsoDate(start || '');
        const isoEnd = normalizeIsoDate(end || '');
        if (!isoStart && !isoEnd) {
            return { start: '', end: '' };
        }

        if (isoStart && !isoEnd) {
            return { start: isoStart, end: isoStart };
        }

        if (!isoStart && isoEnd) {
            return { start: isoEnd, end: isoEnd };
        }

        return { start: isoStart, end: isoEnd };
    }

    function hasOccurrenceInPeriod(turno, periodStart, periodEnd) {
        const range = normalizePeriodRange(periodStart, periodEnd);
        if (!range.start || !range.end) {
            return true;
        }

        if (range.start > range.end) {
            return false;
        }

        const startDate = new Date(`${range.start}T00:00:00`);
        const endDate = new Date(`${range.end}T00:00:00`);

        const maxDays = 93;
        let cursor = new Date(startDate);
        let iterations = 0;

        while (cursor <= endDate && iterations <= maxDays) {
            if (turnoOccursOnDate(turno, cursor)) {
                return true;
            }
            cursor = addDays(cursor, 1);
            iterations += 1;
        }

        return false;
    }

    function startOfWeek(date) {
        const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const day = d.getDay();
        const diff = day === 0 ? -6 : 1 - day;
        d.setDate(d.getDate() + diff);
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function addDays(date, days) {
        const d = new Date(date);
        d.setDate(d.getDate() + days);
        return d;
    }

    function formatDate(date) {
        return String(date.getDate()).padStart(2, '0') + '/' + String(date.getMonth() + 1).padStart(2, '0');
    }

    function formatWeekRangeLabel(anchorDate) {
        const weekStart = startOfWeek(anchorDate);
        const weekEnd = addDays(weekStart, 6);
        const startText = formatDate(weekStart);
        const endText = formatDate(weekEnd);
        return `Semana ${startText} - ${endText}`;
    }

    function formatMonthRangeLabel(anchorDate) {
        const monthLabel = monthNames[anchorDate.getMonth()] || '';
        return `${capitalizeText(monthLabel)} ${anchorDate.getFullYear()}`;
    }

    function isSameDate(a, b) {
        return a.getFullYear() === b.getFullYear() &&
            a.getMonth() === b.getMonth() &&
            a.getDate() === b.getDate();
    }

    function getTurnoTypeClass(turnoTipo) {
        const normalized = normalizeText(turnoTipo)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

        if (normalized.includes('manha')) return 'type-manha';
        if (normalized.includes('tarde')) return 'type-tarde';
        if (normalized.includes('noturno')) return 'type-noturno';
        if (normalized.includes('intermitente')) return 'type-intermitente';
        return 'type-default';
    }

    function getTurnoTypeAccent(turnoTipo) {
        const typeClass = getTurnoTypeClass(turnoTipo);
        if (typeClass === 'type-manha') return '#f59e0b';
        if (typeClass === 'type-tarde') return '#0ea5e9';
        if (typeClass === 'type-noturno') return '#6366f1';
        if (typeClass === 'type-intermitente') return '#14b8a6';
        return '#64748b';
    }

    function styleDayCountBadge(element) {
        if (!element) return;
        element.style.display = 'inline-flex';
        element.style.alignItems = 'center';
        element.style.justifyContent = 'center';
        element.style.minWidth = '24px';
        element.style.height = '24px';
        element.style.padding = '0 8px';
        element.style.borderRadius = '999px';
        element.style.background = '#1d4ed8';
        element.style.color = '#ffffff';
        element.style.fontSize = '0.72rem';
        element.style.fontWeight = '700';
        element.style.lineHeight = '1';
        element.style.boxShadow = '0 6px 14px rgba(29, 78, 216, 0.18)';
    }

    function styleCalendarCard(card, turno) {
        const accent = getTurnoTypeAccent(turno.turnoTipo);
        const isInativo = turno.statusText.includes('inativo');

        card.style.borderRadius = '12px';
        card.style.padding = '0.75rem';
        card.style.border = isInativo ? '1px solid #fecaca' : '1px solid #bfdbfe';
        card.style.borderLeft = `4px solid ${accent}`;
        card.style.background = isInativo
            ? 'linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%)'
            : 'linear-gradient(135deg, #ffffff 0%, #eff6ff 100%)';
        card.style.boxShadow = '0 10px 24px rgba(15, 23, 42, 0.08)';
        card.style.display = 'flex';
        card.style.flexDirection = 'column';
        card.style.gap = '0.25rem';
    }

    function styleCalendarShell() {
        if (turnosCalendarWrapper) {
            turnosCalendarWrapper.style.marginTop = '1rem';
            turnosCalendarWrapper.style.border = '1px solid #dbe6f2';
            turnosCalendarWrapper.style.borderRadius = '18px';
            turnosCalendarWrapper.style.background = 'linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%)';
            turnosCalendarWrapper.style.padding = '1rem';
            turnosCalendarWrapper.style.boxShadow = '0 18px 38px rgba(15, 23, 42, 0.08)';
        }

        if (turnosCalendarGrid) {
            turnosCalendarGrid.style.width = '100%';
        }
    }

    function createTurnoCard(turno) {
        const card = document.createElement('div');
        const statusClass = turno.statusText.includes('inativo') ? 'is-inativo' : 'is-ativo';
        const typeClass = getTurnoTypeClass(turno.turnoTipo);
        card.className = `turnos-calendar-card ${statusClass} ${typeClass}`;
        styleCalendarCard(card, turno);

        const title = document.createElement('div');
        title.className = 'turnos-calendar-card-title';
        title.textContent = turno.funcionario || 'Funcionário';
        title.style.fontWeight = '800';
        title.style.color = '#0f172a';
        title.style.fontSize = '0.88rem';

        const subtitle = document.createElement('div');
        subtitle.className = 'turnos-calendar-card-subtitle';
        subtitle.textContent = `${turno.turnoTipo || '-'} | ${turno.horarioText || '-'}`;
        subtitle.style.color = '#1e3a5f';
        subtitle.style.fontSize = '0.8rem';
        subtitle.style.fontWeight = '600';

        const meta = document.createElement('div');
        meta.className = 'turnos-calendar-card-meta';
        meta.textContent = `Escala: ${turno.escala || '-'} | ${capitalizeText(turno.statusText || 'ativo')}`;
        meta.style.color = '#475569';
        meta.style.fontSize = '0.76rem';

        if (turno.vigenciaText) {
            const rangeMeta = document.createElement('div');
            rangeMeta.textContent = `Vigência: ${turno.vigenciaText}`;
            rangeMeta.style.color = '#64748b';
            rangeMeta.style.fontSize = '0.74rem';
            card.appendChild(title);
            card.appendChild(subtitle);
            card.appendChild(meta);
            card.appendChild(rangeMeta);
            return card;
        }

        card.appendChild(title);
        card.appendChild(subtitle);
        card.appendChild(meta);
        return card;
    }

    function renderWeekCalendar(turnosData) {
        if (!turnosCalendarGrid) return;
        styleCalendarShell();

        const weekStart = startOfWeek(turnosCalendarAnchor);
        turnosCalendarGrid.className = 'turnos-calendar-grid turnos-calendar-grid-week';
        turnosCalendarGrid.innerHTML = '';
        turnosCalendarGrid.style.display = 'grid';
        turnosCalendarGrid.style.gridTemplateColumns = 'repeat(7, minmax(190px, 1fr))';
        turnosCalendarGrid.style.gap = '0.85rem';
        turnosCalendarGrid.style.overflowX = 'auto';
        turnosCalendarGrid.style.alignItems = 'stretch';

        weekDayOrder.forEach((dayIndex, offset) => {
            const dayDate = addDays(weekStart, offset);
            const dayCol = document.createElement('div');
            dayCol.className = 'turnos-calendar-day-col';
            dayCol.style.minHeight = '320px';
            dayCol.style.border = '1px solid #d6e4f5';
            dayCol.style.borderRadius = '16px';
            dayCol.style.background = '#ffffff';
            dayCol.style.display = 'flex';
            dayCol.style.flexDirection = 'column';
            dayCol.style.overflow = 'hidden';
            dayCol.style.boxShadow = '0 12px 28px rgba(15, 23, 42, 0.06)';

            const dayTurnos = turnosData.filter(turno => turnoOccursOnDate(turno, dayDate));
            const isToday = isSameDate(dayDate, new Date());
            if (isToday) {
                dayCol.classList.add('is-today');
                dayCol.style.borderColor = '#3b82f6';
                dayCol.style.boxShadow = '0 0 0 2px rgba(59, 130, 246, 0.16), 0 14px 32px rgba(15, 23, 42, 0.08)';
            }

            const header = document.createElement('div');
            header.className = 'turnos-calendar-day-header';
            header.style.padding = '0.85rem 0.9rem';
            header.style.borderBottom = '1px solid #e5edf7';
            header.style.display = 'grid';
            header.style.gridTemplateColumns = '1fr auto auto';
            header.style.alignItems = 'center';
            header.style.gap = '0.5rem';
            header.style.background = isToday ? 'linear-gradient(90deg, #dbeafe 0%, #eff6ff 100%)' : 'linear-gradient(90deg, #f8fbff 0%, #ffffff 100%)';

            const dayName = document.createElement('strong');
            dayName.textContent = weekDayShortLabels[dayIndex];
            dayName.style.fontSize = '0.95rem';
            dayName.style.color = '#0f172a';

            const dateText = document.createElement('span');
            dateText.textContent = formatDate(dayDate);
            dateText.style.color = '#334155';
            dateText.style.fontWeight = '600';
            dateText.style.fontSize = '0.82rem';

            const countBadge = document.createElement('span');
            countBadge.className = 'turnos-calendar-day-count';
            countBadge.textContent = String(dayTurnos.length);
            styleDayCountBadge(countBadge);

            header.appendChild(dayName);
            header.appendChild(dateText);
            header.appendChild(countBadge);

            const body = document.createElement('div');
            body.className = 'turnos-calendar-day-body';
            body.style.display = 'flex';
            body.style.flexDirection = 'column';
            body.style.gap = '0.65rem';
            body.style.padding = '0.85rem';
            body.style.flex = '1';
            body.style.overflowY = 'auto';
            if (dayTurnos.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'turnos-calendar-empty';
                empty.textContent = 'Sem turnos';
                empty.style.margin = '0';
                empty.style.padding = '0.85rem';
                empty.style.border = '1px dashed #cbd5e1';
                empty.style.borderRadius = '12px';
                empty.style.background = '#f8fafc';
                empty.style.color = '#64748b';
                empty.style.fontStyle = 'italic';
                body.appendChild(empty);
            } else {
                dayTurnos
                    .sort((a, b) => a.horarioInicio.localeCompare(b.horarioInicio, 'pt'))
                    .forEach(turno => body.appendChild(createTurnoCard(turno)));
            }

            dayCol.appendChild(header);
            dayCol.appendChild(body);
            turnosCalendarGrid.appendChild(dayCol);
        });

        if (turnosCalendarRangeLabel) {
            turnosCalendarRangeLabel.textContent = formatWeekRangeLabel(turnosCalendarAnchor);
        }
    }

    function renderMonthCalendar(turnosData) {
        if (!turnosCalendarGrid) return;
        styleCalendarShell();

        const year = turnosCalendarAnchor.getFullYear();
        const month = turnosCalendarAnchor.getMonth();
        const firstDay = new Date(year, month, 1);
        const firstGridDate = startOfWeek(firstDay);

        turnosCalendarGrid.className = 'turnos-calendar-grid turnos-calendar-grid-month';
        turnosCalendarGrid.innerHTML = '';
        turnosCalendarGrid.style.display = 'grid';
        turnosCalendarGrid.style.gridTemplateColumns = 'repeat(7, minmax(140px, 1fr))';
        turnosCalendarGrid.style.gap = '0.65rem';

        weekDayOrder.forEach(dayIndex => {
            const weekday = document.createElement('div');
            weekday.className = 'turnos-calendar-weekday';
            weekday.textContent = weekDayShortLabels[dayIndex];
            weekday.style.fontSize = '0.77rem';
            weekday.style.color = '#1d4e89';
            weekday.style.fontWeight = '800';
            weekday.style.textTransform = 'uppercase';
            weekday.style.letterSpacing = '0.06em';
            weekday.style.padding = '0.2rem 0.35rem';
            turnosCalendarGrid.appendChild(weekday);
        });

        for (let i = 0; i < 42; i += 1) {
            const currentDate = addDays(firstGridDate, i);
            const currentDay = currentDate.getDay();
            const isCurrentMonth = currentDate.getMonth() === month;
            const isToday = isSameDate(currentDate, new Date());
            const dayTurnos = turnosData.filter(turno => turnoOccursOnDate(turno, currentDate));

            const cell = document.createElement('div');
            cell.className = `turnos-calendar-month-cell ${isCurrentMonth ? '' : 'is-outside'}`.trim();
            cell.style.minHeight = '180px';
            cell.style.border = '1px solid #d7e5f3';
            cell.style.borderRadius = '14px';
            cell.style.background = isCurrentMonth ? '#ffffff' : '#f8fafc';
            cell.style.padding = '0.6rem';
            cell.style.display = 'flex';
            cell.style.flexDirection = 'column';
            cell.style.gap = '0.45rem';
            cell.style.boxShadow = isCurrentMonth ? '0 10px 24px rgba(15, 23, 42, 0.05)' : 'none';
            if (!isCurrentMonth) {
                cell.style.opacity = '0.72';
            }
            if (isToday) {
                cell.classList.add('is-today');
                cell.style.borderColor = '#3b82f6';
                cell.style.boxShadow = '0 0 0 2px rgba(59, 130, 246, 0.14), 0 12px 28px rgba(15, 23, 42, 0.08)';
            }

            const dateLabel = document.createElement('div');
            dateLabel.className = 'turnos-calendar-month-date';
            dateLabel.style.display = 'flex';
            dateLabel.style.justifyContent = 'space-between';
            dateLabel.style.alignItems = 'center';

            const dateNumber = document.createElement('span');
            dateNumber.textContent = String(currentDate.getDate());
            dateNumber.style.fontSize = '0.8rem';
            dateNumber.style.fontWeight = '800';
            dateNumber.style.color = '#334155';

            const countBadge = document.createElement('span');
            countBadge.className = 'turnos-calendar-day-count';
            countBadge.textContent = String(dayTurnos.length);
            styleDayCountBadge(countBadge);

            dateLabel.appendChild(dateNumber);
            dateLabel.appendChild(countBadge);

            const list = document.createElement('div');
            list.className = 'turnos-calendar-month-list';
            list.style.display = 'flex';
            list.style.flexDirection = 'column';
            list.style.gap = '0.4rem';
            list.style.flex = '1';
            list.style.overflowY = 'auto';
            if (dayTurnos.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'turnos-calendar-empty';
                empty.textContent = 'Sem turnos';
                empty.style.margin = '0';
                empty.style.padding = '0.65rem';
                empty.style.border = '1px dashed #cbd5e1';
                empty.style.borderRadius = '10px';
                empty.style.background = '#f8fafc';
                empty.style.color = '#64748b';
                empty.style.fontStyle = 'italic';
                list.appendChild(empty);
            } else {
                dayTurnos
                    .sort((a, b) => a.horarioInicio.localeCompare(b.horarioInicio, 'pt'))
                    .slice(0, 3)
                    .forEach(turno => list.appendChild(createTurnoCard(turno)));
                if (dayTurnos.length > 3) {
                    const more = document.createElement('p');
                    more.className = 'turnos-calendar-more';
                    more.textContent = `+${dayTurnos.length - 3} turnos`;
                    more.style.margin = '0';
                    more.style.color = '#1d4ed8';
                    more.style.fontSize = '0.75rem';
                    more.style.fontWeight = '700';
                    list.appendChild(more);
                }
            }

            cell.appendChild(dateLabel);
            cell.appendChild(list);
            turnosCalendarGrid.appendChild(cell);
        }

        if (turnosCalendarRangeLabel) {
            turnosCalendarRangeLabel.textContent = formatMonthRangeLabel(turnosCalendarAnchor);
        }
    }

    function renderTurnosCalendar() {
        if (!turnosCalendarWrapper || turnosCalendarView === 'table') return;

        try {
            const visibleRows = getTurnosRows(false);
            const turnosData = visibleRows.map(extractTurnoModel);

            if (turnosCalendarView === 'week') {
                renderWeekCalendar(turnosData);
                return;
            }

            renderMonthCalendar(turnosData);
        } catch (error) {
            console.error('Erro ao renderizar calendário de turnos:', error);
            if (turnosCalendarGrid) {
                turnosCalendarGrid.innerHTML = '<div style="padding:1rem;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:12px;font-weight:600;">Não foi possível abrir o calendário de turnos.</div>';
            }
        }
    }

    function updateTurnosViewButtons() {
        [btnTurnosViewTable, btnTurnosViewWeek, btnTurnosViewMonth].forEach(btn => {
            if (!btn) return;
            const btnView = btn.getAttribute('data-view') || 'table';
            btn.classList.toggle('active', btnView === turnosCalendarView);
        });
    }

    function applyTurnosView() {
        if (turnosTableElement) {
            turnosTableElement.style.display = turnosCalendarView === 'table' ? '' : 'none';
        }
        if (turnosCalendarWrapper) {
            turnosCalendarWrapper.style.display = turnosCalendarView === 'table' ? 'none' : '';
        }
        updateTurnosViewButtons();
        renderTurnosCalendar();
    }

    function setTurnosView(nextView) {
        if (!nextView) return;
        turnosCalendarView = nextView;
        applyTurnosView();
    }

    function populateTurnosSelect(selectEl, values, defaultLabel) {
        if (!selectEl) return;

        const previousValue = selectEl.value;
        selectEl.innerHTML = '';

        const firstOption = document.createElement('option');
        firstOption.value = '';
        firstOption.textContent = defaultLabel;
        selectEl.appendChild(firstOption);

        values
            .filter((v) => v !== '')
            .sort((a, b) => a.localeCompare(b, 'pt', { sensitivity: 'base' }))
            .forEach((value) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                selectEl.appendChild(option);
            });

        if (previousValue && Array.from(selectEl.options).some((opt) => opt.value === previousValue)) {
            selectEl.value = previousValue;
        }
    }
    
    function updateTurnosFilterBadge() {
        const badge = document.getElementById('turnosFilterBadge');
        if (!badge) return;
        const count = [
            turnosFilterTipo?.value,
            turnosFilterEscala?.value,
            turnosFilterStatus?.value,
            turnosFilterDepartment?.value
        ].filter(Boolean).length;
        if (count > 0) { badge.textContent = String(count); badge.style.display = 'flex'; }
        else { badge.style.display = 'none'; }
    }

    function filterTurnosTable() {
        if (!turnosTable || !searchTurnos || !turnosFilterTipo || !turnosFilterEscala || !turnosFilterStatus) return;
        
        const searchValue = normalizeText(searchTurnos.value);
        const tipoValue = normalizeText(turnosFilterTipo.value);
        const escalaValue = normalizeText(turnosFilterEscala.value);
        const statusValue = normalizeText(turnosFilterStatus.value);
        const departmentValue = normalizeText(turnosFilterDepartment ? turnosFilterDepartment.value : '');
        const periodStartValue = turnosFilterPeriodStart ? String(turnosFilterPeriodStart.value || '').trim() : '';
        const periodEndValue = turnosFilterPeriodEnd ? String(turnosFilterPeriodEnd.value || '').trim() : '';
        const rows = turnosTable.querySelectorAll('tr');
        
        rows.forEach(row => {
            const funcionario = normalizeText(row.cells[0]?.textContent || '');
            const turno = normalizeText(row.cells[1]?.textContent || '');
            const horario = normalizeText(row.cells[2]?.textContent || '');
            const dias = normalizeText(row.cells[3]?.textContent || '');
            const escala = normalizeText(row.cells[4]?.textContent || '');
            const statusBadge = row.querySelector('.status-badge');
            const statusText = normalizeText(statusBadge?.textContent || row.cells[5]?.textContent || '');
            const teamText = normalizeText(row.dataset.turnoTeam || '');
            const turnoModel = extractTurnoModel(row);
            
            const matchesSearch = searchValue === '' || 
                funcionario.includes(searchValue) || 
                turno.includes(searchValue) ||
                horario.includes(searchValue) ||
                dias.includes(searchValue) ||
                escala.includes(searchValue);
            
            const matchesTipo = tipoValue === '' || turno === tipoValue;
            const matchesEscala = escalaValue === '' || escala === escalaValue;
            const matchesStatus = statusValue === '' || statusText.includes(statusValue);
            const matchesDepartment = departmentValue === '' || teamText === departmentValue;
            const matchesPeriod = hasOccurrenceInPeriod(turnoModel, periodStartValue, periodEndValue);
            
            row.style.display = (matchesSearch && matchesTipo && matchesEscala && matchesStatus && matchesDepartment && matchesPeriod) ? '' : 'none';
        });

        const hasAdvancedFilters = Boolean(
            (turnosFilterTipo && turnosFilterTipo.value) ||
            (turnosFilterEscala && turnosFilterEscala.value) ||
            (turnosFilterStatus && turnosFilterStatus.value) ||
            (turnosFilterDepartment && turnosFilterDepartment.value) ||
            (turnosFilterPeriodStart && turnosFilterPeriodStart.value) ||
            (turnosFilterPeriodEnd && turnosFilterPeriodEnd.value)
        );
        if (clearTurnosAdvancedFilters) {
            clearTurnosAdvancedFilters.style.display = hasAdvancedFilters ? '' : 'none';
        }
        updateTurnosFilterBadge();

        renderTurnosCalendar();
    }

    function refreshTurnosDerivedUI() {
        if (!(turnosTable && turnosFilterTipo && turnosFilterEscala)) return;

        const rows = Array.from(turnosTable.querySelectorAll('tr'));
        const tipos = new Set();
        const escalas = new Set();
        const departments = new Set();

        rows.forEach((row) => {
            const tipo = (row.cells[1]?.textContent || '').trim();
            const escala = (row.cells[4]?.textContent || '').trim();
            const department = String(row.dataset.turnoTeam || '').trim();
            if (tipo !== '') tipos.add(tipo);
            if (escala !== '') escalas.add(escala);
            if (department !== '') departments.add(department);
        });

        populateTurnosSelect(turnosFilterTipo, Array.from(tipos), 'Todos os turnos');
        populateTurnosSelect(turnosFilterEscala, Array.from(escalas), 'Todas as escalas');
        if (turnosFilterDepartment) {
            populateTurnosSelect(turnosFilterDepartment, Array.from(departments), 'Todas as equipas/departamentos');
        }
        filterTurnosTable();
    }

    refreshTurnosDerivedUI();
    
    if (searchTurnos) searchTurnos.addEventListener('input', filterTurnosTable);
    if (turnosFilterTipo) turnosFilterTipo.addEventListener('change', filterTurnosTable);
    if (turnosFilterEscala) turnosFilterEscala.addEventListener('change', filterTurnosTable);
    if (turnosFilterStatus) turnosFilterStatus.addEventListener('change', filterTurnosTable);
    if (turnosFilterDepartment) turnosFilterDepartment.addEventListener('change', filterTurnosTable);
    if (turnosFilterPeriodStart) turnosFilterPeriodStart.addEventListener('change', filterTurnosTable);
    if (turnosFilterPeriodEnd) turnosFilterPeriodEnd.addEventListener('change', filterTurnosTable);

    if (clearTurnosAdvancedFilters) {
        clearTurnosAdvancedFilters.addEventListener('click', () => {
            if (turnosFilterTipo) turnosFilterTipo.value = '';
            if (turnosFilterEscala) turnosFilterEscala.value = '';
            if (turnosFilterStatus) turnosFilterStatus.value = '';
            if (turnosFilterDepartment) turnosFilterDepartment.value = '';
            if (turnosFilterPeriodStart) turnosFilterPeriodStart.value = '';
            if (turnosFilterPeriodEnd) turnosFilterPeriodEnd.value = '';
            filterTurnosTable();
        });
    }

    if (btnTurnosMissingCoverage) {
        btnTurnosMissingCoverage.addEventListener('click', () => {
            const range = normalizePeriodRange(
                turnosFilterPeriodStart ? turnosFilterPeriodStart.value : '',
                turnosFilterPeriodEnd ? turnosFilterPeriodEnd.value : ''
            );

            if (!range.start || !range.end) {
                showWarning('Defina início e fim do período para verificar funcionários sem turno.');
                return;
            }

            if (range.start > range.end) {
                showError('A data inicial não pode ser maior que a final.');
                return;
            }

            const models = getTurnosRows(true).map(extractTurnoModel);
            const employees = getCatalogEmployees();
            const uncovered = employees.filter(employee => {
                const employeeTurnos = models.filter(model => Number(model.funcionarioId || 0) === employee.id || normalizeText(model.funcionario) === normalizeText(employee.name));
                if (employeeTurnos.length === 0) {
                    return true;
                }
                return !employeeTurnos.some(model => hasOccurrenceInPeriod(model, range.start, range.end));
            });

            if (uncovered.length === 0) {
                showSuccess(`Todos os funcionários selecionáveis têm turno no período ${formatIsoDateToPt(range.start)} a ${formatIsoDateToPt(range.end)}.`);
                return;
            }

            const names = uncovered.map(item => item.name).slice(0, 20);
            const extra = uncovered.length > 20 ? `\n... e mais ${uncovered.length - 20}` : '';
            showWarning(`Sem turno no período (${uncovered.length}):\n${names.join('\n')}${extra}`);
        });
    }

    if (btnTurnosViewTable) btnTurnosViewTable.addEventListener('click', () => setTurnosView('table'));
    if (btnTurnosViewWeek) btnTurnosViewWeek.addEventListener('click', () => setTurnosView('week'));
    if (btnTurnosViewMonth) btnTurnosViewMonth.addEventListener('click', () => setTurnosView('month'));

    if (turnosCalendarToday) {
        turnosCalendarToday.addEventListener('click', () => {
            turnosCalendarAnchor = new Date();
            renderTurnosCalendar();
        });
    }

    if (turnosCalendarPrev) {
        turnosCalendarPrev.addEventListener('click', () => {
            if (turnosCalendarView === 'week') {
                turnosCalendarAnchor = addDays(turnosCalendarAnchor, -7);
            } else if (turnosCalendarView === 'month') {
                turnosCalendarAnchor = new Date(turnosCalendarAnchor.getFullYear(), turnosCalendarAnchor.getMonth() - 1, 1);
            }
            renderTurnosCalendar();
        });
    }

    if (turnosCalendarNext) {
        turnosCalendarNext.addEventListener('click', () => {
            if (turnosCalendarView === 'week') {
                turnosCalendarAnchor = addDays(turnosCalendarAnchor, 7);
            } else if (turnosCalendarView === 'month') {
                turnosCalendarAnchor = new Date(turnosCalendarAnchor.getFullYear(), turnosCalendarAnchor.getMonth() + 1, 1);
            }
            renderTurnosCalendar();
        });
    }

    document.addEventListener('turnosTableDataChanged', () => {
        refreshTurnosDerivedUI();
    });
    
    if (turnosTable) {
        filterTurnosTable();
        applyTurnosView();
    }
    
    // ========== FILTROS PARA GORJETAS ==========
    const searchGorjetas = document.getElementById('searchGorjetas');
    const filterGorjetasStatus = document.getElementById('filterGorjetasStatus');
    const filterGorjetasMes = document.getElementById('filterGorjetasMes');
    const clearFiltersGorjetas = document.getElementById('clearFiltersGorjetas');
    const resultCountGorjetas = document.getElementById('resultCountGorjetas');
    const gorjetasTable = document.querySelector('#gorjetas-section tbody');
    
    function filterGorjetasTable() {
        if (!gorjetasTable) return;
        
        const searchValue = searchGorjetas.value.toLowerCase();
        const statusValue = filterGorjetasStatus.value.toLowerCase();
        const mesValue = filterGorjetasMes.value;
        const rows = gorjetasTable.querySelectorAll('tr');
        let visibleCount = 0;
        const hoje = new Date();
        
        rows.forEach(row => {
            const dataCell = row.cells[0]?.textContent || '';
            const funcionario = row.cells[1]?.textContent.toLowerCase() || '';
            const statusBadge = row.querySelector('.status-badge');
            const statusText = statusBadge?.textContent.toLowerCase() || '';
            const statusClass = statusBadge?.className || '';
            
            const matchesSearch = searchValue === '' || funcionario.includes(searchValue);
            
            let matchesStatus = statusValue === '';
            if (!matchesStatus) {
                if (statusValue === 'pago' && (statusClass.includes('status-active') || statusText.includes('pago'))) matchesStatus = true;
                if (statusValue === 'pendente' && (statusClass.includes('status-pendente') || statusText.includes('pendente'))) matchesStatus = true;
                if (statusValue === 'rejeitado' && (statusClass.includes('status-rejeitado') || statusText.includes('rejeitado'))) matchesStatus = true;
            }
            
            let matchesMes = mesValue === '';
            if (!matchesMes && dataCell) {
                try {
                    const [dia, mes, ano] = dataCell.split('/');
                    const dataRow = new Date(ano, mes - 1, dia);
                    
                    if (mesValue === 'hoje') {
                        matchesMes = dataRow.toDateString() === hoje.toDateString();
                    } else if (mesValue === 'semana') {
                        const umaSemanaAtras = new Date(hoje);
                        umaSemanaAtras.setDate(hoje.getDate() - 7);
                        matchesMes = dataRow >= umaSemanaAtras && dataRow <= hoje;
                    } else if (mesValue === 'mes') {
                        matchesMes = dataRow.getMonth() === hoje.getMonth() && dataRow.getFullYear() === hoje.getFullYear();
                    } else if (mesValue === 'ultimo_mes') {
                        const mesPassado = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
                        const ultimoDiaMesPassado = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
                        matchesMes = dataRow >= mesPassado && dataRow <= ultimoDiaMesPassado;
                    }
                } catch(e) {
                    matchesMes = true; // Em caso de erro, mostra a linha
                }
            }
            
            if (matchesSearch && matchesStatus && matchesMes) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        resultCountGorjetas.textContent = `${visibleCount} ${visibleCount === 1 ? 'resultado' : 'resultados'}`;
        
        const hasFilters = searchValue || statusValue || mesValue;
        clearFiltersGorjetas.style.display = hasFilters ? 'block' : 'none';
    }
    
    if (searchGorjetas) searchGorjetas.addEventListener('input', filterGorjetasTable);
    if (filterGorjetasStatus) filterGorjetasStatus.addEventListener('change', filterGorjetasTable);
    if (filterGorjetasMes) filterGorjetasMes.addEventListener('change', filterGorjetasTable);
    
    if (clearFiltersGorjetas) {
        clearFiltersGorjetas.addEventListener('click', function() {
            searchGorjetas.value = '';
            filterGorjetasStatus.value = '';
            filterGorjetasMes.value = '';
            filterGorjetasTable();
        });
    }
    
    if (gorjetasTable) filterGorjetasTable();

    const exportGorjetasBtn = document.getElementById('exportGorjetasBtn');
    if (exportGorjetasBtn) {
        exportGorjetasBtn.addEventListener('click', exportGorjetasCSV);
    }

    // ===== Profile dropdown toggle =====
    window.toggleProfileDropdown = function() {
        const profileDropdown = document.getElementById('profileDropdown');
        if (!profileDropdown) return;
        profileDropdown.classList.toggle('show');
    };

    document.addEventListener('click', function(e) {
        const profileDropdown = document.getElementById('profileDropdown');
        const navProfileButton = document.getElementById('navProfileIcon');
        if (!profileDropdown) return;
        if (navProfileButton && navProfileButton.contains(e.target)) return;
        if (!profileDropdown.contains(e.target)) profileDropdown.classList.remove('show');

        // if bulk actions bar is visible and click outside of it, clear selection
        if (document.body.classList.contains('bulk-bar-visible')) {
            const bar = document.getElementById('bulkActionsBar');
            if (bar && !bar.contains(e.target)) {
                clearBulkSelection();
            }
        }
    });

    // ========== BULK ACTIONS - Gerenciar Checkboxes e Seleções ==========
    function initializeBulkActions() {
        const checkboxes = document.querySelectorAll('.employee-row .employee-checkbox');
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const bulkActionsBar = document.getElementById('bulkActionsBar');
        const bulkCountSpan = document.getElementById('bulkCount');

        // Verificar se elementos necessários existem
        if (!bulkActionsBar || !bulkCountSpan) {
            console.warn('✗ Elementos de bulk actions não encontrados');
            return;
        }

        console.log('✓ Inicializando Bulk Actions');
        console.log(`✓ ${checkboxes.length} checkboxes encontradas`);

        // Função para atualizar barra de ações
        window.updateBulkActionsBar = function() {
            const selected = document.querySelectorAll('.employee-row .employee-checkbox:checked');
            const count = selected.length;

            console.log(`✓ Atualizando barra: ${count} selecionados`);

            if (count > 0) {
                // não mostrar barra se o modal estiver aberto; também limpa overlay
                const modal = document.getElementById('notifyModal');
                const modalVisible = !!(modal && getComputedStyle(modal).display !== 'none');
                if (window.__notifyModalOpen || modalVisible) {
                    console.log('Modal já aberto; pulando exibição da barra e removendo overlay');
                    hideBulkBarSafely(bulkActionsBar);
                    return;
                }

                // abrir automaticamente o modal de notificação antes de tornar a barra visível
                if (modal && getComputedStyle(modal).display === 'none') {
                    console.log('Chamando openNotifyModal via atualização de barra');
                    openNotifyModal();
                    return; // evita mostrar barra momentaneamente
                }

                bulkCountSpan.textContent = `${count} funcionário${count !== 1 ? 's' : ''} selecionado${count !== 1 ? 's' : ''}`;
                bulkActionsBar.classList.add('show');
                bulkActionsBar.removeAttribute('inert');
                bulkActionsBar.setAttribute('aria-hidden', 'false');
                // add overlay class to body so backdrop-filter can work or create overlay div
                document.body.classList.add('bulk-bar-visible');
                console.log('✓ Barra visível (classe "show" adicionada)');
            } else {
                hideBulkBarSafely(bulkActionsBar);
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
                console.log('✓ Barra oculta (classe "show" removida)');
            }

            // Atualizar checkbox de "Selecionar Tudo"
            if (selectAllCheckbox) {
                // só marcamos automaticamente quando há mais de um item
                if (checkboxes.length > 1) {
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked && count > 0;
                } else {
                    // quando existir apenas um checkbox, não ativamos "selecionar tudo" automaticamente
                    selectAllCheckbox.checked = false;
                }
            }
        };

        // Fecha a barra de ações em lote
        window.closeBulkActionsBar = function() {
            try {
                const bulkActionsBar = document.getElementById('bulkActionsBar');
                if (!bulkActionsBar) return;
                hideBulkBarSafely(bulkActionsBar);
            } catch (err) {
                console.error('Erro ao fechar bulkActionsBar:', err);
            }
        };

        // Selecionar/Desselecionar Tudo
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                console.log('✓ "Selecionar Tudo" clicado');
                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                    const row = cb.closest('tr');
                    if (this.checked) {
                        row.classList.add('selected');
                    } else {
                        row.classList.remove('selected');
                    }
                });
                window.updateBulkActionsBar();
            });
        }

        // Event listeners para cada checkbox
        checkboxes.forEach((checkbox, index) => {
            checkbox.addEventListener('change', function() {
                console.log(`✓ Checkbox #${index} alterado: ${this.checked ? 'marcado' : 'desmarcado'}`);
                const row = this.closest('tr');
                if (this.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
                window.updateBulkActionsBar();
            });
        });

        console.log('✓ Bulk Actions inicializado com sucesso');
    }

    // Inicializar quando DOM estiver pronto
    initializeBulkActions();

    function normalizeEmployeeStatus(value) {
        const status = String(value || '').trim().toLowerCase();
        if (status === 'ativo') return 'active';
        if (status === 'inativo') return 'inactive';
        if (status === 'férias') return 'ferias';
        return status;
    }

    function getSelectedEmployeesForBulk() {
        return Array.from(document.querySelectorAll('.employee-row .employee-checkbox:checked')).map((cb) => {
            const row = cb.closest('tr');
            return {
                id: String(cb.dataset.employeeId || '').trim(),
                name: String(cb.dataset.employeeName || '').trim(),
                checkbox: cb,
                row,
                currentStatus: normalizeEmployeeStatus(row?.dataset?.status || ''),
                currentDepartment: String(row?.dataset?.department || '').trim()
            };
        }).filter((emp) => emp.id !== '');
    }

    function applyBulkFailureSelection(results) {
        const failures = results.filter((item) => !item.success);
        if (failures.length === 0) {
            if (typeof window.clearBulkSelection === 'function') {
                window.clearBulkSelection();
            }
            return;
        }

        document.querySelectorAll('.employee-row .employee-checkbox').forEach((cb) => {
            cb.checked = false;
            const row = cb.closest('tr');
            if (row) row.classList.remove('selected');
        });

        failures.forEach((failure) => {
            if (failure.checkbox) {
                failure.checkbox.checked = true;
                const row = failure.checkbox.closest('tr');
                if (row) row.classList.add('selected');
            }
        });

        if (typeof window.updateBulkActionsBar === 'function') {
            window.updateBulkActionsBar();
        }
    }

    async function showBulkResultSummary(actionLabel, results) {
        const successCount = results.filter((item) => item.success).length;
        const failed = results.filter((item) => !item.success);
        const failedCount = failed.length;

        if (failedCount === 0) {
            showSuccess(`${actionLabel}: ${successCount} funcionário(s) atualizados com sucesso.`);
            return;
        }

        const failedPreview = failed
            .slice(0, 6)
            .map((item) => `• ${item.name || 'Sem nome'}: ${item.message || 'Falha sem detalhe'}`)
            .join('\n');

        const baseText = `${actionLabel}: ${successCount} sucesso(s), ${failedCount} falha(s).`;

        if (typeof Swal !== 'undefined' && Swal.fire) {
            await Swal.fire({
                icon: failedCount > 0 && successCount > 0 ? 'warning' : 'error',
                title: 'Resumo da ação em lote',
                text: `${baseText}${failedPreview ? `\n\n${failedPreview}` : ''}`,
                confirmButtonText: 'OK'
            });
            return;
        }

        showWarning(`${baseText}${failedPreview ? `\n${failedPreview}` : ''}`);
    }

    async function executeBulkAction(options) {
        const { actionLabel, employees, buildFormData, validateEmployee } = options;
        const results = [];

        for (const emp of employees) {
            const validationMessage = typeof validateEmployee === 'function' ? validateEmployee(emp) : '';
            if (validationMessage) {
                results.push({ ...emp, success: false, message: validationMessage });
                continue;
            }

            try {
                const fd = buildFormData(emp);
                const response = await fetch('../api/employees/update_employee.php', {
                    method: 'POST',
                    body: fd
                });

                const data = await response.json();
                if (data && data.success) {
                    results.push({ ...emp, success: true, message: data.message || 'OK' });
                } else {
                    results.push({ ...emp, success: false, message: data?.message || 'Erro ao atualizar' });
                }
            } catch (err) {
                results.push({ ...emp, success: false, message: 'Falha de comunicação com o servidor' });
            }
        }

        applyBulkFailureSelection(results);
        await showBulkResultSummary(actionLabel, results);

        return results;
    }

    // Função: Marcar Férias em Lote
    window.bulkMarkVacation = function() {
        const canShowBulkWarning = document.body.classList.contains('bulk-bar-visible') || !!document.querySelector('#bulkActionsBar.show');
        const selected = document.querySelectorAll('.employee-row .employee-checkbox:checked');
        if (selected.length === 0) {
            if (canShowBulkWarning) showWarning('Selecione pelo menos um funcionário');
            return;
        }
        document.getElementById('bulkVacationModal').style.display = 'block';
    }

    document.getElementById('bulkVacationForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn?.disabled) return;

        const selected = document.querySelectorAll('.employee-row .employee-checkbox:checked');
        const startDate = document.getElementById('vacationStartDate').value;
        const endDate = document.getElementById('vacationEndDate').value;
        const note = document.getElementById('vacationNote').value.trim();

        if (!startDate || !endDate) {
            showWarning('Informe a data de início e fim das férias.');
            return;
        }

        if (startDate > endDate) {
            showWarning('A data de início não pode ser maior que a data de fim.');
            return;
        }

        const employees = Array.from(selected).map(cb => ({
            id: cb.dataset.employeeId,
            name: cb.dataset.employeeName,
            checkbox: cb,
            row: cb.closest('tr'),
            currentStatus: normalizeEmployeeStatus(cb.closest('tr')?.dataset?.status || '')
        }));

        try {
            if (submitBtn) submitBtn.disabled = true;

            const results = await executeBulkAction({
                actionLabel: 'Marcação de férias',
                employees,
                validateEmployee: (emp) => {
                    if (emp.currentStatus === 'inactive') {
                        return 'Funcionário inativo não pode ser marcado em férias.';
                    }
                    return '';
                },
                buildFormData: (emp) => {
                    const fd = new FormData();
                    fd.append('action', 'update_status');
                    fd.append('id', emp.id);
                    fd.append('status', 'ferias');
                    fd.append('start_vacation', startDate);
                    fd.append('end_vacation', endDate);
                    if (note) fd.append('reason', note);
                    return fd;
                }
            });

            if (results.some((item) => item.success)) {
                document.getElementById('bulkVacationModal').style.display = 'none';
            }
        } catch (err) {
            showError('Erro ao processar férias');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    });

    // Função: Alterar Status em Lote
    window.bulkChangeStatus = function() {
        const canShowBulkWarning = document.body.classList.contains('bulk-bar-visible') || !!document.querySelector('#bulkActionsBar.show');
        const selected = document.querySelectorAll('.employee-row .employee-checkbox:checked');
        if (selected.length === 0) {
            if (canShowBulkWarning) showWarning('Selecione pelo menos um funcionário');
            return;
        }
        document.getElementById('bulkStatusModal').style.display = 'block';
    }

    document.getElementById('bulkStatusForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn?.disabled) return;

        const selected = document.querySelectorAll('.employee-row .employee-checkbox:checked');
        const newStatus = normalizeEmployeeStatus(document.getElementById('bulkNewStatus').value);
        const reason = document.getElementById('bulkStatusReason').value.trim();

        if (!newStatus) {
            showWarning('Selecione um status');
            return;
        }

        const employees = Array.from(selected).map(cb => ({
            id: cb.dataset.employeeId,
            name: cb.dataset.employeeName,
            checkbox: cb,
            row: cb.closest('tr'),
            currentStatus: normalizeEmployeeStatus(cb.closest('tr')?.dataset?.status || '')
        }));

        try {
            if (submitBtn) submitBtn.disabled = true;

            const results = await executeBulkAction({
                actionLabel: 'Alteração de status',
                employees,
                validateEmployee: (emp) => {
                    if (emp.currentStatus === newStatus) {
                        return `Funcionário já está com status "${newStatus}".`;
                    }
                    if (emp.currentStatus === 'inactive' && newStatus === 'ferias') {
                        return 'Funcionário inativo não pode ir direto para férias.';
                    }
                    return '';
                },
                buildFormData: (emp) => {
                    const fd = new FormData();
                    fd.append('action', 'update_status');
                    fd.append('id', emp.id);
                    fd.append('status', newStatus);
                    if (reason) fd.append('reason', reason);
                    return fd;
                }
            });

            if (results.some((item) => item.success)) {
                document.getElementById('bulkStatusModal').style.display = 'none';
            }
        } catch (err) {
            showError('Erro ao alterar status');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    });

    // Função: Alterar Departamento em Massa
    window.bulkChangeDepartment = function() {
        const canShowBulkWarning = document.body.classList.contains('bulk-bar-visible') || !!document.querySelector('#bulkActionsBar.show');
        const selected = document.querySelectorAll('.employee-row .employee-checkbox:checked');
        if (selected.length === 0) {
            if (canShowBulkWarning) showWarning('Selecione pelo menos um funcionário');
            return;
        }
        document.getElementById('bulkDepartmentModal').style.display = 'block';
    }

    document.getElementById('bulkDepartmentForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn?.disabled) return;

        const selected = document.querySelectorAll('.employee-row .employee-checkbox:checked');
        const newDept = document.getElementById('bulkNewDepartment').value.trim();
        if (!newDept) {
            showWarning('Informe o novo departamento');
            return;
        }

        const employees = Array.from(selected).map(cb => ({
            id: cb.dataset.employeeId,
            name: cb.dataset.employeeName,
            checkbox: cb,
            row: cb.closest('tr'),
            currentDepartment: String(cb.closest('tr')?.dataset?.department || '').trim()
        }));

        try {
            if (submitBtn) submitBtn.disabled = true;

            const results = await executeBulkAction({
                actionLabel: 'Alteração de departamento',
                employees,
                validateEmployee: (emp) => {
                    if (String(emp.currentDepartment || '').trim().toLowerCase() === newDept.toLowerCase()) {
                        return 'Funcionário já está nesse departamento.';
                    }
                    return '';
                },
                buildFormData: (emp) => {
                    const fd = new FormData();
                    fd.append('id', emp.id);
                    fd.append('department', newDept);
                    return fd;
                }
            });

            if (results.some((item) => item.success)) {
                document.getElementById('bulkDepartmentModal').style.display = 'none';
            }
        } catch (err) {
            showError('Erro ao alterar departamento');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    });

    // Função: Exportar Selecionados
    window.bulkExportSelected = async function() {
        const canShowBulkWarning = document.body.classList.contains('bulk-bar-visible') || !!document.querySelector('#bulkActionsBar.show');
        const selected = document.querySelectorAll('.employee-row .employee-checkbox:checked');
        if (selected.length === 0) {
            if (canShowBulkWarning) showWarning('Selecione pelo menos um funcionário');
            return;
        }

        const employees = Array.from(selected).map(cb => {
            const row = cb.closest('tr');
            return {
                name: cb.dataset.employeeName,
                position: row.cells[4]?.textContent || '',
                department: row.cells[5]?.textContent || '',
                status: row.cells[6]?.textContent || ''
            };
        });

        // escolha do formato usando SweetAlert2
        const { value: format } = await Swal.fire({
            title: 'Escolha o formato',
            input: 'radio',
            inputOptions: {
                csv: 'CSV (simples)',
                excel: 'Excel (.xlsx)',
                pdf: 'PDF (tabela)',
                report: 'Relatório de Cadastro (PDF detalhado)'
            },
            inputValidator: (value) => {
                if (!value) {
                    return 'Você precisa escolher um formato!';
                }
            },
            showCancelButton: true
        });

        if (!format) {
            return; // usuário cancelou
        }

        try {
            if (format === 'csv') {
                downloadCsv(employees);
            } else if (format === 'excel') {
                downloadExcel(employees);
            } else if (format === 'pdf') {
                generateSimplePdf(employees);
            } else if (format === 'report') {
                generateReportPdf(employees);
            }

            showSuccess(`${employees.length} funcionário(s) exportado(s)`);
        } catch (err) {
            console.error(err);
            showError('Erro durante a exportação');
        }
    }

    // Função: Excluir funcionários selecionados em lote
    window.bulkDeleteSelectedLegacy = async function() {
        const canShowBulkWarning = document.body.classList.contains('bulk-bar-visible') || !!document.querySelector('#bulkActionsBar.show');
        const selected = document.querySelectorAll('.employee-row .employee-checkbox:checked');
        if (selected.length === 0) {
            if (canShowBulkWarning) showWarning('Selecione pelo menos um funcionário');
            return;
        }

        const confirmed = await showConfirm(
            'Excluir Funcionários',
            `Você tem certeza que deseja excluir ${selected.length} funcionário${selected.length !== 1 ? 's' : ''}? Esta ação não pode ser desfeita.`,
            'Sim, excluir',
            'Cancelar'
        );
        if (!confirmed) return;

        try {
            for (const cb of selected) {
                const empId = cb.dataset.employeeId;
                const fd = new FormData();
                fd.append('id', empId);

                const res = await fetch('../api/employees/delete_employee.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (!data.success) {
                    console.error(`Erro ao excluir funcionário ${empId}:`, data.message);
                }
            }

            showSuccess(`${selected.length} funcionário${selected.length !== 1 ? 's' : ''} excluído${selected.length !== 1 ? 's' : ''}`);
            window.clearBulkSelection();
            setTimeout(() => location.reload(), 1500);
        } catch (err) {
            showError('Erro ao excluir funcionários');
            console.error(err);
        }
    }

    // helpers for export
    function downloadCsv(empList) {
        const headers = ['Nome', 'Cargo', 'Departamento', 'Status'];
        const csv = [
            headers.join(','),
            ...empList.map(e => [
                `"${e.name}"`,
                `"${e.position}"`,
                `"${e.department}"`,
                `"${e.status}"`
            ].join(','))
        ].join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `funcionarios_export_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
    }

    function downloadExcel(empList) {
        // usa XLSX library já carregada
        const ws_data = [
            ['Nome', 'Cargo', 'Departamento', 'Status'],
            ...empList.map(e => [e.name, e.position, e.department, e.status])
        ];
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(ws_data);
        XLSX.utils.book_append_sheet(wb, ws, 'Funcionarios');
        XLSX.writeFile(wb, `funcionarios_export_${new Date().toISOString().split('T')[0]}.xlsx`);
    }

    function generateSimplePdf(empList) {
        const doc = new jspdf.jsPDF();
        doc.setFontSize(14);
        doc.text('Lista de Funcionários', 14, 20);
        doc.setFontSize(11);
        let y = 30;
        // cabeçalho
        doc.text('Nome', 14, y);
        doc.text('Cargo', 64, y);
        doc.text('Departamento', 114, y);
        doc.text('Status', 164, y);
        y += 6;
        empList.forEach(e => {
            doc.text(e.name, 14, y);
            doc.text(e.position, 64, y);
            doc.text(e.department, 114, y);
            doc.text(e.status, 164, y);
            y += 6;
            if (y > 280) {
                doc.addPage();
                y = 20;
            }
        });
        doc.save(`funcionarios_export_${new Date().toISOString().split('T')[0]}.pdf`);
    }

    function generateReportPdf(empList) {
        const doc = new jspdf.jsPDF();
        let y = 20;
        empList.forEach((e, idx) => {
            doc.setFontSize(14);
            doc.text(e.name, 14, y);
            y += 6;
            doc.setFontSize(11);
            doc.text(`Cargo: ${e.position}`, 14, y);
            y += 5;
            doc.text(`Departamento: ${e.department}`, 14, y);
            y += 5;
            doc.text(`Status: ${e.status}`, 14, y);
            y += 10;
            if (idx < empList.length - 1 && y > 250) {
                doc.addPage();
                y = 20;
            }
        });
        doc.save(`relatorio_cadastro_${new Date().toISOString().split('T')[0]}.pdf`);
    }

    // Função: Limpar Seleção
    window.clearBulkSelection = function() {
        const checkboxes = document.querySelectorAll('.employee-row .employee-checkbox');
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        
        checkboxes.forEach(cb => {
            cb.checked = false;
            const row = cb.closest('tr');
            if (row) row.classList.remove('selected');
        });
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
        
        if (window.updateBulkActionsBar) {
            window.updateBulkActionsBar();
        }
    }
});



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


// Fallback resiliente: garante ações em lote globais mesmo se blocos anteriores falharem.
(function ensureGlobalBulkActionHandlers() {
    if (window.__bulkActionsGlobalReady) return;
    window.__bulkActionsGlobalReady = true;

    function getSelectedEmployeesFromRows() {
        return Array.from(document.querySelectorAll('#funcionarios-section .employee-row .employee-checkbox:checked')).map((cb) => {
            const row = cb.closest('tr');
            return {
                id: cb.dataset.employeeId,
                name: cb.dataset.employeeName || '',
                row
            };
        }).filter((item) => !!item.id);
    }

    function requireSelection() {
        const selected = getSelectedEmployeesFromRows();
        if (selected.length === 0) {
            const isBulkBarVisible = document.body.classList.contains('bulk-bar-visible');

            // Evita aviso indevido quando a função é disparada fora do contexto de ações em lote.
            if (isBulkBarVisible) {
                if (typeof showWarning === 'function') {
                    showWarning('Selecione pelo menos um funcionário');
                } else {
                    alert('Selecione pelo menos um funcionário');
                }
            }
            return null;
        }
        return selected;
    }

    window.bulkMarkVacation = function() {
        const selected = requireSelection();
        if (!selected) return;
        const modal = document.getElementById('bulkVacationModal');
        if (modal) modal.style.display = 'block';
    };

    window.bulkChangeStatus = function() {
        const selected = requireSelection();
        if (!selected) return;
        const modal = document.getElementById('bulkStatusModal');
        if (modal) modal.style.display = 'block';
    };

    window.bulkChangeDepartment = function() {
        const selected = requireSelection();
        if (!selected) return;
        const modal = document.getElementById('bulkDepartmentModal');
        if (modal) modal.style.display = 'block';
    };

    window.bulkExportSelected = async function() {
        const selected = requireSelection();
        if (!selected) return;

        const rows = selected.map((item) => item.row).filter(Boolean);
        const employees = rows.map((row, index) => {
            const cells = row ? row.querySelectorAll('td') : [];
            return {
                name: selected[index]?.name || (cells[2]?.textContent || '').trim(),
                position: (cells[3]?.textContent || '').trim(),
                department: (cells[4]?.textContent || '').trim(),
                status: (cells[5]?.textContent || '').trim()
            };
        });

        const headers = ['Nome', 'Cargo', 'Departamento', 'Status'];
        const csv = [
            headers.join(','),
            ...employees.map((e) => [
                `"${String(e.name || '').replace(/"/g, '""')}"`,
                `"${String(e.position || '').replace(/"/g, '""')}"`,
                `"${String(e.department || '').replace(/"/g, '""')}"`,
                `"${String(e.status || '').replace(/"/g, '""')}"`
            ].join(','))
        ].join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `funcionarios_selecionados_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();

        if (typeof showSuccess === 'function') {
            showSuccess(`${employees.length} funcionário(s) exportado(s)`);
        }
    };

    async function postBulkUpdate(formData) {
        const response = await fetch('../api/employees/update_employee.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        return response.json();
    }

    function bindBulkForms() {
        const vacationForm = document.getElementById('bulkVacationForm');
        if (vacationForm && vacationForm.dataset.boundGlobalBulk !== '1') {
            vacationForm.dataset.boundGlobalBulk = '1';
            vacationForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const selected = requireSelection();
                if (!selected) return;

                const startDate = document.getElementById('vacationStartDate')?.value || '';
                const endDate = document.getElementById('vacationEndDate')?.value || '';

                for (const emp of selected) {
                    const fd = new FormData();
                    fd.append('action', 'update_status');
                    fd.append('id', emp.id);
                    fd.append('status', 'ferias');
                    if (startDate) fd.append('start_vacation', startDate);
                    if (endDate) fd.append('end_vacation', endDate);
                    await postBulkUpdate(fd);
                }

                if (typeof showSuccess === 'function') {
                    showSuccess(`${selected.length} funcionário(s) marcado(s) em férias`);
                }
                const modal = document.getElementById('bulkVacationModal');
                if (modal) modal.style.display = 'none';
                if (typeof window.clearBulkSelection === 'function') window.clearBulkSelection();
                setTimeout(() => location.reload(), 900);
            });
        }

        const statusForm = document.getElementById('bulkStatusForm');
        if (statusForm && statusForm.dataset.boundGlobalBulk !== '1') {
            statusForm.dataset.boundGlobalBulk = '1';
            statusForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const selected = requireSelection();
                if (!selected) return;

                const newStatus = document.getElementById('bulkNewStatus')?.value || '';
                if (!newStatus) {
                    if (typeof showWarning === 'function') showWarning('Selecione um status');
                    return;
                }

                for (const emp of selected) {
                    const fd = new FormData();
                    fd.append('action', 'update_status');
                    fd.append('id', emp.id);
                    fd.append('status', newStatus);
                    await postBulkUpdate(fd);
                }

                if (typeof showSuccess === 'function') {
                    showSuccess(`Status de ${selected.length} funcionário(s) alterado(s)`);
                }
                const modal = document.getElementById('bulkStatusModal');
                if (modal) modal.style.display = 'none';
                if (typeof window.clearBulkSelection === 'function') window.clearBulkSelection();
                setTimeout(() => location.reload(), 900);
            });
        }

        const departmentForm = document.getElementById('bulkDepartmentForm');
        if (departmentForm && departmentForm.dataset.boundGlobalBulk !== '1') {
            departmentForm.dataset.boundGlobalBulk = '1';
            departmentForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const selected = requireSelection();
                if (!selected) return;

                const newDepartment = (document.getElementById('bulkNewDepartment')?.value || '').trim();
                if (!newDepartment) {
                    if (typeof showWarning === 'function') showWarning('Informe o novo departamento');
                    return;
                }

                for (const emp of selected) {
                    const fd = new FormData();
                    fd.append('id', emp.id);
                    fd.append('department', newDepartment);
                    await postBulkUpdate(fd);
                }

                if (typeof showSuccess === 'function') {
                    showSuccess(`Departamento alterado para ${selected.length} funcionário(s)`);
                }
                const modal = document.getElementById('bulkDepartmentModal');
                if (modal) modal.style.display = 'none';
                if (typeof window.clearBulkSelection === 'function') window.clearBulkSelection();
                setTimeout(() => location.reload(), 900);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindBulkForms);
    } else {
        bindBulkForms();
    }
})();


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

                    document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filtroRelatorioFuncionarios');
    if (!form) return;

    const tableBody = document.querySelector('#relatorio-funcionarios-resumido tbody');
    if (!tableBody) return;

    const rows = Array.from(tableBody.querySelectorAll('tr'));
    const searchInput = document.getElementById('relatorioSearchName');
    const statusSelect = document.getElementById('relatorioFilterStatus');
    const cargoSelect = document.getElementById('relatorioFilterCargo');
    const departamentoSelect = document.getElementById('relatorioFilterDepartamento');
    const totalMinInput = document.getElementById('relatorioFilterTotalMin');
    const totalMaxInput = document.getElementById('relatorioFilterTotalMax');
    const periodoInicioInput = document.getElementById('relatorioPeriodoInicio');
    const periodoFimInput = document.getElementById('relatorioPeriodoFim');
    const resultCount = document.getElementById('relatorioResultCount');

    if (!searchInput || !statusSelect || !cargoSelect || !departamentoSelect || !totalMinInput || !totalMaxInput || !periodoInicioInput || !periodoFimInput) {
        return;
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
    });

    function normalizeText(value) {
        return (value || '').toString().trim().toLowerCase();
    }

    function normalizeStatus(value) {
        const status = normalizeText(value);
        if (status === 'active') return 'ativo';
        if (status === 'inactive') return 'inativo';
        if (status === 'férias') return 'ferias';
        return status;
    }

    function isDateInRange(rowDate, startDate, endDate) {
        if (!startDate && !endDate) return true;
        if (!rowDate) return false;
        if (startDate && rowDate < startDate) return false;
        if (endDate && rowDate > endDate) return false;
        return true;
    }

    function populateSelectFromRows(selectEl, attrName, defaultLabel) {
        const values = new Set();
        rows.forEach((row) => {
            const value = (row.getAttribute(attrName) || '').trim();
            if (value !== '') values.add(value);
        });

        const previousValue = selectEl.value;
        selectEl.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = defaultLabel;
        selectEl.appendChild(defaultOption);

        Array.from(values)
            .sort((a, b) => a.localeCompare(b, 'pt', { sensitivity: 'base' }))
            .forEach((value) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                selectEl.appendChild(option);
            });

        if (previousValue && Array.from(selectEl.options).some((opt) => opt.value === previousValue)) {
            selectEl.value = previousValue;
        }
    }

    function applyRelatorioFilters() {
        const searchValue = normalizeText(searchInput.value);
        const statusValue = normalizeStatus(statusSelect.value);
        const cargoValue = normalizeText(cargoSelect.value);
        const deptValue = normalizeText(departamentoSelect.value);
        const totalMin = parseFloat(totalMinInput.value);
        const totalMax = parseFloat(totalMaxInput.value);
        const startDate = periodoInicioInput.value;
        const endDate = periodoFimInput.value;
        let visibleCount = 0;

        rows.forEach((row) => {
            const rowName = normalizeText(row.getAttribute('data-rel-name'));
            const rowCargo = normalizeText(row.getAttribute('data-rel-cargo'));
            const rowDept = normalizeText(row.getAttribute('data-rel-department'));
            const rowStatus = normalizeStatus(row.getAttribute('data-rel-status'));
            const rowTotal = parseFloat(row.getAttribute('data-rel-total') || '0');
            const rowDate = (row.getAttribute('data-rel-date') || '').trim();

            const matchesSearch = searchValue === '' || rowName.includes(searchValue) || rowCargo.includes(searchValue) || rowDept.includes(searchValue);
            const matchesStatus = statusValue === '' || rowStatus === statusValue;
            const matchesCargo = cargoValue === '' || rowCargo === cargoValue;
            const matchesDept = deptValue === '' || rowDept === deptValue;
            const matchesTotalMin = Number.isNaN(totalMin) || rowTotal >= totalMin;
            const matchesTotalMax = Number.isNaN(totalMax) || rowTotal <= totalMax;
            const matchesDateRange = isDateInRange(rowDate, startDate, endDate);

            const visible = matchesSearch && matchesStatus && matchesCargo && matchesDept && matchesTotalMin && matchesTotalMax && matchesDateRange;
            row.dataset.filterVisible = visible ? 'true' : 'false';
            row.style.display = visible ? '' : 'none';

            if (visible) visibleCount += 1;
        });

        if (resultCount) {
            resultCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'resultado' : 'resultados'}`;
        }

        if (typeof window.updateRelatorioChartsFromFilters === 'function') {
            window.updateRelatorioChartsFromFilters();
        }

        // Reinicializar paginação após filtros
        if (typeof window.reinitTablePagination === 'function') {
            window.reinitTablePagination();
        }
    }

    populateSelectFromRows(cargoSelect, 'data-rel-cargo', 'Todos os cargos');
    populateSelectFromRows(departamentoSelect, 'data-rel-department', 'Todos os departamentos');

    [searchInput, statusSelect, cargoSelect, departamentoSelect, totalMinInput, totalMaxInput, periodoInicioInput, periodoFimInput].forEach((el) => {
        el.addEventListener('input', applyRelatorioFilters);
        el.addEventListener('change', applyRelatorioFilters);
    });

    applyRelatorioFilters();
});

// ============================================================================
// FILTROS PARA PRESENCAS
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    const presencaSearchInput = document.getElementById('presencaSearchInput');
    const presencaStatusFilter = document.getElementById('presencaStatusFilter');
    const presencaStartDate = document.getElementById('presencaStartDate');
    const presencaEndDate = document.getElementById('presencaEndDate');
    const presencaTableBody = document.querySelector('#relatorio-presenca-table tbody');
    const presencaResultCount = document.getElementById('presencaResultCount');
    
    if (!presencaTableBody || !presencaSearchInput || !presencaStatusFilter || !presencaStartDate || !presencaEndDate) return;
    
    const presencaRows = Array.from(presencaTableBody.querySelectorAll('tr'));
    
    function applyPresencaFilters() {
        const searchTerm = presencaSearchInput.value.toLowerCase();
        const statusFilter = presencaStatusFilter.value;
        const startDate = presencaStartDate.value;
        const endDate = presencaEndDate.value;
        let visible = 0;
        
        presencaRows.forEach(row => {
            const nome = row.getAttribute('data-presenca-nome') || '';
            const status = row.getAttribute('data-presenca-status') || '';
            const rowDate = (row.getAttribute('data-presenca-date') || '').trim();
            
            const matchesSearch = nome.includes(searchTerm);
            const matchesStatus = statusFilter === '' || status === statusFilter;
            const matchesDate = (!startDate || rowDate >= startDate) && (!endDate || rowDate <= endDate);
            
            if (matchesSearch && matchesStatus && matchesDate) {
                row.dataset.filterVisible = 'true';
                row.style.display = '';
                visible++;
            } else {
                row.dataset.filterVisible = 'false';
                row.style.display = 'none';
            }
        });
        
        if (presencaResultCount) {
            presencaResultCount.textContent = `${visible} ${visible === 1 ? 'resultado' : 'resultados'}`;
        }

        if (typeof window.updateRelatorioChartsFromFilters === 'function') {
            window.updateRelatorioChartsFromFilters();
        }
        
            // Reinicializar paginação após filtros
            if (typeof window.reinitTablePagination === 'function') {
                window.reinitTablePagination();
            }
    }
    
    presencaSearchInput.addEventListener('input', applyPresencaFilters);
    presencaStatusFilter.addEventListener('change', applyPresencaFilters);
    presencaStartDate.addEventListener('change', applyPresencaFilters);
    presencaEndDate.addEventListener('change', applyPresencaFilters);
    applyPresencaFilters();
});

// ============================================================================
// FILTROS PARA TURNOS
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    const turnoSearchInput = document.getElementById('turnosSearchInput');
    const turnoStatusFilter = document.getElementById('turnosStatusFilter');
    const turnoStartDate = document.getElementById('turnosStartDate');
    const turnoEndDate = document.getElementById('turnosEndDate');
    const turnoTableBody = document.querySelector('#relatorio-turnos-table tbody');
    const turnoResultCount = document.getElementById('turnosResultCount');
    
    if (!turnoTableBody || !turnoSearchInput || !turnoStatusFilter || !turnoStartDate || !turnoEndDate) return;
    
    const turnoRows = Array.from(turnoTableBody.querySelectorAll('tr'));
    
    function applyTurnoFilters() {
        const searchTerm = turnoSearchInput.value.toLowerCase();
        const statusFilter = turnoStatusFilter.value;
        const startDate = turnoStartDate.value;
        const endDate = turnoEndDate.value;
        let visible = 0;
        
        turnoRows.forEach(row => {
            const nome = row.getAttribute('data-turno-nome') || '';
            const tipo = row.getAttribute('data-turno-tipo') || '';
            const status = row.getAttribute('data-turno-status') || '';
            const rowDate = (row.getAttribute('data-turno-date') || '').trim();
            
            const matchesSearch = nome.includes(searchTerm) || tipo.includes(searchTerm);
            const matchesStatus = statusFilter === '' || status === statusFilter;
            const matchesDate = (!startDate || rowDate >= startDate) && (!endDate || rowDate <= endDate);
            
            if (matchesSearch && matchesStatus && matchesDate) {
                row.dataset.filterVisible = 'true';
                row.style.display = '';
                visible++;
            } else {
                row.dataset.filterVisible = 'false';
                row.style.display = 'none';
            }
        });
        
        if (turnoResultCount) {
            turnoResultCount.textContent = `${visible} ${visible === 1 ? 'resultado' : 'resultados'}`;
        }

        if (typeof window.updateRelatorioChartsFromFilters === 'function') {
            window.updateRelatorioChartsFromFilters();
        }

        if (typeof window.reinitTablePagination === 'function') {
            window.reinitTablePagination();
        }
    }
    
    turnoSearchInput.addEventListener('input', applyTurnoFilters);
    turnoStatusFilter.addEventListener('change', applyTurnoFilters);
    turnoStartDate.addEventListener('change', applyTurnoFilters);
    turnoEndDate.addEventListener('change', applyTurnoFilters);
    applyTurnoFilters();
});

// ============================================================================
// FILTROS PARA GORJETAS
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    const gorjetaSearchInput = document.getElementById('gorjetasSearchInput');
    const gorjetaStatusFilter = document.getElementById('gorjetasStatusFilter');
    const gorjetaStartDate = document.getElementById('gorjetasStartDate');
    const gorjetaEndDate = document.getElementById('gorjetasEndDate');
    const gorjetaTableBody = document.querySelector('#relatorio-gorjetas-table tbody');
    const gorjetaResultCount = document.getElementById('gorjetasResultCount');
    
    if (!gorjetaTableBody || !gorjetaSearchInput || !gorjetaStatusFilter || !gorjetaStartDate || !gorjetaEndDate) return;
    
    const gorjetaRows = Array.from(gorjetaTableBody.querySelectorAll('tr'));
    
    function applyGorjetaFilters() {
        const searchTerm = gorjetaSearchInput.value.toLowerCase();
        const statusFilter = gorjetaStatusFilter.value;
        const startDate = gorjetaStartDate.value;
        const endDate = gorjetaEndDate.value;
        let visible = 0;
        
        gorjetaRows.forEach(row => {
            const nome = row.getAttribute('data-gorjeta-nome') || '';
            const status = row.getAttribute('data-gorjeta-status') || '';
            const rowDate = (row.getAttribute('data-gorjeta-date') || '').trim();
            
            const matchesSearch = nome.includes(searchTerm);
            const matchesStatus = statusFilter === '' || status === statusFilter;
            const matchesDate = (!startDate || rowDate >= startDate) && (!endDate || rowDate <= endDate);
            
            if (matchesSearch && matchesStatus && matchesDate) {
                row.dataset.filterVisible = 'true';
                row.style.display = '';
                visible++;
            } else {
                row.dataset.filterVisible = 'false';
                row.style.display = 'none';
            }
        });
        
        if (gorjetaResultCount) {
            gorjetaResultCount.textContent = `${visible} ${visible === 1 ? 'resultado' : 'resultados'}`;
        }

        if (typeof window.updateRelatorioChartsFromFilters === 'function') {
            window.updateRelatorioChartsFromFilters();
        }

        if (typeof window.reinitTablePagination === 'function') {
            window.reinitTablePagination();
        }
    }
    
    gorjetaSearchInput.addEventListener('input', applyGorjetaFilters);
    gorjetaStatusFilter.addEventListener('change', applyGorjetaFilters);
    gorjetaStartDate.addEventListener('change', applyGorjetaFilters);
    gorjetaEndDate.addEventListener('change', applyGorjetaFilters);
    applyGorjetaFilters();
});

// ============================================================================
// FILTROS PARA FOLHA
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    const folhaSearchInput = document.getElementById('folhaSearchInput');
    const folhaStartDate = document.getElementById('folhaStartDate');
    const folhaEndDate = document.getElementById('folhaEndDate');
    const folhaTableBody = document.querySelector('#relatorio-folha-table tbody');
    const folhaResultCount = document.getElementById('folhaResultCount');
    
    if (!folhaTableBody || !folhaSearchInput || !folhaStartDate || !folhaEndDate) return;
    
    const folhaRows = Array.from(folhaTableBody.querySelectorAll('tr'));
    
    function applyFolhaFilters() {
        const searchTerm = folhaSearchInput.value.toLowerCase();
        const startDate = folhaStartDate.value;
        const endDate = folhaEndDate.value;
        let visible = 0;
        
        folhaRows.forEach(row => {
            const nome = row.getAttribute('data-folha-nome') || '';
            const rowDate = (row.getAttribute('data-folha-date') || '').trim();
            
            const matchesSearch = nome.includes(searchTerm);
            const matchesDate = (!startDate || rowDate >= startDate) && (!endDate || rowDate <= endDate);
            
            if (matchesSearch && matchesDate) {
                row.dataset.filterVisible = 'true';
                row.style.display = '';
                visible++;
            } else {
                row.dataset.filterVisible = 'false';
                row.style.display = 'none';
            }
        });
        
        if (folhaResultCount) {
            folhaResultCount.textContent = `${visible} ${visible === 1 ? 'resultado' : 'resultados'}`;
        }

        if (typeof window.updateRelatorioChartsFromFilters === 'function') {
            window.updateRelatorioChartsFromFilters();
        }

        if (typeof window.reinitTablePagination === 'function') {
            window.reinitTablePagination();
        }
    }
    
    folhaSearchInput.addEventListener('input', applyFolhaFilters);
        folhaStartDate.addEventListener('change', applyFolhaFilters);
        folhaEndDate.addEventListener('change', applyFolhaFilters);
    applyFolhaFilters();
});

// ============================================================================
// EXPORTAÇÃO PROFISSIONAL (CSV, EXCEL, PDF) COM METADADOS
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    function collectVisibleTableData(tableSelector) {
        const table = document.querySelector(tableSelector);
        if (!table) return null;

        const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.innerText.replace(/\s+/g, ' ').trim());
        const rows = Array.from(table.querySelectorAll('tbody tr')).filter((row) => row.style.display !== 'none');
        const data = rows.map((row) => Array.from(row.querySelectorAll('td')).map((td) => td.innerText.replace(/\s+/g, ' ').trim()));
        return { headers, data };
    }

    function getPeriodoLabel(startInputId, endInputId) {
        const start = document.getElementById(startInputId)?.value || '';
        const end = document.getElementById(endInputId)?.value || '';
        if (!start && !end) return 'Todos os períodos';
        if (start && end) return `${start} até ${end}`;
        if (start) return `Desde ${start}`;
        return `Até ${end}`;
    }

    function buildMetadata(reportName, periodLabel, totalRows) {
        return [
            ['Relatório', reportName],
            ['Período aplicado', periodLabel],
            ['Registros exportados', String(totalRows)],
            ['Gerado em', new Date().toLocaleString('pt-PT')],
            ['Aplicação', 'RHNeto Pro']
        ];
    }

    function exportCsv(fileBase, metadata, headers, data) {
        const lines = [];
        metadata.forEach((m) => lines.push(`# ${m[0]}: ${m[1]}`));
        lines.push('');
        lines.push(headers.map((h) => `"${String(h).replace(/"/g, '""')}"`).join(','));
        data.forEach((row) => {
            lines.push(row.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(','));
        });
        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${fileBase}_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function exportExcel(fileBase, metadata, headers, data) {
        if (typeof XLSX === 'undefined') {
            exportCsv(fileBase, metadata, headers, data);
            return;
        }
        const aoa = [];
        metadata.forEach((m) => aoa.push([m[0], m[1]]));
        aoa.push([]);
        aoa.push(headers);
        data.forEach((row) => aoa.push(row));
        const ws = XLSX.utils.aoa_to_sheet(aoa);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Relatório');
        XLSX.writeFile(wb, `${fileBase}_${new Date().toISOString().split('T')[0]}.xlsx`);
    }

    function exportPdf(fileBase, metadata, headers, data) {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            exportCsv(fileBase, metadata, headers, data);
            return;
        }
        const doc = new window.jspdf.jsPDF({ orientation: 'landscape' });
        let y = 12;
        doc.setFontSize(11);
        metadata.forEach((m) => {
            doc.text(`${m[0]}: ${m[1]}`, 10, y);
            y += 6;
        });
        y += 4;
        doc.setFontSize(10);
        doc.text(headers.join(' | '), 10, y);
        y += 6;
        data.forEach((row) => {
            if (y > 190) {
                doc.addPage();
                y = 12;
            }
            doc.text(row.join(' | '), 10, y);
            y += 5;
        });
        doc.save(`${fileBase}_${new Date().toISOString().split('T')[0]}.pdf`);
    }

    async function chooseExportFormat() {
        if (window.Swal) {
            const result = await window.Swal.fire({
                title: 'Formato de exportação',
                input: 'radio',
                inputOptions: { csv: 'CSV', excel: 'Excel (.xlsx)', pdf: 'PDF' },
                inputValue: 'excel',
                showCancelButton: true,
                confirmButtonText: 'Exportar',
                cancelButtonText: 'Cancelar'
            });
            return result.isConfirmed ? result.value : null;
        }
        return 'excel';
    }

    async function exportReport(config) {
        const collected = collectVisibleTableData(config.tableSelector);
        if (!collected) return;
        const format = await chooseExportFormat();
        if (!format) return;

        const metadata = buildMetadata(
            config.reportName,
            getPeriodoLabel(config.startInputId, config.endInputId),
            collected.data.length
        );

        if (format === 'csv') {
            exportCsv(config.fileBase, metadata, collected.headers, collected.data);
        } else if (format === 'pdf') {
            exportPdf(config.fileBase, metadata, collected.headers, collected.data);
        } else {
            exportExcel(config.fileBase, metadata, collected.headers, collected.data);
        }
    }

    const exportsConfig = [
        {
            buttonId: 'btnExportarFuncionarios',
            tableSelector: '#relatorio-funcionarios-resumido table',
            reportName: 'Relatório Resumido de Funcionários',
            fileBase: 'relatorio_resumido_funcionarios',
            startInputId: 'relatorioPeriodoInicio',
            endInputId: 'relatorioPeriodoFim'
        },
        {
            buttonId: 'btnExportarPresencas',
            tableSelector: '#relatorio-presenca-table table',
            reportName: 'Relatório de Presenças',
            fileBase: 'relatorio_presencas',
            startInputId: 'presencaStartDate',
            endInputId: 'presencaEndDate'
        },
        {
            buttonId: 'btnExportarTurnos',
            tableSelector: '#relatorio-turnos-table table',
            reportName: 'Relatório de Turnos',
            fileBase: 'relatorio_turnos',
            startInputId: 'turnosStartDate',
            endInputId: 'turnosEndDate'
        },
        {
            buttonId: 'btnExportarGorjetas',
            tableSelector: '#relatorio-gorjetas-table table',
            reportName: 'Relatório de Gorjetas',
            fileBase: 'relatorio_gorjetas',
            startInputId: 'gorjetasStartDate',
            endInputId: 'gorjetasEndDate'
        },
        {
            buttonId: 'btnExportarFolha',
            tableSelector: '#relatorio-folha-table table',
            reportName: 'Relatório de Folha',
            fileBase: 'relatorio_folha',
            startInputId: 'folhaStartDate',
            endInputId: 'folhaEndDate'
        }
    ];

    exportsConfig.forEach((config) => {
        const button = document.getElementById(config.buttonId);
        if (!button) return;
        button.addEventListener('click', function() {
            exportReport(config);
        });
    });

    if (typeof window.reinitTablePagination === 'function') {
        window.reinitTablePagination();
    }
});



