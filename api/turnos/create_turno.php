<?php
session_start();
require_once '../../config/db_connection.php';
require_once '../../includes/activity_logger.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$requiredFields = ['funcionario_id', 'turno_tipo', 'horario_inicio', 'horario_fim', 'dias_semana', 'escala', 'status'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Campo obrigatório ausente: $field"]);
        exit;
    }
}

// verificar funcionário pertence ao cliente
$stmt = $pdo->prepare("SELECT id, name FROM employees WHERE id = ? AND client_id = ?");
$stmt->execute([(int)$input['funcionario_id'], $_SESSION['client_id']]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$employee) {
    echo json_encode(['success'=>false,'message'=>'Funcionário inválido ou não pertence ao seu cliente.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO turnos (funcionario_id, turno_tipo, horario_inicio, horario_fim, dias_semana, escala, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $input['funcionario_id'],
        $input['turno_tipo'],
        $input['horario_inicio'],
        $input['horario_fim'],
        $input['dias_semana'],
        $input['escala'],
        $input['status']
    ]);

    $turnoId = $pdo->lastInsertId();
    $horario = $input['horario_inicio'] . ' - ' . $input['horario_fim'];
    $title = sprintf(
        'Novo turno para %s (%s, %s)',
        $employee['name'],
        $input['turno_tipo'],
        $horario
    );
    logActivity(
        $pdo,
        (int)$_SESSION['client_id'],
        $title,
        'success',
        'Turno',
        (int)$input['funcionario_id']
    );

    echo json_encode(['success' => true, 'id' => $turnoId]);
} catch (Exception $e) {
    error_log('create_turno erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar turno.']);
}