<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db_connection.php';

$response = ['success' => false, 'message' => ''];

// Verifica autenticação
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    http_response_code(401);
    $response['message'] = 'Não autorizado';
    echo json_encode($response);
    exit;
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $employee_id = $_GET['id'];
    $client_id = $_SESSION['client_id'];
    $targetDate = trim((string)($_GET['date'] ?? ''));
    $useTargetDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate) === 1;
    
    try {
        // SEGURANÇA: Verifica que o funcionário pertence ao cliente logado
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND client_id = ?");
        $stmt->execute([$employee_id, $client_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            // Buscar registro de presença mais recente (compatível com data/data_registro)
            $dateColumn = 'data_registro';
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM registros_ponto")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                if (!in_array('data_registro', $cols, true) && in_array('data', $cols, true)) {
                    $dateColumn = 'data';
                }
            } catch (Throwable $e) {
                // Mantém padrão data_registro
            }

            if ($useTargetDate) {
                $stmt2 = $pdo->prepare("SELECT status, status_confirmacao, hora_entrada, hora_saida, obs, tipo_dia, falta_tipo, {$dateColumn} AS data_registro FROM registros_ponto WHERE funcionario_id = ? AND DATE({$dateColumn}) = ? ORDER BY id DESC LIMIT 1");
                $stmt2->execute([$employee_id, $targetDate]);
            } else {
                $stmt2 = $pdo->prepare("SELECT status, status_confirmacao, hora_entrada, hora_saida, obs, tipo_dia, falta_tipo, {$dateColumn} AS data_registro FROM registros_ponto WHERE funcionario_id = ? ORDER BY {$dateColumn} DESC, id DESC LIMIT 1");
                $stmt2->execute([$employee_id]);
            }
            $presenca = $stmt2->fetch(PDO::FETCH_ASSOC);

            if ($presenca) {
                $entrada = $presenca['hora_entrada'] ?? null;
                $saida = $presenca['hora_saida'] ?? null;
                $presenca['horas_trabalhadas'] = '--:--';

                if (!empty($entrada) && !empty($saida)) {
                    $entradaTs = strtotime('1970-01-01 ' . $entrada);
                    $saidaTs = strtotime('1970-01-01 ' . $saida);

                    if ($entradaTs !== false && $saidaTs !== false) {
                        if ($saidaTs < $entradaTs) {
                            $saidaTs += 24 * 60 * 60;
                        }
                        $diffMin = max(0, (int) floor(($saidaTs - $entradaTs) / 60));
                        $h = (int) floor($diffMin / 60);
                        $m = $diffMin % 60;
                        $presenca['horas_trabalhadas'] = sprintf('%02d:%02d', $h, $m);
                    }
                }
            }

            $employee['presenca_atual'] = $presenca ?: null;

            // Timeline de atividades do funcionário (se a tabela tiver employee_id)
            $employee['activity_history'] = [];
            try {
                $hasEmpCol = (bool)$pdo->query("SHOW COLUMNS FROM atividades_recentes LIKE 'employee_id'")->fetch();
                $hasStatusCol = (bool)$pdo->query("SHOW COLUMNS FROM atividades_recentes LIKE 'status'")->fetch();
                if ($hasEmpCol) {
                    $fields = ['id', 'title', 'type', 'timestamp'];
                    if ($hasStatusCol) {
                        $fields[] = 'status';
                    }

                    $sql = "SELECT " . implode(', ', $fields) . " FROM atividades_recentes WHERE client_id = ? AND employee_id = ? ORDER BY timestamp DESC LIMIT 25";
                    $stmt3 = $pdo->prepare($sql);
                    $stmt3->execute([$client_id, $employee_id]);
                    $employee['activity_history'] = $stmt3->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }
            } catch (PDOException $historyError) {
                error_log('get_employee history warning: ' . $historyError->getMessage());
            }

            echo json_encode($employee);
        } else {
            http_response_code(404);
            $response['message'] = 'Funcionário não encontrado.';
            echo json_encode($response);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
        echo json_encode($response);
    }
} else {
    http_response_code(400);
    $response['message'] = 'ID do funcionário não fornecido.';
    echo json_encode($response);
}

// A requisição só deve ser encerrada se não houver um funcionário
// Caso contrário, a função 'echo json_encode($employee)' já fará a impressão
if (!isset($employee)) {
    exit;
}