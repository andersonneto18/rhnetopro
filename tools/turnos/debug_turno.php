<?php
/**
 * Script de DEBUG para criação de turnos
 * Executa: http://localhost/rhneto-proweb/tools/turnos/debug_turno.php
 */

session_start();
require_once __DIR__ . '/../../config/db_connection.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Debug - Criação de Turno</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background: #f5f7fb; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { color: #2563eb; }
        .success { background: #d1fae5; color: #065f46; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .error { background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .info { background: #dbeafe; color: #1e40af; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .warning { background: #fef3c7; color: #92400e; padding: 15px; border-radius: 6px; margin: 15px 0; }
        pre { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; }
        button { background: #2563eb; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
        button:hover { background: #1d4ed8; }
        .btn-test { background: #10b981; margin: 10px 5px; }
        .btn-test:hover { background: #059669; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Debug - Sistema de Turnos</h1>

    <?php
    // VERIFICAR SESSÃO
    echo "<h2>1️⃣ Verificar Sessão</h2>";
    
    if (!isset($_SESSION['user_id'])) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Você não está logado!</strong><br>";
        echo "<a href='../../admin/login.php'>Fazer login</a>";
        echo "</div>";
        
        // Simular sessão para teste
        $stmt = $pdo->query("SELECT id, username, client_id FROM users LIMIT 1");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['client_id'] = $user['client_id'];
            echo "<div class='info'>✓ Sessão simulada para teste</div>";
        }
    }
    
    echo "<div class='info'>";
    echo "<strong>Sessão Atual:</strong><br>";
    echo "• User ID: " . ($_SESSION['user_id'] ?? 'não definido') . "<br>";
    echo "• Client ID: " . ($_SESSION['client_id'] ?? 'não definido') . "<br>";
    echo "• Username: " . ($_SESSION['username'] ?? 'não definido');
    echo "</div>";
    
    // VERIFICAR TABELA TURNOS
    echo "<h2>2️⃣ Verificar Tabela 'turnos'</h2>";
    
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'turnos'");
        
        if ($checkTable->rowCount() > 0) {
            echo "<div class='success'>✓ Tabela 'turnos' existe!</div>";
            
            $count = $pdo->query("SELECT COUNT(*) FROM turnos")->fetchColumn();
            echo "<div class='info'>Total de turnos: <strong>$count</strong></div>";
        } else {
            echo "<div class='error'>✗ Tabela 'turnos' NÃO existe!</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Erro: " . $e->getMessage() . "</div>";
    }
    
    // VERIFICAR FUNCIONÁRIOS
    echo "<h2>3️⃣ Verificar Funcionários</h2>";
    
    try {
        if (isset($_SESSION['client_id'])) {
            $stmt = $pdo->prepare("SELECT id, name FROM employees WHERE client_id = ? LIMIT 5");
            $stmt->execute([$_SESSION['client_id']]);
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($employees) > 0) {
                echo "<div class='success'>✓ Encontrados " . count($employees) . " funcionário(s)</div>";
                echo "<ul>";
                foreach ($employees as $emp) {
                    echo "<li>ID: {$emp['id']} - {$emp['name']}</li>";
                }
                echo "</ul>";
            } else {
                echo "<div class='warning'>⚠️ Nenhum funcionário encontrado para este cliente</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Erro: " . $e->getMessage() . "</div>";
    }
    
    // TESTAR CRIAÇÃO DE TURNO
    echo "<h2>4️⃣ Testar Criação de Turno</h2>";
    
    if (isset($_POST['testar_turno']) && isset($employees) && count($employees) > 0) {
        $funcionarioId = $employees[0]['id'];
        
        echo "<div class='info'><strong>Testando criação de turno...</strong></div>";
        
        $dadosTurno = [
            'funcionario_id' => $funcionarioId,
            'turno_tipo' => 'Manhã',
            'horario_inicio' => '08:00:00',
            'horario_fim' => '16:00:00',
            'dias_semana' => 'Seg - Sex',
            'escala' => 'Fixa semanal',
            'status' => 'ativo'
        ];
        
        echo "<pre>Dados a inserir:\n" . print_r($dadosTurno, true) . "</pre>";
        
        try {
            $stmt = $pdo->prepare(""
                . "INSERT INTO turnos (funcionario_id, turno_tipo, horario_inicio, horario_fim, dias_semana, escala, status)"
                . " VALUES (:funcionario_id, :turno_tipo, :horario_inicio, :horario_fim, :dias_semana, :escala, :status)"
            );
            
            $resultado = $stmt->execute($dadosTurno);
            
            if ($resultado) {
                $turnoId = $pdo->lastInsertId();
                echo "<div class='success'>";
                echo "<strong>✅ TURNO CRIADO COM SUCESSO!</strong><br>";
                echo "ID do turno: <strong>$turnoId</strong>";
                echo "</div>";
            } else {
                echo "<div class='error'>✗ Falha ao criar turno</div>";
                echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "<strong>✗ ERRO ao criar turno:</strong><br>";
            echo $e->getMessage();
            echo "</div>";
        }
    }
    
    // TESTAR API
    echo "<h2>5️⃣ Testar API de Criação</h2>";
    
    if (isset($_POST['testar_api']) && isset($employees) && count($employees) > 0) {
        $funcionarioId = $employees[0]['id'];
        
        $dadosApi = json_encode([
            'funcionario_id' => $funcionarioId,
            'turno_tipo' => 'Tarde',
            'horario_inicio' => '14:00',
            'horario_fim' => '22:00',
            'dias_semana' => 'Seg - Sex',
            'escala' => 'Fixa semanal',
            'status' => 'ativo'
        ]);
        
        echo "<div class='info'><strong>Enviando para API...</strong></div>";
        echo "<pre>POST api/turnos/create_turno.php\n" . $dadosApi . "</pre>";
        
        // Simular chamada à API
        $ch = curl_init('http://localhost/rhneto-proweb/api/turnos/create_turno.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dadosApi);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Cookie: ' . session_name() . '=' . session_id()
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "<div class='info'>";
        echo "<strong>Resposta da API (HTTP $httpCode):</strong><br>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        echo "</div>";
        
        $resultApi = json_decode($response, true);
        if ($resultApi && isset($resultApi['success']) && $resultApi['success']) {
            echo "<div class='success'>✅ API funcionou corretamente!</div>";
        } else {
            echo "<div class='error'>✗ API retornou erro</div>";
        }
    }
    
    // LISTAR TURNOS
    echo "<h2>6️⃣ Turnos Cadastrados</h2>";
    
    try {
        if (isset($_SESSION['client_id'])) {
            $stmt = $pdo->prepare(""
                . "SELECT t.*, e.name as funcionario_nome "
                . "FROM turnos t "
                . "INNER JOIN employees e ON t.funcionario_id = e.id "
                . "WHERE e.client_id = ? "
                . "ORDER BY t.id DESC"
            );
            $stmt->execute([$_SESSION['client_id']]);
            $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($turnos) > 0) {
                echo "<div class='success'>✓ " . count($turnos) . " turno(s) encontrado(s)</div>";
                echo "<table border='1' cellpadding='10' style='width:100%; border-collapse: collapse;'>";
                echo "<tr style='background: #f3f4f6;'><th>ID</th><th>Funcionário</th><th>Turno</th><th>Horário</th><th>Dias</th><th>Escala</th><th>Status</th></tr>";
                
                foreach ($turnos as $t) {
                    echo "<tr>";
                    echo "<td>{$t['id']}</td>";
                    echo "<td><strong>{$t['funcionario_nome']}</strong></td>";
                    echo "<td>{$t['turno_tipo']}</td>";
                    echo "<td>" . substr($t['horario_inicio'], 0, 5) . " - " . substr($t['horario_fim'], 0, 5) . "</td>";
                    echo "<td>{$t['dias_semana']}</td>";
                    echo "<td>{$t['escala']}</td>";
                    echo "<td>{$t['status']}</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            } else {
                echo "<div class='warning'>⚠️ Nenhum turno cadastrado ainda</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Erro: " . $e->getMessage() . "</div>";
    }
    ?>

    <h2>🧪 Ações de Teste</h2>
    
    <form method="post" style="margin: 20px 0;">
        <button type="submit" name="testar_turno" class="btn-test">
            ✅ Testar Criação Direta (SQL)
        </button>
        
        <button type="submit" name="testar_api" class="btn-test">
            🔌 Testar via API
        </button>
    </form>

    <div style="margin-top: 30px;">
        <a href="../../admin/dashboard.php" style="color: #2563eb; text-decoration: none; font-weight: 600;">← Voltar ao Dashboard</a>
    </div>

</div>
</body>
</html>
