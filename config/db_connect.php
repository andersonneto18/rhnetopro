<?php
$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "sistema_cadastro"; 

// Criar a conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar a conexão
if ($conn->connect_error) {
    die("Falha na conexão com a base de dados SISTEMA_CADAST: " . $conn->connect_error);
}

// Definir charset
$conn->set_charset("utf8mb4");

?>
