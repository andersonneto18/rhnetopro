<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'], $_SESSION['client_id'])) {
    http_response_code(401);
    $response['message'] = 'Nao autorizado';
    echo json_encode($response);
    exit;
}

$clientId = (int) $_SESSION['client_id'];
$mode = trim((string) ($_POST['mode'] ?? ''));
$oldMessage = trim((string) ($_POST['old_message'] ?? ''));
$newMessage = trim((string) ($_POST['new_message'] ?? ''));
$scope = trim((string) ($_POST['scope'] ?? 'selected'));

$ids = [];
if (isset($_POST['ids'])) {
    $decoded = json_decode((string) $_POST['ids'], true);
    if (is_array($decoded)) {
        $ids = $decoded;
    }
}

$normalizedIds = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
    return $id > 0;
})));

if ($oldMessage === '') {
    $response['message'] = 'Parametros invalidos';
    echo json_encode($response);
    exit;
}

if ($scope === 'selected' && empty($normalizedIds)) {
    $response['message'] = 'Stestar apliacao com fluxo real , tipo adm usando apliacao elecione ao menos um funcionario';
    echo json_encode($response);
    exit;
}

if (!in_array($scope, ['selected', 'client'], true)) {
    $response['message'] = 'Escopo invalido';
    echo json_encode($response);
    exit;
}

if (!in_array($mode, ['replace', 'delete'], true)) {
    $response['message'] = 'Modo invalido';
    echo json_encode($response);
    exit;
}

if ($mode === 'replace' && $newMessage === '') {
    $response['message'] = 'Nova mensagem invalida';
    echo json_encode($response);
    exit;
}

$dbHost = 'localhost';
$dbName = 'sistema_cadastro';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $validIds = [];
    $validPlaceholders = '';

    if ($scope === 'selected') {
        $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
        $validateParams = $normalizedIds;
        $validateParams[] = $clientId;
        $stmtValid = $pdo->prepare("SELECT id FROM employees WHERE id IN ($placeholders) AND client_id = ?");
        $stmtValid->execute($validateParams);
        $validIds = $stmtValid->fetchAll(PDO::FETCH_COLUMN);

        if (empty($validIds)) {
            $response['message'] = 'Nenhum funcionario valido para este cliente';
            echo json_encode($response);
            exit;
        }

        $validPlaceholders = implode(',', array_fill(0, count($validIds), '?'));
    }

    $pdo->beginTransaction();

    if ($mode === 'replace') {
        $params = [$newMessage, $clientId];
        if ($scope === 'selected') {
            foreach ($validIds as $id) {
                $params[] = (int) $id;
            }
        }
        $params[] = $oldMessage;

        $sql = "UPDATE notificacoes SET mensagem = ? WHERE client_id = ?";
        if ($scope === 'selected') {
            $sql .= " AND funcionario_id IN ($validPlaceholders)";
        }
        $sql .= " AND mensagem = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $affected = $stmt->rowCount();
        $pdo->commit();

        $response['success'] = true;
        $response['message'] = $affected > 0
            ? "Mensagem atualizada em $affected notificacao(oes)."
            : 'Nenhuma notificacao encontrada para atualizar.';
        echo json_encode($response);
        exit;
    }

    $params = [$clientId];
    if ($scope === 'selected') {
        foreach ($validIds as $id) {
            $params[] = (int) $id;
        }
    }
    $params[] = $oldMessage;

    $sql = "DELETE FROM notificacoes WHERE client_id = ?";
    if ($scope === 'selected') {
        $sql .= " AND funcionario_id IN ($validPlaceholders)";
    }
    $sql .= " AND mensagem = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $deleted = $stmt->rowCount();
    $pdo->commit();

    $response['success'] = true;
    $response['message'] = $deleted > 0
        ? "Mensagem removida de $deleted notificacao(oes)."
        : 'Nenhuma notificacao encontrada para remover.';
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $response['message'] = 'Erro interno ao atualizar SMS em lote';
}

echo json_encode($response);
