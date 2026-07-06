<?php
// Secção "Início" — incluída a partir de admin/dashboard.php (depende de $ADMIN_DIR, $pdo, $loggedInClientId, $username, etc. já definidos lá).
?>
        <section id="inicio-section" class="content-section active">

            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon"><i class="fas fa-house-chimney"></i></div>
                    <div>
                        <h2 class="frhd-title"><?php echo htmlspecialchars($username); ?></h2>
                        <p class="frhd-sub"><?php
                            $diasPT = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
                            $mesesPT = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
                            echo $diasPT[(int)date('w')] . ', ' . date('d') . ' de ' . $mesesPT[(int)date('n')-1] . ' de ' . date('Y');
                        ?> &middot; <?php echo (int)($activeEmployeesCount ?? 0); ?> funcionários ativos</p>
                    </div>
                </div>
                <button type="button" class="frhd-add-btn" onclick="showSection('funcionarios')">
                    <i class="fas fa-user-plus"></i> Novo Funcionário
                </button>
            </div>

            <div class="fr-kpi-strip" style="grid-template-columns:repeat(4,1fr);">
                <div class="fr-kpi fr-kpi-total">
                    <div class="fr-kpi-icon"><i class="fas fa-users"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)($activeEmployeesCount ?? 0); ?></span>
                        <span class="fr-kpi-lbl">Funcionários</span>
                        <span class="fr-kpi-pct">total ativo</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-active">
                    <div class="fr-kpi-icon"><i class="fas fa-user-check"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)($presentCount ?? 0); ?></span>
                        <span class="fr-kpi-lbl">Presentes</span>
                        <span class="fr-kpi-pct">hoje</span>
                    </div>
                </div>
                <div class="fr-kpi fr-kpi-inactive">
                    <div class="fr-kpi-icon"><i class="fas fa-user-times"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)($faltasHoje ?? 0); ?></span>
                        <span class="fr-kpi-lbl">Faltas</span>
                        <span class="fr-kpi-pct">hoje</span>
                    </div>
                </div>
                <div class="fr-kpi" style="position:relative;">
                    <div class="fr-kpi-icon" style="background:rgba(167,139,250,.12);color:#a78bfa;"><i class="fas fa-euro-sign"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val" style="font-size:1.25rem;">€ <?php echo number_format((float)($folhaResumo['custo_total'] ?? 0), 2, ',', '.'); ?></span>
                        <span class="fr-kpi-lbl">Custos Salariais</span>
                        <span class="fr-kpi-pct">este mês</span>
                    </div>
                </div>
            </div>

            <div class="data-table fr-table-wrap" style="margin-top:.25rem;">
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#2563eb);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;flex-shrink:0;box-shadow:0 3px 10px rgba(59,130,246,.3);">
                                <i class="fas fa-stream"></i>
                            </div>
                            <div>
                                <div style="font-size:.95rem;font-weight:700;color:var(--text-primary,#f1f5f9);line-height:1.1;">Atividade Recente</div>
                                <div style="font-size:.72rem;color:#64748b;margin-top:1px;">Eventos de hoje</div>
                            </div>
                        </div>
                    </div>
                </div>

                <table class="table fr-table" style="margin:0;">
                    <thead>
                        <tr class="fr-thead-row">
                            <th style="width:44px;"></th>
                            <th>Evento</th>
                            <th style="width:120px;">Estado</th>
                            <th style="width:90px;text-align:right;">Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // 1. Verificamos se a variável existe e se não está vazia
                    if (!empty($recentActivities) && is_array($recentActivities)):
                        foreach ($recentActivities as $activity):
                            // 2. Verificamos se cada item individual tem os dados necessários
                            if (isset($activity['title'], $activity['timestamp'])):
                    ?>
                    <?php
                                // Tentar obter avatar relacionado (se existir)
                                $avatarUrl = null;
                                $avatarAlt = '';

                                if (!empty($activity['employee_profile_picture'] ?? null)) {
                                    $avatarUrl = $activity['employee_profile_picture'];
                                }
                                if (!empty($activity['employee_name'] ?? null)) {
                                    $avatarAlt = $activity['employee_name'];
                                }

                                // Se ainda não houver dados suficientes, tenta buscar pelo employee_id
                                if (!$avatarAlt && !empty($activity['employee_id'] ?? null)) {
                                    try {
                                        $stmtAvatar = $pdo->prepare("SELECT profile_picture, name FROM employees WHERE client_id = ? AND id = ? LIMIT 1");
                                        $stmtAvatar->execute([$loggedInClientId, $activity['employee_id']]);
                                        $rowA = $stmtAvatar->fetch(PDO::FETCH_ASSOC);
                                        if ($rowA) {
                                            $avatarAlt = $rowA['name'] ?? '';
                                            if (!empty($rowA['profile_picture'])) {
                                                $avatarUrl = $rowA['profile_picture'];
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        // ignore lookup errors
                                    }
                                }

                                // fallback: quando criamos uma atividade tipo 'Novo funcionário: Nome' ou 'Nome marcou presença/falta' tentamos buscar pelo nome
                                if (!$avatarUrl) {
                                    $nomeBuscado = '';

                                    if (preg_match('/Novo funcionário:\s*(.+)/i', $activity['title'], $m)) {
                                        $nomeBuscado = trim($m[1]);
                                    } elseif (preg_match('/^(.+?)\s+marcou\s+(?:presen[cç]a|falta)/i', $activity['title'], $m)) {
                                        $nomeBuscado = trim($m[1]);
                                    } elseif (preg_match('/^(?:Presen[cç]a|Falta):\s*(.+)$/i', $activity['title'], $m)) {
                                        $nomeBuscado = trim($m[1]);
                                    } else {
                                        // tentativa mais genérica: texto após ':'
                                        if (strpos($activity['title'], ':') !== false) {
                                            $parts = explode(':', $activity['title'], 2);
                                            $possible = trim($parts[1]);
                                            if (strlen($possible) > 0 && strlen($possible) < 80) {
                                                $nomeBuscado = $possible;
                                            }
                                        }
                                    }

                                    if ($nomeBuscado !== '') {
                                        try {
                                            // tolerância estendida: normaliza espaços e pontuação
                                            $nomeNormalized = preg_replace('/\s+/', ' ', trim($nomeBuscado));

                                            // 1) tentativa de correspondência exata (como antes)
                                            $stmtAvatar = $pdo->prepare("SELECT profile_picture, name FROM employees WHERE client_id = ? AND name = ? LIMIT 1");
                                            $stmtAvatar->execute([$loggedInClientId, $nomeNormalized]);
                                            $rowA = $stmtAvatar->fetch(PDO::FETCH_ASSOC);

                                            // 2) variants mais tolerantes
                                            if (!$rowA) {
                                                // tentar variações de verbo (marcou presença / marcou ponto / registrou presença)
                                                $patterns = [
                                                    $nomeNormalized,
                                                    "%$nomeNormalized%",
                                                ];
                                                $stmtAvatar = $pdo->prepare("SELECT profile_picture, name FROM employees WHERE client_id = ? AND (name = ? OR name LIKE ?) LIMIT 1");
                                                $stmtAvatar->execute([$loggedInClientId, $patterns[0], $patterns[1]]);
                                                $rowA = $stmtAvatar->fetch(PDO::FETCH_ASSOC);
                                            }

                                            // 3) procurar por primeiro/último nome
                                            if (!$rowA) {
                                                $parts = preg_split('/\s+/', $nomeNormalized);
                                                if (count($parts) > 1) {
                                                    $first = $parts[0];
                                                    $last = end($parts);
                                                    $stmtAvatar = $pdo->prepare("SELECT profile_picture, name FROM employees WHERE client_id = ? AND (name LIKE ? OR name LIKE ?) LIMIT 1");
                                                    $stmtAvatar->execute([$loggedInClientId, "%$first%", "%$last%"]);
                                                    $rowA = $stmtAvatar->fetch(PDO::FETCH_ASSOC);
                                                }
                                            }

                                            if ($rowA) {
                                                $avatarAlt = $rowA['name'] ?? $nomeBuscado;
                                                if (!empty($rowA['profile_picture'])) {
                                                    $candidate = '../' . ltrim($rowA['profile_picture'], '/');
                                                    if (@file_exists($ADMIN_DIR . '/' . $candidate) || @file_exists($ADMIN_DIR . '/../' . $rowA['profile_picture'])) {
                                                        $avatarUrl = $rowA['profile_picture'];
                                                    }
                                                }
                                            }
                                        } catch (PDOException $e) {
                                            // ignore
                                        }
                                    }
                                }
                                ?>

                    <?php
                                $avatarSrc = null;
                                if (!empty($avatarUrl)) {
                                    $avatarSrc = preg_match('#^https?://#i', $avatarUrl) ? $avatarUrl : '../' . ltrim($avatarUrl, '/');
                                }
                                $actParts = preg_split('/\s+/', $avatarAlt);
                                $actInitials = '';
                                foreach ($actParts as $p) {
                                    if (trim($p) !== '' && mb_strlen($actInitials) < 2) $actInitials .= mb_strtoupper(mb_substr($p, 0, 1));
                                }
                                $displayStatus = '';
                                if (!empty($activity['status'])) {
                                    $displayStatus = $activity['status'];
                                } else {
                                    $t = mb_strtolower($activity['title'] ?? '');
                                    if (strpos($t, 'marcou presença') !== false || strpos($t, 'presente') !== false) $displayStatus = 'Presente';
                                    elseif (strpos($t, 'marcou falta') !== false || strpos($t, 'falta') !== false) $displayStatus = 'Falta';
                                    elseif (strpos($t, 'novo funcionário') !== false || strpos($t, 'novo') !== false) $displayStatus = 'Novo';
                                }
                                $badgeClass = $displayStatus ? 'status-' . preg_replace('/[^a-z0-9\-]/u', '-', mb_strtolower($displayStatus)) : '';
                                $actTime = !empty($activity['timestamp']) ? strtotime($activity['timestamp']) : 0;
                                ?>
                    <tr class="fr-row">
                        <td style="padding:.75rem .5rem .75rem 1rem;width:44px;vertical-align:middle;">
                            <?php if (!empty($avatarSrc)): ?>
                            <div class="fr-av" style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:50%;">
                                <img class="fr-av-img" style="border-radius:50%;" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="">
                            </div>
                            <?php elseif ($actInitials !== ''): ?>
                            <div class="fr-av" style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:50%;font-size:.8rem;"><?php echo htmlspecialchars($actInitials); ?></div>
                            <?php else:
                                $actIcon = strpos($activity['title'], 'Novo funcionário') !== false ? 'fa-user-plus' : (strpos($activity['title'], 'presente') !== false ? 'fa-check-circle' : 'fa-info-circle');
                            ?>
                            <div class="fr-av" style="background:rgba(59,130,246,.12);color:#3b82f6;border-radius:10px;font-size:.85rem;"><i class="fas <?php echo $actIcon; ?>"></i></div>
                            <?php endif; ?>
                        </td>
                        <td class="fr-td-emp" style="vertical-align:middle;">
                            <span class="fr-emp-name"><?php echo htmlspecialchars($activity['title']); ?></span>
                        </td>
                        <td class="fr-td-status" style="vertical-align:middle;">
                            <?php if ($badgeClass): ?><span class="status-badge <?php echo htmlspecialchars($badgeClass); ?>"><?php echo htmlspecialchars($displayStatus); ?></span><?php endif; ?>
                        </td>
                        <td style="vertical-align:middle;text-align:right;padding-right:1rem;">
                            <span style="font-size:.75rem;color:#64748b;white-space:nowrap;"><?php echo $actTime ? date('H:i', $actTime) : '--:--'; ?></span>
                        </td>
                    </tr>
                    <?php
                            endif;
                        endforeach;
                    else:
                        ?>
                    <tr>
                        <td colspan="4" style="text-align:center;padding:3rem 1rem;color:#475569;">
                            <i class="fas fa-stream" style="font-size:2rem;opacity:.2;display:block;margin-bottom:.75rem;"></i>
                            Nenhuma atividade registada hoje.
                        </td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </section>
