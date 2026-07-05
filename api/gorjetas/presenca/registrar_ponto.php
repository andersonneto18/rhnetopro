<?php
session_start();
require_once '../../config/db_connection.php';

date_default_timezone_set('Europe/Lisbon');

if (!isset($_SESSION['client_id'])) {
    die(json_encode(['success' => false, 'message' => 'Não autorizado']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $tipo = $data['tipo'] ?? '';
    $funcionario_id = (int)($data['funcionario_id'] ?? 0);
    $client_id = (int)$_SESSION['client_id'];

    if (!in_array($tipo, ['entrada', 'saida'], true) || $funcionario_id <= 0) {
        die(json_encode(['success' => false, 'message' => 'Dados inválidos']));
    }

    // O funcionário deve pertencer ao cliente da sessão e estar apto para marcação
    $stmtEmp = $pdo->prepare("SELECT id, status FROM employees WHERE id = ? AND client_id = ? LIMIT 1");
    $stmtEmp->execute([$funcionario_id, $client_id]);
    $employee = $stmtEmp->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        die(json_encode(['success' => false, 'message' => 'Funcionário não encontrado para este cliente']));
    }

    $employeeStatus = mb_strtolower(trim((string)($employee['status'] ?? 'active')));
    if (in_array($employeeStatus, ['inactive', 'inativo', 'ferias', 'férias'], true)) {
        die(json_encode(['success' => false, 'message' => 'Funcionário inativo ou em férias não pode registar ponto']));
    }

    $stmtTurnoAtivo = $pdo->prepare("SELECT id FROM turnos WHERE funcionario_id = ? AND LOWER(COALESCE(status, '')) IN ('ativo', 'active') ORDER BY id DESC LIMIT 1");
    $stmtTurnoAtivo->execute([$funcionario_id]);
    if (!$stmtTurnoAtivo->fetch(PDO::FETCH_ASSOC)) {
        die(json_encode(['success' => false, 'message' => 'Funcionário sem turno ativo não pode registar ponto']));
    }
    
    // Usa a hora atual do servidor (timezone definida acima)
    $hora_atual = date('H:i:s');
    
    // Verifica se já existe registro hoje
    $stmt = $pdo->prepare("SELECT id FROM registros_ponto 
                          WHERE funcionario_id = ? 
                          AND data_registro = CURDATE()");
    $stmt->execute([$funcionario_id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tipo === 'entrada') {
        if (!$registro) {
            // Cria novo registro
            $stmt = $pdo->prepare("INSERT INTO registros_ponto 
                                (funcionario_id, client_id, data_registro, hora_entrada) 
                                VALUES (?, ?, CURDATE(), ?)");
            $stmt->execute([$funcionario_id, $client_id, $hora_atual]);
        } else {
            die(json_encode(['success' => false, 'message' => 'Entrada já registrada hoje']));
        }
    } else if ($tipo === 'saida') {
        if ($registro && !empty($registro['hora_entrada'])) {
            // Atualiza hora de saída
            $stmt = $pdo->prepare("UPDATE registros_ponto 
                                SET hora_saida = ? 
                                WHERE id = ?");
            $stmt->execute([$hora_atual, $registro['id']]);
        } else if ($registro && empty($registro['hora_entrada'])) {
            die(json_encode(['success' => false, 'message' => 'Registo inválido: entrada ausente']));
        } else {
            die(json_encode(['success' => false, 'message' => 'Registre a entrada primeiro']));
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ponto registrado com sucesso',
        'tipo' => $tipo,
        'hora' => date('H:i', strtotime($hora_atual))
    ]);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar ponto']);
}