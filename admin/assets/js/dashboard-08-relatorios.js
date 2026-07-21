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
            ['Registos exportados', String(totalRows)],
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
        const doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const marginX = 12;

        const reportName = (metadata.find((m) => m[0] === 'Relatório') || [, fileBase])[1];
        const periodLabel = (metadata.find((m) => m[0] === 'Período aplicado') || [, ''])[1];
        const totalRows = (metadata.find((m) => m[0] === 'Registros exportados') || [, String(data.length)])[1];
        const generatedAt = new Date().toLocaleString('pt-PT');

        function drawHeader() {
            // Faixa de marca
            doc.setFillColor(29, 78, 216); // #1d4ed8
            doc.rect(0, 0, pageWidth, 22, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(15);
            doc.text('RHNeto Pro', marginX, 10);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.text('Sistema de Gestão de Recursos Humanos', marginX, 16);

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(12);
            doc.text(String(reportName), pageWidth - marginX, 10, { align: 'right' });
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.text(`Gerado em ${generatedAt}`, pageWidth - marginX, 16, { align: 'right' });

            // Linha de metadados (período + total)
            doc.setTextColor(51, 65, 85);
            doc.setFontSize(9);
            doc.text(`Período: ${periodLabel}    ·    Registos: ${totalRows}`, marginX, 29);
        }

        function drawFooter(pageData) {
            const pageCount = doc.internal.getNumberOfPages();
            const currentPage = pageData?.pageNumber || doc.internal.getCurrentPageInfo().pageNumber;
            doc.setDrawColor(226, 232, 240);
            doc.line(marginX, pageHeight - 12, pageWidth - marginX, pageHeight - 12);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(100, 116, 139);
            doc.text('RHNeto Pro — Relatório gerado automaticamente', marginX, pageHeight - 7);
            doc.text(`Página ${currentPage} de ${pageCount}`, pageWidth - marginX, pageHeight - 7, { align: 'right' });
        }

        drawHeader();

        doc.autoTable({
            head: [headers],
            body: data,
            startY: 34,
            margin: { left: marginX, right: marginX, bottom: 16 },
            styles: {
                fontSize: 8.5,
                cellPadding: 2.5,
                textColor: [30, 41, 59],
                lineColor: [226, 232, 240],
                lineWidth: 0.15
            },
            headStyles: {
                fillColor: [37, 99, 235],
                textColor: [255, 255, 255],
                fontStyle: 'bold',
                halign: 'left'
            },
            alternateRowStyles: {
                fillColor: [241, 245, 249]
            },
            didDrawPage: function (pageData) {
                if (pageData.pageNumber > 1) drawHeader();
                drawFooter(pageData);
            }
        });

        // Reaplica o rodapé na última página, cuja contagem total de páginas só é conhecida no final
        drawFooter();

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



