<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'history' => [],
    'message' => ''
];

if (!isset($_SESSION['user_id'], $_SESSION['client_id'])) {
    http_response_code(401);
    $response['message'] = 'Nao autorizado';
    echo json_encode($response);
    exit;
}

$clientId = (int) $_SESSION['client_id'];

$dbHost = 'localhost';
$dbName = 'sistema_cadastro';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $hasSendChannelCol = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM notificacoes LIKE 'send_channel'");
        $hasSendChannelCol = (bool)($checkCol && $checkCol->fetch());
    } catch (Throwable $ignored) {
        $hasSendChannelCol = false;
    }

    $channelSelect = $hasSendChannelCol
        ? "COALESCE(send_channel, 'app') AS send_channel"
        : "'app' AS send_channel";

    $sql = "
        SELECT mensagem,
               data_envio AS envio_data,
               {$channelSelect},
               COUNT(*) AS total_destinatarios,
               SUM(CASE WHEN lida = 1 THEN 1 ELSE 0 END) AS total_visualizadas
        FROM notificacoes
        WHERE client_id = ?
        GROUP BY mensagem, data_envio, send_channel
        ORDER BY envio_data DESC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$clientId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $history = array_map(function ($row) {
        $source = strtolower((string)($row['send_channel'] ?? 'app'));
        if (!in_array($source, ['app', 'phone', 'both'], true)) {
            $source = 'app';
        }
        return [
            'message' => (string) ($row['mensagem'] ?? ''),
            'sent_at' => (string) ($row['envio_data'] ?? ''),
            'recipients' => (int) ($row['total_destinatarios'] ?? 0),
            'viewed_count' => (int) ($row['total_visualizadas'] ?? 0),
            'source' => $source,
            'can_manage' => $source === 'app'
        ];
    }, $rows ?: []);

    $response['success'] = true;
    $response['history'] = $history;
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Erro ao carregar historico de SMS';
}

echo json_encode($response);
