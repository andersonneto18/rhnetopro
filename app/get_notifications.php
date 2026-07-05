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
$clientId   = (int) ($_SESSION['client_id'] ?? 1);
$offset     = max(0, (int) ($_GET['offset'] ?? 0));
$limit      = 20;

$notifications = [];
$source = null;
$total  = 0;

try {
    try {
        $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM notificacoes WHERE funcionario_id = ? AND client_id = ?');
        $stmtCount->execute([$employeeId, $clientId]);
        $total = (int) $stmtCount->fetchColumn();
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== 1146) throw $e;
    }

    if ($total > 0) {
        $_colConfirm = $pdo->query("SHOW COLUMNS FROM notificacoes LIKE 'requer_confirmacao'")->rowCount() > 0;
        $_notifCols  = $_colConfirm ? 'id, mensagem, data_envio, lida, requer_confirmacao, confirmado_em' : 'id, mensagem, data_envio, lida';
        $stmtNotif = $pdo->prepare(
            "SELECT $_notifCols
             FROM notificacoes
             WHERE funcionario_id = ? AND client_id = ?
             ORDER BY data_envio DESC
             LIMIT ? OFFSET ?"
        );
        try {
            $stmtNotif->execute([$employeeId, $clientId, $limit, $offset]);
            $notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($notifications)) {
                $source = 'notificacoes';
            }
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1146) {
                throw $e;
            }
        }
    }

    if (empty($notifications)) {
        try {
            $stmtFallback = $pdo->prepare(
                'SELECT id, title AS mensagem, timestamp AS data_envio
                 FROM atividades_recentes
                 WHERE client_id = ? AND employee_id = ?
                 ORDER BY timestamp DESC
                 LIMIT 20'
            );
            $stmtFallback->execute([$clientId, $employeeId]);
            $notifications = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($notifications)) {
                $source = 'atividades_recentes';
                $total  = count($notifications);
            }
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1146) {
                throw $e;
            }
        }
    }

    $unreadCount = $source === 'notificacoes'
        ? count(array_filter($notifications, fn($n) => empty($n['lida'])))
        : 0;

    echo json_encode([
        'success'       => true,
        'source'        => $source,
        'can_delete'    => $source === 'notificacoes',
        'unread_count'  => $unreadCount,
        'notifications' => $notifications,
        'total'         => $total,
        'offset'        => $offset,
    ]);
} catch (Throwable $e) {
    error_log('get_notifications.php erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar SMS.'
    ]);
}
