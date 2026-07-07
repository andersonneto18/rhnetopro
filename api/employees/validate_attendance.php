<?php
// api/employees/validate_attendance.php
session_start();
require_once '../../config/db_connection.php';
date_default_timezone_set('Europe/Lisbon');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$csrfToken = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$client_id = (int)$_SESSION['client_id'];
$targetDate = trim((string)($_POST['target_date'] ?? date('Y-m-d')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
    echo json_encode(['success' => false, 'message' => 'Data alvo inválida']);
    exit;
}

if (!$employee_id || !in_array($action, ['confirm', 'edit', 'invalidate'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS attendance_audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                employee_id INT NOT NULL,
                admin_user_id INT NOT NULL,
                action VARCHAR(30) NOT NULL,
                target_date DATE NOT NULL,
                before_payload LONGTEXT NULL,
                after_payload LONGTEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_attendance_audit_client_employee_date (client_id, employee_id, target_date),
                KEY idx_attendance_audit_admin_created (admin_user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Throwable $auditTableErr) {
        error_log('validate_attendance audit migration warning: ' . $auditTableErr->getMessage());
    }

    $dateColumn = 'data_registro';
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM registros_ponto")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (!in_array('data_registro', $cols, true) && in_array('data', $cols, true)) {
            $dateColumn = 'data';
        }
    } catch (Throwable $colErr) {
        error_log('validate_attendance date column warning: ' . $colErr->getMessage());
    }

    $hasClientIdInPonto = false;
    try {
        $hasClientIdInPonto = (bool)$pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'client_id'")->fetch();
    } catch (Throwable $colErr) {
        error_log('validate_attendance client_id column warning: ' . $colErr->getMessage());
    }

    try {
        $hasTipoDia = (bool)$pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'tipo_dia'")->fetch();
        if (!$hasTipoDia) {
            $pdo->exec("ALTER TABLE registros_ponto ADD COLUMN tipo_dia VARCHAR(30) NULL AFTER status");
        }
        $hasFaltaTipo = (bool)$pdo->query("SHOW COLUMNS FROM registros_ponto LIKE 'falta_tipo'")->fetch();
        if (!$hasFaltaTipo) {
            $pdo->exec("ALTER TABLE registros_ponto ADD COLUMN falta_tipo VARCHAR(20) NULL AFTER tipo_dia");
        }
    } catch (Throwable $migErr) {
        error_log('validate_attendance tipo_dia migration warning: ' . $migErr->getMessage());
    }

    $stmtEmp = $pdo->prepare("SELECT id, status FROM employees WHERE id = ? AND client_id = ? LIMIT 1");
    $stmtEmp->execute([$employee_id, $client_id]);
    $employeeRow = $stmtEmp->fetch(PDO::FETCH_ASSOC);
    if (!$employeeRow) {
        echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado para este cliente']);
        exit;
    }
    $employeeStatus = mb_strtolower(trim((string)($employeeRow['status'] ?? '')));
    if (in_array($employeeStatus, ['ferias', 'férias'], true)) {
        echo json_encode(['success' => false, 'message' => 'Funcionário em férias não pode marcar presença']);
        exit;
    }

    if (in_array($action, ['confirm', 'edit'], true)) {
        $stmtTurnoAtivo = $pdo->prepare("SELECT id FROM turnos WHERE funcionario_id = ? AND LOWER(COALESCE(status, '')) IN ('ativo', 'active') ORDER BY id DESC LIMIT 1");
        $stmtTurnoAtivo->execute([$employee_id]);
        if (!$stmtTurnoAtivo->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'Funcionário sem turno ativo não pode marcar presença']);
            exit;
        }
    }

    $fetchRecordStmt = $pdo->prepare(
        "SELECT status, status_confirmacao, hora_entrada, hora_saida, obs, tipo_dia, falta_tipo, {$dateColumn} AS data_registro
         FROM registros_ponto
         WHERE funcionario_id = ? AND DATE({$dateColumn}) = ?
         ORDER BY id DESC
         LIMIT 1"
    );

    $fetchRecord = function() use ($fetchRecordStmt, $employee_id, $targetDate) {
        $fetchRecordStmt->execute([$employee_id, $targetDate]);
        return $fetchRecordStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    };

    $insertAudit = function(string $auditAction, ?array $beforeRecord, ?array $afterRecord) use ($pdo, $client_id, $employee_id, $targetDate) {
        try {
            $stmtAudit = $pdo->prepare(
                "INSERT INTO attendance_audit_logs
                (client_id, employee_id, admin_user_id, action, target_date, before_payload, after_payload, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $adminUserId = (int)($_SESSION['user_id'] ?? 0);
            $ipAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            if (mb_strlen($userAgent) > 255) {
                $userAgent = mb_substr($userAgent, 0, 255);
            }

            $beforeJson = $beforeRecord ? json_encode($beforeRecord, JSON_UNESCAPED_UNICODE) : null;
            $afterJson = $afterRecord ? json_encode($afterRecord, JSON_UNESCAPED_UNICODE) : null;

            $stmtAudit->execute([
                $client_id,
                $employee_id,
                $adminUserId,
                $auditAction,
                $targetDate,
                $beforeJson,
                $afterJson,
                $ipAddress,
                $userAgent
            ]);
        } catch (Throwable $auditErr) {
            error_log('validate_attendance audit insert warning: ' . $auditErr->getMessage());
        }
    };

    if ($action === 'confirm') {
        $beforeRecord = $fetchRecord();
        // Atualiza via JOIN para cobrir também registos legados com client_id nulo/0.
        $stmt = $pdo->prepare("UPDATE registros_ponto rp INNER JOIN employees e ON e.id = rp.funcionario_id SET rp.status_confirmacao = 'confirmado' WHERE rp.funcionario_id = ? AND e.client_id = ? AND DATE(rp.{$dateColumn}) = ?");
        $stmt->execute([$employee_id, $client_id, $targetDate]);
        if ($stmt->rowCount() > 0) {
            $afterRecord = $fetchRecord();
            $insertAudit('confirm', $beforeRecord, $afterRecord);
            echo json_encode(['success' => true, 'message' => 'Presença confirmada com sucesso!', 'record' => $afterRecord]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sem registro de ponto para a data selecionada']);
        }
    } elseif ($action === 'invalidate') {
        $beforeRecord = $fetchRecord();
        $stmt = $pdo->prepare("UPDATE registros_ponto rp INNER JOIN employees e ON e.id = rp.funcionario_id SET rp.status = 'invalidado', rp.status_confirmacao = 'pendente' WHERE rp.funcionario_id = ? AND e.client_id = ? AND DATE(rp.{$dateColumn}) = ?");
        $stmt->execute([$employee_id, $client_id, $targetDate]);
        if ($stmt->rowCount() > 0) {
            $afterRecord = $fetchRecord();
            $insertAudit('invalidate', $beforeRecord, $afterRecord);
            echo json_encode(['success' => true, 'message' => 'Registro invalidado!', 'record' => $afterRecord]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sem registro de ponto para a data selecionada']);
        }
    } elseif ($action === 'edit') {
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        $hora_entrada = isset($_POST['hora_entrada']) ? $_POST['hora_entrada'] : null;
        $hora_saida = isset($_POST['hora_saida']) ? $_POST['hora_saida'] : null;
        $obs = isset($_POST['obs']) ? $_POST['obs'] : null;
        $tipoDia = isset($_POST['tipo_dia']) ? mb_strtolower(trim((string)$_POST['tipo_dia'])) : 'normal';
        $faltaTipo = isset($_POST['falta_tipo']) ? mb_strtolower(trim((string)$_POST['falta_tipo'])) : '';
        $tiposValidos = ['normal', 'folga', 'feriado', 'falta'];
        if (!in_array($tipoDia, $tiposValidos, true)) {
            $tipoDia = 'normal';
        }
        if (!in_array($faltaTipo, ['justificada', 'injustificada'], true)) {
            $faltaTipo = '';
        }
        if ($status === 'falta' || $tipoDia === 'falta') {
            if ($faltaTipo === '') {
                $faltaTipo = 'injustificada';
            }
            $status = 'falta';
            $tipoDia = 'falta';
        } else {
            $faltaTipo = null;
        }
        if (!in_array($status, ['presente','falta','invalidado'])) {
            echo json_encode(['success' => false, 'message' => 'Status inválido']);
            exit;
        }

        if (!empty($hora_entrada) && !empty($hora_saida)) {
            $entradaTs = strtotime('1970-01-01 ' . $hora_entrada);
            $saidaTs = strtotime('1970-01-01 ' . $hora_saida);
            if ($entradaTs !== false && $saidaTs !== false && $saidaTs <= $entradaTs) {
                echo json_encode(['success' => false, 'message' => 'Horário inválido: a hora de saída deve ser maior que a hora de entrada.']);
                exit;
            }
        }

        $existingRecord = $fetchRecord();
        $beforeRecord = $existingRecord ?: null;
        if ($existingRecord) {
            $stmt = $pdo->prepare("UPDATE registros_ponto rp INNER JOIN employees e ON e.id = rp.funcionario_id SET rp.status = ?, rp.tipo_dia = ?, rp.falta_tipo = ?, rp.hora_entrada = ?, rp.hora_saida = ?, rp.obs = ?, rp.status_confirmacao = 'confirmado' WHERE rp.funcionario_id = ? AND e.client_id = ? AND DATE(rp.{$dateColumn}) = ?");
            $stmt->execute([$status, $tipoDia, $faltaTipo, $hora_entrada, $hora_saida, $obs, $employee_id, $client_id, $targetDate]);
            $afterRecord = $fetchRecord();
            $insertAudit('edit', $beforeRecord, $afterRecord);
            echo json_encode(['success' => true, 'message' => 'Registro de presença atualizado!', 'record' => $afterRecord]);
        } else {
            if ($hasClientIdInPonto) {
                $stmtInsert = $pdo->prepare("INSERT INTO registros_ponto (funcionario_id, client_id, {$dateColumn}, status, tipo_dia, falta_tipo, hora_entrada, hora_saida, obs, status_confirmacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmado')");
                $stmtInsert->execute([$employee_id, $client_id, $targetDate, $status, $tipoDia, $faltaTipo, $hora_entrada, $hora_saida, $obs]);
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO registros_ponto (funcionario_id, {$dateColumn}, status, tipo_dia, falta_tipo, hora_entrada, hora_saida, obs, status_confirmacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmado')");
                $stmtInsert->execute([$employee_id, $targetDate, $status, $tipoDia, $faltaTipo, $hora_entrada, $hora_saida, $obs]);
            }
            $afterRecord = $fetchRecord();
            $insertAudit('edit', $beforeRecord, $afterRecord);
            echo json_encode(['success' => true, 'message' => 'Registro de presença criado e atualizado!', 'record' => $afterRecord]);
        }
    }
} catch (Exception $e) {
    error_log('validate_attendance fatal error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível processar a operação neste momento. Tente novamente.'
    ]);
}
