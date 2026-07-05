<?php
/**
 * Script para adicionar a coluna profile_picture na tabela usuarios
 * Execute este arquivo UMA VEZ via navegador: http://localhost/rhneto-proweb/admin/add_profile_picture_column.php
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>\u274c ERRO: " . $e->getMessage() . "</p>";
}
if (method_exists($conn, 'close')) {
    $conn->close();
}
    $result = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'profile_picture'");
    
    if ($result->num_rows > 0) {
        // Compatível com MySQLi e PDO
        $numRows = 0;
        if (property_exists($result, 'num_rows')) {
            $numRows = $result->num_rows;
        } elseif (method_exists($result, 'rowCount')) {
            $numRows = $result->rowCount();
        }
        if ($numRows > 0) {
        echo "<p style='color: orange;'>⚠️ A coluna 'profile_picture' JÁ EXISTE na tabela 'usuarios'.</p>";
    } else {
        // Adicionar a coluna
        $sql = "ALTER TABLE usuarios ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL AFTER client_id";
        $conn->query($sql);
        
        echo "<p style='color: green;'>✅ Coluna 'profile_picture' adicionada com SUCESSO!</p>";
    }
    
    // Mostrar estrutura da tabela
    if (method_exists($conn, 'close')) {
        $conn->close();
    }
    echo "<h3>📋 Estrutura atual da tabela 'usuarios':</h3>";
    $columns = $conn->query("DESCRIBE usuarios");
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f3f4f6;'><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    while ($col = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . ($col['Default'] ?? '<em>NULL</em>') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='margin-top: 20px;'><a href='dashboard.php'>← Voltar ao Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>\u274c ERRO: " . $e->getMessage() . "</p>";
}

    if (method_exists($conn, 'close')) {
        $conn->close();
    }
?>
