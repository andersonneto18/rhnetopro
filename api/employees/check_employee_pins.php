<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$clientId = (int)$_SESSION['client_id'];

$ids = [];
if (isset($_POST['ids'])) {
    $decoded = json_decode((string)$_POST['ids'], true);
    if (is_array($decoded)) {
        $ids = $decoded;
    }
}

$normalizedIds = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
    return $id > 0;
})));

if (empty($normalizedIds)) {
    echo json_encode(['success' => true, 'employees' => []]);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
    $params = $normalizedIds;
    $params[] = $clientId;

    $stmt = $pdo->prepare("SELECT id, name, pin FROM employees WHERE id IN ($placeholders) AND client_id = ?");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $employees = array_map(static function ($row) {
        $pin = trim((string)($row['pin'] ?? ''));
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'has_pin' => $pin !== '',
        ];
    }, $rows);

    echo json_encode(['success' => true, 'employees' => $employees]);
} catch (Throwable $e) {
    error_log('check_employee_pins.php erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar PINs.']);
}
