<?php
session_start();
// Define o tipo de conteúdo como JSON
header('Content-Type: application/json');

// Resposta padrão em caso de falha
$response = ['success' => false, 'message' => ''];

// Verifica autenticação
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    http_response_code(401);
    $response['message'] = 'Não autorizado';
    echo json_encode($response);
    exit;
}

// Aceita ID por GET, POST (form-data) ou JSON para compatibilidade.
$rawBody = file_get_contents('php://input');
$jsonBody = json_decode($rawBody, true);
if (!is_array($jsonBody)) {
    $jsonBody = [];
}

$employee_id = $_GET['id'] ?? $_POST['id'] ?? ($jsonBody['id'] ?? null);
$employee_id = (int)$employee_id;

$hardDeleteRaw = $_GET['hard_delete'] ?? $_POST['hard_delete'] ?? ($jsonBody['hard_delete'] ?? null);
$hardDelete = in_array((string)$hardDeleteRaw, ['1', 'true', 'yes'], true);

if ($employee_id > 0) {
    $client_id = $_SESSION['client_id'];
    
    // Configurações do banco de dados
    $db_host = 'localhost';
    $db_name = 'sistema_cadastro'; 
    $db_user = 'root';
    $db_pass = '';

    $conn = null;
    try {
        // Cria a conexão PDO
        $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Inicia a transação
        $conn->beginTransaction();

        // Obtém dados antes da alteração
        $empName = '';
        $stmtCheck = $conn->prepare('SELECT id, name FROM employees WHERE id = ? AND client_id = ?');
        $stmtCheck->execute([$employee_id, $client_id]);
        $rowEmp = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if (!$rowEmp) {
            $conn->rollBack();
            $response['message'] = 'Nenhum funcionário encontrado com este ID.';
            echo json_encode($response);
            exit;
        }
        $empName = $rowEmp['name'];

        if ($hardDelete) {
            $tableExistsCache = [];
            $columnExistsCache = [];

            $tableExists = function($table) use ($conn, &$tableExistsCache) {
                if (array_key_exists($table, $tableExistsCache)) {
                    return $tableExistsCache[$table];
                }
                $stmt = $conn->prepare('SHOW TABLES LIKE ?');
                $stmt->execute([$table]);
                $exists = (bool)$stmt->fetchColumn();
                $tableExistsCache[$table] = $exists;
                return $exists;
            };

            $columnExists = function($table, $column) use ($conn, &$columnExistsCache, $tableExists) {
                $cacheKey = $table . '.' . $column;
                if (array_key_exists($cacheKey, $columnExistsCache)) {
                    return $columnExistsCache[$cacheKey];
                }
                if (!$tableExists($table)) {
                    $columnExistsCache[$cacheKey] = false;
                    return false;
                }
                $stmt = $conn->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
                $stmt->execute([$column]);
                $exists = (bool)$stmt->fetchColumn();
                $columnExistsCache[$cacheKey] = $exists;
                return $exists;
            };

            $dependencies = [
                ['table' => 'turnos', 'columns' => ['funcionario_id', 'employee_id']],
                ['table' => 'presencas', 'columns' => ['employee_id', 'funcionario_id']],
                ['table' => 'registros_ponto', 'columns' => ['employee_id', 'funcionario_id']],
                ['table' => 'employee_documents', 'columns' => ['employee_id', 'funcionario_id']],
                ['table' => 'gorjetas', 'columns' => ['employee_id', 'funcionario_id']],
                ['table' => 'notifications', 'columns' => ['employee_id', 'funcionario_id']],
                ['table' => 'notificacoes', 'columns' => ['employee_id', 'funcionario_id']],
                ['table' => 'sms_history', 'columns' => ['employee_id', 'funcionario_id']],
                ['table' => 'atividades_recentes', 'columns' => ['employee_id', 'funcionario_id']],
                ['table' => 'activity_logs', 'columns' => ['employee_id', 'funcionario_id', 'target_employee_id']]
            ];

            foreach ($dependencies as $dep) {
                $table = $dep['table'];
                if (!$tableExists($table)) {
                    continue;
                }
                foreach ($dep['columns'] as $column) {
                    if (!$columnExists($table, $column)) {
                        continue;
                    }
                    $sql = 'DELETE FROM `' . str_replace('`', '``', $table) . '` WHERE `' . str_replace('`', '``', $column) . '` = ?';
                    $stmtDeleteDep = $conn->prepare($sql);
                    $stmtDeleteDep->execute([$employee_id]);
                }
            }

            $stmtDeleteEmployee = $conn->prepare('DELETE FROM employees WHERE id = ? AND client_id = ?');
            $stmtDeleteEmployee->execute([$employee_id, $client_id]);

            if ($stmtDeleteEmployee->rowCount() > 0) {
                $conn->commit();
                try {
                    require_once __DIR__ . '/../../includes/activity_logger.php';
                    logActivity($conn, $client_id, 'Funcionário excluído: ' . ($empName ?: $employee_id), 'danger', 'Excluído', (int)$employee_id);
                } catch (Throwable $e) {
                    error_log('delete_employee hard log error: ' . $e->getMessage());
                }
                $response['success'] = true;
                $response['message'] = 'Funcionário excluído com sucesso!';
            } else {
                $conn->rollBack();
                $response['message'] = 'Nenhum funcionário encontrado com este ID.';
            }
        } else {
            // Soft delete: desativar em vez de remover da tabela.
            $columns = $conn->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $columnsLower = array_map('strtolower', $columns);

            $updates = [];
            $params = [];

            if (in_array('status', $columnsLower, true)) {
                $updates[] = 'status = ?';
                $params[] = 'inactive';
            }
            if (in_array('deleted_at', $columnsLower, true)) {
                $updates[] = 'deleted_at = NOW()';
            }

            if (empty($updates)) {
                throw new PDOException('Tabela employees sem colunas para soft delete (status/deleted_at).');
            }

            $params[] = $employee_id;
            $params[] = $client_id;
            $sql_employees = 'UPDATE employees SET ' . implode(', ', $updates) . ' WHERE id = ? AND client_id = ?';
            $stmt_employees = $conn->prepare($sql_employees);
            $stmt_employees->execute($params);
            
            // Se a exclusão do funcionário foi bem-sucedida, confirma a transação
            if ($stmt_employees->rowCount() > 0) {
                $conn->commit();
                // log de atividade
                try {
                    require_once __DIR__ . '/../../includes/activity_logger.php';
                    logActivity($conn, $client_id, 'Funcionário desativado: ' . ($empName ?: $employee_id), 'warning', 'Inativo', (int)$employee_id);
                } catch (Throwable $e) {
                    // falha no logging não impede a resposta
                    error_log('delete_employee log error: ' . $e->getMessage());
                }
                
                $response['success'] = true;
                $response['message'] = 'Funcionário desativado com sucesso!';
            } else {
                // Pode acontecer quando o funcionário já está inativo.
                $stmtStillExists = $conn->prepare('SELECT id FROM employees WHERE id = ? AND client_id = ? LIMIT 1');
                $stmtStillExists->execute([$employee_id, $client_id]);

                if ($stmtStillExists->fetch(PDO::FETCH_ASSOC)) {
                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'Funcionário já estava desativado.';
                } else {
                    $conn->rollBack();
                    $response['message'] = 'Nenhum funcionário encontrado com este ID.';
                }
            }
        }
        
    } catch (Throwable $e) {
        // Em caso de qualquer erro, reverte a transação e captura a mensagem de erro
        if ($conn instanceof PDO && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'ID do funcionário não fornecido.';
}

// Retorna a resposta como JSON
echo json_encode($response);