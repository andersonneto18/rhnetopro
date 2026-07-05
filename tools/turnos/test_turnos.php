<?php
session_start();
require_once __DIR__ . '/../../config/db_connection.php';

// Simular uma sessão de admin (para teste)
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: orange;'>⚠ Você não está logado. <a href='../../admin/login.php'>Fazer login</a></p>";
    echo "<p>Para testar, vou simular uma sessão...</p>";
    
    // Busca um usuário admin qualquer
    $stmt = $pdo->query("SELECT id, username, client_id FROM users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['client_id'] = $user['client_id'];
        echo "<p style='color: green;'>✓ Sessão simulada: User ID {$user['id']}, Client ID {$user['client_id']}</p>";
    } else {
        die("<p style='color: red;'>✗ Nenhum usuário encontrado no banco.</p>");
    }
}

echo "<h2>🧪 Teste de Criação de Turno</h2>";
echo "<p><strong>Sessão atual:</strong></p>";
echo "<ul>";
echo "<li>User ID: " . ($_SESSION['user_id'] ?? 'não definido') . "</li>";
echo "<li>Client ID: " . ($_SESSION['client_id'] ?? 'não definido') . "</li>";
echo "<li>Username: " . ($_SESSION['username'] ?? 'não definido') . "</li>";
echo "</ul>";

// Buscar um funcionário para teste
$stmt = $pdo->prepare("SELECT id, name FROM employees WHERE client_id = ? LIMIT 1");
$stmt->execute([$_SESSION['client_id']]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    echo "<p style='color: red;'>✗ Nenhum funcionário encontrado para o Client ID {$_SESSION['client_id']}</p>";
    echo "<p>Você precisa cadastrar um funcionário primeiro.</p>";
    exit;
}

echo "<p style='color: green;'>✓ Funcionário encontrado: <strong>{$employee['name']}</strong> (ID: {$employee['id']})</p>";

// Criar um turno de teste
if (isset($_POST['criar_turno'])) {
    $dadosTurno = [
        'funcionario_id' => (int)$employee['id'],
        'turno_tipo' => 'Manhã',
        'horario_inicio' => '08:00:00',
        'horario_fim' => '16:00:00',
        'dias_semana' => 'Seg - Sex',
        'escala' => 'Fixa semanal',
        'status' => 'ativo'
    ];
    
    try {
        $stmt = $pdo->prepare(""
            . "INSERT INTO turnos (funcionario_id, turno_tipo, horario_inicio, horario_fim, dias_semana, escala, status)"
            . " VALUES (:funcionario_id, :turno_tipo, :horario_inicio, :horario_fim, :dias_semana, :escala, :status)"
        );
        
        $resultado = $stmt->execute($dadosTurno);
        
        if ($resultado) {
            $turnoId = $pdo->lastInsertId();
            echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ TURNO CRIADO COM SUCESSO!</p>";
            echo "<p>ID do turno criado: <strong>$turnoId</strong></p>";
            echo "<pre>" . print_r($dadosTurno, true) . "</pre>";
        } else {
            echo "<p style='color: red;'>✗ Falha ao criar turno (execute retornou false)</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ ERRO ao criar turno:</p>";
        echo "<pre style='background: #fee; padding: 10px; border-left: 4px solid red;'>";
        echo $e->getMessage();
        echo "</pre>";
    }
}

// Listar turnos existentes
try {
    $stmt = $pdo->prepare(""
        . "SELECT t.*, e.name as funcionario_nome "
        . "FROM turnos t "
        . "INNER JOIN employees e ON t.funcionario_id = e.id "
        . "WHERE e.client_id = ? "
        . "ORDER BY t.id DESC "
        . "LIMIT 10"
    );
    $stmt->execute([$_SESSION['client_id']]);
    $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Turnos Existentes (" . count($turnos) . ")</h3>";
    
    if (count($turnos) > 0) {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Funcionário</th><th>Turno</th><th>Horário</th><th>Dias</th><th>Escala</th><th>Status</th><th>Criado em</th>";
        echo "</tr>";
        
        foreach ($turnos as $turno) {
            echo "<tr>";
            echo "<td>" . $turno['id'] . "</td>";
            echo "<td><strong>" . htmlspecialchars($turno['funcionario_nome']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($turno['turno_tipo']) . "</td>";
            echo "<td>" . substr($turno['horario_inicio'], 0, 5) . " - " . substr($turno['horario_fim'], 0, 5) . "</td>";
            echo "<td>" . htmlspecialchars($turno['dias_semana']) . "</td>";
            echo "<td>" . htmlspecialchars($turno['escala']) . "</td>";
            echo "<td><span style='background: " . ($turno['status'] === 'ativo' ? '#d1fae5' : '#fee2e2') . "; padding: 4px 8px; border-radius: 4px;'>" . $turno['status'] . "</span></td>";
            echo "<td>" . (isset($turno['created_at']) ? date('d/m/Y H:i', strtotime($turno['created_at'])) : 'N/A') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠ Nenhum turno cadastrado ainda.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro ao listar turnos: " . $e->getMessage() . "</p>";
}
?>

<hr>

<h3>🧪 Ações de Teste</h3>

<form method="post">
    <button type="submit" name="criar_turno" style="background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer;">
        ➕ Criar Turno de Teste
    </button>
</form>

<p><a href="../../admin/dashboard.php" style="color: #2563eb; text-decoration: none; font-weight: 600;">← Voltar ao Dashboard</a></p>

<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f7fb; }
table { background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
th { text-align: left; }
</style>
