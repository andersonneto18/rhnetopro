<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'message' => '',
    'notifications' => [],
    'total' => 0,
    'deleted' => 0
];

if (!isset($_SESSION['user_id'], $_SESSION['client_id'])) {
    http_response_code(401);
    $response['message'] = 'Nao autorizado';
    echo json_encode($response);
    exit;
}

$clientId = (int) $_SESSION['client_id'];
$action = trim((string) ($_REQUEST['action'] ?? 'list_received'));

$dbHost = 'localhost';
$dbName = 'sistema_cadastro';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($action === 'delete_selected') {
        $idsRaw = isset($_POST['ids']) ? (string) $_POST['ids'] : '[]';
        $ids = json_decode($idsRaw, true);
        if (!is_array($ids)) {
            $ids = [];
        }

        $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));

        if (empty($normalizedIds)) {
            $response['message'] = 'Nenhuma notificacao selecionada';
            echo json_encode($response);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
        $params = [$clientId];
        foreach ($normalizedIds as $id) {
            $params[] = $id;
        }

        $sql = "DELETE FROM notificacoes WHERE client_id = ? AND id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $response['success'] = true;
        $response['deleted'] = $stmt->rowCount();
        $response['message'] = $response['deleted'] > 0
            ? 'Notificacoes selecionadas removidas com sucesso.'
            : 'Nenhuma notificacao removida.';

        echo json_encode($response);
        exit;
    }

    if ($action === 'delete_all') {
        $stmt = $pdo->prepare('DELETE FROM notificacoes WHERE client_id = ?');
        $stmt->execute([$clientId]);

        $response['success'] = true;
        $response['deleted'] = $stmt->rowCount();
        $response['message'] = $response['deleted'] > 0
            ? 'Todas as notificacoes foram removidas.'
            : 'Nao ha notificacoes para remover.';

        echo json_encode($response);
        exit;
    }

    if ($action === 'sent_batch_status') {
        $message = trim((string) ($_POST['message'] ?? ''));
        $sentAt = trim((string) ($_POST['sent_at'] ?? ''));

        if ($message === '' || $sentAt === '') {
            $response['message'] = 'Parametros invalidos para consulta do envio';
            echo json_encode($response);
            exit;
        }

        $sql = 'SELECT n.id,
                       n.funcionario_id,
                       n.lida,
                       COALESCE(e.name, "Funcionario removido") AS employee_name
                FROM notificacoes n
                LEFT JOIN employees e ON e.id = n.funcionario_id AND e.client_id = n.client_id
                WHERE n.client_id = ?
                  AND n.mensagem = ?
                  AND n.data_envio = ?
                ORDER BY employee_name ASC, n.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clientId, $message, $sentAt]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $response['success'] = true;
        $response['message'] = 'Status do envio carregado';
        $response['notifications'] = array_map(function ($row) {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'employee_id' => (int) ($row['funcionario_id'] ?? 0),
                'employee_name' => (string) ($row['employee_name'] ?? ''),
                'read' => (int) ($row['lida'] ?? 0) === 1
            ];
        }, $rows);
        $response['total'] = count($rows);

        echo json_encode($response);
        exit;
    }

    if ($action === 'delete_sent_batch') {
        $message = trim((string) ($_POST['message'] ?? ''));
        $sentAt = trim((string) ($_POST['sent_at'] ?? ''));
        $sendChannel = strtolower(trim((string)($_POST['send_channel'] ?? 'app')));

        if ($message === '' || $sentAt === '') {
            $response['message'] = 'Parametros invalidos para exclusao do envio';
            echo json_encode($response);
            exit;
        }

        if ($sendChannel !== 'app') {
            http_response_code(403);
            $response['message'] = 'Apenas envios feitos pelo canal app podem ser removidos para todos.';
            echo json_encode($response);
            exit;
        }

        $hasSendChannelCol = false;
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM notificacoes LIKE 'send_channel'");
            $hasSendChannelCol = (bool)($checkCol && $checkCol->fetch());
        } catch (Throwable $ignored) {
            $hasSendChannelCol = false;
        }

        if ($hasSendChannelCol) {
            $stmt = $pdo->prepare("DELETE FROM notificacoes WHERE client_id = ? AND mensagem = ? AND data_envio = ? AND COALESCE(send_channel, 'app') = 'app'");
            $stmt->execute([$clientId, $message, $sentAt]);
        } else {
            $stmt = $pdo->prepare('DELETE FROM notificacoes WHERE client_id = ? AND mensagem = ? AND data_envio = ?');
            $stmt->execute([$clientId, $message, $sentAt]);
        }

        $response['success'] = true;
        $response['deleted'] = $stmt->rowCount();
        $response['message'] = $response['deleted'] > 0
            ? 'Envio removido com sucesso.'
            : 'Nenhum registro encontrado para este envio.';

        echo json_encode($response);
        exit;
    }

    $listSql = 'SELECT n.id,
                       n.funcionario_id,
                       n.mensagem,
                       n.data_envio,
                       n.lida,
                       COALESCE(e.name, "Funcionario removido") AS employee_name
                FROM notificacoes n
                LEFT JOIN employees e ON e.id = n.funcionario_id AND e.client_id = n.client_id
                WHERE n.client_id = ?';

    $params = [$clientId];
    if ($action === 'list_received') {
        // "Recebidas" no painel admin = notificações entregues aos funcionários,
        // independentemente de já terem sido lidas no app do funcionário.
        $listSql .= ' AND n.funcionario_id IS NOT NULL';
    }

    $listSql .= ' ORDER BY n.data_envio DESC, n.id DESC LIMIT 1000';

    $stmt = $pdo->prepare($listSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notifications = array_map(function ($row) {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'employee_id' => (int) ($row['funcionario_id'] ?? 0),
            'employee_name' => (string) ($row['employee_name'] ?? ''),
            'message' => (string) ($row['mensagem'] ?? ''),
            'sent_at' => (string) ($row['data_envio'] ?? ''),
            'read' => (int) ($row['lida'] ?? 0) === 1
        ];
    }, $rows ?: []);

    $response['success'] = true;
    $response['notifications'] = $notifications;
    $response['total'] = count($notifications);
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Erro ao processar notificacoes';
}

echo json_encode($response);
