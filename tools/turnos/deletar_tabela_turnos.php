<?php
/**
 * Script para FORÇAR a deleção da tabela TURNOS
 * Execute: http://localhost/rhneto-proweb/tools/turnos/deletar_tabela_turnos.php
 */

require_once __DIR__ . '/../../config/db_connection.php';

echo "<!DOCTYPE html>";
echo "<html lang='pt'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Deletar Tabela Turnos</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 30px; background: #f5f7fb; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    h1 { color: #dc2626; border-bottom: 3px solid #dc2626; padding-bottom: 10px; }
    .success { background: #d1fae5; color: #065f46; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #10b981; }
    .error { background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #ef4444; }
    .warning { background: #fef3c7; color: #92400e; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #f59e0b; }
    .btn { display: inline-block; padding: 12px 24px; color: white; text-decoration: none; border-radius: 6px; margin: 10px 5px; font-weight: 600; }
    .btn-danger { background: #dc2626; }
    .btn-danger:hover { background: #b91c1c; }
    .btn-secondary { background: #6b7280; }
    .btn-secondary:hover { background: #4b5563; }
    .btn-primary { background: #2563eb; }
    .btn-primary:hover { background: #1d4ed8; }
    pre { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; }
    code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace; color: #dc2626; }
</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";

echo "<h1>🗑️ Deletar Tabela TURNOS</h1>";

try {
    // Verificar se a tabela existe
    $checkTable = $pdo->query("SHOW TABLES LIKE 'turnos'");
    
    if ($checkTable->rowCount() === 0) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ A tabela 'turnos' NÃO EXISTE!</strong><br>";
        echo "Não há nada para deletar.";
        echo "</div>";
        
        echo "<a href='criar_tabela_turnos.php' class='btn btn-primary'>➕ Criar Tabela Turnos</a>";
        echo "<a href='../../admin/dashboard.php' class='btn btn-secondary'>← Voltar ao Dashboard</a>";
        
        echo "</div></body></html>";
        exit;
    }
    
    // Contar registros
    $count = $pdo->query("SELECT COUNT(*) FROM turnos")->fetchColumn();
    
    echo "<div class='warning'>";
    echo "<strong>⚠️ ATENÇÃO: A tabela 'turnos' EXISTE!</strong><br><br>";
    echo "Registros atuais: <strong>$count turno(s)</strong><br><br>";
    
    if ($count > 0) {
        echo "<strong style='color: #dc2626; font-size: 18px;'>⚠️ TODOS OS $count TURNO(S) SERÃO PERDIDOS!</strong><br>";
    }
    
    echo "</div>";
    
    // Verificar chaves estrangeiras que referenciam turnos
    echo "<h2>🔍 Verificando Dependências</h2>";
    
    $foreignKeys = $pdo->query(""
        . "SELECT "
        . "    TABLE_NAME,"
        . "    CONSTRAINT_NAME,"
        . "    COLUMN_NAME,"
        . "    REFERENCED_TABLE_NAME,"
        . "    REFERENCED_COLUMN_NAME"
        . " FROM information_schema.KEY_COLUMN_USAGE"
        . " WHERE REFERENCED_TABLE_NAME = 'turnos'"
        . " AND TABLE_SCHEMA = 'sistema_cadastro'"
    )->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($foreignKeys) > 0) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Encontradas " . count($foreignKeys) . " tabela(s) com chave estrangeira referenciando 'turnos':</strong><br><br>";
        echo "<ul>";
        foreach ($foreignKeys as $fk) {
            echo "<li>Tabela: <code>{$fk['TABLE_NAME']}</code> - Coluna: <code>{$fk['COLUMN_NAME']}</code></li>";
        }
        echo "</ul>";
        echo "<p>Essas referências serão automaticamente removidas (ON DELETE CASCADE).</p>";
        echo "</div>";
    } else {
        echo "<div class='success'>✓ Nenhuma dependência encontrada.</div>";
    }
    
    // CONFIRMAR DELEÇÃO
    if (!isset($_GET['confirmar'])) {
        echo "<div class='warning' style='margin-top: 30px; padding: 25px;'>";
        echo "<h2 style='margin-top: 0; color: #dc2626;'>⚠️ CONFIRME A AÇÃO</h2>";
        echo "<p style='font-size: 16px;'><strong>Tem certeza que deseja DELETAR a tabela 'turnos'?</strong></p>";
        echo "<p>Esta ação é IRREVERSÍVEL e todos os dados serão perdidos!</p>";
        echo "<div style='margin-top: 25px;'>";
        echo "<a href='?confirmar=SIM' class='btn btn-danger'>🗑️ SIM, DELETAR TABELA</a> ";
        echo "<a href='../../admin/dashboard.php' class='btn btn-secondary'>❌ CANCELAR</a>";
        echo "</div>";
        echo "</div>";
        
    } else {
        // EXECUTAR DELEÇÃO
        echo "<h2>🗑️ Executando Deleção</h2>";
        
        echo "<div style='background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 6px; margin: 15px 0;'>";
        echo "<p>Executando comandos...</p>";
        
        // Desabilitar verificação de chaves estrangeiras
        echo "<p>→ Desabilitando verificação de chaves estrangeiras...</p>";
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Deletar a tabela
        echo "<p>→ Deletando tabela 'turnos'...</p>";
        $pdo->exec("DROP TABLE IF EXISTS turnos");
        
        // Reabilitar verificação de chaves estrangeiras
        echo "<p>→ Reabilitando verificação de chaves estrangeiras...</p>";
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo "<p style='color: #10b981; font-weight: bold;'>✅ Concluído!</p>";
        echo "</div>";
        
        // Verificar se foi deletada
        $checkAgain = $pdo->query("SHOW TABLES LIKE 'turnos'");
        
        if ($checkAgain->rowCount() === 0) {
            echo "<div class='success'>";
            echo "<h2>✅ TABELA DELETADA COM SUCESSO!</h2>";
            echo "<p>A tabela 'turnos' foi completamente removida do banco de dados.</p>";
            echo "</div>";
            
            echo "<h3>📋 Próximos Passos:</h3>";
            echo "<div style='background: #dbeafe; padding: 20px; border-radius: 6px; margin: 15px 0;'>";
            echo "<ol style='margin: 0;'>";
            echo "<li style='margin: 10px 0;'>Criar a nova tabela 'turnos' com a estrutura correta</li>";
            echo "<li style='margin: 10px 0;'>Testar a criação de turnos no dashboard</li>";
            echo "<li style='margin: 10px 0;'>Recadastrar os turnos (se necessário)</li>";
            echo "</ol>";
            echo "</div>";
            
            echo "<a href='criar_tabela_turnos.php' class='btn btn-primary'>➕ Criar Nova Tabela Turnos</a> ";
            echo "<a href='../../admin/dashboard.php' class='btn btn-secondary'>← Voltar ao Dashboard</a>";
            
        } else {
            echo "<div class='error'>";
            echo "<strong>❌ ERRO: A tabela ainda existe!</strong><br>";
            echo "Algo deu errado durante a deleção.";
            echo "</div>";
        }
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ ERRO AO DELETAR TABELA:</strong><br><br>";
    echo "<strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>";
    
    echo "<h3>💡 Possíveis Soluções:</h3>";
    echo "<ol>";
    echo "<li>Acesse o <strong>phpMyAdmin</strong> (http://localhost/phpmyadmin)</li>";
    echo "<li>Selecione o banco <code>sistema_cadastro</code></li>";
    echo "<li>Vá na aba <strong>SQL</strong></li>";
    echo "<li>Execute os seguintes comandos:</li>";
    echo "</ol>";
    
    echo "<pre style='margin: 20px 0;'>";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n";
    echo "DROP TABLE IF EXISTS turnos;\n";
    echo "SET FOREIGN_KEY_CHECKS = 1;</pre>";
    
    echo "<strong>Detalhes técnicos:</strong><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</div>";
echo "</body>";
echo "</html>";
?>
