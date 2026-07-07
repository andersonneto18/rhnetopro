<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/db_connection.php';
date_default_timezone_set('Europe/Lisbon');
require_once '../../includes/activity_logger.php';

// Verifica se o utilizador está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilizador não autenticado.']);
    exit();
}

// Obtém o client_id do utilizador logado para Multi-Tenancy
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão inválida. Cliente não identificado.']);
    exit();
}
$loggedInClientId = $_SESSION['client_id'];

// Verifica se o método é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

// Recolhe e sanitiza os dados do formulário
$name = htmlspecialchars(trim($_POST['name'] ?? ''));
$position = htmlspecialchars(trim($_POST['position'] ?? ''));
$department = htmlspecialchars(trim($_POST['department'] ?? ''));
$email = htmlspecialchars(trim($_POST['email'] ?? ''));
$phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
$startDate = htmlspecialchars(trim($_POST['startDate'] ?? ''));
$status = htmlspecialchars(trim($_POST['status'] ?? 'active'));
$pin = trim($_POST['pin'] ?? '');

// Novos campos adicionais
$birthDate = htmlspecialchars(trim($_POST['birthDate'] ?? ''));
$nif = htmlspecialchars(trim($_POST['nif'] ?? ''));
$niss = htmlspecialchars(trim($_POST['niss'] ?? ''));
$address = htmlspecialchars(trim($_POST['address'] ?? ''));
$emergencyContact = htmlspecialchars(trim($_POST['emergencyContact'] ?? ''));
$contractType    = htmlspecialchars(trim($_POST['contractType'] ?? ''));
$salaryBase      = (float)($_POST['salary_base'] ?? 0);
$subsidioAlim    = (float)($_POST['subsidio_alimentacao'] ?? 0);
$bonusMensal     = (float)($_POST['bonus'] ?? 0);

function isValidDateYmd(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $value));
    return checkdate($m, $d, $y);
}

// Validação básica
if (empty($name) || empty($email) || empty($startDate)) {
    echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos obrigatórios (Nome, Email, Data de Início).']);
    exit();
}

// Validação do PIN
if ($pin !== '' && strlen($pin) < 4) {
    echo json_encode(['success' => false, 'message' => 'O PIN deve ter pelo menos 4 caracteres.']);
    exit();
}

// Validação do email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido.']);
    exit();
}

if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
    echo json_encode(['success' => false, 'message' => 'Nome deve ter entre 3 e 120 caracteres.']);
    exit();
}

if ($position !== '' && mb_strlen($position) > 100) {
    echo json_encode(['success' => false, 'message' => 'Cargo demasiado longo (máx. 100 caracteres).']);
    exit();
}

if ($department !== '' && mb_strlen($department) > 100) {
    echo json_encode(['success' => false, 'message' => 'Departamento demasiado longo (máx. 100 caracteres).']);
    exit();
}

$normalizedPhone = preg_replace('/\D+/', '', $phone);
if ($phone !== '' && (strlen($normalizedPhone) < 9 || strlen($normalizedPhone) > 15)) {
    echo json_encode(['success' => false, 'message' => 'Telefone inválido. Use entre 9 e 15 dígitos.']);
    exit();
}

if ($nif !== '' && !preg_match('/^\d{9}$/', $nif)) {
    echo json_encode(['success' => false, 'message' => 'NIF inválido. Deve conter 9 dígitos.']);
    exit();
}

if ($niss !== '' && !preg_match('/^\d{11}$/', $niss)) {
    echo json_encode(['success' => false, 'message' => 'NISS inválido. Deve conter 11 dígitos.']);
    exit();
}

if ($startDate === '' || !isValidDateYmd($startDate)) {
    echo json_encode(['success' => false, 'message' => 'Data de início inválida.']);
    exit();
}

if ($birthDate !== '' && !isValidDateYmd($birthDate)) {
    echo json_encode(['success' => false, 'message' => 'Data de nascimento inválida.']);
    exit();
}

if ($birthDate !== '' && isValidDateYmd($birthDate)) {
    $birthTs = strtotime($birthDate);
    $todayTs = strtotime(date('Y-m-d'));
    if ($birthTs !== false && $birthTs > $todayTs) {
        echo json_encode(['success' => false, 'message' => 'Data de nascimento não pode ser futura.']);
        exit();
    }
}

if (!in_array($status, ['active', 'inactive', 'ferias'], true)) {
    echo json_encode(['success' => false, 'message' => 'Status inválido.']);
    exit();
}

if ($contractType !== '' && !in_array($contractType, ['efetivo', 'temporario', 'part-time', 'estagio', 'freelancer'], true)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de contrato inválido.']);
    exit();
}

if ($salaryBase < 0 || $salaryBase > 1000000 || $subsidioAlim < 0 || $subsidioAlim > 1000000 || $bonusMensal < 0 || $bonusMensal > 1000000) {
    echo json_encode(['success' => false, 'message' => 'Valores salariais inválidos.']);
    exit();
}

$phone = $phone !== '' ? $normalizedPhone : '';

try {
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
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            echo json_encode(['success' => false, 'message' => 'Formato de imagem inválido. Use JPG, PNG ou GIF']);
            exit();
        }
        
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Imagem muito grande. Tamanho máximo: 2MB']);
            exit();
        }
        
        $fileName = 'emp_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $profilePicturePath = 'uploads/profile/' . $fileName;
        }
    }
    
    // Descobrir colunas reais disponíveis para compatibilidade com esquemas antigos.
    $tableColumns = $pdo->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);
    $tableColumns = array_map('strtolower', $tableColumns ?: []);

    // Verifica se o email já existe para este cliente.
    // Também cobre cenários em que client_id não existe no schema legado.
    if (in_array('client_id', $tableColumns, true)) {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ? AND client_id = ?");
        $stmtCheck->execute([$email, $loggedInClientId]);
    } else {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
        $stmtCheck->execute([$email]);
    }

    if ($stmtCheck->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Já existe um funcionário com este email.']);
        exit();
    }

    // Gera hash do PIN se preenchido
    $pin_hash = $pin !== '' ? password_hash($pin, PASSWORD_DEFAULT) : null;

    // Mapeia campos recebidos para colunas possíveis (nome do campo no BD pode variar)
    $payloadByColumn = [
        'name' => $name,
        'position' => $position,
        'department' => $department,
        'email' => $email,
        'phone' => $phone,
        'startdate' => $startDate,
        'start_date' => $startDate,
        'status' => $status,
        'client_id' => (int)$loggedInClientId,
        'pin_hash' => $pin_hash,
        'pin' => $pin !== '' ? $pin : null,
        'birthdate' => $birthDate !== '' ? $birthDate : null,
        'birth_date' => $birthDate !== '' ? $birthDate : null,
        'nif' => $nif !== '' ? $nif : null,
        'niss' => $niss !== '' ? $niss : null,
        'address' => $address !== '' ? $address : null,
        'emergencycontact' => $emergencyContact !== '' ? $emergencyContact : null,
        'emergency_contact' => $emergencyContact !== '' ? $emergencyContact : null,
        'contracttype'          => $contractType !== '' ? $contractType : null,
        'contract_type'         => $contractType !== '' ? $contractType : null,
        'profile_picture'       => $profilePicturePath,
        'salary_base'           => $salaryBase,
        'subsidio_alimentacao'  => $subsidioAlim,
        'bonus'                 => $bonusMensal,
        'vacation_days'         => max(0, (int)($_POST['vacation_days'] ?? 22)),
        'enddate'               => (($_POST['endDate'] ?? '') !== '') ? trim($_POST['endDate']) : null,
        'end_date'              => (($_POST['endDate'] ?? '') !== '') ? trim($_POST['endDate']) : null,
    ];

    $insertColumns = [];
    $insertValues = [];

    foreach ($tableColumns as $dbCol) {
        if (array_key_exists($dbCol, $payloadByColumn)) {
            $insertColumns[] = $dbCol;
            $insertValues[] = $payloadByColumn[$dbCol];
        }
    }

    if (!in_array('name', $insertColumns, true) || !in_array('email', $insertColumns, true)) {
        throw new PDOException('Schema da tabela employees inválido: colunas name/email ausentes.');
    }

    if (!in_array('startdate', $insertColumns, true) && !in_array('start_date', $insertColumns, true)) {
        throw new PDOException('Schema da tabela employees inválido: coluna de data de início ausente.');
    }

    $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
    $sql = 'INSERT INTO employees (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($insertValues);

    $employeeId = $pdo->lastInsertId();

    logActivity(
        $pdo,
        (int)$loggedInClientId,
        'Novo funcionário: ' . $name,
        'success',
        'Novo',
        (int)$employeeId
    );

    echo json_encode([
        'success' => true, 
        'message' => 'Funcionário adicionado com sucesso!',
        'employee_id' => $employeeId,
        'profile_picture' => $profilePicturePath
    ]);

} catch (PDOException $e) {
    $sqlState = $e->getCode();
    $rawMessage = $e->getMessage();
    error_log("Erro em create_employee.php: [$sqlState] $rawMessage");

    $message = 'Erro ao adicionar funcionário. Por favor, tente novamente.';
    if ($sqlState === '23000') {
        $message = 'Não foi possível adicionar: já existe registro com dados únicos (ex.: email).';
    } elseif (stripos($rawMessage, 'Unknown column') !== false) {
        $message = 'Estrutura do banco desatualizada. Execute as migrações da tabela employees.';
    } elseif (stripos($rawMessage, 'cannot be null') !== false) {
        $message = 'Preencha os campos obrigatórios do funcionário.';
    }

    echo json_encode(['success' => false, 'message' => $message]);
}