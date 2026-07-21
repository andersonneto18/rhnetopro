<?php
require_once __DIR__ . '/env.php';

// Usa DB_* do .env quando definido, caso contrário assume o ambiente local (XAMPP).
$servername = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') ?: "";
$dbname = getenv('DB_NAME') ?: "sistema_cadastro";

// Criar a conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar a conexão
if ($conn->connect_error) {
    die("Falha na conexão com a base de dados SISTEMA_CADAST: " . $conn->connect_error);
}

// Definir charset
$conn->set_charset("utf8mb4");

?>
