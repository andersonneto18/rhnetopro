<?php
session_start();
header('Content-Type: application/json');

require_once '../config/db_connection.php';

if (empty($_SESSION['employee_id']) || empty($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];
$client_id   = (int)$_SESSION['client_id'];

$tiposValidos = [
    'doenca','consulta_medica','assistencia_familiar','falecimento_familiar',
    'casamento','maternidade_paternidade','formacao_profissional',
    'convocacao_judicial','acidente','transporte','motivo_pessoal','outro',
];

try {
    // Suporte FormData (multipart) e JSON
    $isMultipart = isset($_SERVER['CONTENT_TYPE'])
        && strpos($_SERVER['CONTENT_TYPE'], 'multipart') !== false;

    if ($isMultipart) {
        $dataAusencia = trim((string)($_POST['data_ausencia'] ?? $_POST['data_ausencia_vis'] ?? ''));
        $motivo       = trim((string)($_POST['motivo']        ?? ''));
        $tipo         = trim((string)($_POST['tipo']          ?? ''));
    } else {
        $body         = json_decode(file_get_contents('php://input'), true) ?? [];
        $dataAusencia = trim((string)($body['data_ausencia'] ?? ''));
        $motivo       = trim((string)($body['motivo']        ?? ''));
        $tipo         = trim((string)($body['tipo']          ?? ''));
    }

    // Validações de entrada
    if (!$dataAusencia || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAusencia)) {
        throw new Exception('Data inválida. Seleccione uma data correcta.');
    }
    if ($dataAusencia > date('Y-m-d')) {
        throw new Exception('Não é possível justificar datas futuras.');
    }
    if (strlen($motivo) < 5) {
        throw new Exception('Motivo demasiado curto (mínimo 5 caracteres).');
    }
    if (strlen($motivo) > 500) {
        throw new Exception('Motivo demasiado longo (máximo 500 caracteres).');
    }
    if ($tipo && !in_array($tipo, $tiposValidos, true)) {
        throw new Exception('Tipo de justificação inválido.');
    }

    // ── Garantir que a tabela existe com schema correcto ─────────────────
    // CREATE TABLE IF NOT EXISTS — com tipo VARCHAR desde o início
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS justificativas_presenca (
            id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id        INT NOT NULL,
            employee_id      INT NOT NULL,
            data_ocorrencia  DATE NOT NULL,
            tipo             VARCHAR(60) NOT NULL DEFAULT 'falta',
            motivo           TEXT NOT NULL,
            anexo_path       VARCHAR(255) NULL,
            status           ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
            admin_observacao TEXT NULL,
            decidido_por     INT NULL,
            decidido_em      DATETIME NULL,
            created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_jpc_status (client_id, status),
            KEY idx_jpc_emp    (employee_id, data_ocorrencia)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Migrar tipo de ENUM para VARCHAR — verificar com INFORMATION_SCHEMA
    $stmtColCheck = $pdo->prepare("
        SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'justificativas_presenca'
          AND COLUMN_NAME  = 'tipo'
        LIMIT 1
    ");
    $stmtColCheck->execute();
    $colType = (string)($stmtColCheck->fetchColumn() ?: '');

    if (stripos($colType, 'enum') !== false) {
        // Coluna ainda é ENUM (criada pelo admin antigo) — converter para VARCHAR
        $pdo->exec(
            "ALTER TABLE justificativas_presenca
             MODIFY COLUMN tipo VARCHAR(60) NOT NULL DEFAULT 'falta'"
        );
    }

    // Verificar duplicado
    $stmtCheck = $pdo->prepare(
        "SELECT id FROM justificativas_presenca
         WHERE employee_id = ? AND client_id = ? AND data_ocorrencia = ?
         LIMIT 1"
    );
    $stmtCheck->execute([$employee_id, $client_id, $dataAusencia]);
    if ($stmtCheck->fetch()) {
        throw new Exception('Já existe uma justificação para essa data.');
    }

    // ── Upload de documento ───────────────────────────────────────────────
    $anexoPath = null;
    if (!empty($_FILES['documento']['name']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['documento'];
        $maxSize = 5 * 1024 * 1024;

        if ($file['size'] > $maxSize) {
            throw new Exception('Ficheiro demasiado grande (máximo 5 MB).');
        }

        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        if (!in_array($ext, $allowed, true)) {
            throw new Exception('Tipo de ficheiro não permitido. Use PDF, JPG, PNG ou DOC.');
        }

        // Verificação de segurança básica (sem depender de finfo)
        // Bloquear scripts PHP disfarçados
        $firstBytes = file_get_contents($file['tmp_name'], false, null, 0, 6);
        if ($firstBytes !== false && strpos($firstBytes, '<?php') !== false) {
            throw new Exception('Ficheiro não permitido por razões de segurança.');
        }

        // Tentar MIME check se finfo estiver disponível (opcional)
        if (function_exists('finfo_open')) {
            $dangerousMimes = [
                'text/x-php','application/x-php','application/x-httpd-php',
                'application/x-sh','application/x-executable',
            ];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (in_array($mime, $dangerousMimes, true)) {
                throw new Exception('Tipo de ficheiro bloqueado por razões de segurança.');
            }
        }

        $uploadDir = __DIR__ . '/uploads/justificativas/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        $anexoPath    = $employee_id . '_' . time() . '_' . $safeFilename;

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $anexoPath)) {
            throw new Exception('Erro ao guardar o documento. Verifique as permissões da pasta.');
        }
    }

    // ── Inserir na tabela que o admin monitoriza ──────────────────────────
    $tipoGravado = $tipo ?: 'falta';
    $stmtIns = $pdo->prepare("
        INSERT INTO justificativas_presenca
            (client_id, employee_id, data_ocorrencia, tipo, motivo, anexo_path)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtIns->execute([$client_id, $employee_id, $dataAusencia, $tipoGravado, $motivo, $anexoPath]);
    $newId = (int)$pdo->lastInsertId();

    // Log de actividade para o admin
    try {
        $stmtEmp = $pdo->prepare("SELECT name FROM employees WHERE id = ? LIMIT 1");
        $stmtEmp->execute([$employee_id]);
        $empName = (string)($stmtEmp->fetchColumn() ?: 'Funcionário');
        $pdo->prepare(
            "INSERT INTO atividades_recentes (title, type, timestamp, client_id) VALUES (?, 'info', NOW(), ?)"
        )->execute([
            "$empName enviou justificativa de ausência para " . date('d/m/Y', strtotime($dataAusencia)),
            $client_id,
        ]);
    } catch (Exception $e) {
        // log de actividade não é crítico
    }

    echo json_encode([
        'success' => true,
        'message' => 'Justificativa enviada com sucesso. O administrador irá analisar o pedido.',
        'item'    => [
            'id'           => $newId,
            'data_ausencia'=> $dataAusencia,
            'data_fmt'     => date('d/m/Y', strtotime($dataAusencia)),
            'tipo'         => $tipoGravado,
            'motivo'       => $motivo,
            'documento'    => $anexoPath,
            'status'       => 'pendente',
        ],
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
