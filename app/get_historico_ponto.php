<?php
session_start();
header('Content-Type: application/json');

require_once '../config/db_connection.php';

if (empty($_SESSION['employee_id']) || empty($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];
$mes         = $_GET['mes'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $mes) || $mes > date('Y-m')) {
    echo json_encode(['success' => false, 'message' => 'Mês inválido']);
    exit;
}

try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'registros_ponto'");
    if ($checkTable->rowCount() === 0) {
        echo json_encode(['success' => true, 'registros' => [], 'turno_inicio' => null]);
        exit;
    }

    $cols    = $pdo->query("SHOW COLUMNS FROM registros_ponto")->fetchAll(PDO::FETCH_COLUMN);
    $dateCol = in_array('data_registro', $cols) ? 'data_registro' : (in_array('data', $cols) ? 'data' : null);

    if (!$dateCol) {
        echo json_encode(['success' => true, 'registros' => [], 'turno_inicio' => null]);
        exit;
    }

    // Buscar todos os registos do mês (sem LIMIT — agrupa por dia a seguir)
    $stmt = $pdo->prepare("
        SELECT * FROM registros_ponto
        WHERE funcionario_id = ?
          AND DATE_FORMAT({$dateCol}, '%Y-%m') = ?
        ORDER BY {$dateCol} ASC, id ASC
    ");
    $stmt->execute([$employee_id, $mes]);
    $registos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Turno de referência para pontualidade
    $turnoInicio = null;
    $stmtT = $pdo->prepare("
        SELECT horario_inicio FROM turnos
        WHERE funcionario_id = ? AND LOWER(COALESCE(status,'ativo')) IN ('ativo','active')
        ORDER BY id DESC LIMIT 1
    ");
    $stmtT->execute([$employee_id]);
    $turnoRow = $stmtT->fetch(PDO::FETCH_ASSOC);
    if ($turnoRow) $turnoInicio = substr((string)($turnoRow['horario_inicio'] ?? ''), 0, 5);

    // Justificativas do mês para mostrar estado correcto nas faltas
    $justByDate = [];
    try {
        $checkJust = $pdo->query("SHOW TABLES LIKE 'justificativas_presenca'");
        if ($checkJust->rowCount() > 0) {
            [$mesY, $mesM] = explode('-', $mes);
            $mesStart = $mes . '-01';
            $mesEnd   = date('Y-m-t', mktime(0, 0, 0, (int)$mesM, 1, (int)$mesY));
            $stmtJ = $pdo->prepare("
                SELECT data_ocorrencia, status, tipo, motivo, admin_observacao, anexo_path, created_at
                FROM justificativas_presenca
                WHERE employee_id = ? AND data_ocorrencia BETWEEN ? AND ?
            ");
            $stmtJ->execute([$employee_id, $mesStart, $mesEnd]);
            foreach ($stmtJ->fetchAll(PDO::FETCH_ASSOC) as $j) {
                $dk = substr((string)($j['data_ocorrencia'] ?? ''), 0, 10);
                if ($dk) $justByDate[$dk] = $j;
            }
        }
    } catch (PDOException $e) { /* tabela pode não existir */ }

    // Agrupar por data — suporte a múltiplos períodos por dia
    $byDate = [];
    foreach ($registos as $r) {
        $dk = substr((string)($r[$dateCol] ?? ''), 0, 10);
        if ($dk) $byDate[$dk][] = $r;
    }

    // Construir resposta — uma entrada por dia, com totais agregados
    $out = [];
    foreach (array_reverse(array_keys($byDate)) as $dk) {
        $dayRecs = $byDate[$dk];
        $lastRec = end($dayRecs);

        // Primeira entrada e última saída
        $hEnt = '';
        foreach ($dayRecs as $dr) {
            if (!empty($dr['hora_entrada'])) { $hEnt = substr((string)$dr['hora_entrada'], 0, 5); break; }
        }
        $hSai = '';
        foreach (array_reverse($dayRecs) as $dr) {
            if (!empty($dr['hora_saida'])) { $hSai = substr((string)$dr['hora_saida'], 0, 5); break; }
        }

        // Total de horas = soma de todos os pares completos
        $totalSecs = 0;
        foreach ($dayRecs as $dr) {
            $e = (string)($dr['hora_entrada'] ?? '');
            $s = (string)($dr['hora_saida']   ?? '');
            if ($e && $s) {
                $d = strtotime('today ' . $s) - strtotime('today ' . $e);
                if ($d > 0) $totalSecs += $d;
            }
        }
        $totalH = $totalSecs > 0
            ? sprintf('%dh%02dm', floor($totalSecs / 3600), floor(($totalSecs % 3600) / 60))
            : '';

        $status = (string)($lastRec['status_confirmacao'] ?? 'pendente');

        $comp = ''; $compClass = '';
        if ($turnoInicio && $hEnt) {
            $diff = (int)round((strtotime('today ' . $hEnt) - strtotime('today ' . $turnoInicio)) / 60);
            if ($diff > 15)     { $comp = "Atraso {$diff}min"; $compClass = 'comp-badge--late'; }
            elseif ($diff < -5) { $comp = 'Antecipado';        $compClass = 'comp-badge--early'; }
            else                { $comp = 'A tempo';            $compClass = 'comp-badge--ok'; }
        }

        $obs = implode(' | ', array_filter(array_map(fn($dr) => trim((string)($dr['observacao'] ?? '')), $dayRecs)));

        $periodos = [];
        foreach ($dayRecs as $dr) {
            $pe = substr((string)($dr['hora_entrada'] ?? ''), 0, 5);
            if ($pe) $periodos[] = [
                'entrada' => $pe,
                'saida'   => substr((string)($dr['hora_saida'] ?? ''), 0, 5),
                'obs'     => trim((string)($dr['observacao'] ?? '')),
            ];
        }

        $just       = $justByDate[$dk] ?? null;
        $justStatus = $just ? (string)($just['status']             ?? 'pendente') : '';
        $justTipo   = $just ? (string)($just['tipo']              ?? '') : '';
        $justMotivo = $just ? (string)($just['motivo']            ?? '') : '';
        $justObs    = $just ? (string)($just['admin_observacao']  ?? '') : '';
        $justDoc    = $just ? (string)($just['anexo_path']        ?? '') : '';
        $justAt     = $just ? substr((string)($just['created_at'] ?? ''), 0, 16) : '';

        $out[] = [
            'data_raw'    => $dk,
            'data_fmt'    => date('d/m/Y', strtotime($dk)),
            'entrada'     => $hEnt ?: '--:--',
            'saida'       => $hSai ?: '--:--',
            'total'       => $totalH,
            'status'      => $status,
            'comp'        => $comp,
            'comp_class'  => $compClass,
            'obs'         => $obs,
            'n_periodos'  => count($dayRecs),
            'periodos'    => $periodos,
            'just_status' => $justStatus,
            'just_tipo'   => $justTipo,
            'just_motivo' => $justMotivo,
            'just_obs'    => $justObs,
            'just_doc'    => $justDoc,
            'just_at'     => $justAt,
        ];
    }

    echo json_encode(['success' => true, 'registros' => $out, 'turno_inicio' => $turnoInicio]);

} catch (PDOException $e) {
    error_log("get_historico_ponto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar histórico']);
}
