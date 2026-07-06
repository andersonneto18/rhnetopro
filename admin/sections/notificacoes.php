<?php
// Secção "Notificações" — incluída a partir de admin/dashboard.php (depende de variáveis já definidas lá).
?>
        <section id="notificacoes-section" class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <span>Notificações</span>
                </div>
            </div>

            <div class="card-grid" style="grid-template-columns: 1fr; gap: 1rem;">
                <div class="info-card" style="padding: 1rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:.9rem;">
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
                        <div id="notificationsTotalCountBadge" style="font-size:.85rem; color:var(--text-secondary); font-weight:600;">
                            <i class="fas fa-eye"></i> -- notificações
                        </div>
                    </div>

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
