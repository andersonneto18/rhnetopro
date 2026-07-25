<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/../../includes/sms_sender.php';

function buildAppLoginUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Este ficheiro vive em /api/employees/; a raiz do projeto fica dois níveis acima.
    $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $projectRoot = preg_replace('#/api/employees$#', '', $scriptDir);
    return $scheme . '://' . $host . $projectRoot . '/app/employee_login.php';
}

function buildManualUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $projectRoot = preg_replace('#/api/employees$#', '', $scriptDir);
    return $scheme . '://' . $host . $projectRoot . '/manualuserfuncionario/manual.html';
}

function personalizarMensagemSms(string $template, array $employee, string $clientName, string $appLoginUrl, string $manualUrl = ''): string
{
    $pin = trim((string)($employee['pin'] ?? ''));
    $email = trim((string)($employee['email'] ?? ''));
    return strtr($template, [
        '{nome}' => (string)($employee['name'] ?? 'Funcionário'),
        '{restaurante}' => $clientName,
        '{link_app}' => $appLoginUrl,
        '{manual_link}' => $manualUrl,
        '{email}' => $email !== '' ? $email : '(peça ao administrador)',
        '{pin}' => $pin !== '' ? $pin : '(peça ao administrador)',
    ]);
}

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

$channels = [];
if (isset($_POST['channels'])) {
    $decodedChannels = json_decode((string)$_POST['channels'], true);
    if (is_array($decodedChannels)) {
        $channels = array_map('strtolower', $decodedChannels);
    }
} elseif (isset($_POST['delivery_mode'])) {
    // Compatibilidade com o formato antigo (um único modo: app/phone/both).
    $legacyMode = strtolower(trim((string)$_POST['delivery_mode']));
    $channels = $legacyMode === 'both' ? ['app', 'phone'] : [$legacyMode];
}
$channels = array_values(array_intersect($channels, ['app', 'phone', 'email']));

if (empty($channels)) {
    $channels = ['app', 'phone'];
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
    $stmt = $pdo->prepare("SELECT id, name, phone, email, pin FROM employees WHERE id IN ($placeholders) AND client_id = ?");
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

    $sendToApp = in_array('app', $channels, true);
    $sendToPhone = in_array('phone', $channels, true);
    $sendToEmail = in_array('email', $channels, true);

    if ($sendToEmail) {
        require_once __DIR__ . '/../../includes/mail_sender.php';
    }

    // Mensagem com marcadores (modelo de boas-vindas): personaliza por funcionário
    // ({nome}, {pin}, {restaurante}, {link_app}) em vez de enviar o mesmo texto a todos.
    $usaPersonalizacao = (bool)preg_match('/\{nome\}|\{pin\}|\{restaurante\}|\{link_app\}/', $message);
    $personalizedMessages = [];
    $personalizedEmailBodies = [];

    if ($usaPersonalizacao) {
        // Novos PINs definidos no modal (para funcionários que ainda não tinham PIN em texto).
        $newPins = [];
        if (isset($_POST['new_pins'])) {
            $decodedPins = json_decode((string)$_POST['new_pins'], true);
            if (is_array($decodedPins)) {
                $newPins = $decodedPins;
            }
        }

        foreach ($newPins as $empIdRaw => $novoPin) {
            $empId = (int)$empIdRaw;
            $novoPin = trim((string)$novoPin);
            if ($empId <= 0 || strlen($novoPin) < 4) {
                continue;
            }
            $stmtPin = $pdo->prepare('UPDATE employees SET pin = ?, pin_hash = ? WHERE id = ? AND client_id = ?');
            $stmtPin->execute([$novoPin, password_hash($novoPin, PASSWORD_DEFAULT), $empId, $client_id]);
            foreach ($validEmployees as &$empRef) {
                if ((int)($empRef['id'] ?? 0) === $empId) {
                    $empRef['pin'] = $novoPin;
                }
            }
            unset($empRef);
        }

        $clientNameStmt = $pdo->prepare('SELECT client_name FROM clients WHERE id = ? LIMIT 1');
        $clientNameStmt->execute([$client_id]);
        $clientName = (string)($clientNameStmt->fetchColumn() ?: 'a equipa');
        $appLoginUrl = buildAppLoginUrl();
        $manualUrl = buildManualUrl();

        foreach ($validEmployees as $employee) {
            $empId = (int)($employee['id'] ?? 0);
            $personalizedMessages[$empId] = personalizarMensagemSms($message, $employee, $clientName, $appLoginUrl, $manualUrl);

            if ($sendToEmail) {
                $pinParaEmail = trim((string)($employee['pin'] ?? ''));
                $personalizedEmailBodies[$empId] = renderBrandedEmailShell(renderWelcomeEmailBody(
                    (string)($employee['name'] ?? 'Funcionário'),
                    $clientName,
                    $pinParaEmail !== '' ? $pinParaEmail : '(peça ao administrador)',
                    $appLoginUrl,
                    (string)($employee['email'] ?? ''),
                    $manualUrl
                ));
            }
        }
    }

    $pdo->beginTransaction();

    // Inserção em lote para reduzir latência
    $valueParts = [];
    $insertParams = [];
    foreach ($validIds as $empId) {
        $valueParts[] = '(?, ?, NOW(), ?, ?)';
        $insertParams[] = $personalizedMessages[$empId] ?? $message;
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

        $notifChannel = count($channels) > 1 ? 'both' : 'app';
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
            $notifParams[] = $personalizedMessages[$empId] ?? $message;
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
        $smsSendResult = sendInfobipSms($validEmployees, $message, $smsConfig, $personalizedMessages);
    }

    $emailSendResult = [
        'configured' => false,
        'sent_count' => 0,
        'failed_count' => 0,
        'skipped_count' => 0,
        'details' => [],
    ];

    if ($sendToEmail) {
        $emailSendResult['configured'] = trim((string)(getenv('SMTP_HOST') ?: '')) !== ''
            && trim((string)(getenv('MAIL_FROM_EMAIL') ?: '')) !== '';

        $emailSubject = $usaPersonalizacao ? 'Bem-vindo(a) à equipa!' : 'Mensagem da equipa';
        $defaultEmailHtml = renderBrandedEmailShell(renderPlainMessageEmailBody($message));

        foreach ($validEmployees as $employee) {
            $empId = (int)($employee['id'] ?? 0);
            $empEmail = trim((string)($employee['email'] ?? ''));

            if ($empEmail === '') {
                $emailSendResult['skipped_count']++;
                $emailSendResult['details'][] = ['employee_id' => $empId, 'status' => 'skipped', 'error' => 'Email ausente.'];
                continue;
            }

            $htmlBody = $personalizedEmailBodies[$empId] ?? $defaultEmailHtml;
            $plainFallback = $personalizedMessages[$empId] ?? $message;
            $emailResult = sendTransactionalEmail($empEmail, (string)($employee['name'] ?? ''), $emailSubject, $htmlBody, $plainFallback);

            if ($emailResult['success']) {
                $emailSendResult['sent_count']++;
                $emailSendResult['details'][] = ['employee_id' => $empId, 'status' => 'sent'];
            } else {
                $emailSendResult['failed_count']++;
                $emailSendResult['details'][] = ['employee_id' => $empId, 'status' => 'failed', 'error' => $emailResult['error']];
            }
        }
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
                $historyParams[] = $personalizedMessages[$employeeId] ?? $message;
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
    $response['channels'] = $channels;
    $response['app_sent_count'] = $sendToApp ? $sent : 0;
    $response['sms'] = $smsSendResult;
    $response['email'] = $emailSendResult;

    $messageParts = [];
    if ($sendToApp) {
        $messageParts[] = sprintf('App: %d notificado(s)', $sent);
    }
    if ($sendToPhone) {
        $messageParts[] = !empty($smsSendResult['configured'])
            ? sprintf(
                'SMS: %d enviado(s), %d falha(s), %d ignorado(s)',
                (int)($smsSendResult['sent_count'] ?? 0),
                (int)($smsSendResult['failed_count'] ?? 0),
                (int)($smsSendResult['skipped_count'] ?? 0)
            )
            : sprintf('SMS não configurado (%s)', (string)($smsSendResult['error'] ?? 'configuração ausente'));
    }
    if ($sendToEmail) {
        $messageParts[] = !empty($emailSendResult['configured'])
            ? sprintf(
                'Email: %d enviado(s), %d falha(s), %d ignorado(s)',
                $emailSendResult['sent_count'],
                $emailSendResult['failed_count'],
                $emailSendResult['skipped_count']
            )
            : 'Email não configurado';
    }

    $response['message'] = $messageParts !== [] ? implode(' | ', $messageParts) : 'Nenhum canal processado.';
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Erro ao enviar notificações: ' . $e->getMessage();
}

echo json_encode($response);
