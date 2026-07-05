<?php
/**
 * Script de Teste de PIN
 * Acesse: http://localhost/rhneto-proweb/employee/test_pin.php
 */

require_once '../config/db_connection.php';

// Pega dados do formulário
$test_employee_name = $_POST['employee_name'] ?? "";
$test_pin = $_POST['pin'] ?? "";

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de PIN - Funcionário</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f7fb; }
        h1 { color: #1f2937; }
        .form-box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        label { display: block; margin: 10px 0 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        button { background: #0ea5e9; color: white; padding: 12px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; margin-top: 15px; }
        button:hover { background: #0284c7; }
        .result { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #10b981; font-size: 18px; font-weight: bold; }
        .error { color: #ef4444; font-size: 18px; font-weight: bold; }
        .warning { color: #f59e0b; }
        hr { margin: 20px 0; border: none; border-top: 1px solid #e5e7eb; }
        pre { background: #f3f4f6; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔐 Teste de Autenticação de PIN</h1>
    
    <div class="form-box">
        <form method="POST">
            <label for="employee_name">Nome do Funcionário:</label>
            <input type="text" id="employee_name" name="employee_name" value="<?php echo htmlspecialchars($test_employee_name); ?>" required autofocus>
            
            <label for="pin">PIN:</label>
            <input type="text" id="pin" name="pin" value="<?php echo htmlspecialchars($test_pin); ?>" required>
            
            <button type="submit">🔍 Testar PIN</button>
        </form>
    </div>

<?php
if (empty($test_employee_name) || empty($test_pin)) {
    echo "<div class='result'>";
    echo "<p style='color: #6b7280;'>👆 Preencha o formulário acima para testar o PIN de um funcionário.</p>";
    echo "</div>";
    echo "</body></html>";
    exit;
}

echo "<div class='result'>";

try {
    $stmt = $pdo->prepare("SELECT id, name, pin_hash, pin FROM employees WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $stmt->execute([$test_employee_name]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) {
        echo "<p class='error'>❌ Funcionário não encontrado: <strong>" . htmlspecialchars($test_employee_name) . "</strong></p>";
        echo "<p>Verifique se o nome está escrito corretamente.</p>";
        echo "</div></body></html>";
        exit;
    }

    echo "<h2>✅ Funcionário Encontrado</h2>";
    echo "<ul>";
    echo "<li><strong>ID:</strong> " . $emp['id'] . "</li>";
    echo "<li><strong>Nome:</strong> " . htmlspecialchars($emp['name']) . "</li>";
    echo "<li><strong>Tem pin_hash:</strong> " . (!empty($emp['pin_hash']) ? '✅ Sim' : '❌ Não') . "</li>";
    echo "<li><strong>Tem pin (legacy):</strong> " . (!empty($emp['pin']) ? '✅ Sim' : '❌ Não') . "</li>";
    echo "</ul>";

    echo "<hr>";
    echo "<h2>🔍 Testando PIN: " . htmlspecialchars($test_pin) . "</h2>";

    // Testa com pin_hash
    if (!empty($emp['pin_hash'])) {
        echo "<p><strong>Testando com pin_hash...</strong></p>";
        $hash_length = strlen($emp['pin_hash']);
        echo "<p>Tamanho do hash: $hash_length caracteres</p>";
        echo "<p>Primeiros 20 caracteres: " . substr($emp['pin_hash'], 0, 20) . "...</p>";
        
        if (password_verify($test_pin, $emp['pin_hash'])) {
            echo "<p class='success'>✅ <strong>PIN CORRETO!</strong> (verificado com hash)</p>";
            echo "<p>✨ Você pode fazer login normalmente com este PIN.</p>";
        } else {
            echo "<p class='error'>❌ PIN incorreto (hash não corresponde)</p>";
            
            // Testa se o hash está válido
            $info = password_get_info($emp['pin_hash']);
            echo "<p>Informações do hash:</p>";
            echo "<pre>" . print_r($info, true) . "</pre>";
        }
    }

    // Testa com pin legacy
    if (!empty($emp['pin'])) {
        echo "<p><strong>Testando com pin legacy (texto simples)...</strong></p>";
        echo "<p>PIN armazenado: '" . htmlspecialchars($emp['pin']) . "' (comprimento: " . strlen($emp['pin']) . ")</p>";
        echo "<p>PIN testado: '" . htmlspecialchars($test_pin) . "' (comprimento: " . strlen($test_pin) . ")</p>";
        
        $stored = trim((string)$emp['pin']);
        $entered = trim((string)$test_pin);
        
        echo "<p>Após trim:</p>";
        echo "<p>- Armazenado: '$stored' (comprimento: " . strlen($stored) . ")</p>";
        echo "<p>- Digitado: '$entered' (comprimento: " . strlen($entered) . ")</p>";
        
        if (hash_equals($stored, $entered)) {
            echo "<p class='success'>✅ <strong>PIN CORRETO!</strong> (verificado com método legacy)</p>";
            echo "<p class='warning'>⚠️ Este PIN está no formato antigo (texto simples). Será convertido para hash seguro no próximo login.</p>";
        } else {
            echo "<p class='error'>❌ PIN incorreto (comparação direta falhou)</p>";
            
            // Debug adicional
            echo "<p>Debug de comparação:</p>";
            echo "<pre>";
            echo "Stored bytes: ";
            for ($i = 0; $i < strlen($stored); $i++) {
                echo ord($stored[$i]) . " ";
            }
            echo "\nEntered bytes: ";
            for ($i = 0; $i < strlen($entered); $i++) {
                echo ord($entered[$i]) . " ";
            }
            echo "</pre>";
        }
    }

    if (empty($emp['pin_hash']) && empty($emp['pin'])) {
        echo "<p class='error'>❌ <strong>Este funcionário não tem PIN configurado!</strong></p>";
        echo "<p>📝 Configure um PIN no painel administrativo:</p>";
        echo "<ol>";
        echo "<li>Entre no dashboard admin</li>";
        echo "<li>Vá em 'Funcionários'</li>";
        echo "<li>Edite o funcionário</li>";
        echo "<li>Defina um PIN de 4 ou mais dígitos</li>";
        echo "</ol>";
    }

} catch (Exception $e) {
    echo "<p class='error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";
?>
</body>
</html>