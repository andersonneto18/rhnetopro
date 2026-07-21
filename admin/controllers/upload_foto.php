<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

if (!isset($_FILES['profile_photo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhum ficheiro enviado']);
    exit;
}

$file = $_FILES['profile_photo'];

if ($file['error'] !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Erro no upload']);
    exit;
}

$allowed = ['jpg', 'jpeg', 'png', 'webp'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'Formato não permitido']);
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) { // 2MB
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Ficheiro muito grande (máx: 2MB)']);
    exit;
}

$folder = '../../uploads/profile/';
if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
}

$filename = 'user_' . $_SESSION['user_id'] . '_' . uniqid() . '.' . $ext;
$path = $folder . $filename;
$dbPath = 'uploads/profile/' . $filename;

if (move_uploaded_file($file['tmp_name'], $path)) {
    // Salvar no banco de dados
    try {
        // Verificar se a coluna existe
        $check = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'profile_picture'");
        $numRows = ($check instanceof mysqli_result) ? $check->num_rows : 0;
        if ($numRows == 0) {
            $conn->query("ALTER TABLE usuarios ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL AFTER client_id");
        }
        $stmt = $conn->prepare("UPDATE usuarios SET profile_picture = ? WHERE id = ?");
        $stmt->bind_param("si", $dbPath, $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->affected_rows > 0 || $stmt->errno == 0) {
            // Fechar conexão apenas se for MySQLi
            if ($conn instanceof mysqli) {
                $conn->close();
            }
            // Atualizar sessão
            $_SESSION['user_preferences']['profile_picture'] = '../' . $dbPath;
            echo json_encode([
                'success' => true, 
                'message' => 'Foto atualizada com sucesso!',
                'path' => '../' . $dbPath
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Nenhuma linha atualizada. Verifique se o utilizador existe.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao guardar na base de dados: ' . $e->getMessage()]);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao guardar ficheiro no servidor']);
}
?>
