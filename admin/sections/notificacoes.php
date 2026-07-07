<?php
// Secção "Notificações" — incluída a partir de admin/dashboard.php (depende de variáveis já definidas lá).
?>
        <section id="notificacoes-section" class="content-section">
            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon"><i class="fas fa-bell"></i></div>
                    <div>
                        <h2 class="frhd-title">Notificações</h2>
                        <p class="frhd-sub" id="notificationsTotalCountBadge">-- notificações</p>
                    </div>
                </div>
                <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                    <button id="btnViewReceivedNotifications" type="button" class="btn btn-primary" onclick="setNotificationsView('received')">
                        <i class="fas fa-inbox"></i>
                        <span>Notificações</span>
                    </button>
                    <button id="btnViewSentSMSOptions" type="button" class="btn btn-secondary" onclick="setNotificationsView('sent')">
                        <i class="fas fa-paper-plane"></i>
                        <span>Mensagens enviadas</span>
                    </button>
                </div>
            </div>

            <div class="card-grid" style="grid-template-columns: 1fr; gap: 1rem;">
                <div class="info-card" style="padding: 1rem;">
                    <div id="notificationsReceivedPanel">
                        <div id="adminNotificationsFeed" style="display:grid; gap:.65rem;">
                            <div style="border: 1px dashed var(--border-primary); border-radius: 10px; padding: 0.9rem; color: var(--text-secondary); text-align: center;">
                                Carregando notificações...
                            </div>
                        </div>
                    </div>

                    <div id="notificationsSentPanel" style="display:none;">
                        <div id="notificationsSentHistoryList" style="display:grid; gap:.65rem;">
                            <div style="border: 1px dashed var(--border-primary); border-radius: 10px; padding: 0.9rem; color: var(--text-secondary); text-align: center;">
                                Carregando SMS enviadas...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
