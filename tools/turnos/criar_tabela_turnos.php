<?php
/**
 * Script PHP para criar a tabela TURNOS
 * Execute este arquivo no navegador: http://localhost/rhneto-proweb/tools/turnos/criar_tabela_turnos.php
 */

require_once __DIR__ . '/../../config/db_connection.php';

echo "<!DOCTYPE html>";
echo "<html lang='pt'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Criar Tabela Turnos</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 30px; background: #f5f7fb; }
    .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    h1 { color: #2563eb; border-bottom: 3px solid #2563eb; padding-bottom: 10px; }
    h2 { color: #059669; margin-top: 30px; }
    .success { background: #d1fae5; color: #065f46; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #10b981; }
    .error { background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #ef4444; }
    .info { background: #dbeafe; color: #1e40af; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #3b82f6; }
    .warning { background: #fef3c7; color: #92400e; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #f59e0b; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    th { background: #f3f4f6; padding: 12px; text-align: left; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; }
    td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
    tr:hover { background: #f9fafb; }
    code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace; color: #dc2626; }
    .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
    .btn:hover { background: #1d4ed8; }
    pre { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; }
</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";

echo "<h1>🔧 Criar Tabela TURNOS</h1>";

try {
    // PASSO 1: Verificar se a tabela já existe
    echo "<h2>📋 Passo 1: Verificando tabela existente</h2>";
    
    $checkTable = $pdo->query("SHOW TABLES LIKE 'turnos'");
    
    if ($checkTable->rowCount() > 0) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ ATENÇÃO:</strong> A tabela 'turnos' JÁ EXISTE!<br>";
        
        // Contar registros
        $count = $pdo->query("SELECT COUNT(*) FROM turnos")->fetchColumn();
        echo "Registros atuais: <strong>$count</strong><br><br>";
        
        if ($count > 0) {
            echo "<strong style='color: #dc2626;'>⚠️ CUIDADO: Existem $count turno(s) cadastrado(s)!</strong><br>";
            echo "Se você deletar e recriar a tabela, TODOS OS DADOS SERÃO PERDIDOS!<br>";
        }
        
        echo "</div>";
        
        // Mostrar estrutura atual
        echo "<h3>Estrutura atual da tabela:</h3>";
        $structure = $pdo->query("DESCRIBE turnos")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($structure as $col) {
            echo "<tr>";
            echo "<td><code>" . $col['Field'] . "</code></td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . $col['Null'] . "</td>";
            echo "<td>" . $col['Key'] . "</td>";
            echo "<td>" . ($col['Default'] ?? '<em>NULL</em>') . "</td>";
            echo "<td>" . $col['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // OPÇÃO: Deletar tabela
        if (isset($_GET['confirmar_delete']) && $_GET['confirmar_delete'] === 'SIM') {
            echo "<div class='warning'><strong>🗑️ DELETANDO TABELA...</strong></div>";
            $pdo->exec("DROP TABLE IF EXISTS turnos");
            echo "<div class='success'><strong>✅ Tabela 'turnos' deletada com sucesso!</strong></div>";
            echo "<meta http-equiv='refresh' content='2'>";
        } else {
            echo "<div class='warning'>";
            echo "<p><strong>Deseja DELETAR e RECRIAR a tabela?</strong></p>";
            echo "<p style='margin: 15px 0;'>";
            echo "<a href='?confirmar_delete=SIM' class='btn' style='background: #dc2626;'>🗑️ SIM, DELETAR E RECRIAR</a> ";
            echo "<a href='../../admin/dashboard.php' class='btn' style='background: #6b7280;'>❌ NÃO, MANTER COMO ESTÁ</a>";
            echo "</p>";
            echo "</div>";
            
            echo "</div></body></html>";
            exit;
        }
    } else {
        echo "<div class='info'>✓ Tabela 'turnos' não existe. Pronto para criar!</div>";
    }
    
    // PASSO 2: Criar a tabela
    echo "<h2>🔨 Passo 2: Criando tabela TURNOS</h2>";
    
    $sql = "
    CREATE TABLE turnos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario_id INT NOT NULL,
        turno_tipo VARCHAR(50) NOT NULL,
        horario_inicio TIME NOT NULL COMMENT 'Horário de início do turno',
        horario_fim TIME NOT NULL COMMENT 'Horário de término do turno',
        dias_semana VARCHAR(100) NOT NULL COMMENT 'Ex: Seg-Sex, Seg-Dom, Seg/Qua/Sex',
        escala VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'ativo ou inativo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última atualização',
        
        INDEX idx_funcionario_id (funcionario_id),
        INDEX idx_status (status),
        INDEX idx_turno_tipo (turno_tipo),
        
        CONSTRAINT fk_turnos_funcionario 
            FOREIGN KEY (funcionario_id) 
            REFERENCES employees(id) 
            ON DELETE CASCADE 
            ON UPDATE CASCADE
            
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de turnos e escalas dos funcionários'
    ";
    
    $pdo->exec($sql);
    
    echo "<div class='success'>";
    echo "<strong>✅ TABELA 'turnos' CRIADA COM SUCESSO!</strong>";
    echo "</div>";
    
    // PASSO 3: Verificar estrutura criada
    echo "<h2>✓ Passo 3: Verificando estrutura criada</h2>";
    
    $structure = $pdo->query("DESCRIBE turnos")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($structure as $col) {
        echo "<tr>";
        echo "<td><code>" . $col['Field'] . "</code></td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . ($col['Null'] === 'YES' ? '✓' : '✗') . "</td>";
        echo "<td>" . ($col['Key'] ? '<strong>' . $col['Key'] . '</strong>' : '-') . "</td>";
        echo "<td>" . ($col['Default'] ?? '<em>NULL</em>') . "</td>";
        echo "<td>" . ($col['Extra'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // PASSO 4: Informações finais
    echo "<div class='success'>";
    echo "<h2>🎉 CONCLUÍDO!</h2>";
    echo "<p><strong>A tabela 'turnos' foi criada com sucesso!</strong></p>";
    echo "<p>Campos criados:</p>";
    echo "<ul>";
    echo "<li><code>id</code> - Identificador único (auto-incremento)</li>";
    echo "<li><code>funcionario_id</code> - ID do funcionário (obrigatório)</li>";
    echo "<li><code>turno_tipo</code> - Tipo do turno (Manhã/Tarde/Noturno/Intermitente)</li>";
    echo "<li><code>horario_inicio</code> - Hora de início</li>";
    echo "<li><code>horario_fim</code> - Hora de término</li>";
    echo "<li><code>dias_semana</code> - Dias da semana</li>";
    echo "<li><code>escala</code> - Tipo de escala</li>";
    echo "<li><code>status</code> - Status (ativo/inativo)</li>";
    echo "<li><code>created_at</code> - Data de criação</li>";
    echo "<li><code>updated_at</code> - Data de atualização</li>";
    echo "</ul>";
    echo "<p>✓ Índices criados para otimização</p>";
    echo "<p>✓ Chave estrangeira configurada com <code>employees</code></p>";
    echo "</div>";
    
    echo "<a href='../../admin/dashboard.php' class='btn'>← Voltar ao Dashboard</a> ";
    echo "<a href='test_turnos.php' class='btn' style='background: #059669;'>🧪 Testar Criação de Turno</a>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ ERRO AO CRIAR TABELA:</strong><br><br>";
    echo "<strong>Mensagem:</strong> " . $e->getMessage() . "<br><br>";
    
    if (strpos($e->getMessage(), '1005') !== false || strpos($e->getMessage(), '1215') !== false) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ ERRO DE CHAVE ESTRANGEIRA</strong><br><br>";
        echo "A tabela 'employees' precisa existir antes de criar 'turnos'.<br>";
        echo "Verifique se a tabela 'employees' existe no banco de dados.";
        echo "</div>";
    }
    
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

echo "</div>";
echo "</body>";
echo "</html>";
?>
