<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/../../includes/sms_sender.php';

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    http_response_code(401);
    $response['message'] = 'Não autorizado';
    echo json_encode($response);
    exit;
}

$client_id = (int) $_SESSION['client_id'];

// obtém parâmetros
$ids = [];
if (isset($_POST['ids'])) {
    $decoded = json_decode($_POST['ids'], true);
    if (is_array($decoded)) {
        $ids = $decoded;
    }
}
$message = trim($_POST['message'] ?? '');
$deliveryMode = strtolower(trim((string)($_POST['delivery_mode'] ?? 'both')));

if (!in_array($deliveryMode, ['app', 'phone', 'both'], true)) {
    $deliveryMode = 'both';
}

if (empty($ids) || $message === '') {
    $response['message'] = 'IDs ou mensagem inválidos';
    echo json_encode($response);
    exit;
}

try {
    // carregar helper de atividades
    require_once __DIR__ . '/../../includes/activity_logger.php';
    $smsConfig = require __DIR__ . '/../../config/sms_config.php';

    $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
        return $id > 0;
    })));

    if (empty($normalizedIds)) {
        $response['message'] = 'Nenhum funcionário válido selecionado';
        echo json_encode($response);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
    $params = $normalizedIds;
    $params[] = $client_id;

    // valida todos os IDs do cliente em uma única query
    $stmt = $pdo->prepare("SELECT id, name, phone FROM employees WHERE id IN ($placeholders) AND client_id = ?");
    $stmt->execute($params);
    $validEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (empty($validEmployees)) {
        $response['message'] = 'Nenhum funcionário encontrado para este cliente';
        echo json_encode($response);
        exit;
    }

    $validIds = array_map(static function ($employee) {
        return (int)($employee['id'] ?? 0);
    }, $validEmployees);

    $sendToApp = in_array($deliveryMode, ['app', 'both'], true);
    $sendToPhone = in_array($deliveryMode, ['phone', 'both'], true);

    $pdo->beginTransaction();

    // Inserção em lote para reduzir latência
    $valueParts = [];
    $insertParams = [];
    foreach ($validIds as $empId) {
        $valueParts[] = '(?, ?, NOW(), ?, ?)';
        $insertParams[] = $message;
        $insertParams[] = 'info';
        $insertParams[] = $client_id;
        $insertParams[] = (int)$empId;
    }

    $sql = 'INSERT INTO atividades_recentes (title, type, timestamp, client_id, employee_id) VALUES ' . implode(', ', $valueParts);
    $insertStmt = $pdo->prepare($sql);
    $insertStmt->execute($insertParams);

    // Compatibilidade: o portal app/ lê SMS da tabela notificacoes.
    $hasNotificacoes = false;
    try {
        $checkNotif = $pdo->query("SHOW TABLES LIKE 'notificacoes'");
        $hasNotificacoes = $checkNotif && $checkNotif->fetch();
    } catch (PDOException $ignored) {
        $hasNotificacoes = false;
    }

    if ($sendToApp && $hasNotificacoes) {
        $hasSendChannelCol = false;
        try {
            $checkSendChannel = $pdo->query("SHOW COLUMNS FROM notificacoes LIKE 'send_channel'");
            $hasSendChannelCol = (bool)($checkSendChannel && $checkSendChannel->fetch());
            if (!$hasSendChannelCol) {
                $pdo->exec("ALTER TABLE notificacoes ADD COLUMN send_channel VARCHAR(20) NOT NULL DEFAULT 'app'");
                $hasSendChannelCol = true;
            }
        } catch (Throwable $ignored) {
            $hasSendChannelCol = false;
        }

        $notifChannel = $deliveryMode === 'both' ? 'both' : 'app';
        $notifParts = [];
        $notifParams = [];
        foreach ($validIds as $empId) {
            if ($hasSendChannelCol) {
                $notifParts[] = '(?, ?, ?, NOW(), 0, ?)';
            } else {
                $notifParts[] = '(?, ?, ?, NOW(), 0)';
            }
            $notifParams[] = (int)$empId;
            $notifParams[] = $client_id;
            $notifParams[] = $message;
            if ($hasSendChannelCol) {
                $notifParams[] = $notifChannel;
            }
        }

        $sqlNotif = $hasSendChannelCol
            ? 'INSERT INTO notificacoes (funcionario_id, client_id, mensagem, data_envio, lida, send_channel) VALUES ' . implode(', ', $notifParts)
            : 'INSERT INTO notificacoes (funcionario_id, client_id, mensagem, data_envio, lida) VALUES ' . implode(', ', $notifParts);
        $insertNotif = $pdo->prepare($sqlNotif);
        $insertNotif->execute($notifParams);
    }

    $pdo->commit();

    $sent = count($validIds);

    $smsSendResult = [
        'configured' => false,
        'sent_count' => 0,
        'failed_count' => 0,
        'skipped_count' => 0,
        'provider' => 'infobip',
        'details' => [],
        'error' => null,
    ];

    if ($sendToPhone) {
        $smsSendResult = sendInfobipSms($validEmployees, $message, $smsConfig);
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sms_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                employee_id INT NOT NULL,
                phone VARCHAR(20) DEFAULT NULL,
                message TEXT NOT NULL,
                provider VARCHAR(50) DEFAULT NULL,
                provider_message_id VARCHAR(120) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                error_message TEXT DEFAULT NULL,
                sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sms_history_client_employee (client_id, employee_id),
                KEY idx_sms_history_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        if ($sendToPhone && !empty($smsSendResult['details']) && is_array($smsSendResult['details'])) {
            $historyParts = [];
            $historyParams = [];
            foreach ($smsSendResult['details'] as $detail) {
                $employeeId = (int)($detail['employee_id'] ?? 0);
                if ($employeeId <= 0) {
                    continue;
                }

                $historyParts[] = '(?, ?, ?, ?, ?, ?, ?, ?, NOW())';
                $historyParams[] = $client_id;
                $historyParams[] = $employeeId;
                $historyParams[] = (string)($detail['phone'] ?? '');
                $historyParams[] = $message;
                $historyParams[] = (string)($smsSendResult['provider'] ?? 'infobip');
                $historyParams[] = isset($detail['provider_message_id']) ? (string)$detail['provider_message_id'] : null;
                $historyParams[] = (string)($detail['status'] ?? 'pending');
                $historyParams[] = isset($detail['error']) ? (string)$detail['error'] : null;
            }

            if (!empty($historyParts)) {
                $sqlHistory = 'INSERT INTO sms_history (client_id, employee_id, phone, message, provider, provider_message_id, status, error_message, sent_at) VALUES ' . implode(', ', $historyParts);
                $stmtHistory = $pdo->prepare($sqlHistory);
                $stmtHistory->execute($historyParams);
            }
        }
    } catch (Throwable $historyError) {
        error_log('notify_employees.php sms_history erro: ' . $historyError->getMessage());
    }

    $response['success'] = true;
    $response['delivery_mode'] = $deliveryMode;
    $response['app_sent_count'] = $sendToApp ? $sent : 0;
    $response['sms'] = $smsSendResult;

    if ($deliveryMode === 'app') {
        $response['message'] = sprintf(
            'Notificação no app enviada para %d funcionário(s).',
            $sent
        );
    } elseif ($deliveryMode === 'phone' && !empty($smsSendResult['configured'])) {
        $response['message'] = sprintf(
            'SMS real enviado para %d funcionário(s): %d enviado(s), %d falha(s), %d ignorado(s).',
            $sent,
            (int)($smsSendResult['sent_count'] ?? 0),
            (int)($smsSendResult['failed_count'] ?? 0),
            (int)($smsSendResult['skipped_count'] ?? 0)
        );
    } elseif ($deliveryMode === 'both' && !empty($smsSendResult['configured'])) {
        $response['message'] = sprintf(
            'Notificações internas enviadas para %d funcionário(s). SMS real: %d enviado(s), %d falha(s), %d ignorado(s).',
            $sent,
            (int)($smsSendResult['sent_count'] ?? 0),
            (int)($smsSendResult['failed_count'] ?? 0),
            (int)($smsSendResult['skipped_count'] ?? 0)
        );
    } elseif ($deliveryMode === 'phone') {
        $response['message'] = sprintf(
            'SMS real não configurado para %d funcionário(s): %s',
            $sent,
            (string)($smsSendResult['error'] ?? 'configuração ausente')
        );
    } else {
        $response['message'] = sprintf(
            'Notificações internas enviadas para %d funcionário(s). SMS real não configurado: %s',
            $sent,
            (string)($smsSendResult['error'] ?? 'configuração ausente')
        );
    }
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Erro ao enviar notificações: ' . $e->getMessage();
}

echo json_encode($response);
