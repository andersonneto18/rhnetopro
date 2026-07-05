<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/db_connection.php';
require_once '../../includes/activity_logger.php';

// Verifica autenticação
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

try {
    $employee_id = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
    $document_type = isset($_POST['document_type']) ? trim($_POST['document_type']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $expiry_date = isset($_POST['expiry_date']) ? trim($_POST['expiry_date']) : '';

    $isValidDate = static function (string $value): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));
        return checkdate($m, $d, $y);
    };

    // Validar employee_id
    if ($employee_id <= 0) {
        throw new Exception('ID do funcionário inválido');
    }

    if ($expiry_date !== '' && !$isValidDate($expiry_date)) {
        throw new Exception('Data de validade inválida');
    }

    // Verificar se o funcionário pertence ao cliente
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND client_id = ?");
    $stmt->execute([$employee_id, $_SESSION['client_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Funcionário não encontrado');
    }

    // Verificar se há arquivo
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Nenhum arquivo foi enviado ou houve erro no upload');
    }

    $file = $_FILES['document'];
    $originalName = $file['name'];
    $fileSize = $file['size'];
    $fileTmpPath = $file['tmp_name'];
    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    // Extensões permitidas
    $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'xls', 'xlsx'];

    if (!in_array($fileExtension, $allowedExtensions, true)) {
        throw new Exception('Formato de arquivo não permitido. Use: ' . implode(', ', $allowedExtensions));
    }

    // Tamanho máximo: 5MB
    if ($fileSize > 5 * 1024 * 1024) {
        throw new Exception('Arquivo muito grande. Tamanho máximo: 5MB');
    }

    // Criar diretório se não existir
    $uploadDir = '../../uploads/documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Gerar nome único
    $newFileName = 'doc_' . $employee_id . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
    $destinationPath = $uploadDir . $newFileName;

    // Mover arquivo
    if (!move_uploaded_file($fileTmpPath, $destinationPath)) {
        throw new Exception('Erro ao salvar o arquivo no servidor');
    }

    $filePath = 'uploads/documents/' . $newFileName;

    // Salvar no banco de dados (compatível com schema com/sem expiry_date)
    $hasExpiryDateColumn = false;
    try {
        $hasExpiryDateColumn = (bool)$pdo->query("SHOW COLUMNS FROM employee_documents LIKE 'expiry_date'")->fetch();
    } catch (Throwable $e) {
    }

    if ($hasExpiryDateColumn) {
        $stmt = $pdo->prepare(
            "INSERT INTO employee_documents
            (employee_id, client_id, document_name, document_type, file_path, file_size, file_extension, uploaded_by, description, expiry_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $employee_id,
            $_SESSION['client_id'],
            $originalName,
            $document_type,
            $filePath,
            $fileSize,
            $fileExtension,
            $_SESSION['user_id'],
            $description,
            $expiry_date !== '' ? $expiry_date : null
        ]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO employee_documents
            (employee_id, client_id, document_name, document_type, file_path, file_size, file_extension, uploaded_by, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $employee_id,
            $_SESSION['client_id'],
            $originalName,
            $document_type,
            $filePath,
            $fileSize,
            $fileExtension,
            $_SESSION['user_id'],
            $description
        ]);
    }

    $documentId = $pdo->lastInsertId();

    try {
        $title = 'Documento anexado: ' . $originalName;
        logActivity(
            $pdo,
            (int)$_SESSION['client_id'],
            $title,
            'info',
            'Documento',
            (int)$employee_id
        );
    } catch (Throwable $logErr) {
        error_log('upload_document log warning: ' . $logErr->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Documento enviado com sucesso',
        'document' => [
            'id' => $documentId,
            'document_name' => $originalName,
            'document_type' => $document_type,
            'file_size' => $fileSize,
            'file_extension' => $fileExtension,
            'file_path' => $filePath,
            'expiry_date' => $expiry_date !== '' ? $expiry_date : null,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    // Se houve erro e o arquivo foi salvo, remover
    if (isset($destinationPath) && file_exists($destinationPath)) {
        unlink($destinationPath);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
