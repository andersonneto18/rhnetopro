<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autorizado']);
    exit;
}

require_once '../config/db_connection.php';

$employeeId = (int) $_SESSION['employee_id'];
$clientId = (int) ($_SESSION['client_id'] ?? 1);

$notifications = [];
$source = null;

try {
    $stmtNotif = $pdo->prepare(
        'SELECT id, mensagem, data_envio
         FROM notificacoes
         WHERE funcionario_id = ? AND client_id = ?
         ORDER BY data_envio DESC
         LIMIT 20'
    );
    try {
        $stmtNotif->execute([$employeeId, $clientId]);
        $notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($notifications)) {
            $source = 'notificacoes';
        }
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1146) {
            throw $e;
        }
    }

    if (empty($notifications)) {
        try {
            $stmtFallback = $pdo->prepare(
                'SELECT id, title AS mensagem, timestamp AS data_envio
                 FROM atividades_recentes
                 WHERE client_id = ? AND (employee_id = ? OR employee_id IS NULL)
                 ORDER BY timestamp DESC
                 LIMIT 20'
            );
            $stmtFallback->execute([$clientId, $employeeId]);
            $notifications = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($notifications)) {
                $source = 'atividades_recentes';
            }
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1146) {
                throw $e;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'source' => $source,
        'can_delete' => $source === 'notificacoes',
        'notifications' => $notifications
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('employee/get_notifications.php erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar SMS.']);
}
