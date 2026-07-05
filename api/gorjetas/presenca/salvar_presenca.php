<?php
// Define que a resposta será em formato JSON
header('Content-Type: application/json');

session_start();
require_once '../../config/db_connection.php';
require_once '../../includes/activity_logger.php';

if (!isset($_SESSION['client_id'])) {
    die(json_encode(['success' => false, 'message' => 'Não autorizado']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new Exception('Payload inválido');
    }

    $funcionario_id = (int)($data['id'] ?? 0);
    $statusRaw = isset($data['status']) ? trim((string)$data['status']) : '';
    if (!in_array($statusRaw, ['presente', 'falta'], true)) {
        throw new Exception('Status inválido');
    }
    $status = $statusRaw;
    $client_id = (int)$_SESSION['client_id'];

    if ($funcionario_id <= 0) {
        throw new Exception('Funcionário inválido');
    }

    $stmtFuncionario = $pdo->prepare("SELECT name, status FROM employees WHERE id = ? AND client_id = ?");
    $stmtFuncionario->execute([$funcionario_id, $client_id]);
    $employee = $stmtFuncionario->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        throw new Exception('Funcionário não encontrado para este cliente');
    }

    $employeeStatus = mb_strtolower(trim((string)($employee['status'] ?? 'active')));
    if (in_array($employeeStatus, ['inactive', 'inativo', 'ferias', 'férias'], true)) {
        throw new Exception('Funcionário inativo ou em férias não pode marcar presença');
    }

    $stmtTurnoAtivo = $pdo->prepare("SELECT id FROM turnos WHERE funcionario_id = ? AND LOWER(COALESCE(status, '')) IN ('ativo', 'active') ORDER BY id DESC LIMIT 1");
    $stmtTurnoAtivo->execute([$funcionario_id]);
    if (!$stmtTurnoAtivo->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Funcionário sem turno ativo não pode marcar presença');
    }
    
    // Primeiro verifica se já existe registro para hoje
    $stmt = $pdo->prepare("
        SELECT id 
        FROM presencas 
        WHERE funcionario_id = ? 
        AND DATE(data_registro) = CURDATE()
    ");
    $stmt->execute([$funcionario_id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($registro) {
        // Atualiza registro existente
        $stmt = $pdo->prepare("
            UPDATE presencas 
            SET status = ?, 
                data_registro = NOW() 
            WHERE id = ?
        ");
        $success = $stmt->execute([$status, $registro['id']]);
    } else {
        // Insere novo registro
        $stmt = $pdo->prepare("
            INSERT INTO presencas 
            (funcionario_id, client_id, status, data_registro) 
            VALUES (?, ?, ?, NOW())
        ");
        $success = $stmt->execute([$funcionario_id, $client_id, $status]);
    }

    if ($success) {
        $statusLabel = $status === 'presente' ? 'Presente' : 'Falta';
        $activityType = $status === 'presente' ? 'success' : 'warning';
        logActivity(
            $pdo,
            $client_id,
            $employee['name'] . ' marcado como ' . $statusLabel,
            $activityType,
            $statusLabel,
            $funcionario_id
        );

        echo json_encode([
            'success' => true,
            'message' => 'Status atualizado com sucesso',
            'status' => $status,
            'employee' => $employee['name']
        ]);
    } else {
        throw new Exception('Erro ao salvar status');
    }

} catch (Exception $e) {
    error_log("Erro ao registrar presença: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar status'
    ]);
}
?>