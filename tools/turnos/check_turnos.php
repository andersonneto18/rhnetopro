<?php
require_once __DIR__ . '/../../config/db_connection.php';

echo "<h2>Verificando Tabela 'turnos'</h2>";

try {
    // Verifica se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'turnos'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Tabela 'turnos' existe!</p>";
        
        // Mostra estrutura
        echo "<h3>Estrutura da tabela:</h3>";
        $cols = $pdo->query("DESCRIBE turnos")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($cols as $col) {
            echo "<tr>";
            echo "<td><strong>" . $col['Field'] . "</strong></td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . $col['Null'] . "</td>";
            echo "<td>" . $col['Key'] . "</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . $col['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Conta registros
        $count = $pdo->query("SELECT COUNT(*) FROM turnos")->fetchColumn();
        echo "<p>Total de registros: <strong>$count</strong></p>";
        
        // Mostra últimos registros
        if ($count > 0) {
            echo "<h3>Últimos 5 registros:</h3>";
            $stmt = $pdo->query("SELECT * FROM turnos ORDER BY id DESC LIMIT 5");
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Funcionário ID</th><th>Turno Tipo</th><th>Horário</th><th>Dias Semana</th><th>Escala</th><th>Status</th></tr>";
            foreach ($registros as $reg) {
                echo "<tr>";
                echo "<td>" . $reg['id'] . "</td>";
                echo "<td>" . $reg['funcionario_id'] . "</td>";
                echo "<td>" . $reg['turno_tipo'] . "</td>";
                echo "<td>" . $reg['horario_inicio'] . " - " . $reg['horario_fim'] . "</td>";
                echo "<td>" . $reg['dias_semana'] . "</td>";
                echo "<td>" . $reg['escala'] . "</td>";
                echo "<td>" . $reg['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Tabela 'turnos' NÃO existe!</p>";
        echo "<p>Precisa criar a tabela. SQL sugerido:</p>";
        echo "<pre>
CREATE TABLE turnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    turno_tipo VARCHAR(50) NOT NULL,
    horario_inicio TIME NOT NULL,
    horario_fim TIME NOT NULL,
    dias_semana VARCHAR(100) NOT NULL,
    escala VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        </pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}
?>
