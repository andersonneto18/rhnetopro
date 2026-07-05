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
    $data = json_decode(file_get_contents('php://input'), true);
    $document_id = isset($data['document_id']) ? (int)$data['document_id'] : 0;
    
    if ($document_id <= 0) {
        throw new Exception('ID do documento inválido');
    }
    
    // Buscar documento (verificar se pertence ao cliente)
    $stmt = $pdo->prepare("SELECT file_path, employee_id, document_name FROM employee_documents WHERE id = ? AND client_id = ?");
    $stmt->execute([$document_id, $_SESSION['client_id']]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception('Documento não encontrado');
    }
    
    // Deletar do banco de dados
    $stmt = $pdo->prepare("DELETE FROM employee_documents WHERE id = ? AND client_id = ?");
    $stmt->execute([$document_id, $_SESSION['client_id']]);
    
    // Deletar arquivo físico
    $filePath = '../../' . $document['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    try {
        $title = 'Documento removido: ' . ($document['document_name'] ?? ('ID ' . $document_id));
        logActivity(
            $pdo,
            (int)$_SESSION['client_id'],
            $title,
            'warning',
            'Documento',
            (int)($document['employee_id'] ?? 0)
        );
    } catch (Throwable $logErr) {
        error_log('delete_document log warning: ' . $logErr->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Documento excluído com sucesso'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
