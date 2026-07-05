<?php
session_start();
require_once '../../config/db_connection.php';

// Sempre retornar JSON
header('Content-Type: application/json; charset=utf-8');

// Verify authentication
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Validate ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID não informado']);
    exit;
}

try {
    // Busca turno apenas do cliente logado
    $stmt = $pdo->prepare("
        SELECT t.*
        FROM turnos t
        INNER JOIN employees e ON t.funcionario_id = e.id
        WHERE t.id = ? AND e.client_id = ?
        LIMIT 1
    ");

    $stmt->execute([$id, $_SESSION['client_id']]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        echo json_encode(['success' => false, 'message' => 'Turno não encontrado']);
        exit;
    }

        $turnoStart = !empty($turno['data_inicio']) ? (string)$turno['data_inicio'] : null;
        $turnoEnd = !empty($turno['data_fim']) ? (string)$turno['data_fim'] : null;

        $stmtLocked = $pdo->prepare(
                "SELECT id
                 FROM turnos_publicacoes
                 WHERE client_id = ?
                     AND LOWER(COALESCE(status, '')) = 'fechado'
                     AND period_start <= COALESCE(?, '9999-12-31')
                     AND period_end >= COALESCE(?, '1000-01-01')
                 LIMIT 1"
        );
        $stmtLocked->execute([$_SESSION['client_id'], $turnoEnd, $turnoStart]);
        $turno['locked'] = $stmtLocked->fetch(PDO::FETCH_ASSOC) ? true : false;

    // Format times to HH:mm for frontend
    if (!empty($turno['horario_inicio'])) {
        $turno['horario_inicio'] = date('H:i', strtotime($turno['horario_inicio']));
    }
    if (!empty($turno['horario_fim'])) {
        $turno['horario_fim'] = date('H:i', strtotime($turno['horario_fim']));
    }

    // ✅ Retorna somente JSON
    echo json_encode($turno);
    exit;

} catch (Exception $e) {

    // Registrar erro no servidor sem exibir para o usuário
    error_log('Erro get_turnos.php: ' . $e->getMessage());

    echo json_encode(['success' => false, 'message' => 'Erro ao buscar turno']);
    exit;
}