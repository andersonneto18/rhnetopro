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
$id     = isset($payload['id'])     ? (int)$payload['id']                   : 0;
$motivo = isset($payload['motivo']) ? trim((string)$payload['motivo'])       : '';

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Auto-migration: adicionar coluna se não existir
    $colCheck = $pdo->query("SHOW COLUMNS FROM gorjetas LIKE 'motivo_rejeicao'");
    if ($colCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE gorjetas ADD COLUMN motivo_rejeicao VARCHAR(500) DEFAULT NULL");
    }

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

    if (in_array(strtolower((string) $gorjeta['status']), ['rejeitado', 'rejeitada'], true)) {
        echo json_encode(['success' => true, 'status' => 'rejeitado']);
        exit;
    }

    $update = $pdo->prepare("UPDATE gorjetas SET status = 'rejeitado', motivo_rejeicao = ? WHERE id = ? AND client_id = ?");
    $update->execute([$motivo ?: null, $id, $_SESSION['client_id']]);

    if ($update->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma alteração realizada']);
        exit;
    }

    $valor = isset($gorjeta['valor']) ? (float) $gorjeta['valor'] : 0.0;
    $valorFormatado = number_format($valor, 2, ',', '.');
    $titulo = sprintf('Gorjeta rejeitada: €%s de %s', $valorFormatado, $gorjeta['funcionario_nome'] ?? 'Funcionário');

    logActivity(
        $pdo,
        (int) $_SESSION['client_id'],
        $titulo,
        'warning',
        'Gorjeta',
        (int) $gorjeta['funcionario_id']
    );

    // Notificar o funcionário via caixa de mensagens
    try {
        $notifCheck = $pdo->query("SHOW TABLES LIKE 'notificacoes'");
        if ($notifCheck->rowCount() > 0) {
            $notifMsg  = $motivo
                ? sprintf('A sua gorjeta de €%s foi rejeitada. Motivo: %s', $valorFormatado, $motivo)
                : sprintf('A sua gorjeta de €%s foi rejeitada pelo administrador.', $valorFormatado);
            $stmtNotif = $pdo->prepare("INSERT INTO notificacoes (funcionario_id, client_id, mensagem, data_envio, lida) VALUES (?, ?, ?, NOW(), 0)");
            $stmtNotif->execute([(int)$gorjeta['funcionario_id'], (int)$_SESSION['client_id'], $notifMsg]);
        }
    } catch (Exception $e) {
        error_log('reject_gorjeta notif erro: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'status' => 'rejeitado']);
} catch (Throwable $e) {
    error_log('reject_gorjeta erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor']);
}
