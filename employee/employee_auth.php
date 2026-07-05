<?php
session_start();
require_once '../config/db_connection.php'; // deve expor $pdo (PDO)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: employee_login.php');
    exit;
}

$employee_name = trim($_POST['employee_name'] ?? '');
$pin = trim($_POST['pin'] ?? '');

if ($employee_name === '' || $pin === '') {
    $_SESSION['login_error'] = 'Nome e PIN obrigatórios';
    header('Location: employee_login.php');
    exit;
}

try {
    // busca por nome (case-insensitive)
    $stmt = $pdo->prepare("SELECT id, name, pin_hash, pin, client_id FROM employees WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $stmt->execute([$employee_name]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) {
        $_SESSION['login_error'] = 'Funcionário não encontrado';
        error_log("Login attempt - Employee not found: $employee_name");
        header('Location: employee_login.php');
        exit;
    }

    // Log para debug
    error_log("Login attempt for: {$emp['name']} (ID: {$emp['id']})");
    error_log("Has pin_hash: " . (!empty($emp['pin_hash']) ? 'yes' : 'no'));
    error_log("Has legacy pin: " . (!empty($emp['pin']) ? 'yes' : 'no'));
    
    $valid = false;
    
    // Tenta verificar com pin_hash (método novo)
    if (!empty($emp['pin_hash'])) {
        if (password_verify($pin, $emp['pin_hash'])) {
            $valid = true;
            error_log("PIN verified with hash");
        } else {
            error_log("PIN hash verification failed");
        }
    } 
    // Tenta verificar com pin legacy (texto simples)
    elseif (!empty($emp['pin'])) {
        // Remove espaços e compara
        $stored_pin = trim((string)$emp['pin']);
        $entered_pin = trim((string)$pin);
        
        if (hash_equals($stored_pin, $entered_pin)) {
            $valid = true;
            error_log("PIN verified with legacy method");
            
            // Migra PIN para hash automaticamente
            try {
                $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE employees SET pin_hash = ?, pin = NULL WHERE id = ?");
                $updateStmt->execute([$pin_hash, $emp['id']]);
                error_log("PIN migrated to hash for employee ID: {$emp['id']}");
            } catch (Exception $e) {
                error_log("Failed to migrate PIN: " . $e->getMessage());
            }
        } else {
            error_log("PIN legacy verification failed. Stored: '$stored_pin', Entered: '$entered_pin'");
        }
    } else {
        error_log("No PIN configured for this employee");
        $_SESSION['login_error'] = 'Funcionário sem PIN configurado. Contacte o administrador.';
        header('Location: employee_login.php');
        exit;
    }

    if ($valid) {
        session_regenerate_id(true);
        $_SESSION['employee_id'] = (int)$emp['id'];
        $_SESSION['employee_name'] = $emp['name'];
        $_SESSION['client_id'] = (int)($emp['client_id'] ?? 0);
        error_log("Login successful for: {$emp['name']}");
        header('Location: employee_portal.php');
        exit;
    } else {
        $_SESSION['login_error'] = 'PIN incorreto';
        error_log("Login failed - Invalid PIN for: {$emp['name']}");
        header('Location: employee_login.php');
        exit;
    }
} catch (Exception $e) {
    error_log('employee_auth error: '.$e->getMessage());
    $_SESSION['login_error'] = 'Erro no servidor';
    header('Location: employee_login.php');
    exit;
}