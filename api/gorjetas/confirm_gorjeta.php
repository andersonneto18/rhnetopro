<?php
session_start();
require_once '../../config/db_connection.php';
require_once '../../includes/activity_logger.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$id = isset($payload['id']) ? (int) $payload['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT g.id, g.funcionario_id, g.valor, g.status, g.client_id, g.data, e.name AS funcionario_nome
        FROM gorjetas g
        INNER JOIN employees e ON g.funcionario_id = e.id
        WHERE g.id = ? AND g.client_id = ?
        LIMIT 1
    ');
    $stmt->execute([$id, $_SESSION['client_id']]);
    $gorjeta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gorjeta) {
        echo json_encode(['success' => false, 'message' => 'Gorjeta não encontrada']);
        exit;
    }

    if (strtolower((string) $gorjeta['status']) === 'pago') {
        echo json_encode(['success' => true, 'status' => 'pago']);
        exit;
    }

    $update = $pdo->prepare("UPDATE gorjetas SET status = 'pago' WHERE id = ? AND client_id = ?");
    $update->execute([$id, $_SESSION['client_id']]);

    if ($update->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma alteração realizada']);
        exit;
    }

    $valor = isset($gorjeta['valor']) ? (float) $gorjeta['valor'] : 0.0;
    $valorFormatado = number_format($valor, 2, ',', '.');
    $titulo = sprintf('Gorjeta confirmada: €%s para %s', $valorFormatado, $gorjeta['funcionario_nome'] ?? 'Funcionário');

    logActivity(
        $pdo,
        (int) $_SESSION['client_id'],
        $titulo,
        'success',
        'Gorjeta',
        (int) $gorjeta['funcionario_id']
    );

    // Notificar o funcionário via caixa de mensagens
    try {
        $notifCheck = $pdo->query("SHOW TABLES LIKE 'notificacoes'");
        if ($notifCheck->rowCount() > 0) {
            $notifMsg   = sprintf('A sua gorjeta de €%s foi confirmada e marcada como paga.', $valorFormatado);
            $stmtNotif  = $pdo->prepare("INSERT INTO notificacoes (funcionario_id, client_id, mensagem, data_envio, lida) VALUES (?, ?, ?, NOW(), 0)");
            $stmtNotif->execute([(int)$gorjeta['funcionario_id'], (int)$_SESSION['client_id'], $notifMsg]);
        }
    } catch (Exception $e) {
        error_log('confirm_gorjeta notif erro: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'status' => 'pago']);
} catch (Throwable $e) {
    error_log('confirm_gorjeta erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor']);
}
