<?php
// Secção "Férias" — incluída a partir de admin/dashboard.php (depende de $pdo, $employees, $loggedInClientId, $csrfToken, etc. já definidos lá).
?>
       <section id="ferias-section" class="content-section">
    <?php
    $feriasAll = [];
    $feriasPendentesCount = 0;
    $feriasAgendadasCount = 0;
    $feriasEmCursoCount = 0;
    $feriasConcluidasCount = 0;
    $feriasRejeitadasCount = 0;
    $feriasCanceladasCount = 0;
    $todayDate = date('Y-m-d');

    try {
        $feriasSectionCols = $pdo->query('SHOW COLUMNS FROM ferias')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $feriasSectionCols = array_map(static fn($c) => mb_strtolower((string)$c), $feriasSectionCols);
        $feriasSectionEmployeeCol = in_array('funcionario_id', $feriasSectionCols, true)
            ? 'funcionario_id'
            : (in_array('employee_id', $feriasSectionCols, true) ? 'employee_id' : 'funcionario_id');

        $hasMotivoRejeicao = in_array('motivo_rejeicao', $feriasSectionCols, true);
        $motRejeicaoSel = $hasMotivoRejeicao ? ', f.motivo_rejeicao' : '';

        $stmtFeriasSection = $pdo->prepare("SELECT f.id, f.{$feriasSectionEmployeeCol} AS employee_id,
                    f.data_inicio, f.data_fim, f.status, f.motivo{$motRejeicaoSel},
                    e.name AS employee_name, e.profile_picture AS employee_profile_picture, e.position AS employee_position
             FROM ferias f
             INNER JOIN employees e ON e.id = f.{$feriasSectionEmployeeCol}
             WHERE e.client_id = ?
             ORDER BY FIELD(f.status,'pendente','aprovada','rejeitada','cancelada'), f.data_inicio DESC, f.id DESC");
        $stmtFeriasSection->execute([(int)$loggedInClientId]);
        $feriasAll = $stmtFeriasSection->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Aviso de conflito de equipa: marca cada pedido ativo (pendente/aprovada) que se
        // sobrepõe a outro pedido ativo de um colega com a mesma função (cargo).
        $feriasAtivosPorPosicao = [];
        foreach ($feriasAll as $fc) {
            $fcStatus = mb_strtolower(trim((string)($fc['status'] ?? '')));
            if ($fcStatus === 'aprovado') $fcStatus = 'aprovada';
            if (!in_array($fcStatus, ['pendente', 'pending', 'aprovada'], true)) continue;
            $fcPos = trim((string)($fc['employee_position'] ?? ''));
            if ($fcPos === '') continue;
            $feriasAtivosPorPosicao[$fcPos][] = $fc;
        }
        foreach ($feriasAll as &$fc) {
            $fc['conflito_count'] = 0;
            $fcStatus = mb_strtolower(trim((string)($fc['status'] ?? '')));
            if ($fcStatus === 'aprovado') $fcStatus = 'aprovada';
            if (!in_array($fcStatus, ['pendente', 'pending', 'aprovada'], true)) continue;
            $fcPos = trim((string)($fc['employee_position'] ?? ''));
            if ($fcPos === '' || empty($feriasAtivosPorPosicao[$fcPos])) continue;
            $n = 0;
            foreach ($feriasAtivosPorPosicao[$fcPos] as $other) {
                if ((int)$other['id'] === (int)$fc['id']) continue;
                if ((int)$other['employee_id'] === (int)$fc['employee_id']) continue;
                if ((string)$other['data_inicio'] <= (string)$fc['data_fim'] && (string)$other['data_fim'] >= (string)$fc['data_inicio']) {
                    $n++;
                }
            }
            $fc['conflito_count'] = $n;
        }
        unset($fc);

        foreach ($feriasAll as $fStats) {
            $st = mb_strtolower(trim((string)($fStats['status'] ?? 'pendente')));
            if ($st === 'aprovado') $st = 'aprovada';
            $ini = (string)($fStats['data_inicio'] ?? '');
            $fim = (string)($fStats['data_fim'] ?? '');
            if ($st === 'pendente') { $feriasPendentesCount++; }
            elseif ($st === 'aprovada') {
                if ($ini !== '' && $todayDate < $ini) $feriasAgendadasCount++;
                elseif ($fim !== '' && $todayDate > $fim) $feriasConcluidasCount++;
                else $feriasEmCursoCount++;
            }
            elseif ($st === 'rejeitada') { $feriasRejeitadasCount++; }
            elseif ($st === 'cancelada') { $feriasCanceladasCount++; }
        }
    } catch (Throwable $e) {
        error_log('Erro ao carregar seção de férias: ' . $e->getMessage());
    }
    $feriasReview = trim((string)($_GET['review'] ?? ''));
    ?>

    <?php if ($feriasReview === 'created'): ?>
    <div class="alert-success" style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#14532d,#166534);color:#ecfdf5;">
        <i class="fas fa-check-circle"></i> Férias criadas e aprovadas com sucesso.
    </div>
    <?php elseif ($feriasReview === 'ok'): ?>
    <div class="alert-success" style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#14532d,#166534);color:#ecfdf5;">
        <i class="fas fa-check-circle"></i> Operação concluída com sucesso.
    </div>
    <?php elseif ($feriasReview === 'error'): ?>
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#7f1d1d,#991b1b);color:#fef2f2;">
        <i class="fas fa-exclamation-circle"></i> Ocorreu um erro. Tente novamente.
    </div>
    <?php elseif ($feriasReview === 'blocked'): ?>
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#78350f,#92400e);color:#fffbeb;">
        <i class="fas fa-ban"></i> Operação não permitida para o estado atual das férias.
    </div>
    <?php elseif ($feriasReview === 'saldo'): ?>
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding:.85rem 1.1rem;border-radius:10px;background:linear-gradient(90deg,#78350f,#92400e);color:#fffbeb;">
        <i class="fas fa-exclamation-triangle"></i> O funcionário não tem saldo de dias suficiente para este período. Marque "Ignorar saldo" para criar mesmo assim.
    </div>
    <?php endif; ?>

    <div class="frhd">
        <div class="frhd-left">
            <div class="frhd-icon" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);box-shadow:0 4px 14px rgba(14,165,233,.35);"><i class="fas fa-umbrella-beach"></i></div>
            <div>
                <h2 class="frhd-title">Férias</h2>
                <p class="frhd-sub"><?php echo count($feriasAll); ?> registos &middot; <?php echo (int)$feriasPendentesCount; ?> pendente<?php echo $feriasPendentesCount !== 1 ? 's' : ''; ?> hoje</p>
            </div>
        </div>
        <button type="button" class="frhd-add-btn" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);box-shadow:0 4px 12px rgba(14,165,233,.3);" onclick="openFeriasCreateModal()">
            <i class="fas fa-plus"></i> Nova Marcação
        </button>
    </div>

    <style>
        .fv-kpi-pending .fr-kpi-icon { background:rgba(245,158,11,.12); color:#f59e0b; }
        .fv-kpi-sched .fr-kpi-icon   { background:rgba(59,130,246,.12);  color:#3b82f6; }
        .fv-kpi-active .fr-kpi-icon  { background:rgba(16,185,129,.12);  color:#10b981; }
        .fv-kpi-done .fr-kpi-icon    { background:rgba(163,230,53,.1);   color:#a3e635; }
        .fv-kpi-rej .fr-kpi-icon     { background:rgba(239,68,68,.12);   color:#ef4444; }
        .fv-kpi-pending { position:relative; }
        .fv-kpi-badge { position:absolute;top:-6px;right:-6px;min-width:20px;height:20px;border-radius:50%;background:#ef4444;color:#fff;font-size:.65rem;font-weight:900;display:flex;align-items:center;justify-content:center;animation:solicitacaoBadgeFloat 1.5s ease-in-out infinite; }
    </style>
    <div class="fr-kpi-strip" style="grid-template-columns:repeat(5,1fr);">
        <div class="fr-kpi fv-kpi-pending">
            <?php if ($feriasPendentesCount > 0): ?>
            <span class="fv-kpi-badge"><?php echo (int)$feriasPendentesCount; ?></span>
            <?php endif; ?>
            <div class="fr-kpi-icon"><i class="fas fa-clock"></i></div>
            <div class="fr-kpi-body">
                <span class="fr-kpi-val"><?php echo (int)$feriasPendentesCount; ?></span>
                <span class="fr-kpi-lbl">Pendentes</span>
                <span class="fr-kpi-pct">aguardam aprovação</span>
            </div>
        </div>
        <div class="fr-kpi fv-kpi-sched">
            <div class="fr-kpi-icon"><i class="fas fa-calendar-plus"></i></div>
            <div class="fr-kpi-body">
                <span class="fr-kpi-val"><?php echo (int)$feriasAgendadasCount; ?></span>
                <span class="fr-kpi-lbl">Agendadas</span>
                <span class="fr-kpi-pct">início futuro</span>
            </div>
        </div>
        <div class="fr-kpi fv-kpi-active">
            <div class="fr-kpi-icon"><i class="fas fa-person-walking-luggage"></i></div>
            <div class="fr-kpi-body">
                <span class="fr-kpi-val"><?php echo (int)$feriasEmCursoCount; ?></span>
                <span class="fr-kpi-lbl">Em curso</span>
                <span class="fr-kpi-pct">a decorrer agora</span>
            </div>
        </div>
        <div class="fr-kpi fv-kpi-done">
            <div class="fr-kpi-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="fr-kpi-body">
                <span class="fr-kpi-val"><?php echo (int)$feriasConcluidasCount; ?></span>
                <span class="fr-kpi-lbl">Concluídas</span>
                <span class="fr-kpi-pct">terminou</span>
            </div>
        </div>
        <div class="fr-kpi fv-kpi-rej">
            <div class="fr-kpi-icon"><i class="fas fa-times-circle"></i></div>
            <div class="fr-kpi-body">
                <span class="fr-kpi-val"><?php echo (int)$feriasRejeitadasCount; ?></span>
                <span class="fr-kpi-lbl">Rejeitadas</span>
                <span class="fr-kpi-pct">pedidos recusados</span>
            </div>
        </div>
    </div>

    <div class="data-table fr-table-wrap" style="margin-top:.5rem;">
        <div class="fr-toolbar">
            <div class="fr-toolbar-top">
                <div class="fr-search-wrap">
                    <i class="fas fa-search fr-search-icon"></i>
                    <input type="text" id="feriasSearchInput" class="fr-search" placeholder="Pesquisar funcionário…">
                </div>
                <div class="fr-toolbar-right">
                    <span id="feriasResultCount" style="font-size:.78rem;color:#64748b;white-space:nowrap;"></span>
                    <button type="button" class="fr-filter-toggle" id="feriasViewToggle" onclick="toggleFeriasView()">
                        <i class="fas fa-calendar-alt"></i> <span id="feriasViewToggleLabel">Calendário</span>
                    </button>
                    <button type="button" class="fr-filter-toggle" id="feriasFilterToggle"
                        onclick="document.getElementById('feriasAdvFilters').classList.toggle('fr-adv-open'); this.classList.toggle('pa-filter-open')">
                        <i class="fas fa-sliders-h"></i> Filtros
                        <span class="fr-filter-badge" id="feriasFilterBadge" style="display:none"></span>
                    </button>
                </div>
            </div>

            <div class="fr-chips">
                <button class="fr-chip fv-chip-all active" data-fv-chip="">
                    <i class="fas fa-th-large"></i> Todos
                    <span class="fr-chip-count"><?php echo count($feriasAll); ?></span>
                </button>
                <?php if ($feriasPendentesCount > 0): ?>
                <button class="fr-chip fv-chip-pending" data-fv-chip="pendente">
                    <span class="fr-dot" style="background:#f59e0b;"></span> Pendentes
                    <span class="fr-chip-count"><?php echo (int)$feriasPendentesCount; ?></span>
                </button>
                <?php endif; ?>
                <button class="fr-chip fv-chip-sched" data-fv-chip="agendada">
                    <span class="fr-dot fr-dot-blue"></span> Agendadas
                    <span class="fr-chip-count"><?php echo (int)$feriasAgendadasCount; ?></span>
                </button>
                <button class="fr-chip fv-chip-active" data-fv-chip="em_curso">
                    <span class="fr-dot fr-dot-green"></span> Em curso
                    <span class="fr-chip-count"><?php echo (int)$feriasEmCursoCount; ?></span>
                </button>
                <button class="fr-chip fv-chip-done" data-fv-chip="terminada">
                    <span class="fr-dot" style="background:#64748b;"></span> Concluídas
                    <span class="fr-chip-count"><?php echo (int)$feriasConcluidasCount; ?></span>
                </button>
                <button class="fr-chip fv-chip-rej" data-fv-chip="rejeitada">
                    <span class="fr-dot fr-dot-red"></span> Rejeitadas
                    <span class="fr-chip-count"><?php echo (int)$feriasRejeitadasCount; ?></span>
                </button>
            </div>

            <div class="fr-adv-filters" id="feriasAdvFilters">
                <select id="feriasStatusFilter" class="fr-select" style="min-width:170px;">
                    <option value="">Todos os estados</option>
                    <option value="pendente">Pendente</option>
                    <option value="agendada">Agendada</option>
                    <option value="em_curso">Em curso</option>
                    <option value="terminada">Concluída</option>
                    <option value="rejeitada">Rejeitada</option>
                    <option value="cancelada">Cancelada</option>
                </select>
                <input type="date" id="feriasDateFrom" class="fr-select" style="min-width:148px;" title="Data início (de)" />
                <input type="date" id="feriasDateTo" class="fr-select" style="min-width:148px;" title="Data início (até)" />
                <button type="button" class="fr-clear-btn" id="clearFiltersFerias"><i class="fas fa-times"></i> Limpar</button>
            </div>
        </div>

        <style>
            .fv-chip-all.active    { background:rgba(14,165,233,.2); color:#38bdf8; border-color:rgba(14,165,233,.35); }
            .fv-chip-pending.active{ background:rgba(245,158,11,.2); color:#fbbf24; border-color:rgba(245,158,11,.35); }
            .fv-chip-sched.active  { background:rgba(59,130,246,.2); color:#60a5fa; border-color:rgba(59,130,246,.35); }
            .fv-chip-active.active { background:rgba(16,185,129,.2); color:#34d399; border-color:rgba(16,185,129,.35); }
            .fv-chip-done.active   { background:rgba(100,116,139,.18); color:#94a3b8; border-color:rgba(100,116,139,.3); }
            .fv-chip-rej.active    { background:rgba(239,68,68,.2); color:#f87171; border-color:rgba(239,68,68,.35); }
        </style>

        <?php
            // Dados leves para a vista de calendário (reaproveitados no cliente, sem nova consulta).
            $feriasCalendarData = [];
            foreach ($feriasAll as $fcCal) {
                $fcStatusRaw = mb_strtolower(trim((string)($fcCal['status'] ?? 'pendente')));
                if ($fcStatusRaw === 'aprovado') $fcStatusRaw = 'aprovada';
                $fcIni = (string)($fcCal['data_inicio'] ?? '');
                $fcFim = (string)($fcCal['data_fim'] ?? '');
                $fcKey = 'pendente';
                if ($fcStatusRaw === 'aprovada') {
                    if ($fcIni !== '' && $todayDate < $fcIni) { $fcKey = 'agendada'; }
                    elseif ($fcFim !== '' && $todayDate > $fcFim) { $fcKey = 'terminada'; }
                    else { $fcKey = 'em_curso'; }
                } elseif ($fcStatusRaw === 'rejeitada') { $fcKey = 'rejeitada'; }
                elseif ($fcStatusRaw === 'cancelada') { $fcKey = 'cancelada'; }
                if ($fcIni === '' || $fcFim === '') continue;
                $feriasCalendarData[] = [
                    'nome' => (string)($fcCal['employee_name'] ?? 'Funcionário'),
                    'inicio' => $fcIni,
                    'fim' => $fcFim,
                    'estado' => $fcKey,
                    'conflito' => (int)($fcCal['conflito_count'] ?? 0) > 0,
                ];
            }
        ?>
        <div id="feriasCalendarView" style="display:none;">
            <div class="fv-cal-header">
                <button type="button" class="fr-clear-btn" onclick="feriasCalNav(-1)"><i class="fas fa-chevron-left"></i></button>
                <span id="feriasCalLabel" class="fv-cal-label"></span>
                <button type="button" class="fr-clear-btn" onclick="feriasCalNav(1)"><i class="fas fa-chevron-right"></i></button>
                <button type="button" class="fr-clear-btn" onclick="feriasCalNav(0)" style="margin-left:auto;">Hoje</button>
            </div>
            <div id="feriasCalGrid" class="fv-cal-grid"></div>
            <div class="fv-cal-legend">
                <span><i class="fr-dot" style="background:#fbbf24;"></i> Pendente</span>
                <span><i class="fr-dot fr-dot-blue"></i> Agendada</span>
                <span><i class="fr-dot fr-dot-green"></i> Em curso</span>
                <span><i class="fr-dot" style="background:#64748b;"></i> Concluída</span>
                <span><i class="fas fa-exclamation-triangle" style="color:#f87171;"></i> Conflito de equipa</span>
            </div>
        </div>
        <style>
            .fv-cal-header { display:flex; align-items:center; gap:.6rem; margin-bottom:.75rem; }
            .fv-cal-label { font-weight:700; font-size:.95rem; color:var(--text-primary,#f1f5f9); min-width:160px; text-align:center; }
            .fv-cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
            .fv-cal-dow { font-size:.7rem; font-weight:700; color:#64748b; text-align:center; padding:.3rem 0; text-transform:uppercase; }
            .fv-cal-day { min-height:84px; border-radius:8px; background:var(--card-bg,#1e293b); border:1px solid var(--border-color,rgba(255,255,255,.07)); padding:.3rem; display:flex; flex-direction:column; gap:2px; }
            .fv-cal-day.fv-cal-outside { opacity:.35; }
            .fv-cal-day.fv-cal-today { border-color:#0ea5e9; box-shadow:0 0 0 1px #0ea5e9 inset; }
            .fv-cal-daynum { font-size:.72rem; color:#94a3b8; font-weight:600; }
            .fv-cal-bar { font-size:.64rem; padding:1px 5px; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#0f172a; font-weight:600; }
            .fv-cal-bar-pendente { background:#fbbf24; }
            .fv-cal-bar-agendada { background:#60a5fa; }
            .fv-cal-bar-em_curso { background:#34d399; }
            .fv-cal-bar-terminada { background:#94a3b8; }
            .fv-cal-bar-rejeitada,.fv-cal-bar-cancelada { display:none; }
            .fv-cal-legend { display:flex; gap:1rem; flex-wrap:wrap; margin-top:.75rem; font-size:.74rem; color:#94a3b8; }
            .fv-cal-legend span { display:flex; align-items:center; gap:5px; }
        </style>
        <table class="table fr-table" id="feriasSectionTable">
            <thead>
                <tr class="fr-thead-row">
                    <th class="fr-th-emp">Funcionário</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Duração</th>
                    <th class="fr-th-status">Estado</th>
                    <th class="fr-th-acts">Ações</th>
                </tr>
            </thead>
            <tbody id="feriasSectionTableBody">
                <?php if (empty($feriasAll)): ?>
                <tr id="ferias-empty-state">
                    <td colspan="6" style="text-align:center;color:var(--text-secondary);padding:3rem 1rem;">
                        <i class="fas fa-umbrella-beach" style="font-size:2.5rem;opacity:.2;display:block;margin-bottom:.75rem;"></i>
                        Ainda não existem registos de férias.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($feriasAll as $fRow):
                    $fStatusRaw = mb_strtolower(trim((string)($fRow['status'] ?? 'pendente')));
                    if ($fStatusRaw === 'aprovado') $fStatusRaw = 'aprovada';

                    $fInicioIso = (string)($fRow['data_inicio'] ?? '');
                    $fFimIso    = (string)($fRow['data_fim'] ?? '');
                    $fInicioFmt = $fInicioIso !== '' ? date('d/m/Y', strtotime($fInicioIso)) : 'N/D';
                    $fFimFmt    = $fFimIso    !== '' ? date('d/m/Y', strtotime($fFimIso))    : 'N/D';

                    $fStatusLabel = 'Pendente';
                    $fStatusClass = 'status-warning';
                    $fStatusFilterKey = 'pendente';
                    if ($fStatusRaw === 'aprovada') {
                        if ($fInicioIso !== '' && $todayDate < $fInicioIso) {
                            $fStatusLabel = 'Agendada';  $fStatusClass = 'status-atrasado';    $fStatusFilterKey = 'agendada';
                        } elseif ($fFimIso !== '' && $todayDate > $fFimIso) {
                            $fStatusLabel = 'Concluída'; $fStatusClass = 'status-nao-marcado'; $fStatusFilterKey = 'terminada';
                        } else {
                            $fStatusLabel = 'Em curso';  $fStatusClass = 'status-presente';    $fStatusFilterKey = 'em_curso';
                        }
                    } elseif ($fStatusRaw === 'rejeitada') {
                        $fStatusLabel = 'Rejeitada'; $fStatusClass = 'status-falta';    $fStatusFilterKey = 'rejeitada';
                    } elseif ($fStatusRaw === 'cancelada') {
                        $fStatusLabel = 'Cancelada'; $fStatusClass = 'status-inactive'; $fStatusFilterKey = 'cancelada';
                    }

                    $duracaoLabel = '-';
                    if ($fInicioIso !== '' && $fFimIso !== '') {
                        try {
                            $d1 = new DateTime($fInicioIso); $d2 = new DateTime($fFimIso);
                            if ($d2 >= $d1) { $dias = (int)$d1->diff($d2)->days + 1; $duracaoLabel = $dias . ' dia' . ($dias > 1 ? 's' : ''); }
                        } catch (Throwable $e) {}
                    }

                    $employeeName   = (string)($fRow['employee_name'] ?? 'Funcionário');
                    $employeePic    = (string)($fRow['employee_profile_picture'] ?? '');
                    $motivoTooltip  = trim((string)($fRow['motivo'] ?? ''));
                    $motivoRejeicao = trim((string)($fRow['motivo_rejeicao'] ?? ''));
                    $feriasIdRow    = (int)($fRow['id'] ?? 0);
                    $empInitials    = strtoupper(mb_substr($employeeName, 0, 2));
                    $conflitoCount  = (int)($fRow['conflito_count'] ?? 0);
                    $conflitoTitle  = $conflitoCount > 0
                        ? ($conflitoCount . ' colega(s) da mesma função com férias a sobrepor-se a este período')
                        : '';
                ?>
                <tr class="fr-row"
                    data-ferias-nome="<?php echo htmlspecialchars(mb_strtolower($employeeName)); ?>"
                    data-ferias-status="<?php echo htmlspecialchars($fStatusFilterKey); ?>"
                    data-ferias-inicio="<?php echo htmlspecialchars($fInicioIso); ?>"
                    data-ferias-fim="<?php echo htmlspecialchars($fFimIso); ?>">
                    <td class="fr-td-emp">
                        <div class="fr-emp-cell">
                            <div class="fr-av" style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:12px;">
                                <?php if ($employeePic !== ''): ?>
                                <img class="fr-av-img" src="../<?php echo htmlspecialchars($employeePic); ?>" alt=""
                                    onerror="this.parentElement.textContent='<?php echo $empInitials; ?>'; this.remove();">
                                <?php else: echo $empInitials; endif; ?>
                            </div>
                            <div class="fr-emp-info">
                                <span class="fr-emp-name"><?php echo htmlspecialchars($employeeName); ?></span>
                                <span class="fr-emp-email"><?php echo htmlspecialchars($duracaoLabel !== '-' ? $duracaoLabel : '—'); ?></span>
                            </div>
                        </div>
                    </td>
                    <td class="fr-td-role"><?php echo htmlspecialchars($fInicioFmt); ?></td>
                    <td class="fr-td-role"><?php echo htmlspecialchars($fFimFmt); ?></td>
                    <td class="fr-td-role"><?php echo htmlspecialchars($duracaoLabel); ?></td>
                    <td class="fr-td-status">
                        <span class="status-badge <?php echo $fStatusClass; ?>"><?php echo htmlspecialchars($fStatusLabel); ?></span>
                        <?php if ($conflitoCount > 0): ?>
                        <span class="status-badge status-falta" title="<?php echo htmlspecialchars($conflitoTitle); ?>" style="margin-left:4px;">
                            <i class="fas fa-exclamation-triangle"></i> Conflito
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="fr-td-acts">
                        <div class="fr-acts">
                            <button type="button" class="fr-btn fr-btn-view" title="Ver detalhes"
                                data-ferias-id="<?php echo $feriasIdRow; ?>"
                                data-ferias-funcionario="<?php echo htmlspecialchars($employeeName); ?>"
                                data-ferias-inicio="<?php echo htmlspecialchars($fInicioFmt); ?>"
                                data-ferias-fim="<?php echo htmlspecialchars($fFimFmt); ?>"
                                data-ferias-duracao="<?php echo htmlspecialchars($duracaoLabel); ?>"
                                data-ferias-status-label="<?php echo htmlspecialchars($fStatusLabel); ?>"
                                data-ferias-motivo="<?php echo htmlspecialchars($motivoTooltip); ?>"
                                data-ferias-motivo-rejeicao="<?php echo htmlspecialchars($motivoRejeicao); ?>"
                                onclick="openFeriasViewModal(this)"><i class="fas fa-eye"></i></button>

                            <?php if ($fStatusRaw === 'pendente'): ?>
                            <form method="POST" style="display:contents;" class="ferias-approve-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="approve_ferias_request">
                                <input type="hidden" name="ferias_id" value="<?php echo $feriasIdRow; ?>">
                                <input type="hidden" name="from_section" value="ferias">
                                <button type="submit" class="fr-btn fr-btn-activate" title="Aprovar"><i class="fas fa-check"></i></button>
                            </form>
                            <button type="button" class="fr-btn fr-btn-deact" title="Rejeitar"
                                onclick="openFeriasRejectModal(<?php echo $feriasIdRow; ?>, '<?php echo htmlspecialchars(addslashes($employeeName)); ?>')">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php elseif ($fStatusFilterKey === 'agendada'): ?>
                            <button type="button" class="fr-btn fr-btn-edit" title="Editar"
                                data-ferias-id="<?php echo $feriasIdRow; ?>"
                                data-ferias-funcionario="<?php echo htmlspecialchars($employeeName); ?>"
                                data-ferias-inicio-iso="<?php echo htmlspecialchars($fInicioIso); ?>"
                                data-ferias-fim-iso="<?php echo htmlspecialchars($fFimIso); ?>"
                                data-ferias-motivo="<?php echo htmlspecialchars($motivoTooltip); ?>"
                                onclick="openFeriasEditModal(this)"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:contents;" class="ferias-cancel-form"
                                data-confirm-message="Cancelar estas férias agendadas?">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="cancel_ferias_admin">
                                <input type="hidden" name="ferias_id" value="<?php echo $feriasIdRow; ?>">
                                <button type="submit" class="fr-btn fr-btn-deact" title="Cancelar"><i class="fas fa-ban"></i></button>
                            </form>
                            <?php elseif ($fStatusFilterKey === 'em_curso'): ?>
                            <?php $canCancelInCourse = ($fInicioIso !== '' && $todayDate === $fInicioIso); ?>
                            <form method="POST" style="display:contents;" class="ferias-cancel-form"
                                data-confirm-message="Cancelar férias em curso? Só permitido no primeiro dia.">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="cancel_ferias_admin">
                                <input type="hidden" name="ferias_id" value="<?php echo $feriasIdRow; ?>">
                                <button type="submit" class="fr-btn fr-btn-deact <?php echo $canCancelInCourse ? '' : 'fr-btn-off'; ?>"
                                    title="<?php echo $canCancelInCourse ? 'Cancelar' : 'Só pode cancelar no primeiro dia'; ?>"
                                    <?php echo $canCancelInCourse ? '' : 'disabled'; ?>><i class="fas fa-ban"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>


    <!-- ── Férias: Modal Ver ─────────────────────────── -->
    <div id="feriasViewModal" class="modal" style="display:none;overflow-y:auto;padding:24px 16px 48px;">
        <div class="am-sheet vm-sheet" style="max-width:560px;">
            <button class="am-close" type="button" onclick="closeFeriasViewModal()" aria-label="Fechar">&times;</button>

            <div class="vm-hero">
                <div class="vm-hero-av" id="fvmAvatar"><i class="fas fa-umbrella-beach"></i></div>
                <div class="vm-hero-info">
                    <h2 class="vm-hero-name" id="fvmName"></h2>
                    <p class="vm-hero-pos" id="fvmPeriod" style="margin:0 0 8px;"></p>
                    <div id="fvmStatusBadge"></div>
                </div>
            </div>

            <div class="vm-section">
                <div class="vm-sec-lbl"><i class="fas fa-calendar-alt"></i> Período</div>
                <div class="vm-g2">
                    <div>
                        <div class="vm-field-label">Data Início</div>
                        <div class="vm-field-value" id="fvmInicio"></div>
                    </div>
                    <div>
                        <div class="vm-field-label">Data Fim</div>
                        <div class="vm-field-value" id="fvmFim"></div>
                    </div>
                    <div>
                        <div class="vm-field-label">Duração</div>
                        <div class="vm-field-value" id="fvmDuracao"></div>
                    </div>
                    <div>
                        <div class="vm-field-label">Estado</div>
                        <div class="vm-field-value" id="fvmEstado"></div>
                    </div>
                </div>
            </div>

            <div class="vm-section" id="fvmMotivoSection">
                <div class="vm-sec-lbl"><i class="fas fa-comment-alt"></i> Motivo</div>
                <div class="vm-field-value" id="fvmMotivo" style="color:#cbd5e1;line-height:1.55;"></div>
            </div>

            <div id="fvmRejeicaoSection" style="display:none;margin-bottom:18px;">
                <div class="vm-sec-lbl"><i class="fas fa-ban" style="color:#ef4444;"></i> <span style="color:#f87171;">Motivo da Rejeição</span></div>
                <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:12px 14px;color:#fca5a5;font-size:.87rem;line-height:1.55;" id="fvmMotRejeicao"></div>
            </div>

            <div class="am-footer">
                <button type="button" class="am-btn-cancel" onclick="closeFeriasViewModal()">Fechar</button>
            </div>
        </div>
    </div>

    <!-- ── Férias: Modal Criar ────────────────────────── -->
    <div id="feriasCreateModal" class="modal" style="display:none;overflow-y:auto;padding:24px 16px 48px;">
        <div class="am-sheet" style="max-width:580px;">
            <button class="am-close" type="button" onclick="closeFeriasCreateModal()" aria-label="Fechar">&times;</button>

            <div class="am-header">
                <div class="am-header-icon" style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 6px 16px rgba(5,150,105,.35);">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <div>
                    <h3 class="am-title">Nova Marcação de Férias</h3>
                    <p class="am-subtitle">Aprovação direta pelo administrador</p>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="create_ferias_admin">

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-user"></i> Funcionário</div>
                    <div class="am-f">
                        <label class="am-lbl" for="feriasCreateEmployee">Selecione o funcionário <span style="color:#ef4444;">*</span></label>
                        <select id="feriasCreateEmployee" name="employee_id" class="am-inp am-sel" required>
                            <option value="">Selecione um funcionário...</option>
                            <?php foreach ($employees as $emp):
                                $empStatusRaw = mb_strtolower(trim((string)($emp['status'] ?? '')));
                                if (in_array($empStatusRaw, ['inativo', 'inactive'], true)) continue;
                            ?>
                            <option value="<?php echo (int)($emp['id'] ?? 0); ?>"><?php echo htmlspecialchars((string)($emp['name'] ?? 'Funcionário')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-calendar-alt"></i> Período</div>
                    <div class="am-g2">
                        <div class="am-f">
                            <label class="am-lbl" for="feriasCreateInicio">Data Início <span style="color:#ef4444;">*</span></label>
                            <input id="feriasCreateInicio" name="data_inicio" type="date" class="am-inp" required>
                        </div>
                        <div class="am-f">
                            <label class="am-lbl" for="feriasCreateFim">Data Fim <span style="color:#ef4444;">*</span></label>
                            <input id="feriasCreateFim" name="data_fim" type="date" class="am-inp" required>
                        </div>
                    </div>
                    <div style="margin-top:8px;padding:8px 12px;border-radius:8px;background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.18);font-size:.78rem;color:#6ee7b7;" id="feriasCreateDuracaoPreview" style="display:none;"></div>
                </div>

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-comment-alt"></i> Observações</div>
                    <div class="am-f">
                        <label class="am-lbl" for="feriasCreateMotivo">Motivo / Notas <span class="am-opt">(opcional)</span></label>
                        <textarea id="feriasCreateMotivo" name="motivo" rows="3" class="am-inp" style="resize:vertical;" placeholder="Notas internas ou justificação…"></textarea>
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:.82rem;color:#cbd5e1;cursor:pointer;">
                        <input type="checkbox" name="ignorar_saldo" value="1" style="width:16px;height:16px;">
                        Ignorar saldo de dias disponível (exceção / abono especial)
                    </label>
                </div>

                <div class="am-footer">
                    <button type="button" class="am-btn-cancel" onclick="closeFeriasCreateModal()">Cancelar</button>
                    <button type="submit" class="am-btn-submit" style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 4px 14px rgba(5,150,105,.3);">
                        <i class="fas fa-check"></i> Criar e Aprovar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Férias: Modal Editar ───────────────────────── -->
    <div id="feriasEditModal" class="modal" style="display:none;overflow-y:auto;padding:24px 16px 48px;">
        <div class="am-sheet" style="max-width:540px;">
            <button class="am-close" type="button" onclick="closeFeriasEditModal()" aria-label="Fechar">&times;</button>

            <div class="am-header">
                <div class="am-header-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div>
                    <h3 class="am-title">Editar Férias</h3>
                    <p class="am-subtitle" id="feriasEditEmployee">—</p>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="edit_ferias_admin">
                <input type="hidden" name="ferias_id" id="feriasEditId" value="">

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-calendar-alt"></i> Período</div>
                    <div class="am-g2">
                        <div class="am-f">
                            <label class="am-lbl" for="feriasEditInicio">Data Início <span style="color:#ef4444;">*</span></label>
                            <input id="feriasEditInicio" name="data_inicio" type="date" class="am-inp" required>
                        </div>
                        <div class="am-f">
                            <label class="am-lbl" for="feriasEditFim">Data Fim <span style="color:#ef4444;">*</span></label>
                            <input id="feriasEditFim" name="data_fim" type="date" class="am-inp" required>
                        </div>
                    </div>
                </div>

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-comment-alt"></i> Observações</div>
                    <div class="am-f">
                        <label class="am-lbl" for="feriasEditMotivo">Motivo / Notas <span class="am-opt">(opcional)</span></label>
                        <textarea id="feriasEditMotivo" name="motivo" rows="3" class="am-inp" style="resize:vertical;"></textarea>
                    </div>
                </div>

                <div class="am-footer">
                    <button type="button" class="am-btn-cancel" onclick="closeFeriasEditModal()">Cancelar</button>
                    <button type="submit" class="am-btn-submit"><i class="fas fa-save"></i> Guardar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Férias: Modal Rejeitar ─────────────────────── -->
    <div id="feriasRejectModal" class="modal" style="display:none;overflow-y:auto;padding:24px 16px 48px;">
        <div class="am-sheet" style="max-width:500px;">
            <button class="am-close" type="button" onclick="closeFeriasRejectModal()" aria-label="Fechar">&times;</button>

            <div class="am-header">
                <div class="am-header-icon" style="background:linear-gradient(135deg,#dc2626,#b91c1c);box-shadow:0 6px 16px rgba(185,28,28,.35);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div>
                    <h3 class="am-title">Rejeitar Pedido de Férias</h3>
                    <p class="am-subtitle" id="feriasRejectModalEmployee">—</p>
                </div>
            </div>

            <form method="POST" id="feriasRejectForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="reject_ferias_request">
                <input type="hidden" name="ferias_id" id="feriasRejectId" value="">
                <input type="hidden" name="from_section" value="ferias">

                <div class="am-section">
                    <div class="am-sec-lbl"><i class="fas fa-comment-alt" style="color:#ef4444;"></i> Motivo da Rejeição</div>
                    <div class="am-f">
                        <label class="am-lbl" for="feriasRejectMotivo">Indique o motivo <span style="color:#ef4444;">*</span></label>
                        <textarea id="feriasRejectMotivo" name="motivo_rejeicao" rows="4" class="am-inp" style="resize:vertical;" placeholder="O pedido é rejeitado porque…"></textarea>
                        <span class="am-hint">Este motivo será comunicado ao funcionário.</span>
                    </div>
                </div>

                <div class="am-footer">
                    <button type="button" class="am-btn-cancel" onclick="closeFeriasRejectModal()">Cancelar</button>
                    <button type="submit" class="am-btn-submit" style="background:linear-gradient(135deg,#dc2626,#b91c1c);box-shadow:0 4px 14px rgba(185,28,28,.3);">
                        <i class="fas fa-times"></i> Confirmar Rejeição
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    var feriasCalendarData = <?php echo json_encode($feriasCalendarData, JSON_UNESCAPED_UNICODE); ?>;
    var feriasCalCursor = new Date();
    feriasCalCursor.setDate(1);

    function toggleFeriasView() {
        var table = document.getElementById('feriasSectionTable');
        var cal = document.getElementById('feriasCalendarView');
        var label = document.getElementById('feriasViewToggleLabel');
        if (!table || !cal) return;
        var showingCalendar = cal.style.display !== 'none';
        if (showingCalendar) {
            cal.style.display = 'none';
            table.style.display = '';
            if (label) label.textContent = 'Calendário';
        } else {
            cal.style.display = '';
            table.style.display = 'none';
            if (label) label.textContent = 'Tabela';
            renderFeriasCalendar();
        }
    }

    function feriasCalNav(direction) {
        if (direction === 0) {
            feriasCalCursor = new Date();
        } else {
            feriasCalCursor.setMonth(feriasCalCursor.getMonth() + direction);
        }
        feriasCalCursor.setDate(1);
        renderFeriasCalendar();
    }

    function renderFeriasCalendar() {
        var grid = document.getElementById('feriasCalGrid');
        var labelEl = document.getElementById('feriasCalLabel');
        if (!grid || !labelEl) return;

        var year = feriasCalCursor.getFullYear();
        var month = feriasCalCursor.getMonth();
        var monthNames = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        labelEl.textContent = monthNames[month] + ' de ' + year;

        var firstOfMonth = new Date(year, month, 1);
        var startOffset = (firstOfMonth.getDay() + 6) % 7; // semana começa à segunda-feira
        var gridStart = new Date(year, month, 1 - startOffset);
        var todayIso = new Date().toISOString().slice(0, 10);

        var dowLabels = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'];
        var html = dowLabels.map(function(d) { return '<div class="fv-cal-dow">' + d + '</div>'; }).join('');

        for (var i = 0; i < 42; i++) {
            var d = new Date(gridStart);
            d.setDate(gridStart.getDate() + i);
            var dIso = d.toISOString().slice(0, 10);
            var outside = d.getMonth() !== month;
            var isToday = dIso === todayIso;

            var dayItems = feriasCalendarData.filter(function(f) {
                return f.inicio <= dIso && f.fim >= dIso;
            });

            var bars = dayItems.slice(0, 3).map(function(f) {
                var icon = f.conflito ? '<i class="fas fa-exclamation-triangle"></i> ' : '';
                return '<div class="fv-cal-bar fv-cal-bar-' + f.estado + '" title="' + escapeHtmlFerias(f.nome) + '">' + icon + escapeHtmlFerias(f.nome) + '</div>';
            }).join('');
            var more = dayItems.length > 3 ? '<div class="fv-cal-bar" style="background:transparent;color:#64748b;">+' + (dayItems.length - 3) + '</div>' : '';

            html += '<div class="fv-cal-day' + (outside ? ' fv-cal-outside' : '') + (isToday ? ' fv-cal-today' : '') + '">'
                + '<span class="fv-cal-daynum">' + d.getDate() + '</span>'
                + bars + more
                + '</div>';
        }

        grid.innerHTML = html;
    }

    function escapeHtmlFerias(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function openFeriasViewModal(btn) {
        var modal = document.getElementById('feriasViewModal');
        if (!modal || !btn) return;

        var funcionario    = btn.getAttribute('data-ferias-funcionario') || 'Funcionário';
        var inicio         = btn.getAttribute('data-ferias-inicio') || 'N/D';
        var fim            = btn.getAttribute('data-ferias-fim') || 'N/D';
        var duracao        = btn.getAttribute('data-ferias-duracao') || '-';
        var statusLabel    = btn.getAttribute('data-ferias-status-label') || '-';
        var motivo         = btn.getAttribute('data-ferias-motivo') || '';
        var motivoRejeicao = btn.getAttribute('data-ferias-motivo-rejeicao') || '';

        var initials = funcionario.replace(/\s+/g,'').substring(0,2).toUpperCase();
        var avEl = document.getElementById('fvmAvatar');
        if (avEl) avEl.innerHTML = '<span style="font-size:1.4rem;font-weight:700;">' + escapeHtmlFerias(initials) + '</span>';

        var setTxt = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
        setTxt('fvmName',    funcionario);
        setTxt('fvmPeriod',  inicio + ' — ' + fim);
        setTxt('fvmInicio',  inicio);
        setTxt('fvmFim',     fim);
        setTxt('fvmDuracao', duracao);
        setTxt('fvmEstado',  statusLabel);

        var badgeEl = document.getElementById('fvmStatusBadge');
        if (badgeEl) {
            var cls = 'status-warning';
            var sl = statusLabel.toLowerCase();
            if (sl === 'em curso') cls = 'status-presente';
            else if (sl === 'concluída' || sl === 'concluida') cls = 'status-nao-marcado';
            else if (sl === 'rejeitada') cls = 'status-falta';
            else if (sl === 'cancelada') cls = 'status-inactive';
            else if (sl === 'agendada') cls = 'status-atrasado';
            badgeEl.innerHTML = '<span class="status-badge ' + cls + '">' + escapeHtmlFerias(statusLabel) + '</span>';
        }

        var motivoSection = document.getElementById('fvmMotivoSection');
        var motivoEl = document.getElementById('fvmMotivo');
        if (motivoSection && motivoEl) {
            if (motivo) { motivoEl.textContent = motivo; motivoSection.style.display = ''; }
            else { motivoSection.style.display = 'none'; }
        }

        var rejSection = document.getElementById('fvmRejeicaoSection');
        var rejEl = document.getElementById('fvmMotRejeicao');
        if (rejSection && rejEl) {
            if (motivoRejeicao) { rejEl.textContent = motivoRejeicao; rejSection.style.display = ''; }
            else { rejSection.style.display = 'none'; }
        }

        modal.style.display = 'block';
    }

    function closeFeriasViewModal() {
        var modal = document.getElementById('feriasViewModal');
        if (modal) modal.style.display = 'none';
    }

    function openFeriasRejectModal(feriasId, funcionarioName) {
        var modal = document.getElementById('feriasRejectModal');
        if (!modal) return;
        var idEl = document.getElementById('feriasRejectId');
        var empEl = document.getElementById('feriasRejectModalEmployee');
        var motivoEl = document.getElementById('feriasRejectMotivo');
        if (idEl) idEl.value = feriasId;
        if (empEl) empEl.textContent = 'Funcionário: ' + (funcionarioName || '');
        if (motivoEl) motivoEl.value = '';
        modal.style.display = 'block';
    }

    function closeFeriasRejectModal() {
        var modal = document.getElementById('feriasRejectModal');
        if (modal) modal.style.display = 'none';
    }

    function feriasDuracaoLabel(inicioVal, fimVal) {
        if (!inicioVal || !fimVal) return '';
        var d1 = new Date(inicioVal), d2 = new Date(fimVal);
        if (isNaN(d1) || isNaN(d2) || d2 < d1) return '';
        var dias = Math.round((d2 - d1) / 86400000) + 1;
        return '<i class="fas fa-info-circle"></i> ' + dias + ' dia' + (dias > 1 ? 's' : '') + ' de férias';
    }

    function openFeriasCreateModal() {
        var modal = document.getElementById('feriasCreateModal');
        if (!modal) return;
        var employeeEl = document.getElementById('feriasCreateEmployee');
        var inicioEl   = document.getElementById('feriasCreateInicio');
        var fimEl      = document.getElementById('feriasCreateFim');
        var motivoEl   = document.getElementById('feriasCreateMotivo');
        var previewEl  = document.getElementById('feriasCreateDuracaoPreview');
        if (employeeEl) employeeEl.value = '';
        if (inicioEl)   inicioEl.value   = '';
        if (fimEl)      fimEl.value      = '';
        if (motivoEl)   motivoEl.value   = '';
        if (previewEl)  { previewEl.innerHTML = ''; previewEl.style.display = 'none'; }
        modal.style.display = 'block';
    }

    function closeFeriasCreateModal() {
        var modal = document.getElementById('feriasCreateModal');
        if (modal) modal.style.display = 'none';
    }

    function openFeriasEditModal(btn) {
        var modal = document.getElementById('feriasEditModal');
        if (!modal || !btn) return;
        var idEl       = document.getElementById('feriasEditId');
        var inicioEl   = document.getElementById('feriasEditInicio');
        var fimEl      = document.getElementById('feriasEditFim');
        var motivoEl   = document.getElementById('feriasEditMotivo');
        var subtitleEl = document.getElementById('feriasEditEmployee');
        if (idEl)       idEl.value       = btn.getAttribute('data-ferias-id')        || '';
        if (inicioEl)   inicioEl.value   = btn.getAttribute('data-ferias-inicio-iso') || '';
        if (fimEl)      fimEl.value      = btn.getAttribute('data-ferias-fim-iso')   || '';
        if (motivoEl)   motivoEl.value   = btn.getAttribute('data-ferias-motivo')    || '';
        if (subtitleEl) subtitleEl.textContent = btn.getAttribute('data-ferias-funcionario') || '';
        modal.style.display = 'block';
    }

    function closeFeriasEditModal() {
        var modal = document.getElementById('feriasEditModal');
        if (modal) modal.style.display = 'none';
    }

    (function() {
        var ini = document.getElementById('feriasCreateInicio');
        var fim = document.getElementById('feriasCreateFim');
        var preview = document.getElementById('feriasCreateDuracaoPreview');
        function updatePreview() {
            if (!preview) return;
            var label = feriasDuracaoLabel(ini && ini.value, fim && fim.value);
            if (label) { preview.innerHTML = label; preview.style.display = ''; }
            else { preview.innerHTML = ''; preview.style.display = 'none'; }
        }
        if (ini) ini.addEventListener('change', updatePreview);
        if (fim) fim.addEventListener('change', updatePreview);
    })();

    (function initFeriasCancelSweetAlert() {
        var forms = document.querySelectorAll('#feriasSectionTable .ferias-cancel-form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();

                var submitButton = form.querySelector('button[type="submit"]');
                if (submitButton && submitButton.disabled) {
                    return;
                }

                var confirmMessage = form.getAttribute('data-confirm-message') || 'Confirmar cancelamento destas férias?';

                if (typeof showConfirm === 'function') {
                    showConfirm(
                        'Confirmar cancelamento',
                        confirmMessage,
                        'Sim, cancelar',
                        'Cancelar'
                    ).then(function(result) {
                        if (result && result.isConfirmed) {
                            form.submit();
                        }
                    });
                    return;
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Confirmar cancelamento',
                        text: confirmMessage,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Sim, cancelar',
                        cancelButtonText: 'Cancelar'
                    }).then(function(result) {
                        if (result && result.isConfirmed) {
                            form.submit();
                        }
                    });
                    return;
                }

                if (window.confirm(confirmMessage)) {
                    form.submit();
                }
            });
        });
    })();

    window.addEventListener('click', function(event) {
        var viewModal = document.getElementById('feriasViewModal');
        var createModal = document.getElementById('feriasCreateModal');
        var editModal = document.getElementById('feriasEditModal');
        var rejectModal = document.getElementById('feriasRejectModal');
        if (event.target === viewModal) closeFeriasViewModal();
        if (event.target === createModal) closeFeriasCreateModal();
        if (event.target === editModal) closeFeriasEditModal();
        if (event.target === rejectModal) closeFeriasRejectModal();
    });

    (function() {
        var rejectForm = document.getElementById('feriasRejectForm');
        if (!rejectForm) return;
        rejectForm.addEventListener('submit', function(e) {
            var motivoEl = document.getElementById('feriasRejectMotivo');
            if (!motivoEl || !motivoEl.value.trim()) {
                e.preventDefault();
                motivoEl && motivoEl.focus();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Motivo obrigatório', text: 'Por favor, indique o motivo da rejeição.', confirmButtonColor: '#3b82f6' });
                } else {
                    alert('Por favor, indique o motivo da rejeição.');
                }
            }
        });
    })();

    (function() {
        var searchInput = document.getElementById('feriasSearchInput');
        var statusFilter = document.getElementById('feriasStatusFilter');
        var dateFrom = document.getElementById('feriasDateFrom');
        var dateTo = document.getElementById('feriasDateTo');
        var clearBtn = document.getElementById('clearFiltersFerias');
        var resultCount = document.getElementById('feriasResultCount');
        var body = document.getElementById('feriasSectionTableBody');
        if (!searchInput || !statusFilter || !dateFrom || !dateTo || !clearBtn || !resultCount || !body) {
            return;
        }

        function updateFeriasFilterBadge() {
            var badge = document.getElementById('feriasFilterBadge');
            if (!badge) return;
            var count = [statusFilter.value, dateFrom.value, dateTo.value].filter(Boolean).length;
            if (count > 0) { badge.textContent = String(count); badge.style.display = 'flex'; }
            else { badge.style.display = 'none'; }
        }

        function applyFeriasFilters() {
            var term = (searchInput.value || '').toLowerCase().trim();
            var status = (statusFilter.value || '').toLowerCase().trim();
            var fromVal = (dateFrom.value || '').trim();
            var toVal = (dateTo.value || '').trim();
            var rows = body.querySelectorAll('tr[data-ferias-nome]');
            var visibleCount = 0;

            rows.forEach(function(row) {
                var nome = (row.getAttribute('data-ferias-nome') || '').toLowerCase();
                var rowStatus = (row.getAttribute('data-ferias-status') || '').toLowerCase();
                var rowInicio = (row.getAttribute('data-ferias-inicio') || '').trim();
                var rowFim = (row.getAttribute('data-ferias-fim') || '').trim();

                var okNome = term === '' || nome.indexOf(term) !== -1;
                var okStatus = status === '' || rowStatus === status;

                // Interseção de período: férias cujo intervalo cruza o intervalo filtrado.
                var okPeriodo = true;
                if (fromVal !== '' && rowFim !== '' && rowFim < fromVal) {
                    okPeriodo = false;
                }
                if (toVal !== '' && rowInicio !== '' && rowInicio > toVal) {
                    okPeriodo = false;
                }

                var show = okNome && okStatus && okPeriodo;
                row.style.display = show ? '' : 'none';
                if (show) {
                    visibleCount++;
                }
            });

            resultCount.textContent = visibleCount + ' resultado' + (visibleCount === 1 ? '' : 's');

            var emptyState = document.getElementById('ferias-empty-state');
            if (emptyState) {
                emptyState.style.display = visibleCount === 0 ? '' : 'none';
                if (visibleCount === 0) {
                    var cell = emptyState.querySelector('td');
                    if (cell) {
                        cell.textContent = 'Nenhum registo encontrado para os filtros aplicados.';
                    }
                }
            }

            updateFeriasFilterBadge();
        }

        function clearFeriasFilters() {
            searchInput.value = '';
            statusFilter.value = '';
            dateFrom.value = '';
            dateTo.value = '';
            applyFeriasFilters();
        }

        searchInput.addEventListener('input', applyFeriasFilters);
        statusFilter.addEventListener('change', applyFeriasFilters);
        dateFrom.addEventListener('change', applyFeriasFilters);
        dateTo.addEventListener('change', applyFeriasFilters);
        clearBtn.addEventListener('click', function() {
            clearFeriasFilters();
            setFeriasActiveChip('');
        });

        // Chips
        function setFeriasActiveChip(chipVal) {
            document.querySelectorAll('[data-fv-chip]').forEach(function(chip) {
                chip.classList.toggle('active', chip.getAttribute('data-fv-chip') === chipVal);
            });
        }

        document.querySelectorAll('[data-fv-chip]').forEach(function(chip) {
            chip.addEventListener('click', function() {
                var val = this.getAttribute('data-fv-chip');
                statusFilter.value = val;
                setFeriasActiveChip(val);
                applyFeriasFilters();
            });
        });

        applyFeriasFilters();
    })();
    </script>
</section>
