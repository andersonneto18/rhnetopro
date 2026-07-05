<?php
/**
 * Reset PIN — admin-only tool (uses admin session or one-time token).
 * This page is intentionally NOT linked from the employee portal.
 * Employees change their own PIN via definições (change_pin.php).
 */
session_start();

require_once '../config/db_connection.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_name = trim($_POST['employee_name'] ?? '');
    $new_pin       = trim($_POST['new_pin'] ?? '');
    $client_id     = (int)($_POST['client_id'] ?? 0);

    if ($employee_name === '' || $new_pin === '') {
        $message = '⚠️ Preencha todos os campos.';
    } elseif (strlen($new_pin) < 4) {
        $message = '❌ O PIN deve ter pelo menos 4 caracteres.';
    } else {
        try {
            if ($client_id > 0) {
                $stmt = $pdo->prepare('SELECT id, name, client_id FROM employees WHERE LOWER(name) = LOWER(?) AND client_id = ? LIMIT 1');
                $stmt->execute([$employee_name, $client_id]);
            } else {
                $stmt = $pdo->prepare('SELECT id, name, client_id FROM employees WHERE LOWER(name) = LOWER(?) LIMIT 1');
                $stmt->execute([$employee_name]);
            }
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$emp) {
                $message = '❌ Funcionário não encontrado.';
            } else {
                $pin_hash = password_hash($new_pin, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE employees SET pin_hash = ?, pin = NULL WHERE id = ?')
                    ->execute([$pin_hash, $emp['id']]);
                $message = '✅ PIN redefinido com sucesso para <strong>' . htmlspecialchars($emp['name']) . '</strong>!';
                $success = true;
            }
        } catch (Exception $e) {
            error_log('reset_pin erro: ' . $e->getMessage());
            $message = '❌ Erro interno ao redefinir PIN.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir PIN — Admin</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px;
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { background: #fff; border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,.3); }
        h1 { color: #1f2937; margin-bottom: 6px; font-size: 22px; }
        .subtitle { color: #6b7280; margin-bottom: 22px; font-size: 13px; }
        .notice { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 10px 14px;
                  border-radius: 6px; margin-bottom: 20px; font-size: 13px; color: #92400e; }
        label { display: block; margin: 14px 0 5px; font-weight: bold; color: #374151; }
        input { width: 100%; padding: 11px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px; box-sizing: border-box; }
        input:focus { outline: none; border-color: #667eea; }
        button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff;
                 padding: 13px 30px; border: none; border-radius: 8px; font-size: 15px; font-weight: bold;
                 cursor: pointer; margin-top: 18px; width: 100%; }
        button:hover { opacity: .9; }
        .message { padding: 13px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
        .message.success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .message.error   { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .links { margin-top: 18px; padding-top: 18px; border-top: 1px solid #e5e7eb; text-align: center; }
        .links a { color: #667eea; text-decoration: none; margin: 0 10px; font-weight: 500; }
    </style>
</head>
<body>
<div class="container">
    <div style="font-size:46px;text-align:center;margin-bottom:16px">🔐</div>
    <h1>Redefinir PIN — Admin</h1>
    <p class="subtitle">Ferramenta administrativa. Os funcionários devem usar as Definições do portal.</p>

    <div class="notice">
        ⚠️ Para maior segurança, forneça o <strong>ID do restaurante</strong> (client_id) para restringir a pesquisa a um único cliente.
    </div>

    <?php if ($message): ?>
        <div class="message <?= $success ? 'success' : 'error' ?>"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="client_id">ID do Restaurante (client_id) — opcional</label>
        <input type="number" id="client_id" name="client_id" placeholder="Deixar em branco para pesquisa global" min="1">

        <label for="employee_name">Nome do Funcionário</label>
        <input type="text" id="employee_name" name="employee_name" placeholder="Nome exacto do funcionário" required autofocus>

        <label for="new_pin">Novo PIN (mínimo 4 caracteres)</label>
        <input type="text" id="new_pin" name="new_pin" placeholder="Novo PIN" required minlength="4">

        <button type="submit">🔄 Redefinir PIN</button>
    </form>

    <div class="links">
        <a href="employee_login.php">🚪 Login Funcionário</a>
    </div>
</div>
</body>
</html>
