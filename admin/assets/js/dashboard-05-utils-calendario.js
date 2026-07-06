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
        card.style.border = isInativo ? '1px solid rgba(239,68,68,.3)' : '1px solid rgba(59,130,246,.3)';
        card.style.borderLeft = `4px solid ${accent}`;
        card.style.background = isInativo
            ? 'linear-gradient(135deg, rgba(239,68,68,.14), rgba(239,68,68,.05))'
            : 'linear-gradient(135deg, rgba(59,130,246,.12), rgba(59,130,246,.05))';
        card.style.boxShadow = '0 10px 24px rgba(0, 0, 0, 0.18)';
        card.style.display = 'flex';
        card.style.flexDirection = 'column';
        card.style.gap = '0.25rem';
    }

    function styleCalendarShell() {
        if (turnosCalendarWrapper) {
            turnosCalendarWrapper.style.marginTop = '1rem';
            turnosCalendarWrapper.style.border = '1px solid rgba(255,255,255,.08)';
            turnosCalendarWrapper.style.borderRadius = '18px';
            turnosCalendarWrapper.style.background = 'var(--card-bg, #1e293b)';
            turnosCalendarWrapper.style.padding = '1rem';
            turnosCalendarWrapper.style.boxShadow = '0 18px 38px rgba(0, 0, 0, 0.25)';
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
        title.style.color = '#f1f5f9';
        title.style.fontSize = '0.88rem';

        const subtitle = document.createElement('div');
        subtitle.className = 'turnos-calendar-card-subtitle';
        subtitle.textContent = `${turno.turnoTipo || '-'} | ${turno.horarioText || '-'}`;
        subtitle.style.color = '#93c5fd';
        subtitle.style.fontSize = '0.8rem';
        subtitle.style.fontWeight = '600';

        const meta = document.createElement('div');
        meta.className = 'turnos-calendar-card-meta';
        meta.textContent = `Escala: ${turno.escala || '-'} | ${capitalizeText(turno.statusText || 'ativo')}`;
        meta.style.color = '#94a3b8';
        meta.style.fontSize = '0.76rem';

        if (turno.vigenciaText) {
            const rangeMeta = document.createElement('div');
            rangeMeta.textContent = `Vigência: ${turno.vigenciaText}`;
            rangeMeta.style.color = '#94a3b8';
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
            dayCol.style.border = '1px solid rgba(255,255,255,.08)';
            dayCol.style.borderRadius = '16px';
            dayCol.style.background = 'rgba(255,255,255,.03)';
            dayCol.style.display = 'flex';
            dayCol.style.flexDirection = 'column';
            dayCol.style.overflow = 'hidden';
            dayCol.style.boxShadow = '0 12px 28px rgba(0, 0, 0, 0.18)';

            const dayTurnos = turnosData.filter(turno => turnoOccursOnDate(turno, dayDate));
            const isToday = isSameDate(dayDate, new Date());
            if (isToday) {
                dayCol.classList.add('is-today');
                dayCol.style.borderColor = '#3b82f6';
                dayCol.style.boxShadow = '0 0 0 2px rgba(59, 130, 246, 0.16), 0 14px 32px rgba(0, 0, 0, 0.22)';
            }

            const header = document.createElement('div');
            header.className = 'turnos-calendar-day-header';
            header.style.padding = '0.85rem 0.9rem';
            header.style.borderBottom = '1px solid rgba(255,255,255,.08)';
            header.style.display = 'grid';
            header.style.gridTemplateColumns = '1fr auto auto';
            header.style.alignItems = 'center';
            header.style.gap = '0.5rem';
            header.style.background = isToday ? 'linear-gradient(90deg, rgba(59,130,246,.18), rgba(59,130,246,.06))' : 'rgba(255,255,255,.02)';

            const dayName = document.createElement('strong');
            dayName.textContent = weekDayShortLabels[dayIndex];
            dayName.style.fontSize = '0.95rem';
            dayName.style.color = '#e2e8f0';

            const dateText = document.createElement('span');
            dateText.textContent = formatDate(dayDate);
            dateText.style.color = '#94a3b8';
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
                empty.style.border = '1px dashed rgba(255,255,255,.15)';
                empty.style.borderRadius = '12px';
                empty.style.background = 'rgba(255,255,255,.02)';
                empty.style.color = '#94a3b8';
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
            weekday.style.color = '#60a5fa';
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
            cell.style.border = '1px solid rgba(255,255,255,.08)';
            cell.style.borderRadius = '14px';
            cell.style.background = isCurrentMonth ? 'rgba(255,255,255,.03)' : 'rgba(255,255,255,.015)';
            cell.style.padding = '0.6rem';
            cell.style.display = 'flex';
            cell.style.flexDirection = 'column';
            cell.style.gap = '0.45rem';
            cell.style.boxShadow = isCurrentMonth ? '0 10px 24px rgba(0, 0, 0, 0.15)' : 'none';
            if (!isCurrentMonth) {
                cell.style.opacity = '0.72';
            }
            if (isToday) {
                cell.classList.add('is-today');
                cell.style.borderColor = '#3b82f6';
                cell.style.boxShadow = '0 0 0 2px rgba(59, 130, 246, 0.14), 0 12px 28px rgba(0, 0, 0, 0.2)';
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
            dateNumber.style.color = '#94a3b8';

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
                empty.style.border = '1px dashed rgba(255,255,255,.15)';
                empty.style.borderRadius = '10px';
                empty.style.background = 'rgba(255,255,255,.02)';
                empty.style.color = '#94a3b8';
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
                    more.style.color = '#60a5fa';
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



