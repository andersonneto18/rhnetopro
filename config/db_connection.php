<?php
// db_connection.php

// Configurações da base de dados
$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "sistema_cadastro";  // **** SUBSTITUA PELA SUA SENHA DA BASE DE DADOS (se tiver) ****
try {
    // Cria uma nova instância PDO para a conexão
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);

    // Define o modo de erro para que exceções sejam lançadas em caso de erro
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Define o modo de busca padrão para retornar arrays associativos (nome da coluna => valor)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Opcional: Para verificar se a conexão foi bem-sucedida (pode remover após o teste)
    // echo "Conexão com a base de dados estabelecida com sucesso!";

} catch (PDOException $e) {
    // Se houver um erro na conexão, exibe a mensagem de erro e interrompe o script
    die("Erro de conexão com a base de dados: " . $e->getMessage());
}
?>