<?php
// Verificar fotos na base de dados
require_once __DIR__ . '/../../config/db_connection.php';

$rootPath = dirname(__DIR__, 2);

echo "<h1>🔍 Verificação de Fotos de Perfil</h1>";
echo "<style>body{font-family:Arial;padding:2rem;background:#f5f7fb} table{width:100%;border-collapse:collapse;} th,td{padding:1rem;border:1px solid #ddd;text-align:left;} th{background:#667eea;color:white;} img{width:50px;height:50px;border-radius:50%;object-fit:cover;}</style>";

try {
    $stmt = $pdo->query(
        "SELECT id, name, profile_picture, created_at " .
        "FROM employees " .
        "WHERE profile_picture IS NOT NULL " .
        "ORDER BY id DESC " .
        "LIMIT 10"
    );
    
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>✅ Funcionários com Foto ({$stmt->rowCount()})</h2>";
    
    if (count($employees) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Nome</th><th>Caminho da Foto</th><th>Preview</th><th>Existe?</th></tr>";
        
        foreach ($employees as $emp) {
            $photoPath = $emp['profile_picture'];
            $normalizedPath = ltrim($photoPath, '/');
            $fullPath = $rootPath . '/' . $normalizedPath;
            $exists = file_exists($fullPath);
            $existsIcon = $exists ? '✅' : '❌';
            $imgSrc = '../../' . $normalizedPath;
            
            echo "<tr>";
            echo "<td>{$emp['id']}</td>";
            echo "<td>{$emp['name']}</td>";
            echo "<td><code>{$photoPath}</code></td>";
            echo "<td>";
            if ($exists) {
                echo "<img src='{$imgSrc}' alt='Preview'>";
            } else {
                echo "<span style='color:red;'>Arquivo não encontrado</span>";
            }
            echo "</td>";
            echo "<td>{$existsIcon}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color:orange;'>⚠️ Nenhum funcionário com foto encontrado.</p>";
    }
    
    // Verificar estrutura da tabela
    echo "<h2>📋 Estrutura da Coluna profile_picture</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'profile_picture'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($column) {
        echo "<pre>";
        print_r($column);
        echo "</pre>";
    } else {
        echo "<p style='color:red;'>❌ Coluna profile_picture não existe!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>
