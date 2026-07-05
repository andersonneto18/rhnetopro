<?php
// ✅ VALIDAÇÃO DE SEGURANÇA - Inicia a sessão e limpa buffers
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Carrega a conexão com a base de dados
require_once '../../config/db_connection.php';

// ✅ Verifica se a conexão PDO existe
if (!isset($pdo)) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro: Conexão com base de dados não disponível.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ✅ VALIDAÇÃO 1: Verifica se o utilizador está autenticado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado. Faça login primeiro.']);
    exit;
}

$loggedInClientId = (int)$_SESSION['client_id'];
$loggedInUserId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ob_end_clean();
        
        $mensagem = trim($_POST['mensagem'] ?? '');
        $idsRaw = $_POST['funcionario_ids'] ?? [];

        // ✅ Garante que $ids é sempre um array de inteiros
        if (!is_array($idsRaw)) {
            $idsRaw = [$idsRaw];
        }
        $ids = array_map('intval', $idsRaw);
        $ids = array_filter($ids); // Remove zeros

        // ✅ VALIDAÇÃO: Campos obrigatórios
        if (empty($mensagem)) {
            throw new Exception('Mensagem vazia.');
        }
        if (empty($ids)) {
            throw new Exception('Nenhum funcionário selecionado.');
        }
        if (strlen($mensagem) > 160) {
            throw new Exception('Mensagem ultrapassou 160 caracteres.');
        }

        // ✅ VALIDAÇÃO: Todos os funcionários pertencem ao client_id
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $checkQuery = "SELECT COUNT(*) as cnt FROM employees WHERE id IN ($placeholders) AND client_id = ?";
        
        $stmtCheck = $pdo->prepare($checkQuery);
        $checkParams = array_merge($ids, [$loggedInClientId]);
        $stmtCheck->execute($checkParams);
        $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        $validCount = (int)($result['cnt'] ?? 0);

        if ($validCount != count($ids)) {
            throw new Exception("Apenas $validCount de " . count($ids) . " funcionários pertencem ao seu cliente.");
        }

        // ✅ Garante que a tabela notificacoes existe COM todas as colunas necessárias
        try {
            // Verifica se a tabela existe
            $checkTable = $pdo->query("SHOW TABLES LIKE 'notificacoes'");
            if ($checkTable->rowCount() === 0) {
                // Tabela não existe, criar
                $pdo->exec("
                    CREATE TABLE notificacoes (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        funcionario_id INT NOT NULL,
                        client_id INT NOT NULL,
                        mensagem TEXT NOT NULL,
                        data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
                        lida TINYINT DEFAULT 0,
                        INDEX idx_funcionario (funcionario_id),
                        INDEX idx_client (client_id),
                        INDEX idx_data (data_envio)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } else {
                // Tabela existe, verificar e adicionar colunas que faltam
                $columns = $pdo->query("SHOW COLUMNS FROM notificacoes")->fetchAll(PDO::FETCH_COLUMN);
                
                if (!in_array('client_id', $columns)) {
                    $pdo->exec("ALTER TABLE notificacoes ADD COLUMN client_id INT NOT NULL DEFAULT 0");
                    $pdo->exec("ALTER TABLE notificacoes ADD INDEX idx_client (client_id)");
                }
                
                if (!in_array('lida', $columns)) {
                    $pdo->exec("ALTER TABLE notificacoes ADD COLUMN lida TINYINT DEFAULT 0");
                }
                
                if (!in_array('data_envio', $columns)) {
                    $pdo->exec("ALTER TABLE notificacoes ADD COLUMN data_envio DATETIME DEFAULT CURRENT_TIMESTAMP");
                    $pdo->exec("ALTER TABLE notificacoes ADD INDEX idx_data (data_envio)");
                }
            }
        } catch (PDOException $alterErr) {
            error_log("Aviso ao verificar/alterar tabela: " . $alterErr->getMessage());
            // Continua mesmo se houver erro na alteração
        }

        // ✅ Insere notifications para cada funcionário
        $insertQuery = "
            INSERT INTO notificacoes (funcionario_id, client_id, mensagem, data_envio, lida) 
            VALUES (?, ?, ?, NOW(), 0)
        ";
        $stmtInsert = $pdo->prepare($insertQuery);

        $successCount = 0;
        foreach ($ids as $funcId) {
            if ($stmtInsert->execute([$funcId, $loggedInClientId, $mensagem])) {
                $successCount++;
            }
        }

        if ($successCount > 0) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => "Mensagem registada para $successCount funcionário(s).",
                'count' => $successCount
            ]);
        } else {
            throw new Exception("Falha ao gravar mensagens na base de dados.");
        }

    } catch (Exception $e) {
        http_response_code(400);
        error_log("Erro em enviar_notificacao.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
}
?>