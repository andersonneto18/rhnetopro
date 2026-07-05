<?php
// admin/controllers/limpar_historico_solicitacoes.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

require_once '../../config/db_connection.php';

try {
    // Limpa histórico de justificativas, presenças e gorjetas decididas
    $clientId = (int)($_SESSION['client_id'] ?? 0);
    if (!$clientId) throw new Exception('Cliente não identificado.');

    $tableExists = static function (PDO $pdo, string $tableName): bool {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetchColumn();
    };

    $deleteByStatus = static function (PDO $pdo, string $tableName, array $statuses, int $clientId): void {
        if (empty($statuses)) {
            return;
        }
        $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
        $sql = "DELETE FROM {$tableName} WHERE status IN ({$placeholders}) AND client_id = ?";
        $stmt = $pdo->prepare($sql);
        $params = array_merge($statuses, [$clientId]);
        $stmt->execute($params);
    };

    // Justificativas
    if ($tableExists($pdo, 'justificativas_presenca')) {
        $deleteByStatus($pdo, 'justificativas_presenca', ['aprovada', 'rejeitada'], $clientId);
    } elseif ($tableExists($pdo, 'justificativas')) {
        // Compatibilidade com esquemas legados.
        $deleteByStatus($pdo, 'justificativas', ['aprovada', 'rejeitada'], $clientId);
    }

    // Presenças
    if ($tableExists($pdo, 'presencas')) {
        $deleteByStatus($pdo, 'presencas', ['confirmado', 'invalidado'], $clientId);
    }

    // Gorjetas
    if ($tableExists($pdo, 'gorjetas')) {
        $deleteByStatus(
            $pdo,
            'gorjetas',
            ['pago', 'paid', 'confirmado', 'aprovado', 'rejeitado', 'rejeitada', 'cancelado', 'cancelada'],
            $clientId
        );
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
