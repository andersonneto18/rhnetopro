<?php
session_start();
require_once '../../config/db_connection.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido']);
    exit;
}

$clientId = (int)$_SESSION['client_id'];
$userId = (int)$_SESSION['user_id'];
$justificativaId = (int)($_POST['justificativa_id'] ?? 0);
$decisionRaw = mb_strtolower(trim((string)($_POST['decision'] ?? '')));
$adminObs = trim((string)($_POST['admin_observacao'] ?? ''));
$returnUrl = trim((string)($_POST['return_url'] ?? ''));
$csrfToken = (string)($_POST['csrf_token'] ?? '');

if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfToken)) {
    if ($returnUrl !== '') {
        header('Location: ' . $returnUrl . '&review=csrf');
        exit;
    }
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

if ($justificativaId <= 0 || !in_array($decisionRaw, ['aprovar', 'rejeitar'], true)) {
    if ($returnUrl !== '') {
        header('Location: ' . $returnUrl . '&review=invalid');
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Dados invalidos']);
    exit;
}

$newStatus = $decisionRaw === 'aprovar' ? 'aprovada' : 'rejeitada';

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS justificativas_presenca (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            employee_id INT NOT NULL,
            data_ocorrencia DATE NOT NULL,
            tipo ENUM('falta','atraso') NOT NULL,
            motivo TEXT NOT NULL,
            anexo_path VARCHAR(255) NULL,
            status ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
            admin_observacao TEXT NULL,
            decidido_por INT NULL,
            decidido_em DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_justificativas_client_status (client_id, status),
            KEY idx_justificativas_employee_data (employee_id, data_ocorrencia)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $stmtCheck = $pdo->prepare('SELECT id FROM justificativas_presenca WHERE id = ? AND client_id = ? LIMIT 1');
    $stmtCheck->execute([$justificativaId, $clientId]);
    if (!$stmtCheck->fetch(PDO::FETCH_ASSOC)) {
        if ($returnUrl !== '') {
            header('Location: ' . $returnUrl . '&review=notfound');
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Justificativa nao encontrada']);
        exit;
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE justificativas_presenca
         SET status = ?, admin_observacao = ?, decidido_por = ?, decidido_em = NOW()
         WHERE id = ? AND client_id = ?'
    );
    $stmtUpdate->execute([$newStatus, $adminObs !== '' ? $adminObs : null, $userId, $justificativaId, $clientId]);

    if ($returnUrl !== '') {
        header('Location: ' . $returnUrl . '&review=ok');
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Justificativa atualizada com sucesso']);
} catch (Throwable $e) {
    error_log('Erro em review_justificativa.php: ' . $e->getMessage());

    if ($returnUrl !== '') {
        header('Location: ' . $returnUrl . '&review=error');
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar justificativa']);
}
