<?php
/**
 * Migration: adiciona campos de salário à tabela employees
 * Execute uma vez: http://localhost/app-rhnetopro/tools/migrate_salary_fields.php
 */
require_once __DIR__ . '/../config/db_connection.php';

$cols = $pdo->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);

$toAdd = [
    'salary_base'           => "ADD COLUMN salary_base DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Salário base mensal'",
    'subsidio_alimentacao'  => "ADD COLUMN subsidio_alimentacao DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Subsídio de alimentação mensal'",
    'bonus'                 => "ADD COLUMN bonus DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Bónus mensal fixo'",
];

$added = 0;
foreach ($toAdd as $col => $def) {
    if (!in_array($col, $cols)) {
        $pdo->exec("ALTER TABLE employees $def");
        echo "✓ Coluna '$col' adicionada.\n";
        $added++;
    } else {
        echo "• Coluna '$col' já existe.\n";
    }
}

echo $added > 0 ? "\nMigração concluída! $added coluna(s) adicionada(s).\n" : "\nNenhuma alteração necessária.\n";
