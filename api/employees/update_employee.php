<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db_connection.php';
require_once '../../includes/activity_logger.php';

// Verifica autenticação
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Garante client_id na sessão
if (!isset($_SESSION['client_id'])) {
    try {
        $stmtClient = $pdo->prepare("SELECT client_id FROM usuarios WHERE id = ? LIMIT 1");
        $stmtClient->execute([(int)$_SESSION['user_id']]);
        $userRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
        if (!empty($userRow['client_id'])) {
            $_SESSION['client_id'] = (int)$userRow['client_id'];
        }
    } catch (Throwable $e) {
        error_log('update_employee client_id lookup warning: ' . $e->getMessage());
    }
}

if (!isset($_SESSION['client_id']) || (int)$_SESSION['client_id'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sessão inválida: client_id ausente']);
    exit;
}

function isValidDateYmd(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $value));
    return checkdate($m, $d, $y);
}

$data = $_POST;
if (empty($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nenhum dado recebido. O formulário pode estar enviando dados incorretamente.'
    ]);
    exit;
}

if (empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID do funcionário é obrigatório']);
    exit;
}

$employeeId = (int)$data['id'];
if ($employeeId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do funcionário inválido']);
    exit;
}

try {
    $clientId = (int)$_SESSION['client_id'];

    // Garante tabela de pedidos críticos
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS employee_change_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            employee_id INT NOT NULL,
            request_type VARCHAR(60) NOT NULL DEFAULT 'critical_update',
            payload_json LONGTEXT NOT NULL,
            reason TEXT NULL,
            status ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
            requested_by INT NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            review_note TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ecr_client_status (client_id, status),
            KEY idx_ecr_employee (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Funcionário atual para comparação de alterações críticas
    $stmtCurrent = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND client_id = ? LIMIT 1");
    $stmtCurrent->execute([$employeeId, $clientId]);
    $current = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado ou sem permissão']);
        exit;
    }

    // Processar upload de foto se enviado
    $profilePicturePath = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file = $_FILES['profile_picture'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($fileExtension, $allowedExtensions, true)) {
            echo json_encode(['success' => false, 'message' => 'Formato de imagem inválido. Use JPG, PNG ou GIF']);
            exit;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Imagem muito grande. Tamanho máximo: 2MB']);
            exit;
        }

        $fileName = 'emp_' . $employeeId . '_' . time() . '.' . $fileExtension;
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $profilePicturePath = 'uploads/profile/' . $fileName;
        }
    }

    // Campos permitidos
    $fields = ['name', 'position', 'department', 'email', 'phone', 'startDate', 'endDate', 'status', 'birthDate', 'nif', 'niss', 'address', 'emergencyContact', 'contractType', 'salary_base', 'subsidio_alimentacao', 'bonus', 'vacation_days'];

    if (isset($data['name']) && $data['name'] !== '') {
        $data['name'] = trim((string)$data['name']);
        if (mb_strlen($data['name']) < 3 || mb_strlen($data['name']) > 120) {
            echo json_encode(['success' => false, 'message' => 'Nome deve ter entre 3 e 120 caracteres']);
            exit;
        }
    }

    if (isset($data['position']) && mb_strlen(trim((string)$data['position'])) > 100) {
        echo json_encode(['success' => false, 'message' => 'Cargo demasiado longo (máx. 100 caracteres)']);
        exit;
    }

    if (isset($data['department']) && mb_strlen(trim((string)$data['department'])) > 100) {
        echo json_encode(['success' => false, 'message' => 'Departamento demasiado longo (máx. 100 caracteres)']);
        exit;
    }

    if (isset($data['email']) && $data['email'] !== '') {
        $emailNorm = trim((string)$data['email']);
        if (!filter_var($emailNorm, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Email inválido']);
            exit;
        }

        $stmtEmail = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE email = ? AND client_id = ? AND id <> ?');
        $stmtEmail->execute([$emailNorm, $clientId, $employeeId]);
        if ((int)$stmtEmail->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Já existe outro funcionário com este email']);
            exit;
        }
        $data['email'] = $emailNorm;
    }

    if (isset($data['phone'])) {
        $phoneNorm = preg_replace('/\D+/', '', (string)$data['phone']);
        if ($phoneNorm !== '' && (strlen($phoneNorm) < 9 || strlen($phoneNorm) > 15)) {
            echo json_encode(['success' => false, 'message' => 'Telefone inválido. Use entre 9 e 15 dígitos']);
            exit;
        }
        $data['phone'] = $phoneNorm;
    }

    if (isset($data['nif']) && trim((string)$data['nif']) !== '' && !preg_match('/^\d{9}$/', trim((string)$data['nif']))) {
        echo json_encode(['success' => false, 'message' => 'NIF inválido. Deve conter 9 dígitos']);
        exit;
    }

    if (isset($data['niss']) && trim((string)$data['niss']) !== '' && !preg_match('/^\d{11}$/', trim((string)$data['niss']))) {
        echo json_encode(['success' => false, 'message' => 'NISS inválido. Deve conter 11 dígitos']);
        exit;
    }

    if (isset($data['startDate']) && $data['startDate'] !== '' && !isValidDateYmd(trim((string)$data['startDate']))) {
        echo json_encode(['success' => false, 'message' => 'Data de início inválida']);
        exit;
    }

    if (isset($data['endDate']) && $data['endDate'] !== '' && !isValidDateYmd(trim((string)$data['endDate']))) {
        echo json_encode(['success' => false, 'message' => 'Data de fim de contrato inválida']);
        exit;
    }
    if (isset($data['endDate']) && $data['endDate'] === '') {
        $data['endDate'] = null;
    }

    if (isset($data['vacation_days']) && $data['vacation_days'] !== '') {
        $vd = (int)$data['vacation_days'];
        if ($vd < 0 || $vd > 365) {
            echo json_encode(['success' => false, 'message' => 'Dias de férias inválidos (0–365)']);
            exit;
        }
        $data['vacation_days'] = $vd;
    }

    if (isset($data['birthDate']) && $data['birthDate'] !== '' && !isValidDateYmd(trim((string)$data['birthDate']))) {
        echo json_encode(['success' => false, 'message' => 'Data de nascimento inválida']);
        exit;
    }

    if (isset($data['birthDate']) && $data['birthDate'] !== '' && isValidDateYmd(trim((string)$data['birthDate']))) {
        $birthTs = strtotime((string)$data['birthDate']);
        $todayTs = strtotime(date('Y-m-d'));
        if ($birthTs !== false && $birthTs > $todayTs) {
            echo json_encode(['success' => false, 'message' => 'Data de nascimento não pode ser futura']);
            exit;
        }
    }

    if (isset($data['contractType']) && $data['contractType'] !== '' && !in_array($data['contractType'], ['efetivo', 'temporario', 'part-time', 'estagio', 'freelancer'], true)) {
        echo json_encode(['success' => false, 'message' => 'Tipo de contrato inválido']);
        exit;
    }

    foreach (['salary_base', 'subsidio_alimentacao', 'bonus'] as $moneyField) {
        if (isset($data[$moneyField]) && $data[$moneyField] !== '') {
            if (!is_numeric($data[$moneyField])) {
                echo json_encode(['success' => false, 'message' => 'Valor numérico inválido em remuneração']);
                exit;
            }
            $val = (float)$data[$moneyField];
            if ($val < 0 || $val > 1000000) {
                echo json_encode(['success' => false, 'message' => 'Valor fora do intervalo permitido em remuneração']);
                exit;
            }
            $data[$moneyField] = number_format($val, 2, '.', '');
        }
    }

    if (isset($data['status']) && $data['status'] !== '') {
        $raw = mb_strtolower(trim((string)$data['status']));
        $map = [
            'active' => 'active', 'ativo' => 'active',
            'inactive' => 'inactive', 'inativo' => 'inactive',
            'ferias' => 'ferias', 'férias' => 'ferias'
        ];
        if (isset($map[$raw])) {
            $data['status'] = $map[$raw];
        } else {
            echo json_encode(['success' => false, 'message' => 'Valor de status inválido']);
            exit;
        }
    }

        // Fluxo rápido: permite aplicar status diretamente para ações explícitas de ativar/desativar.
        $allowDirectStatusUpdate = false;
        if (isset($data['status']) && $data['status'] !== '') {
            $quickToggleRaw = mb_strtolower(trim((string)($data['quick_status_toggle'] ?? '')));
            $isQuickToggle = in_array($quickToggleRaw, ['1', 'true', 'yes', 'on'], true);

            $payloadKeys = array_keys($data);
            $allowedQuickKeys = ['id', 'status', 'quick_status_toggle', 'approval_reason'];
            $extraKeys = array_diff($payloadKeys, $allowedQuickKeys);
            $hasOnlyStatusPayload = empty($extraKeys);

            $currentStatusRaw = mb_strtolower(trim((string)($current['status'] ?? '')));
            $currentStatusMap = [
                'active' => 'active', 'ativo' => 'active',
                'inactive' => 'inactive', 'inativo' => 'inactive',
                'ferias' => 'ferias', 'férias' => 'ferias'
            ];
            $currentStatusNorm = $currentStatusMap[$currentStatusRaw] ?? $currentStatusRaw;

            if (
                $isQuickToggle
                && $hasOnlyStatusPayload
                && in_array($data['status'], ['active', 'inactive'], true)
                && $data['status'] !== $currentStatusNorm
            ) {
                $allowDirectStatusUpdate = true;
            }
        }

    // Detecta alterações críticas e cria pedido de aprovação
    $criticalFields = ['status', 'salary_base', 'subsidio_alimentacao', 'bonus', 'contractType'];
    $criticalPayload = [];

    foreach ($criticalFields as $criticalField) {
        if (!array_key_exists($criticalField, $data) || $data[$criticalField] === '') {
            continue;
        }

        if ($criticalField === 'status' && $allowDirectStatusUpdate) {
            // Ação rápida de ativar/desativar: aplica imediatamente sem passar por aprovação.
            continue;
        }

        $newValue = $data[$criticalField];
        $oldValue = $current[$criticalField] ?? null;

        if (in_array($criticalField, ['salary_base', 'subsidio_alimentacao', 'bonus'], true)) {
            $oldNum = (float)$oldValue;
            $newNum = (float)$newValue;
            if (abs($oldNum - $newNum) > 0.0001) {
                $criticalPayload[$criticalField] = $newNum;
            }
            continue;
        }

        $oldText = trim((string)$oldValue);
        $newText = trim((string)$newValue);
        if ($oldText !== $newText) {
            $criticalPayload[$criticalField] = $newText;
        }
    }

    $approvalRequestId = null;
    if (!empty($criticalPayload)) {
        $approvalReason = trim((string)($data['approval_reason'] ?? ''));
        if ($approvalReason === '') {
            $approvalReason = 'Alteração crítica solicitada pelo administrador.';
        }

        $stmtReq = $pdo->prepare(
            "INSERT INTO employee_change_requests
            (client_id, employee_id, request_type, payload_json, reason, status, requested_by)
            VALUES (?, ?, 'critical_update', ?, ?, 'pendente', ?)"
        );
        $stmtReq->execute([
            $clientId,
            $employeeId,
            json_encode($criticalPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $approvalReason,
            (int)$_SESSION['user_id']
        ]);
        $approvalRequestId = (int)$pdo->lastInsertId();
    }

    $updates = [];
    $params = [];
    $dateFields = ['birthDate', 'startDate', 'endDate'];

    foreach ($fields as $field) {
        if (!array_key_exists($field, $data)) {
            continue;
        }

        if (in_array($field, $criticalFields, true)) {
            // Alterações críticas seguem por aprovação e não aplicam direto.
            // Exceção: status em ação rápida de ativar/desativar.
            if (!($field === 'status' && $allowDirectStatusUpdate)) {
                continue;
            }
        }

        if (in_array($field, $dateFields, true)) {
            $updates[] = "$field = ?";
            $dv = $data[$field];
            $params[] = ($dv !== null && $dv !== '' && $dv !== '0000-00-00') ? trim((string)$dv) : null;
            continue;
        }

        if ($data[$field] !== '' && $data[$field] !== null) {
            $updates[] = "$field = ?";
            $params[] = trim((string)$data[$field]);
        }
    }

    // Processa PIN se enviado
    if (isset($data['pin']) && $data['pin'] !== '') {
        $pin = trim((string)$data['pin']);
        if (strlen($pin) === 60 && substr($pin, 0, 4) === '$2y$') {
            error_log('Tentativa de salvar hash como PIN ignorada');
        } elseif (strlen($pin) < 4) {
            echo json_encode(['success' => false, 'message' => 'PIN deve ter pelo menos 4 dígitos']);
            exit;
        } else {
            $updates[] = 'pin_hash = ?';
            $params[] = password_hash($pin, PASSWORD_DEFAULT);
        }
    }

    if ($profilePicturePath !== null) {
        $updates[] = 'profile_picture = ?';
        $params[] = $profilePicturePath;
    }

    $appliedDirectly = false;
    if (!empty($updates)) {
        $params[] = $employeeId;
        $params[] = $clientId;
        $sql = 'UPDATE employees SET ' . implode(', ', $updates) . ' WHERE id = ? AND client_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $appliedDirectly = true;
    }

    if (!$appliedDirectly && $approvalRequestId === null) {
        echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar']);
        exit;
    }

    try {
        $stmtName = $pdo->prepare('SELECT name FROM employees WHERE id = ? AND client_id = ? LIMIT 1');
        $stmtName->execute([$employeeId, $clientId]);
        $empRow = $stmtName->fetch(PDO::FETCH_ASSOC);
        $empName = $empRow['name'] ?? ('#' . $employeeId);

        if ($appliedDirectly) {
            logActivity(
                $pdo,
                $clientId,
                'Dados atualizados: ' . $empName,
                'info',
                'Atualização',
                $employeeId
            );
        }

        if ($approvalRequestId !== null) {
            logActivity(
                $pdo,
                $clientId,
                'Pedido de aprovação (alteração crítica): ' . $empName,
                'warning',
                'Pendente',
                $employeeId
            );
        }
    } catch (Throwable $logErr) {
        error_log('update_employee log warning: ' . $logErr->getMessage());
    }

    $message = 'Funcionário atualizado com sucesso';
    if ($approvalRequestId !== null && $appliedDirectly) {
        $message = 'Dados base atualizados e alteração crítica enviada para aprovação';
    } elseif ($approvalRequestId !== null) {
        $message = 'Alteração crítica enviada para aprovação';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'profile_picture' => $profilePicturePath,
        'approval_required' => $approvalRequestId !== null,
        'approval_request_id' => $approvalRequestId
    ]);
} catch (Exception $e) {
    error_log('update_employee error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro no servidor',
        'error' => $e->getMessage()
    ]);
}
