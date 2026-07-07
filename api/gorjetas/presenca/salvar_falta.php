<?php

session_start();
require_once '../../config/db_connection.php';
date_default_timezone_set('Europe/Lisbon');

// Adiciona log para debug
error_log("Iniciando registro de falta");

// Verifica autenticação
if (!isset($_SESSION['client_id'])) {
    error_log("Erro: Usuário não autenticado");
    die(json_encode(['success' => false, 'message' => 'Não autorizado']));
}

// Verifica se recebeu dados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        error_log("Dados recebidos: " . print_r($data, true));
        
        // Validação dos dados
        if (empty($data['id'])) {
            throw new Exception('ID do funcionário não informado');
        }
        
        $funcionario_id = $data['id'];
        $client_id = $_SESSION['client_id'];
        
        // Verifica se já existe falta hoje
        $checkSql = "SELECT COUNT(*) FROM faltas 
                    WHERE funcionario_id = ? 
                    AND client_id = ? 
                    AND DATE(data_falta) = CURDATE()";
        
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$funcionario_id, $client_id]);
        
        if ($checkStmt->fetchColumn() > 0) {
            error_log("Falta já registrada hoje para funcionário $funcionario_id");
            echo json_encode([
                'success' => false,
                'message' => 'Já existe falta registrada para hoje'
            ]);
            exit;
        }
        
        // Insere a falta
        $sql = "INSERT INTO faltas (funcionario_id, client_id, data_falta) 
                VALUES (?, ?, CURDATE())";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([$funcionario_id, $client_id]);

        if ($success) {
            error_log("Falta registrada com sucesso para funcionário $funcionario_id");
        }

        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Falta registrada com sucesso' : 'Erro ao registrar falta',
            'debug' => [
                'funcionario_id' => $funcionario_id,
                'client_id' => $client_id,
                'date' => date('Y-m-d')
            ]
        ]);

    } catch (Exception $e) {
        error_log("Erro ao registrar falta: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro: ' . $e->getMessage()
        ]);
    }
} else {
    error_log("Método inválido recebido: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false,
        'message' => 'Método inválido'
    ]);
}
?>