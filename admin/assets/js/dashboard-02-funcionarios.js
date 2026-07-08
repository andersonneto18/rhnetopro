// Fallback global: garante desativacao mesmo se algum bloco posterior falhar na inicializacao.
(function installEmergencyEmployeeDeactivateHandler() {
    if (window.__employeeDeactivateEmergencyReady) return;
    window.__employeeDeactivateEmergencyReady = true;

    async function deactivateEmployeeRequest(employeeId) {
        const fd = new FormData();
        fd.append('id', employeeId);
        fd.append('status', 'inactive');
        fd.append('quick_status_toggle', '1');

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
                formData.append('quick_status_toggle', '1');

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




