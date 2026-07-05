<?php
/**
 * Script para Redefinir PIN do Funcionário
 * Acesse: http://localhost/rhneto-proweb/employee/reset_pin.php
 */

require_once '../config/db_connection.php';

$message = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_name = trim($_POST['employee_name'] ?? '');
    $new_pin = trim($_POST['new_pin'] ?? '');
    
    if (!empty($employee_name) && !empty($new_pin)) {
        try {
            // Busca funcionário
            $stmt = $pdo->prepare("SELECT id, name FROM employees WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $stmt->execute([$employee_name]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$emp) {
                $message = "❌ Funcionário não encontrado: " . htmlspecialchars($employee_name);
            } elseif (strlen($new_pin) < 4) {
                $message = "❌ O PIN deve ter pelo menos 4 caracteres.";
            } else {
                // Gera novo hash
                $pin_hash = password_hash($new_pin, PASSWORD_DEFAULT);
                
                // Atualiza no banco
                $updateStmt = $pdo->prepare("UPDATE employees SET pin_hash = ?, pin = NULL WHERE id = ?");
                $updateStmt->execute([$pin_hash, $emp['id']]);
                
                $message = "✅ PIN redefinido com sucesso para <strong>" . htmlspecialchars($emp['name']) . "</strong>!";
                $success = true;
                
                // Teste automático
                if (password_verify($new_pin, $pin_hash)) {
                    $message .= "<br>✅ Verificação: PIN funcionando corretamente!";
                }
            }
        } catch (Exception $e) {
            $message = "❌ Erro: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $message = "⚠️ Preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir PIN - Funcionário</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 600px; 
            margin: 40px auto; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        h1 { 
            color: #1f2937; 
            margin-bottom: 10px;
            font-size: 24px;
        }
        .subtitle {
            color: #6b7280;
            margin-bottom: 25px;
            font-size: 14px;
        }
        label { 
            display: block; 
            margin: 15px 0 5px; 
            font-weight: bold;
            color: #374151;
        }
        input { 
            width: 100%; 
            padding: 12px; 
            border: 2px solid #e5e7eb; 
            border-radius: 8px; 
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        button { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            padding: 14px 30px; 
            border: none; 
            border-radius: 8px; 
            font-size: 16px; 
            font-weight: bold;
            cursor: pointer; 
            margin-top: 20px;
            width: 100%;
            transition: transform 0.2s;
        }
        button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .message { 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px;
            font-size: 15px;
        }
        .message.success { 
            background: #d1fae5; 
            color: #065f46; 
            border-left: 4px solid #10b981;
        }
        .message.error { 
            background: #fee2e2; 
            color: #991b1b; 
            border-left: 4px solid #ef4444;
        }
        .links {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
            font-weight: 500;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔐</div>
        <h1>Redefinir PIN do Funcionário</h1>
        <p class="subtitle">Configure um novo PIN para permitir o acesso ao app</p>
        
        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <label for="employee_name">Nome do Funcionário:</label>
            <input type="text" 
                   id="employee_name" 
                   name="employee_name" 
                   placeholder="Digite o nome exato do funcionário"
                   required 
                   autofocus>
            
            <label for="new_pin">Novo PIN (mínimo 4 dígitos):</label>
            <input type="text" 
                   id="new_pin" 
                   name="new_pin" 
                   placeholder="Digite o novo PIN"
                   required
                   minlength="4">
            
            <button type="submit">🔄 Redefinir PIN</button>
        </form>
        
        <div class="links">
            <a href="test_pin.php">🔍 Testar PIN</a>
            <a href="employee_login.php">🚪 Fazer Login</a>
        </div>
    </div>
</body>
</html>
