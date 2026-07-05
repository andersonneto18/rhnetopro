<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

require_once '../config/db_connection.php';

$employeeId = (int)$_SESSION['employee_id'];
$clientId   = (int)($_SESSION['client_id'] ?? 0);

try {
    if ($clientId <= 0) {
        $s = $pdo->prepare('SELECT client_id FROM employees WHERE id = ? LIMIT 1');
        $s->execute([$employeeId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        $clientId = (int)($r['client_id'] ?? 0);
        if ($clientId > 0) $_SESSION['client_id'] = $clientId;
    }

    $updates = [];
    $params  = [];

    // Phone update
    if (isset($_POST['phone'])) {
        $phone = trim($_POST['phone']);
        // Basic sanitisation: digits, spaces, +, -, (, )
        $phone = preg_replace('/[^\d\s\+\-\(\)]/', '', $phone);
        $phone = substr($phone, 0, 20);
        $updates[] = 'phone = ?';
        $params[]  = $phone;
    }

    // Avatar upload
    $avatarPath = null;
    if (!empty($_FILES['avatar']['tmp_name'])) {
        $file    = $_FILES['avatar'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowed, true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Tipo de ficheiro não suportado. Use JPG, PNG ou WebP.']);
            exit;
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Ficheiro demasiado grande (máx. 2 MB).']);
            exit;
        }

        $uploadDir = __DIR__ . '/uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
        $filename = 'emp_' . $employeeId . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao guardar imagem.']);
            exit;
        }

        // Delete old avatar if exists
        $stmtOld = $pdo->prepare('SELECT profile_picture FROM employees WHERE id = ? AND client_id = ? LIMIT 1');
        $stmtOld->execute([$employeeId, $clientId]);
        $oldPic = $stmtOld->fetchColumn();
        if ($oldPic && strpos($oldPic, 'emp_') !== false) {
            $oldFile = $uploadDir . basename($oldPic);
            if (is_file($oldFile)) unlink($oldFile);
        }

        $avatarPath = 'uploads/avatars/' . $filename;
        $updates[] = 'profile_picture = ?';
        $params[]  = $avatarPath;
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'Nada para actualizar.']);
        exit;
    }

    $params[] = $employeeId;
    $params[] = $clientId;
    $pdo->prepare('UPDATE employees SET ' . implode(', ', $updates) . ' WHERE id = ? AND client_id = ?')
        ->execute($params);

    $response = ['success' => true, 'message' => 'Perfil actualizado.'];
    if ($avatarPath !== null) {
        $response['avatar_url'] = $avatarPath;
    }
    echo json_encode($response);
} catch (Throwable $e) {
    error_log('update_profile erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao actualizar perfil.']);
}
