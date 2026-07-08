




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
    const isExportTrigger = event.target.matches('.btn-accent') || event.target.closest('.btn-accent')
        || event.target.matches('.fr-export-btn') || event.target.closest('.fr-export-btn');
    if (!isExportTrigger) {
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






 




