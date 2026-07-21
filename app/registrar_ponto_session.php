<?php
session_start();
header('Content-Type: application/json');

require_once '../config/db_connection.php';

date_default_timezone_set('Europe/Lisbon');

// Verifica autenticação
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

try {
    $data        = json_decode(file_get_contents('php://input'), true);
    $tipo        = isset($data['tipo']) ? trim($data['tipo']) : '';
    $observacao  = mb_substr(trim((string)($data['observacao'] ?? '')), 0, 200);

    if (!in_array($tipo, ['entrada', 'saida'])) {
        throw new Exception('Tipo inválido');
    }
    
    $employee_id = (int)$_SESSION['employee_id'];
    $client_id = (int)$_SESSION['client_id'];
    $data_hoje = date('Y-m-d');
    $hora_atual = date('H:i:s');

    // Funcionário em férias não pode marcar ponto/presença.
    $stmtEmpStatus = $pdo->prepare("SELECT status FROM employees WHERE id = ? AND client_id = ? LIMIT 1");
    $stmtEmpStatus->execute([$employee_id, $client_id]);
    $empStatusRow = $stmtEmpStatus->fetch(PDO::FETCH_ASSOC);
    $empStatus = mb_strtolower(trim((string)($empStatusRow['status'] ?? '')));
    if (in_array($empStatus, ['ferias', 'férias'], true)) {
        throw new Exception('Funcionário em férias não pode marcar presença.');
    }

    $stmtTurnoAtivo = $pdo->prepare("
        SELECT horario_inicio, horario_fim, dias_semana, data_inicio, data_fim
        FROM turnos
        WHERE funcionario_id = ? AND LOWER(COALESCE(status, '')) IN ('ativo', 'active')
    ");
    $stmtTurnoAtivo->execute([$employee_id]);
    $turnosAtivos = $stmtTurnoAtivo->fetchAll(PDO::FETCH_ASSOC);
    if (empty($turnosAtivos)) {
        throw new Exception('Funcionário sem turno ativo não pode marcar presença.');
    }

    // Encontra o turno previsto para hoje (dia da semana + vigência), tal como usado em Assiduidade.
    $turnoDeHoje = null;
    $weekdayMap = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];
    $weekdayToken = $weekdayMap[(int)date('w')];
    foreach ($turnosAtivos as $t) {
        $diasRaw = trim((string)($t['dias_semana'] ?? ''));
        $dias = [];
        if ($diasRaw !== '') {
            $map = [
                'domingo' => 'dom', 'dom' => 'dom', 'segunda' => 'seg', 'seg' => 'seg',
                'terca' => 'ter', 'terça' => 'ter', 'ter' => 'ter', 'quarta' => 'qua', 'qua' => 'qua',
                'quinta' => 'qui', 'qui' => 'qui', 'sexta' => 'sex', 'sex' => 'sex', 'sabado' => 'sab', 'sábado' => 'sab', 'sab' => 'sab',
            ];
            foreach (preg_split('/\s*,\s*/', mb_strtolower($diasRaw)) as $p) {
                if (isset($map[$p])) $dias[] = $map[$p];
            }
        }
        $diaCorreto = empty($dias) || in_array($weekdayToken, $dias, true);

        $inicioVig = trim((string)($t['data_inicio'] ?? ''));
        $fimVig = trim((string)($t['data_fim'] ?? ''));
        $dentroVig = ($inicioVig === '' || $inicioVig === '0000-00-00' || $inicioVig <= $data_hoje)
            && ($fimVig === '' || $fimVig === '0000-00-00' || $fimVig >= $data_hoje);

        if ($diaCorreto && $dentroVig) {
            $turnoDeHoje = $t;
            break;
        }
    }

    // Sem turno agendado para o dia da semana de hoje, não pode marcar entrada.
    if ($tipo === 'entrada' && !$turnoDeHoje) {
        throw new Exception('Não tem turno agendado para hoje.');
    }

    // Se marcar entrada, o turno de hoje precisa de ainda estar em curso — não deixa
    // marcar entrada antes do turno começar nem depois de já ter terminado (esse fica
    // só para o próximo turno agendado).
    if ($tipo === 'entrada' && $turnoDeHoje) {
        $horaFimTurno = substr((string)($turnoDeHoje['horario_fim'] ?? ''), 0, 5);
        $horaInicioTurno = substr((string)($turnoDeHoje['horario_inicio'] ?? ''), 0, 5);
        if ($horaFimTurno !== '' && $horaInicioTurno !== '') {
            $fimTurnoTs = strtotime($data_hoje . ' ' . $horaFimTurno);
            $inicioTurnoTs = strtotime($data_hoje . ' ' . $horaInicioTurno);
            if ($fimTurnoTs !== false && $inicioTurnoTs !== false && $fimTurnoTs <= $inicioTurnoTs) {
                $fimTurnoTs += 24 * 60 * 60; // turno noturno
            }
            if ($inicioTurnoTs !== false && time() < $inicioTurnoTs) {
                throw new Exception('O seu turno começa às ' . $horaInicioTurno . '. Ainda não pode marcar entrada.');
            }
            if ($fimTurnoTs !== false && time() > $fimTurnoTs) {
                throw new Exception('O seu turno já terminou. Só poderá marcar entrada no próximo turno agendado.');
            }
        }
    }

    // Buscar registo em aberto hoje (entrada sem saída) — suporte a múltiplos períodos por dia.
    // Registos invalidados (entrada rejeitada pelo admin) não contam como "em aberto".
    $stmt = $pdo->prepare("
        SELECT * FROM registros_ponto
        WHERE funcionario_id = ? AND DATE(data_registro) = ?
          AND hora_entrada IS NOT NULL AND hora_entrada != ''
          AND (hora_saida IS NULL OR hora_saida = '')
          AND LOWER(COALESCE(status, '')) <> 'invalidado'
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$employee_id, $data_hoje]);
    $registoAberto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tipo === 'entrada') {
        if ($registoAberto) {
            throw new Exception('Já tem uma entrada em aberto. Registe a saída antes de iniciar um novo período.');
        }

        // Contar períodos do dia para mensagem informativa
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM registros_ponto WHERE funcionario_id = ? AND DATE(data_registro) = ?");
        $stmtCount->execute([$employee_id, $data_hoje]);
        $nPeriodos = (int)$stmtCount->fetchColumn() + 1;

        // Inserir novo período (client_id obrigatório na tabela)
        $cols = $pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'observacao'")->fetch();
        if ($cols && $observacao !== '') {
            $stmtInsert = $pdo->prepare("INSERT INTO registros_ponto (funcionario_id, client_id, data_registro, hora_entrada, observacao) VALUES (?, ?, ?, ?, ?)");
            $stmtInsert->execute([$employee_id, $client_id, $data_hoje, $hora_atual, $observacao]);
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO registros_ponto (funcionario_id, client_id, data_registro, hora_entrada) VALUES (?, ?, ?, ?)");
            $stmtInsert->execute([$employee_id, $client_id, $data_hoje, $hora_atual]);
        }

        $message = $nPeriodos > 1
            ? "Regresso registado às $hora_atual (período $nPeriodos)"
            : "Entrada registada às $hora_atual";

    } else { // saida
        if (!$registoAberto) {
            throw new Exception('Não tem entrada em aberto. Registe a entrada primeiro.');
        }

        // Fechar o período em aberto
        $cols = $pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'observacao'")->fetch();
        if ($cols && $observacao !== '') {
            $stmtUpdate = $pdo->prepare("UPDATE registros_ponto SET hora_saida = ?, observacao = CONCAT(COALESCE(observacao,''), IF(observacao IS NOT NULL AND observacao <> '', ' | ', ''), ?) WHERE id = ?");
            $stmtUpdate->execute([$hora_atual, $observacao, $registoAberto['id']]);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE registros_ponto SET hora_saida = ? WHERE id = ?");
            $stmtUpdate->execute([$hora_atual, $registoAberto['id']]);
        }

        $isPausaObs = str_contains(mb_strtolower($observacao), 'pausa');
        $message = $isPausaObs
            ? "$observacao registada às $hora_atual. Bom descanso!"
            : "Saída registada às $hora_atual";
    }
    
    // Registrar atividade para o admin
    try {
        $stmtEmployee = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
        $stmtEmployee->execute([$employee_id]);
        $employee = $stmtEmployee->fetch(PDO::FETCH_ASSOC);
        $employee_name = $employee['name'] ?? 'Funcionário';
        
        $titulo = "$employee_name registou " . ($tipo === 'entrada' ? 'entrada' : 'saída') . " às $hora_atual";
        $tipoAtividade = 'info';
        $statusText = ucfirst($tipo);
        
        // Verificar colunas disponíveis
        $hasEmpCol = false; $hasStatusCol = false;
        try {
            $check = $pdo->query("SHOW COLUMNS FROM atividades_recentes LIKE 'employee_id'")->fetch();
            if ($check) $hasEmpCol = true;
            $check2 = $pdo->query("SHOW COLUMNS FROM atividades_recentes LIKE 'status'")->fetch();
            if ($check2) $hasStatusCol = true;
        } catch (Exception $e) { }
        
        if ($hasEmpCol && $hasStatusCol) {
            $stmtAct = $pdo->prepare("INSERT INTO atividades_recentes (title, type, status, timestamp, client_id, employee_id) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmtAct->execute([$titulo, $tipoAtividade, $statusText, $client_id, $employee_id]);
        } elseif ($hasStatusCol) {
            $stmtAct = $pdo->prepare("INSERT INTO atividades_recentes (title, type, status, timestamp, client_id) VALUES (?, ?, ?, NOW(), ?)");
            $stmtAct->execute([$titulo, $tipoAtividade, $statusText, $client_id]);
        } elseif ($hasEmpCol) {
            $stmtAct = $pdo->prepare("INSERT INTO atividades_recentes (title, type, timestamp, client_id, employee_id) VALUES (?, ?, NOW(), ?, ?)");
            $stmtAct->execute([$titulo, $tipoAtividade, $client_id, $employee_id]);
        } else {
            $stmtAct = $pdo->prepare("INSERT INTO atividades_recentes (title, type, timestamp, client_id) VALUES (?, ?, NOW(), ?)");
            $stmtAct->execute([$titulo, $tipoAtividade, $client_id]);
        }
    } catch (Exception $e) {
        error_log("Erro ao registrar atividade: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'hora' => $hora_atual
    ]);
    
} catch (PDOException $e) {
    error_log('registrar_ponto_session.php erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor. Tente novamente.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
