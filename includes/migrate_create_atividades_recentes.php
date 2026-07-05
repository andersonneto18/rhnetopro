<?php
/**
 * Migração para garantir estrutura da tabela atividades_recentes.
 *
 * Esta migração:
 * 1) Cria a tabela se não existir.
 * 2) Garante colunas obrigatórias (status, employee_id).
 * 3) Garante índices para performance da timeline.
 *
 * Execute uma vez: php includes/migrate_create_atividades_recentes.php
 */

require_once __DIR__ . '/../config/db_connection.php';

try {
    echo "Iniciando migração de atividades_recentes...\n\n";

    // 1) Cria tabela base se não existir
    $pdo->exec("CREATE TABLE IF NOT EXISTS atividades_recentes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        type VARCHAR(32) NOT NULL DEFAULT 'info',
        status VARCHAR(64) NULL,
        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        client_id INT NOT NULL,
        employee_id INT NULL,
        INDEX idx_atividades_client_timestamp (client_id, timestamp),
        INDEX idx_atividades_employee (employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "✓ Tabela atividades_recentes garantida.\n";

    // 2) Verifica colunas existentes
    $existingColumns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM atividades_recentes");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }

    if (!in_array('status', $existingColumns, true)) {
        $pdo->exec("ALTER TABLE atividades_recentes ADD COLUMN status VARCHAR(64) NULL AFTER type");
        echo "✓ Coluna 'status' adicionada.\n";
    } else {
        echo "• Coluna 'status' já existe.\n";
    }

    if (!in_array('employee_id', $existingColumns, true)) {
        $pdo->exec("ALTER TABLE atividades_recentes ADD COLUMN employee_id INT NULL AFTER client_id");
        echo "✓ Coluna 'employee_id' adicionada.\n";
    } else {
        echo "• Coluna 'employee_id' já existe.\n";
    }

    // 3) Garante índices (ignora erro se já existirem)
    try {
        $pdo->exec("ALTER TABLE atividades_recentes ADD INDEX idx_atividades_client_timestamp (client_id, timestamp)");
        echo "✓ Índice idx_atividades_client_timestamp criado.\n";
    } catch (PDOException $e) {
        echo "• Índice idx_atividades_client_timestamp já existe (ou não necessário).\n";
    }

    try {
        $pdo->exec("ALTER TABLE atividades_recentes ADD INDEX idx_atividades_employee (employee_id)");
        echo "✓ Índice idx_atividades_employee criado.\n";
    } catch (PDOException $e) {
        echo "• Índice idx_atividades_employee já existe (ou não necessário).\n";
    }

    // FK opcional: tenta criar, mas não falha se já existir/incompatibilidade
    try {
        $pdo->exec("ALTER TABLE atividades_recentes
            ADD CONSTRAINT fk_atividades_employee
            FOREIGN KEY (employee_id) REFERENCES employees(id)
            ON DELETE SET NULL ON UPDATE CASCADE");
        echo "✓ FK fk_atividades_employee criada.\n";
    } catch (PDOException $e) {
        echo "• FK fk_atividades_employee não criada (já existe ou estrutura incompatível).\n";
    }

    echo "\nMigração concluída com sucesso.\n";

} catch (PDOException $e) {
    echo "ERRO na migração: " . $e->getMessage() . "\n";
    exit(1);
}
