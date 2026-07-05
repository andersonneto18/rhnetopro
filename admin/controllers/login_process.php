<?php
session_start(); // Inicia a sessão no início do script

// Inclui o arquivo de conexão MySQLi para a base de dados 'sistema_cadastro'
require_once '../../config/db_connect.php'; // Corrigido: sobe dois níveis para acessar config

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_email = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Limpa qualquer mensagem de erro anterior da sessão
    unset($_SESSION['error_message']);

    // Validação Básica dos Dados
    if (empty($username_or_email) || empty($password)) {
        $_SESSION['error_message'] = "Por favor, preencha todos os campos.";
        header("Location: ../views/login.php");
        exit();
    }

    try {
        // Prepara a consulta para buscar o utilizador usando MySQLi
        // ATENÇÃO: Mudança de $pdo->prepare para $conn->prepare
        $stmt = $conn->prepare("SELECT id, nome_completo, nome_usuario, senha, client_id, profile_picture FROM usuarios WHERE nome_usuario = ? OR email = ?");
        
        // Vincula os parâmetros e executa a consulta usando MySQLi
        // ATENÇÃO: Mudança de $stmt->execute([params]) para $stmt->bind_param e $stmt->execute()
        $stmt->bind_param("ss", $username_or_email, $username_or_email);
        $stmt->execute();
        
        // Obtém o resultado
        $result = $stmt->get_result();
        $user = $result->fetch_assoc(); // Busca o resultado como um array associativo

        if ($user) { // Se um utilizador foi encontrado
            $hashed_password = $user['senha'];

            if (password_verify($password, $hashed_password)) {
                // Login bem-sucedido: Armazena dados na sessão e redireciona para o dashboard
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['nome_usuario'];
                $_SESSION['fullname'] = $user['nome_completo'];
                $_SESSION['client_id'] = $user['client_id']; // CRÍTICO para Multi-tenancy!
                
                // Carregar foto de perfil
                $_SESSION['user_preferences'] = [
                    'theme' => 'light',
                    'profile_picture' => !empty($user['profile_picture']) 
                        ? '../' . $user['profile_picture'] 
                        : '../assets/images/perfil.png'
                ];

                header("Location: ../dashboard.php");
                exit();
            } else {
                // Senha incorreta
                $_SESSION['error_message'] = "Nome de utilizador/Email ou senha incorretos.";
                header("Location: ../views/login.php");
                exit();
            }
        } else {
            // Utilizador não encontrado
            $_SESSION['error_message'] = "Nome de utilizador/Email ou senha incorretos.";
            header("Location: ../views/login.php");
            exit();
        }

    } catch (Exception $e) { // Captura exceções gerais, incluindo as de MySQLi
        // Captura e exibe erros
        $_SESSION['error_message'] = "Erro de base de dados: " . $e->getMessage();
        error_log("Erro no login_process.php: " . $e->getMessage()); // Para depuração
        header("Location: ../views/login.php");
        exit();
    } finally {
        // Fechar o statement e a conexão MySQLi
        if (isset($stmt)) {
            $stmt->close();
        }
        if (isset($conn)) {
            // Se for MySQLi, pode fechar. Se for PDO, não precisa.
            // Não é necessário fechar PDO ou wrapper customizado
        }
    }

} else {
    // Se a requisição não for POST, redireciona para a página de login
    header("Location: ../views/login.php");
    exit();
}
?>