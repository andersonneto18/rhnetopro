<?php
// Secção "Solicitações" — incluída a partir de admin/dashboard.php, logo após admin/sections/assiduidade.php (depende de variáveis definidas lá: $allJustificativas, $pontoDateColumn, $justificativasPendentes). Começa com um bloco de preparação de dados (histórico decidido, pendências) antes da tag <section>.
?>













        <?php
        $justificativasAprovadas = 0;
        $justificativasRejeitadas = 0;
        $solicitacoesHistoricoAprovadas = [];
        $solicitacoesHistoricoRejeitadas = [];
        foreach ($allJustificativas as $justificativaResumo) {
            $statusResumo = mb_strtolower(trim((string)($justificativaResumo['status'] ?? 'pendente')));
            if ($statusResumo === 'aprovada') {
                $justificativasAprovadas++;
                $solicitacoesHistoricoAprovadas[] = [
                    'tipo' => 'Justificativa',
                    'funcionario' => (string)($justificativaResumo['employee_name'] ?? 'Funcionário'),
                    'employee_profile_picture' => $justificativaResumo['employee_profile_picture'] ?? '',
                    'data_ref' => (string)($justificativaResumo['data_ocorrencia'] ?? ''),
                    'detalhe' => (string)($justificativaResumo['tipo'] ?? 'falta'),
                    'status_label' => 'Aprovada'
                ];
            } elseif ($statusResumo === 'rejeitada') {
                $justificativasRejeitadas++;
                $solicitacoesHistoricoRejeitadas[] = [
                    'tipo' => 'Justificativa',
                    'funcionario' => (string)($justificativaResumo['employee_name'] ?? 'Funcionário'),
                    'employee_profile_picture' => $justificativaResumo['employee_profile_picture'] ?? '',
                    'data_ref' => (string)($justificativaResumo['data_ocorrencia'] ?? ''),
                    'detalhe' => (string)($justificativaResumo['tipo'] ?? 'falta'),
                    'status_label' => 'Rejeitada'
                ];
            }
        }

        $presencasPendentes = [];
        $presencasAprovadasSolic = 0;
        $presencasRejeitadasSolic = 0;
        try {
            $hasClientIdInPonto = false;
            try {
                $hasClientIdInPonto = (bool)$pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'client_id'")->fetch();
            } catch (Throwable $e) {
            }

            $solPontoSql    = '';
            $solPontoParams = [(int)$loggedInClientId];
            if ($solServerStart !== '') {
                $solPontoSql    .= " AND DATE(rp.{$pontoDateColumn}) >= ?";
                $solPontoParams[] = $solServerStart;
            }
            if ($solServerEnd !== '') {
                $solPontoSql    .= " AND DATE(rp.{$pontoDateColumn}) <= ?";
                $solPontoParams[] = $solServerEnd;
            }

            if ($hasClientIdInPonto) {
                $stmtSolicPres = $pdo->prepare(
                    "SELECT rp.id, rp.funcionario_id AS employee_id, rp.status, rp.status_confirmacao,
                    rp.hora_entrada, rp.hora_saida, rp.tipo_dia, rp.falta_tipo,
                    DATE(rp.{$pontoDateColumn}) AS data_ref, e.name AS employee_name, e.profile_picture AS employee_profile_picture
             FROM registros_ponto rp
             INNER JOIN employees e ON e.id = rp.funcionario_id
             WHERE e.client_id = ? {$solPontoSql}
             ORDER BY rp.{$pontoDateColumn} DESC, rp.id DESC"
                );
                $stmtSolicPres->execute($solPontoParams);
            } else {
                $stmtSolicPres = $pdo->prepare(
                    "SELECT rp.id, rp.funcionario_id AS employee_id, rp.status, rp.status_confirmacao,
                    rp.hora_entrada, rp.hora_saida, rp.tipo_dia, rp.falta_tipo,
                    DATE(rp.{$pontoDateColumn}) AS data_ref, e.name AS employee_name, e.profile_picture AS employee_profile_picture
             FROM registros_ponto rp
             INNER JOIN employees e ON e.id = rp.funcionario_id
             WHERE e.client_id = ? {$solPontoSql}
             ORDER BY rp.{$pontoDateColumn} DESC, rp.id DESC"
                );
                $stmtSolicPres->execute($solPontoParams);
            }

            $presRows = $stmtSolicPres->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $seenPresence = [];
            foreach ($presRows as $pRow) {
                $empId = (int)($pRow['employee_id'] ?? 0);
                $dateRef = (string)($pRow['data_ref'] ?? '');
                if ($empId <= 0 || $dateRef === '') {
                    continue;
                }

                $key = $empId . '|' . $dateRef;
                if (isset($seenPresence[$key])) {
                    continue;
                }
                $seenPresence[$key] = true;

                $status = mb_strtolower(trim((string)($pRow['status'] ?? '')));
                $confirm = mb_strtolower(trim((string)($pRow['status_confirmacao'] ?? 'pendente')));
                $hasAnyData =
                    trim((string)($pRow['hora_entrada'] ?? '')) !== '' ||
                    trim((string)($pRow['hora_saida'] ?? '')) !== '' ||
                    $status !== '';

                if (!$hasAnyData) {
                    continue;
                }

                if ($status === 'invalidado') {
                    $presencasRejeitadasSolic++;
                    $solicitacoesHistoricoRejeitadas[] = [
                        'tipo' => 'Presença',
                        'funcionario' => (string)($pRow['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $pRow['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($pRow['data_ref'] ?? ''),
                        'detalhe' => 'Entrada: ' . (trim((string)($pRow['hora_entrada'] ?? '')) !== '' ? substr((string)$pRow['hora_entrada'], 0, 5) : '--:--') . ' | Saída: ' . (trim((string)($pRow['hora_saida'] ?? '')) !== '' ? substr((string)$pRow['hora_saida'], 0, 5) : '--:--'),
                        'status_label' => 'Rejeitada'
                    ];
                    continue;
                }

                if ($confirm === 'confirmado') {
                    $presencasAprovadasSolic++;
                    $solicitacoesHistoricoAprovadas[] = [
                        'tipo' => 'Presença',
                        'funcionario' => (string)($pRow['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $pRow['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($pRow['data_ref'] ?? ''),
                        'detalhe' => 'Entrada: ' . (trim((string)($pRow['hora_entrada'] ?? '')) !== '' ? substr((string)$pRow['hora_entrada'], 0, 5) : '--:--') . ' | Saída: ' . (trim((string)($pRow['hora_saida'] ?? '')) !== '' ? substr((string)$pRow['hora_saida'], 0, 5) : '--:--'),
                        'status_label' => 'Aprovada'
                    ];
                    continue;
                }

                $presencasPendentes[] = $pRow;
            }
        } catch (Throwable $e) {
            error_log('Erro ao carregar solicitações de presença: ' . $e->getMessage());
        }

        $trocasTurnoPendentes = [];
        $trocasTurnoAprovadasSolic = 0;
        $trocasTurnoRejeitadasSolic = 0;
        try {
            $solSwapSql = '';
            $solSwapParams = [(int)$loggedInClientId];
            if ($solServerStart !== '') {
                $solSwapSql .= ' AND DATE(r.requested_at) >= ?';
                $solSwapParams[] = $solServerStart;
            }
            if ($solServerEnd !== '') {
                $solSwapSql .= ' AND DATE(r.requested_at) <= ?';
                $solSwapParams[] = $solServerEnd;
            }

            $stmtSwapSolic = $pdo->prepare(
                "SELECT r.id, r.requested_date, r.reason, r.status, r.requested_at,
                        r.requester_employee_id, r.target_employee_id,
                        r.requester_turno_id, r.target_turno_id,
                        er.name AS requester_name, er.profile_picture AS requester_profile_picture,
                        et.name AS target_name, et.profile_picture AS target_profile_picture,
                        rt.turno_tipo AS requester_turno_tipo, rt.horario_inicio AS requester_horario_inicio, rt.horario_fim AS requester_horario_fim, rt.dias_semana AS requester_dias,
                        tt.turno_tipo AS target_turno_tipo, tt.horario_inicio AS target_horario_inicio, tt.horario_fim AS target_horario_fim, tt.dias_semana AS target_dias
                 FROM turno_swap_requests r
                 INNER JOIN employees er ON er.id = r.requester_employee_id
                 INNER JOIN employees et ON et.id = r.target_employee_id
                 LEFT JOIN turnos rt ON rt.id = r.requester_turno_id
                 LEFT JOIN turnos tt ON tt.id = r.target_turno_id
                 WHERE r.client_id = ? {$solSwapSql}
                 ORDER BY r.requested_at DESC, r.id DESC"
            );
            $stmtSwapSolic->execute($solSwapParams);
            $swapRows = $stmtSwapSolic->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($swapRows as $swapRow) {
                $swapStatus = mb_strtolower(trim((string)($swapRow['status'] ?? 'pendente_colega')));
                $requestDate = (string)($swapRow['requested_date'] ?? '');
                $detail = 'Troca: '
                    . (string)($swapRow['requester_turno_tipo'] ?? '-') . ' '
                    . substr((string)($swapRow['requester_horario_inicio'] ?? ''), 0, 5) . '-'
                    . substr((string)($swapRow['requester_horario_fim'] ?? ''), 0, 5)
                    . ' ↔ '
                    . (string)($swapRow['target_turno_tipo'] ?? '-') . ' '
                    . substr((string)($swapRow['target_horario_inicio'] ?? ''), 0, 5) . '-'
                    . substr((string)($swapRow['target_horario_fim'] ?? ''), 0, 5);
                if ($requestDate !== '') {
                    $detail .= ' | Data: ' . $requestDate;
                }

                if (in_array($swapStatus, ['aprovada', 'aprovado'], true)) {
                    $trocasTurnoAprovadasSolic++;
                    $solicitacoesHistoricoAprovadas[] = [
                        'tipo' => 'Troca Turno',
                        'funcionario' => (string)($swapRow['requester_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $swapRow['requester_profile_picture'] ?? '',
                        'data_ref' => (string)($swapRow['requested_at'] ?? ''),
                        'detalhe' => $detail,
                        'status_label' => 'Aprovada'
                    ];
                    continue;
                }

                if (in_array($swapStatus, ['rejeitada', 'rejeitado'], true)) {
                    $trocasTurnoRejeitadasSolic++;
                    $solicitacoesHistoricoRejeitadas[] = [
                        'tipo' => 'Troca Turno',
                        'funcionario' => (string)($swapRow['requester_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $swapRow['requester_profile_picture'] ?? '',
                        'data_ref' => (string)($swapRow['requested_at'] ?? ''),
                        'detalhe' => $detail,
                        'status_label' => 'Rejeitada'
                    ];
                    continue;
                }

                if (in_array($swapStatus, ['rejeitada_colega', 'rejeitado_colega'], true)) {
                    $trocasTurnoRejeitadasSolic++;
                    $solicitacoesHistoricoRejeitadas[] = [
                        'tipo' => 'Troca Turno',
                        'funcionario' => (string)($swapRow['requester_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $swapRow['requester_profile_picture'] ?? '',
                        'data_ref' => (string)($swapRow['requested_at'] ?? ''),
                        'detalhe' => $detail,
                        'status_label' => 'Rejeitada (colega)'
                    ];
                    continue;
                }

                if (in_array($swapStatus, ['pendente_admin', 'pendente'], true)) {
                    $trocasTurnoPendentes[] = $swapRow;
                }
            }
        } catch (Throwable $e) {
            error_log('Erro ao carregar solicitações de troca de turno: ' . $e->getMessage());
        }

        $gorjetasPendentes = [];
        $gorjetasAprovadasSolic = 0;
        $gorjetasRejeitadasSolic = 0;
        $feriasAprovadasSolic = 0;
        $feriasRejeitadasSolic = 0;
        try {
            $gorjetaDateColumn = 'data';
            try {
                $gorjetaCols = $pdo->query('SHOW COLUMNS FROM gorjetas')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                if (!in_array('data', $gorjetaCols, true) && in_array('data_registro', $gorjetaCols, true)) {
                    $gorjetaDateColumn = 'data_registro';
                }
            } catch (Throwable $e) {
            }

            $stmtSolicGor = $pdo->prepare(
                "SELECT g.id, g.funcionario_id AS employee_id, g.valor, g.status, g.forma_pagamento, g.origem,
                g.turno, DATE(g.{$gorjetaDateColumn}) AS data_ref, e.name AS employee_name, e.profile_picture AS employee_profile_picture
         FROM gorjetas g
         INNER JOIN employees e ON e.id = g.funcionario_id
         WHERE g.client_id = ?
         ORDER BY g.{$gorjetaDateColumn} DESC, g.id DESC"
            );
            $stmtSolicGor->execute([(int)$loggedInClientId]);
            $gorRows = $stmtSolicGor->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($gorRows as $gRow) {
                $status = mb_strtolower(trim((string)($gRow['status'] ?? 'pendente')));
                if (in_array($status, ['pago', 'paid', 'confirmado', 'aprovado'], true)) {
                    $gorjetasAprovadasSolic++;
                    $solicitacoesHistoricoAprovadas[] = [
                        'tipo' => 'Gorjeta',
                        'funcionario' => (string)($gRow['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $gRow['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($gRow['data_ref'] ?? ''),
                        'detalhe' => '€' . number_format((float)($gRow['valor'] ?? 0), 2, ',', '.') . ' | Turno: ' . (string)($gRow['turno'] ?? '-'),
                        'status_label' => 'Aprovada'
                    ];
                    continue;
                }
                if (in_array($status, ['rejeitado', 'rejeitada', 'cancelado', 'cancelada'], true)) {
                    $gorjetasRejeitadasSolic++;
                    $solicitacoesHistoricoRejeitadas[] = [
                        'tipo' => 'Gorjeta',
                        'funcionario' => (string)($gRow['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $gRow['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($gRow['data_ref'] ?? ''),
                        'detalhe' => '€' . number_format((float)($gRow['valor'] ?? 0), 2, ',', '.') . ' | Turno: ' . (string)($gRow['turno'] ?? '-'),
                        'status_label' => 'Rejeitada'
                    ];
                    continue;
                }
                $gorjetasPendentes[] = $gRow;
            }
        } catch (Throwable $e) {
            error_log('Erro ao carregar solicitações de gorjetas: ' . $e->getMessage());
        }

        try {
            $feriasColsHist = $pdo->query('SHOW COLUMNS FROM ferias')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $feriasColsHist = array_map(static fn($c) => mb_strtolower((string)$c), $feriasColsHist);
            $feriasEmployeeColHist = in_array('funcionario_id', $feriasColsHist, true)
                ? 'funcionario_id'
                : (in_array('employee_id', $feriasColsHist, true) ? 'employee_id' : 'funcionario_id');

            $stmtSolicFeriasHist = $pdo->prepare(
                "SELECT f.id, f.{$feriasEmployeeColHist} AS employee_id, f.data_inicio, f.data_fim, f.status, f.motivo,
                        e.name AS employee_name, e.profile_picture AS employee_profile_picture
                 FROM ferias f
                 INNER JOIN employees e ON e.id = f.{$feriasEmployeeColHist}
                 WHERE e.client_id = ?
                 ORDER BY f.id DESC"
            );
            $stmtSolicFeriasHist->execute([(int)$loggedInClientId]);
            $feriasRowsHist = $stmtSolicFeriasHist->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($feriasRowsHist as $fRowHist) {
                $fStatus = mb_strtolower(trim((string)($fRowHist['status'] ?? 'pendente')));
                if (in_array($fStatus, ['pendente', 'pending', ''], true)) {
                    continue;
                }

                $detalhe = 'Período: '
                    . (string)($fRowHist['data_inicio'] ?? '-')
                    . ' a '
                    . (string)($fRowHist['data_fim'] ?? '-');
                $motivoFerias = trim((string)($fRowHist['motivo'] ?? ''));
                if ($motivoFerias !== '') {
                    $detalhe .= ' | Motivo: ' . $motivoFerias;
                }

                if (in_array($fStatus, ['aprovada', 'aprovado'], true)) {
                    $feriasAprovadasSolic++;
                    $solicitacoesHistoricoAprovadas[] = [
                        'tipo' => 'Férias',
                        'funcionario' => (string)($fRowHist['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $fRowHist['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($fRowHist['data_inicio'] ?? ''),
                        'detalhe' => $detalhe,
                        'status_label' => 'Aprovada'
                    ];
                    continue;
                }

                if (in_array($fStatus, ['rejeitada', 'rejeitado', 'recusada', 'recusado'], true)) {
                    $feriasRejeitadasSolic++;
                    $solicitacoesHistoricoRejeitadas[] = [
                        'tipo' => 'Férias',
                        'funcionario' => (string)($fRowHist['employee_name'] ?? 'Funcionário'),
                        'employee_profile_picture' => $fRowHist['employee_profile_picture'] ?? '',
                        'data_ref' => (string)($fRowHist['data_inicio'] ?? ''),
                        'detalhe' => $detalhe,
                        'status_label' => 'Rejeitada'
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log('Erro ao carregar histórico de férias: ' . $e->getMessage());
        }

        usort($solicitacoesHistoricoAprovadas, function (array $a, array $b): int {
            $timeA = strtotime((string)($a['data_ref'] ?? '')) ?: 0;
            $timeB = strtotime((string)($b['data_ref'] ?? '')) ?: 0;
            return $timeB <=> $timeA;
        });

        usort($solicitacoesHistoricoRejeitadas, function (array $a, array $b): int {
            $timeA = strtotime((string)($a['data_ref'] ?? '')) ?: 0;
            $timeB = strtotime((string)($b['data_ref'] ?? '')) ?: 0;
            return $timeB <=> $timeA;
        });

        $solicitacoesHistoricoDecididas = array_merge($solicitacoesHistoricoAprovadas, $solicitacoesHistoricoRejeitadas);
        usort($solicitacoesHistoricoDecididas, function (array $a, array $b): int {
            $timeA = strtotime((string)($a['data_ref'] ?? '')) ?: 0;
            $timeB = strtotime((string)($b['data_ref'] ?? '')) ?: 0;
            return $timeB <=> $timeA;
        });

        $solicitacoesPendentesTotal = count($justificativasPendentes) + count($presencasPendentes) + count($gorjetasPendentes) + count($feriasPendentes) + count($trocasTurnoPendentes);
        $solicitacoesAprovadasTotal = $justificativasAprovadas + $presencasAprovadasSolic + $gorjetasAprovadasSolic + $feriasAprovadasSolic + $trocasTurnoAprovadasSolic;
        $solicitacoesRejeitadasTotal = $justificativasRejeitadas + $presencasRejeitadasSolic + $gorjetasRejeitadasSolic + $feriasRejeitadasSolic + $trocasTurnoRejeitadasSolic;
        $solicitacoesTotal =
            count($allJustificativas) +
            count($presencasPendentes) + $presencasAprovadasSolic + $presencasRejeitadasSolic +
            count($gorjetasPendentes) + $gorjetasAprovadasSolic + $gorjetasRejeitadasSolic +
            count($feriasPendentes) + $feriasAprovadasSolic + $feriasRejeitadasSolic +
            count($trocasTurnoPendentes) + $trocasTurnoAprovadasSolic + $trocasTurnoRejeitadasSolic;
        ?>























        <section id="solicitacoes-section" class="content-section">
            <?php
                $solReview = trim((string)($_GET['review'] ?? ''));
                $solPanelOpen = ($solServerStart !== '' || $solServerEnd !== '');
                $solTotalPendentes = count($justificativasPendentes) + count($presencasPendentes)
                    + count($gorjetasPendentes) + count($feriasPendentes) + count($trocasTurnoPendentes);
                $solTotalHistorico = (int)($solicitacoesAprovadasTotal + $solicitacoesRejeitadasTotal);
            ?>
            <?php if ($solReview === 'ok'): ?>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#14532d,#166534);color:#ecfdf5;">
                <i class="fas fa-check-circle"></i> Operação concluída com sucesso.
            </div>
            <?php elseif ($solReview === 'error'): ?>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#7f1d1d,#991b1b);color:#fef2f2;">
                <i class="fas fa-exclamation-circle"></i> Ocorreu um erro. Tente novamente.
            </div>
            <?php elseif ($solReview === 'csrf'): ?>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#7f1d1d,#991b1b);color:#fef2f2;">
                <i class="fas fa-shield-alt"></i> Sessão expirada. Recarregue a página.
            </div>
            <?php endif; ?>

            <div class="frhd">
                <div class="frhd-left">
                    <div class="frhd-icon" style="background:linear-gradient(135deg,#6366f1,#4f46e5);box-shadow:0 4px 14px rgba(99,102,241,.35);">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div>
                        <h2 class="frhd-title">Solicitações</h2>
                        <p class="frhd-sub"><?php echo (int)$solTotalPendentes; ?> pendente<?php echo $solTotalPendentes !== 1 ? 's' : ''; ?> &middot; <?php echo (int)$solTotalHistorico; ?> no histórico</p>
                    </div>
                </div>
                <button type="button" class="fr-filter-toggle <?php echo $solPanelOpen ? 'pa-filter-open' : ''; ?>" id="solFilterToggle"
                    onclick="document.getElementById('solAdvFilters').classList.toggle('fr-adv-open'); this.classList.toggle('pa-filter-open')">
                    <i class="fas fa-calendar-alt"></i> Período
                    <span class="fr-filter-badge" id="solFilterBadge" style="<?php echo $solPanelOpen ? 'display:flex' : 'display:none'; ?>">
                        <?php echo (int)(($solServerStart !== '') + ($solServerEnd !== '')); ?>
                    </span>
                </button>
            </div>

            <style>
            .sol-kpi-total .fr-kpi-icon  { background:rgba(99,102,241,.12); color:#818cf8; }
            .sol-kpi-justif .fr-kpi-icon { background:rgba(245,158,11,.12); color:#fbbf24; }
            .sol-kpi-pres .fr-kpi-icon   { background:rgba(59,130,246,.12);  color:#60a5fa; }
            .sol-kpi-gorj .fr-kpi-icon   { background:rgba(16,185,129,.12);  color:#34d399; }
            .sol-kpi-fer .fr-kpi-icon    { background:rgba(14,165,233,.12);  color:#38bdf8; }
            .sol-kpi-troca .fr-kpi-icon  { background:rgba(163,230,53,.1);   color:#a3e635; }
            @keyframes solicitacaoBadgeFloat {
                0%   { transform:translateY(0) scale(1); }
                50%  { transform:translateY(-6px) scale(1.08); }
                100% { transform:translateY(0) scale(1); }
            }
            </style>
            <div class="fr-kpi-strip" style="grid-template-columns:repeat(6,1fr);">
                <div class="fr-kpi sol-kpi-total" style="position:relative;">
                    <div class="fr-kpi-icon"><i class="fas fa-inbox"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo (int)$solTotalPendentes; ?></span>
                        <span class="fr-kpi-lbl">Total Pendentes</span>
                        <span class="fr-kpi-pct">aguardam decisão</span>
                    </div>
                    <?php if ($solTotalPendentes > 0): ?>
                    <span style="position:absolute;top:-6px;right:-6px;min-width:20px;height:20px;border-radius:50%;background:#ef4444;color:#fff;font-size:.65rem;font-weight:900;display:flex;align-items:center;justify-content:center;animation:solicitacaoBadgeFloat 1.5s ease-in-out infinite;"><?php echo (int)$solTotalPendentes; ?></span>
                    <?php endif; ?>
                </div>
                <div class="fr-kpi sol-kpi-justif">
                    <div class="fr-kpi-icon"><i class="fas fa-file-medical"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo count($justificativasPendentes); ?></span>
                        <span class="fr-kpi-lbl">Justificativas</span>
                        <span class="fr-kpi-pct">faltas &amp; atrasos</span>
                    </div>
                </div>
                <div class="fr-kpi sol-kpi-pres">
                    <div class="fr-kpi-icon"><i class="fas fa-user-check"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo count($presencasPendentes); ?></span>
                        <span class="fr-kpi-lbl">Presenças</span>
                        <span class="fr-kpi-pct">marcações manuais</span>
                    </div>
                </div>
                <div class="fr-kpi sol-kpi-gorj">
                    <div class="fr-kpi-icon"><i class="fas fa-coins"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo count($gorjetasPendentes); ?></span>
                        <span class="fr-kpi-lbl">Gorjetas</span>
                        <span class="fr-kpi-pct">aguardam validação</span>
                    </div>
                </div>
                <div class="fr-kpi sol-kpi-fer">
                    <div class="fr-kpi-icon"><i class="fas fa-umbrella-beach"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo count($feriasPendentes); ?></span>
                        <span class="fr-kpi-lbl">Férias</span>
                        <span class="fr-kpi-pct">pedidos de férias</span>
                    </div>
                </div>
                <div class="fr-kpi sol-kpi-troca">
                    <div class="fr-kpi-icon"><i class="fas fa-exchange-alt"></i></div>
                    <div class="fr-kpi-body">
                        <span class="fr-kpi-val"><?php echo count($trocasTurnoPendentes); ?></span>
                        <span class="fr-kpi-lbl">Trocas</span>
                        <span class="fr-kpi-pct">aguardam admin</span>
                    </div>
                </div>
            </div>

            <div class="data-table fr-table-wrap" style="margin-top:.5rem;">
                <div class="fr-toolbar">
                    <div class="fr-toolbar-top">
                        <div class="fr-search-wrap">
                            <i class="fas fa-search fr-search-icon"></i>
                            <input type="text" id="solSearchInput" class="fr-search" placeholder="Pesquisar funcionário…">
                        </div>
                        <div class="fr-toolbar-right">
                            <span id="solResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                        </div>
                    </div>
                    <div class="fr-chips">
                        <button class="fr-chip sol-chip-all active" data-sol-chip="" onclick="applySolChip(this)">
                            <i class="fas fa-th-large"></i> Pendentes
                            <span class="fr-chip-count"><?php echo (int)$solTotalPendentes; ?></span>
                        </button>
                        <button class="fr-chip sol-chip-justif" data-sol-chip="justificativa" onclick="applySolChip(this)">
                            <span class="fr-dot" style="background:#fbbf24;"></span> Justificativas
                            <span class="fr-chip-count"><?php echo count($justificativasPendentes); ?></span>
                        </button>
                        <button class="fr-chip sol-chip-pres" data-sol-chip="presenca" onclick="applySolChip(this)">
                            <span class="fr-dot fr-dot-blue"></span> Presenças
                            <span class="fr-chip-count"><?php echo count($presencasPendentes); ?></span>
                        </button>
                        <button class="fr-chip sol-chip-gorj" data-sol-chip="gorjeta" onclick="applySolChip(this)">
                            <span class="fr-dot fr-dot-green"></span> Gorjetas
                            <span class="fr-chip-count"><?php echo count($gorjetasPendentes); ?></span>
                        </button>
                        <button class="fr-chip sol-chip-fer" data-sol-chip="ferias" onclick="applySolChip(this)">
                            <span class="fr-dot" style="background:#38bdf8;"></span> Férias
                            <span class="fr-chip-count"><?php echo count($feriasPendentes); ?></span>
                        </button>
                        <button class="fr-chip sol-chip-troca" data-sol-chip="troca" onclick="applySolChip(this)">
                            <span class="fr-dot" style="background:#a3e635;"></span> Trocas
                            <span class="fr-chip-count"><?php echo count($trocasTurnoPendentes); ?></span>
                        </button>
                        <button class="fr-chip sol-chip-hist" data-sol-chip="historico" onclick="applySolChip(this)">
                            <span class="fr-dot" style="background:#64748b;"></span> Histórico
                            <span class="fr-chip-count"><?php echo (int)$solTotalHistorico; ?></span>
                        </button>
                    </div>
                    <div class="fr-adv-filters <?php echo $solPanelOpen ? 'fr-adv-open' : ''; ?>" id="solAdvFilters">
                        <input type="date" id="filterSolStart" class="fr-select" style="min-width:160px;"
                            title="Data inicial" value="<?php echo htmlspecialchars($solServerStart); ?>">
                        <input type="date" id="filterSolEnd" class="fr-select" style="min-width:160px;"
                            title="Data final" value="<?php echo htmlspecialchars($solServerEnd); ?>">
                        <button type="button" onclick="applySolicitacoesServerFilter()"
                            style="padding:.5rem 1rem;white-space:nowrap;background:linear-gradient(145deg,#3b82f6,#2563eb);color:#fff;border:none;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;">
                            <i class="fas fa-database"></i> Aplicar período
                        </button>
                        <button type="button" class="fr-clear-btn" onclick="clearSolicitacoesServerFilter()">
                            <i class="fas fa-eraser"></i> Limpar
                        </button>
                        <?php if ($solPanelOpen): ?>
                        <span style="font-size:.82rem;color:var(--text-secondary);background:var(--bg-tertiary);border:1px solid var(--border-primary);border-radius:8px;padding:.35rem .65rem;white-space:nowrap;">
                            <i class="fas fa-filter" style="margin-right:.3rem;"></i>
                            <?php
                            if ($solServerStart !== '' && $solServerEnd !== '') {
                                echo htmlspecialchars(date('d/m/Y', strtotime($solServerStart))) . ' – ' . htmlspecialchars(date('d/m/Y', strtotime($solServerEnd)));
                            } elseif ($solServerStart !== '') {
                                echo 'desde ' . htmlspecialchars(date('d/m/Y', strtotime($solServerStart)));
                            } else {
                                echo 'até ' . htmlspecialchars(date('d/m/Y', strtotime($solServerEnd)));
                            }
                            ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <style>
                .sol-chip-all.active     { background:rgba(99,102,241,.2);  color:#a5b4fc; border-color:rgba(99,102,241,.35); }
                .sol-chip-justif.active  { background:rgba(245,158,11,.2);  color:#fbbf24; border-color:rgba(245,158,11,.35); }
                .sol-chip-pres.active    { background:rgba(59,130,246,.2);  color:#60a5fa; border-color:rgba(59,130,246,.35); }
                .sol-chip-gorj.active    { background:rgba(16,185,129,.2);  color:#34d399; border-color:rgba(16,185,129,.35); }
                .sol-chip-fer.active     { background:rgba(14,165,233,.2);  color:#38bdf8; border-color:rgba(14,165,233,.35); }
                .sol-chip-troca.active   { background:rgba(163,230,53,.1);  color:#a3e635; border-color:rgba(163,230,53,.25); }
                .sol-chip-hist.active    { background:rgba(100,116,139,.18); color:#94a3b8; border-color:rgba(100,116,139,.3); }
                .sol-tipo-badge { display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:6px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em; }
                .sol-tipo-justif { background:rgba(245,158,11,.15); color:#fbbf24; }
                .sol-tipo-pres   { background:rgba(59,130,246,.15); color:#93c5fd; }
                .sol-tipo-gorj   { background:rgba(16,185,129,.15); color:#6ee7b7; }
                .sol-tipo-fer    { background:rgba(14,165,233,.15); color:#7dd3fc; }
                .sol-tipo-troca  { background:rgba(163,230,53,.1);  color:#bef264; }
                </style>

                <table class="table fr-table" id="solMainTable">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-emp">Funcionário</th>
                            <th>Tipo</th>
                            <th>Data</th>
                            <th>Detalhe</th>
                            <th class="fr-th-status">Estado</th>
                            <th class="fr-th-acts">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="solMainTableBody">
                        <?php if ($solTotalPendentes === 0): ?>
                        <tr id="solEmptyState">
                            <td colspan="6" style="text-align:center;color:var(--text-secondary);padding:3rem 1rem;">
                                <i class="fas fa-inbox" style="font-size:2.5rem;opacity:.2;display:block;margin-bottom:.75rem;"></i>
                                Sem solicitações pendentes. Tudo em dia!
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php foreach ($justificativasPendentes as $pend):
                            $pendDataFmt = !empty($pend['data_ocorrencia']) ? date('d/m/Y', strtotime((string)$pend['data_ocorrencia'])) : 'N/D';
                            $pendTipoRaw = mb_strtolower(trim((string)($pend['tipo'] ?? 'falta')));
                            $tiposLabelMap = [
                                'falta'=>'Falta','atraso'=>'Atraso','doenca'=>'Doença',
                                'consulta_medica'=>'Consulta Médica','assistencia_familiar'=>'Assist. Familiar',
                                'falecimento_familiar'=>'Falecimento','casamento'=>'Casamento',
                                'maternidade_paternidade'=>'Maternidade/Pat.','formacao_profissional'=>'Formação',
                                'convocacao_judicial'=>'Conv. Judicial','acidente'=>'Acidente',
                                'transporte'=>'Transporte','motivo_pessoal'=>'Motivo Pessoal','outro'=>'Outro',
                            ];
                            $pendTipoLabel = $tiposLabelMap[$pendTipoRaw] ?? ucfirst(str_replace('_', ' ', $pendTipoRaw));
                            $pendMotivo = trim((string)($pend['motivo'] ?? ''));
                            $pendAnexo  = trim((string)($pend['anexo_path'] ?? ''));
                            $jEmpName   = (string)($pend['employee_name'] ?? 'Funcionário');
                            $jEmpPic    = (string)($pend['employee_profile_picture'] ?? '');
                            $jInitials  = strtoupper(mb_substr($jEmpName, 0, 2));
                        ?>
                        <tr class="fr-row" data-sol-tipo="justificativa" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($jEmpName)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                                        <?php if ($jEmpPic !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($jEmpPic); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $jInitials; ?>'; this.remove();">
                                        <?php else: echo $jInitials; endif; ?>
                                    </div>
                                    <span class="fr-emp-name"><?php echo htmlspecialchars($jEmpName); ?></span>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge sol-tipo-justif"><i class="fas fa-file-medical"></i> <?php echo htmlspecialchars($pendTipoLabel); ?></span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($pendDataFmt); ?></td>
                            <td class="fr-td-role" style="max-width:220px;">
                                <?php if ($pendMotivo !== ''): ?>
                                <span style="font-size:.8rem;color:var(--text-secondary);"><?php echo htmlspecialchars(mb_substr($pendMotivo,0,60)).(mb_strlen($pendMotivo)>60?'…':''); ?></span>
                                <?php else: ?><span style="color:#475569;">—</span><?php endif; ?>
                                <?php if ($pendAnexo !== ''): ?>
                                <a href="../<?php echo htmlspecialchars($pendAnexo); ?>" target="_blank" rel="noopener noreferrer"
                                    class="fr-btn" style="margin-left:4px;font-size:.65rem;padding:1px 6px;" title="Ver anexo"><i class="fas fa-paperclip"></i></a>
                                <?php endif; ?>
                            </td>
                            <td class="fr-td-status"><span class="status-badge status-warning">Pendente</span></td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <form method="POST" action="../api/employees/review_justificativa.php" style="display:contents;">
                                        <input type="hidden" name="justificativa_id" value="<?php echo (int)$pend['id']; ?>">
                                        <input type="hidden" name="decision" value="aprovar">
                                        <input type="hidden" name="return_url" value="../../admin/dashboard.php?section=solicitacoes&solicitacao_card=justificativas">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" action="../api/employees/review_justificativa.php" style="display:contents;">
                                        <input type="hidden" name="justificativa_id" value="<?php echo (int)$pend['id']; ?>">
                                        <input type="hidden" name="decision" value="rejeitar">
                                        <input type="hidden" name="return_url" value="../../admin/dashboard.php?section=solicitacoes&solicitacao_card=justificativas">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-deact" title="Rejeitar"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php foreach ($presencasPendentes as $pSolic):
                            $pDataFmt  = !empty($pSolic['data_ref']) ? date('d/m/Y', strtotime((string)$pSolic['data_ref'])) : 'N/D';
                            $pEntrada  = trim((string)($pSolic['hora_entrada'] ?? '')) !== '' ? substr((string)$pSolic['hora_entrada'], 0, 5) : '--:--';
                            $pSaida    = trim((string)($pSolic['hora_saida']   ?? '')) !== '' ? substr((string)$pSolic['hora_saida'],   0, 5) : '--:--';
                            $pEmpName  = (string)($pSolic['employee_name'] ?? 'Funcionário');
                            $pEmpPic   = (string)($pSolic['employee_profile_picture'] ?? '');
                            $pInitials = strtoupper(mb_substr($pEmpName, 0, 2));
                        ?>
                        <tr class="fr-row" data-sol-tipo="presenca" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($pEmpName)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">
                                        <?php if ($pEmpPic !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($pEmpPic); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $pInitials; ?>'; this.remove();">
                                        <?php else: echo $pInitials; endif; ?>
                                    </div>
                                    <span class="fr-emp-name"><?php echo htmlspecialchars($pEmpName); ?></span>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge sol-tipo-pres"><i class="fas fa-user-check"></i> Presença</span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($pDataFmt); ?></td>
                            <td class="fr-td-role"><span style="font-size:.8rem;color:var(--text-secondary);">Entrada <?php echo htmlspecialchars($pEntrada); ?> · Saída <?php echo htmlspecialchars($pSaida); ?></span></td>
                            <td class="fr-td-status"><span class="status-badge status-warning">Pendente</span></td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="approve_presence_request">
                                        <input type="hidden" name="solicitacao_card" value="presenca">
                                        <input type="hidden" name="employee_id" value="<?php echo (int)$pSolic['employee_id']; ?>">
                                        <input type="hidden" name="target_date" value="<?php echo htmlspecialchars((string)$pSolic['data_ref']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="reject_presence_request">
                                        <input type="hidden" name="solicitacao_card" value="presenca">
                                        <input type="hidden" name="employee_id" value="<?php echo (int)$pSolic['employee_id']; ?>">
                                        <input type="hidden" name="target_date" value="<?php echo htmlspecialchars((string)$pSolic['data_ref']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-deact" title="Rejeitar"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php foreach ($gorjetasPendentes as $gSolic):
                            $gDataFmt  = !empty($gSolic['data_ref']) ? date('d/m/Y', strtotime((string)$gSolic['data_ref'])) : 'N/D';
                            $gEmpName  = (string)($gSolic['employee_name'] ?? 'Funcionário');
                            $gEmpPic   = (string)($gSolic['employee_profile_picture'] ?? '');
                            $gInitials = strtoupper(mb_substr($gEmpName, 0, 2));
                        ?>
                        <tr class="fr-row" data-sol-tipo="gorjeta" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($gEmpName)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#10b981,#059669);">
                                        <?php if ($gEmpPic !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($gEmpPic); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $gInitials; ?>'; this.remove();">
                                        <?php else: echo $gInitials; endif; ?>
                                    </div>
                                    <span class="fr-emp-name"><?php echo htmlspecialchars($gEmpName); ?></span>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge sol-tipo-gorj"><i class="fas fa-coins"></i> Gorjeta</span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($gDataFmt); ?></td>
                            <td class="fr-td-role"><span style="font-size:.8rem;color:var(--text-secondary);">€<?php echo number_format((float)($gSolic['valor']??0),2,',','.'); ?> · <?php echo htmlspecialchars((string)($gSolic['turno']??'-')); ?></span></td>
                            <td class="fr-td-status"><span class="status-badge status-warning">Pendente</span></td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="approve_gorjeta_request">
                                        <input type="hidden" name="solicitacao_card" value="gorjetas">
                                        <input type="hidden" name="gorjeta_id" value="<?php echo (int)$gSolic['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="reject_gorjeta_request">
                                        <input type="hidden" name="solicitacao_card" value="gorjetas">
                                        <input type="hidden" name="gorjeta_id" value="<?php echo (int)$gSolic['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-deact" title="Rejeitar"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php foreach ($feriasPendentes as $fSolic):
                            $fDataInicio = !empty($fSolic['data_inicio']) ? date('d/m/Y', strtotime((string)$fSolic['data_inicio'])) : 'N/D';
                            $fDataFim    = !empty($fSolic['data_fim'])    ? date('d/m/Y', strtotime((string)$fSolic['data_fim']))    : 'N/D';
                            $fMotivo     = trim((string)($fSolic['motivo'] ?? ''));
                            $fEmpName    = (string)($fSolic['employee_name'] ?? 'Funcionário');
                            $fEmpPic     = (string)($fSolic['employee_profile_picture'] ?? '');
                            $fInitials   = strtoupper(mb_substr($fEmpName, 0, 2));
                            $fDias       = (!empty($fSolic['data_inicio']) && !empty($fSolic['data_fim']))
                                ? max(0, (int)(strtotime($fSolic['data_fim']) - strtotime($fSolic['data_inicio'])) / 86400 + 1) : 0;
                        ?>
                        <tr class="fr-row" data-sol-tipo="ferias" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($fEmpName)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);">
                                        <?php if ($fEmpPic !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($fEmpPic); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $fInitials; ?>'; this.remove();">
                                        <?php else: echo $fInitials; endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo htmlspecialchars($fEmpName); ?></span>
                                        <?php if ($fDias > 0): ?><span class="fr-emp-email"><?php echo $fDias; ?> dia<?php echo $fDias !== 1 ? 's' : ''; ?></span><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge sol-tipo-fer"><i class="fas fa-umbrella-beach"></i> Férias</span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($fDataInicio); ?> – <?php echo htmlspecialchars($fDataFim); ?></td>
                            <td class="fr-td-role"><span style="font-size:.8rem;color:var(--text-secondary);"><?php echo $fMotivo !== '' ? htmlspecialchars(mb_substr($fMotivo,0,50)).(mb_strlen($fMotivo)>50?'…':'') : '—'; ?></span></td>
                            <td class="fr-td-status"><span class="status-badge status-warning">Pendente</span></td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="approve_ferias_request">
                                        <input type="hidden" name="solicitacao_card" value="ferias">
                                        <input type="hidden" name="ferias_id" value="<?php echo (int)$fSolic['id']; ?>">
                                        <input type="hidden" name="from_section" value="solicitacoes">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar férias"><i class="fas fa-check"></i></button>
                                    </form>
                                    <button type="button" class="fr-btn fr-btn-deact" title="Rejeitar férias"
                                        onclick="openSolFeriasRejectPrompt(<?php echo (int)$fSolic['id']; ?>, '<?php echo htmlspecialchars(addslashes($fEmpName)); ?>')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php foreach ($trocasTurnoPendentes as $swapSolic):
                            $swapDate      = !empty($swapSolic['requested_date']) ? date('d/m/Y', strtotime((string)$swapSolic['requested_date'])) : '-';
                            $swapReason    = trim((string)($swapSolic['reason'] ?? ''));
                            $swapRequester = (string)($swapSolic['requester_name'] ?? 'Funcionário');
                            $swapTarget    = (string)($swapSolic['target_name'] ?? 'Colega');
                            $swapReqPic    = (string)($swapSolic['requester_profile_picture'] ?? '');
                            $swapInitials  = strtoupper(mb_substr($swapRequester, 0, 2));
                            $swapReqTurno  = trim((string)($swapSolic['requester_turno_tipo'] ?? '-')).' '.substr((string)($swapSolic['requester_horario_inicio']??''),0,5).'-'.substr((string)($swapSolic['requester_horario_fim']??''),0,5);
                            $swapTgtTurno  = trim((string)($swapSolic['target_turno_tipo'] ?? '-')).' '.substr((string)($swapSolic['target_horario_inicio']??''),0,5).'-'.substr((string)($swapSolic['target_horario_fim']??''),0,5);
                        ?>
                        <tr class="fr-row" data-sol-tipo="troca" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($swapRequester)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#65a30d,#4d7c0f);">
                                        <?php if ($swapReqPic !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($swapReqPic); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $swapInitials; ?>'; this.remove();">
                                        <?php else: echo $swapInitials; endif; ?>
                                    </div>
                                    <div class="fr-emp-info">
                                        <span class="fr-emp-name"><?php echo htmlspecialchars($swapRequester); ?></span>
                                        <span class="fr-emp-email">↔ <?php echo htmlspecialchars($swapTarget); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge sol-tipo-troca"><i class="fas fa-exchange-alt"></i> Troca</span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($swapDate); ?></td>
                            <td class="fr-td-role" style="max-width:200px;"><span style="font-size:.78rem;color:var(--text-secondary);"><?php echo htmlspecialchars(trim($swapReqTurno)); ?> → <?php echo htmlspecialchars(trim($swapTgtTurno)); ?><?php if ($swapReason !== '') { echo ' · '.htmlspecialchars(mb_substr($swapReason,0,40)); } ?></span></td>
                            <td class="fr-td-status"><span class="status-badge status-warning">Pendente admin</span></td>
                            <td class="fr-td-acts">
                                <div class="fr-acts">
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="approve_turno_swap_request">
                                        <input type="hidden" name="solicitacao_card" value="trocas_turno">
                                        <input type="hidden" name="turno_swap_request_id" value="<?php echo (int)$swapSolic['id']; ?>">
                                        <input type="hidden" name="review_note" value="Aprovada no painel de solicitações.">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar troca"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" action="dashboard.php?section=solicitacoes" style="display:contents;">
                                        <input type="hidden" name="action" value="reject_turno_swap_request">
                                        <input type="hidden" name="solicitacao_card" value="trocas_turno">
                                        <input type="hidden" name="turno_swap_request_id" value="<?php echo (int)$swapSolic['id']; ?>">
                                        <input type="hidden" name="review_note" value="Rejeitada no painel de solicitações.">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <button type="submit" class="fr-btn fr-btn-deact" title="Rejeitar troca"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>

                <table class="table fr-table" id="solHistoricoTable" style="display:none;">
                    <thead>
                        <tr class="fr-thead-row">
                            <th class="fr-th-emp">Funcionário</th>
                            <th>Tipo</th>
                            <th>Data</th>
                            <th>Detalhe</th>
                            <th class="fr-th-status">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($solicitacoesHistoricoDecididas)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;color:var(--text-secondary);padding:3rem 1rem;">
                                <i class="fas fa-history" style="font-size:2.5rem;opacity:.2;display:block;margin-bottom:.75rem;"></i>
                                Sem registos no histórico.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($solicitacoesHistoricoDecididas as $emp):
                            $hStatusLabel = (string)($emp['status_label'] ?? '');
                            $hStatusLow   = mb_strtolower($hStatusLabel);
                            $hStatusClass = 'status-falta';
                            if (in_array($hStatusLow, ['agendado','agendada'], true))              { $hStatusClass = 'status-warning'; }
                            elseif (in_array($hStatusLow, ['aprovada','em curso','pago','paga'], true)) { $hStatusClass = 'status-presente'; }
                            elseif (in_array($hStatusLow, ['terminada','concluida','concluída'], true)) { $hStatusClass = 'status-nao-marcado'; }
                            $hFoto = !empty($emp['employee_profile_picture']) ? $emp['employee_profile_picture'] : ($emp['profile_picture'] ?? '');
                            $hNome = !empty($emp['employee_name']) ? $emp['employee_name'] : ($emp['name'] ?? ($emp['funcionario'] ?? 'Funcionário'));
                            $hInitials = strtoupper(mb_substr($hNome, 0, 2));
                            $hTipoRaw  = mb_strtolower(trim((string)($emp['tipo'] ?? '-')));
                            $hTipoBadgeClass = 'sol-tipo-justif'; $hTipoIcon = 'fa-file-alt';
                            if (str_contains($hTipoRaw,'gorjet'))                               { $hTipoBadgeClass='sol-tipo-gorj';  $hTipoIcon='fa-coins'; }
                            elseif (str_contains($hTipoRaw,'feria')||str_contains($hTipoRaw,'féria')) { $hTipoBadgeClass='sol-tipo-fer'; $hTipoIcon='fa-umbrella-beach'; }
                            elseif (str_contains($hTipoRaw,'presen'))                           { $hTipoBadgeClass='sol-tipo-pres';  $hTipoIcon='fa-user-check'; }
                            elseif (str_contains($hTipoRaw,'troca')||str_contains($hTipoRaw,'turno')) { $hTipoBadgeClass='sol-tipo-troca'; $hTipoIcon='fa-exchange-alt'; }
                            $hDataFmt = (string)($emp['data_ref'] ?? '');
                            $hDataFmt = $hDataFmt !== '' ? date('d/m/Y', strtotime($hDataFmt)) : '-';
                        ?>
                        <tr class="fr-row" data-sol-hist-tipo="<?php echo htmlspecialchars($hTipoRaw); ?>" data-sol-nome="<?php echo htmlspecialchars(mb_strtolower($hNome)); ?>">
                            <td class="fr-td-emp">
                                <div class="fr-emp-cell">
                                    <div class="fr-av" style="background:linear-gradient(135deg,#667eea,#764ba2);">
                                        <?php if ($hFoto !== ''): ?>
                                        <img class="fr-av-img" src="../<?php echo htmlspecialchars($hFoto); ?>" alt=""
                                            onerror="this.parentElement.textContent='<?php echo $hInitials; ?>'; this.remove();">
                                        <?php else: echo $hInitials; endif; ?>
                                    </div>
                                    <span class="fr-emp-name"><?php echo htmlspecialchars($hNome); ?></span>
                                </div>
                            </td>
                            <td><span class="sol-tipo-badge <?php echo $hTipoBadgeClass; ?>"><i class="fas <?php echo $hTipoIcon; ?>"></i> <?php echo htmlspecialchars((string)($emp['tipo'] ?? '-')); ?></span></td>
                            <td class="fr-td-role"><?php echo htmlspecialchars($hDataFmt); ?></td>
                            <td class="fr-td-role"><span style="font-size:.8rem;color:var(--text-secondary);"><?php echo htmlspecialchars(mb_substr((string)($emp['detalhe'] ?? '-'), 0, 60)); ?></span></td>
                            <td class="fr-td-status"><span class="status-badge <?php echo $hStatusClass; ?>"><?php echo htmlspecialchars($hStatusLabel !== '' ? $hStatusLabel : '-'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div id="solHistoricoToolbar" style="display:none;margin-top:.75rem;gap:.5rem;flex-wrap:wrap;align-items:center;">
                    <select id="solHistoricoFiltroTipo" class="fr-select" style="min-width:190px;">
                        <option value="">Todos os tipos</option>
                        <option value="férias">Férias</option>
                        <option value="gorjeta">Gorjeta</option>
                        <option value="presença">Presença</option>
                        <option value="troca turno">Troca de Turno</option>
                        <option value="justificativa">Justificativa</option>
                    </select>
                    <button type="button" id="btnExportarHistorico" class="fr-filter-toggle">
                        <i class="fas fa-file-export"></i> Exportar
                    </button>
                    <button type="button" id="btnLimparHistorico" class="fr-clear-btn">
                        <i class="fas fa-trash-alt"></i> Limpar histórico
                    </button>
                </div>
            </div>

            <form method="POST" action="dashboard.php?section=solicitacoes" id="solFeriasRejectForm" style="display:none;">
                <input type="hidden" name="action" value="reject_ferias_request">
                <input type="hidden" name="solicitacao_card" value="ferias">
                <input type="hidden" name="ferias_id" id="solFeriasRejectId" value="">
                <input type="hidden" name="motivo_rejeicao" id="solFeriasRejectMotivo" value="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            </form>

            <script>
            function openSolFeriasRejectPrompt(feriasId, empName) {
                Swal.fire({
                    title: 'Rejeitar férias',
                    html: 'Funcionário: <strong>' + escSol(empName) + '</strong>',
                    input: 'textarea',
                    inputPlaceholder: 'Opcional — indique o motivo da rejeição…',
                    showCancelButton: true,
                    confirmButtonText: 'Rejeitar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#ef4444',
                    inputAttributes: { maxlength: 500 },
                    background: 'var(--card-bg, #1e293b)',
                    color: 'var(--text-primary, #f1f5f9)'
                }).then(function(result) {
                    if (!result.isConfirmed) return;
                    document.getElementById('solFeriasRejectId').value = feriasId;
                    document.getElementById('solFeriasRejectMotivo').value = result.value || '';
                    document.getElementById('solFeriasRejectForm').submit();
                });
            }
            function escSol(v) {
                return String(v || '').replace(/[&<>"']/g, function(c) {
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
                });
            }

            (function initSolicitacoes() {
                var searchInput = document.getElementById('solSearchInput');
                var resultCount = document.getElementById('solResultCount');
                var mainTable   = document.getElementById('solMainTable');
                var histTable   = document.getElementById('solHistoricoTable');
                var histToolbar = document.getElementById('solHistoricoToolbar');
                var histFiltro  = document.getElementById('solHistoricoFiltroTipo');
                var chips       = document.querySelectorAll('[data-sol-chip]');
                var currentChip = '';

                function getMainRows() { return mainTable ? Array.from(mainTable.querySelectorAll('tr.fr-row[data-sol-tipo]')) : []; }
                function getHistRows()  { return histTable  ? Array.from(histTable.querySelectorAll('tr.fr-row[data-sol-hist-tipo]')) : []; }

                function updateCount(vis, tot) {
                    if (resultCount) resultCount.textContent = vis < tot ? vis + ' de ' + tot : tot + ' resultado' + (tot !== 1 ? 's' : '');
                }

                function applyMainFilters() {
                    var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
                    var rows = getMainRows(); var vis = 0;
                    rows.forEach(function(row) {
                        var show = (currentChip === '' || row.getAttribute('data-sol-tipo') === currentChip)
                            && (q === '' || (row.getAttribute('data-sol-nome') || '').includes(q));
                        row.style.display = show ? '' : 'none';
                        if (show) vis++;
                    });
                    var emptyRow = document.getElementById('solEmptyState');
                    if (emptyRow) emptyRow.style.display = (vis === 0 && rows.length > 0) ? '' : 'none';
                    updateCount(vis, rows.length);
                }

                function applyHistFilters() {
                    var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
                    var tf = histFiltro ? histFiltro.value.toLowerCase() : '';
                    var rows = getHistRows(); var vis = 0;
                    rows.forEach(function(row) {
                        var show = (tf === '' || (row.getAttribute('data-sol-hist-tipo') || '') === tf)
                            && (q === '' || (row.getAttribute('data-sol-nome') || '').includes(q));
                        row.style.display = show ? '' : 'none';
                        if (show) vis++;
                    });
                    updateCount(vis, rows.length);
                }

                window.applySolChip = function(chipBtn) {
                    chips.forEach(function(c) { c.classList.remove('active'); });
                    chipBtn.classList.add('active');
                    currentChip = chipBtn.getAttribute('data-sol-chip') || '';
                    var isHist = currentChip === 'historico';
                    if (mainTable) mainTable.style.display = isHist ? 'none' : '';
                    if (histTable) histTable.style.display = isHist ? '' : 'none';
                    if (histToolbar) histToolbar.style.display = isHist ? 'flex' : 'none';
                    isHist ? applyHistFilters() : applyMainFilters();
                };

                if (searchInput) searchInput.addEventListener('input', function() { currentChip === 'historico' ? applyHistFilters() : applyMainFilters(); });
                if (histFiltro)  histFiltro.addEventListener('change', applyHistFilters);

                (function restoreChipFromQuery() {
                    var params = new URLSearchParams(window.location.search);
                    if (params.get('section') !== 'solicitacoes') return;
                    var cardMap = { justificativas:'justificativa', presenca:'presenca', gorjetas:'gorjeta', ferias:'ferias', trocas_turno:'troca', historico:'historico' };
                    var chipVal = cardMap[params.get('solicitacao_card') || ''] || '';
                    if (chipVal) { var t = document.querySelector('[data-sol-chip="' + chipVal + '"]'); if (t) window.applySolChip(t); }
                })();

                (function persistScroll() {
                    var KEY = 'dashboard_solicitacoes_scroll_y';
                    var params = new URLSearchParams(window.location.search);
                    if (params.get('section') === 'solicitacoes') {
                        var y = parseInt(sessionStorage.getItem(KEY) || '', 10);
                        if (!isNaN(y) && y >= 0) {
                            requestAnimationFrame(function() { requestAnimationFrame(function() { window.scrollTo(0, y); sessionStorage.removeItem(KEY); }); });
                        }
                    }
                    document.querySelectorAll('form[action*="section=solicitacoes"], form[action*="review_justificativa.php"]').forEach(function(form) {
                        form.addEventListener('submit', function() { sessionStorage.setItem(KEY, String(window.scrollY || 0)); });
                    });
                })();

                applyMainFilters();
            })();
            </script>
        </section>
