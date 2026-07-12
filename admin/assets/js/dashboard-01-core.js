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
    const overtimeEl = document.getElementById('view-presenca-horas-extras');
    const workedDaysEl = document.getElementById('view-presenca-dias-trabalhados');
    const absencesEl = document.getElementById('view-presenca-numero-faltas');

    if (overtimeEl) overtimeEl.textContent = '00:00';
    if (workedDaysEl) workedDaysEl.textContent = '0';
    if (absencesEl) absencesEl.textContent = '0';

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
        const _photo = String(d.employeePhoto || '').trim();
        if (_photo !== '') {
            _presAvEl.innerHTML = `<img src="../${_photo}" alt="${funcionario}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`;
        } else {
            const _np = String(funcionario).trim().split(/\s+/);
            _presAvEl.textContent = ((_np[0]?.[0] || '') + (_np[1]?.[0] || '')).toUpperCase() || '--';
        }
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
    const atrasoEl = document.getElementById('view-presenca-atraso');
    if (atrasoEl) {
        atrasoEl.textContent = atraso;
        atrasoEl.style.color = atraso.startsWith('Atrasado')
            ? '#dc2626'
            : (atraso === 'Pontual' ? '#16a34a' : '');
    }
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

    const hasJustificativaRecord = [
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
        document.getElementById('view-presenca-just-status').textContent = justStatus;

        const justDetailsEl = document.getElementById('view-presenca-just-details');
        if (justDetailsEl) {
            justDetailsEl.style.display = hasJustificativaRecord ? '' : 'none';
        }

        if (hasJustificativaRecord) {
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
        }

        justSection.style.display = '';
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

    const subtitleEl = document.getElementById('edit-presenca-subtitle');
    if (subtitleEl) {
        const nome = row.dataset.funcionarioNome || '';
        const dataDisplay = row.dataset.dateDisplay || row.dataset.presencaDate || '';
        subtitleEl.textContent = nome ? `${nome} — ${dataDisplay}` : 'Alterar dados de presença';
    }

    document.getElementById('edit-presenca-employee-id').value = employeeId;
    const targetDateEl = document.getElementById('edit-presenca-target-date');
    if (targetDateEl) {
        targetDateEl.value = row.dataset.presencaDate || getTodayIsoDate();
    }

    const statusBadge = row.querySelector(`#attendance-status-${employeeId}`);
    // Dia sem turno hoje (Folga/Sem Turno): não existe nenhum registo por trás, então não faz
    // sentido herdar o "Normal" armazenado por omissão nem cair em "Invalidado" (que sugere um
    // registo real que foi invalidado). Nestes dias, o motivo mais provável de abrir esta edição
    // é registar que a pessoa trabalhou mesmo assim.
    const isDiaSemTurno = !!(statusBadge && ['folga', 'sem-turno'].includes(statusBadge.dataset.statusKey || ''));

    const tipoDiaSelect = document.getElementById('edit-presenca-tipo-dia');
    if (tipoDiaSelect) {
        tipoDiaSelect.value = isDiaSemTurno ? 'folga' : normalizeTipoDiaFromCell(row.dataset.tipoDia || 'Normal');
    }

    const isPresente = !!(statusBadge && statusBadge.classList.contains('status-presente'));
    const isFalta = !!(statusBadge && statusBadge.classList.contains('status-falta'));
    document.getElementById('edit-presenca-status').value = isPresente ? 'presente' : (isFalta ? 'falta' : (isDiaSemTurno ? 'presente' : 'invalidado'));

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

