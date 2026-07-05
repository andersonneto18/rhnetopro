<?php
session_start();
require_once '../../config/db_connection.php';
header('Content-Type: application/json; charset=utf-8');

// 🔹 Verifica sessão
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

// 🔹 Lê o JSON do body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID ausente ou JSON inválido.']);
    exit;
}

$id = (int)$input['id'];
$client_id = (int)$_SESSION['client_id'];

// 🔹 Validação dos campos obrigatórios
$required = ['funcionario_id', 'turno_tipo', 'horario_inicio', 'horario_fim', 'dias_semana', 'escala', 'status'];
foreach ($required as $f) {
    if (!isset($input[$f]) || trim($input[$f]) === '') {
        echo json_encode(['success' => false, 'message' => "Campo obrigatório ausente: $f"]);
        exit;
    }
}

try {
    // 🔹 Garante que o turno pertence ao cliente logado
    $stmt = $pdo->prepare("
        SELECT t.id 
        FROM turnos t
        INNER JOIN employees e ON e.id = t.funcionario_id
        WHERE t.id = ? AND e.client_id = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $client_id]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        echo json_encode(['success' => false, 'message' => 'Turno não encontrado ou não pertence ao seu cliente.']);
        exit;
    }

    // 🔹 Atualiza o turno
    $stmt = $pdo->prepare("
        UPDATE turnos 
        SET 
            funcionario_id = :funcionario_id,
            turno_tipo = :turno_tipo,
            horario_inicio = :horario_inicio,
            horario_fim = :horario_fim,
            dias_semana = :dias_semana,
            escala = :escala,
            status = :status
        WHERE id = :id
    ");
    $ok = $stmt->execute([
        ':funcionario_id' => (int)$input['funcionario_id'],
        ':turno_tipo'     => trim($input['turno_tipo']),
        ':horario_inicio' => trim($input['horario_inicio']),
        ':horario_fim'    => trim($input['horario_fim']),
        ':dias_semana'    => trim($input['dias_semana']),
        ':escala'         => trim($input['escala']),
        ':status'         => trim($input['status']),
        ':id'             => $id
    ]);

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Turno atualizado com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar turno.']);
    }

} catch (Exception $e) {
    error_log('update_turno erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor.']);
}

