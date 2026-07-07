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

    $stmtTurnoAtivo = $pdo->prepare("SELECT id FROM turnos WHERE funcionario_id = ? AND LOWER(COALESCE(status, '')) IN ('ativo', 'active') ORDER BY id DESC LIMIT 1");
    $stmtTurnoAtivo->execute([$employee_id]);
    if (!$stmtTurnoAtivo->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Funcionário sem turno ativo não pode marcar presença.');
    }
    
    // Buscar registo em aberto hoje (entrada sem saída) — suporte a múltiplos períodos por dia
    $stmt = $pdo->prepare("
        SELECT * FROM registros_ponto
        WHERE funcionario_id = ? AND DATE(data_registro) = ?
          AND hora_entrada IS NOT NULL AND hora_entrada != ''
          AND (hora_saida IS NULL OR hora_saida = '')
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
        
        $titulo = "$employee_name registrou " . ($tipo === 'entrada' ? 'entrada' : 'saída') . " às $hora_atual";
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
