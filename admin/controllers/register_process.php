<?php
// Inicia a sessão no início do script para armazenar mensagens de erro ou sucesso
session_start(); 

// Assume que 'db_connect.php' retorna a variável $conn (MySQLi) para a conexão com a BD
require_once '../../config/db_connect.php'; 

// Verifica se o método da requisição é POST. Se não for, redireciona para a página de registo.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Limpa as mensagens de sessão anteriores para evitar que sejam mostradas novamente
    unset($_SESSION['register_error_message']);
    unset($_SESSION['register_success_message']);

    // Coleta e sanitiza os dados do formulário, usando o operador de coalescência nula para evitar avisos
    $nome_completo = trim($_POST['full-name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $nif = trim($_POST['nif'] ?? '');
    $nome_empresa = trim($_POST['company-name'] ?? ''); // *** CAMPO ADICIONADO ***
    
    // *** CORREÇÃO AQUI: 'username-login' é o nome correto do campo no HTML ***
    $nome_usuario = trim($_POST['username-login'] ?? ''); 
    $senha = $_POST['new-password'] ?? '';
    $confirm_senha = $_POST['confirm-password'] ?? '';
    $termos_aceites = isset($_POST['terms']);

    // === INÍCIO DA VALIDAÇÃO DOS DADOS ===
    // Verifica se os campos obrigatórios estão vazios.
    // Agora inclui 'nome_empresa' e 'nome_usuario' corretamente
    if (empty($nome_completo) || empty($email) || empty($nome_usuario) || empty($senha) || empty($confirm_senha) || empty($nome_empresa)) {
        $_SESSION['register_error_message'] = "Por favor, preencha todos os campos obrigatórios.";
        header("Location: ../views/signup.php");
        exit();
    }

    // Validação do formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error_message'] = "Formato de email inválido.";
        header("Location: ../views/signup.php");
        exit();
    }

    // Verifica se a senha e a confirmação de senha coincidem
    if ($senha !== $confirm_senha) {
        $_SESSION['register_error_message'] = "A senha e a confirmação de senha não coincidem.";
        header("Location: ../views/signup.php");
        exit();
    }

    // Verifica se os termos foram aceites
    if (!$termos_aceites) {
        $_SESSION['register_error_message'] = "Você deve aceitar os Termos de Serviço e a Política de Privacidade.";
        header("Location: ../views/signup.php");
        exit();
    }

    // Validação de formato para Telefone e NIF (opcional, pode ser removido se a validação do browser for suficiente)
    if (!empty($telefone) && !preg_match("/^[0-9]{9}$/", $telefone)) {
        $_SESSION['register_error_message'] = "Número de telemóvel inválido. Deve ter 9 dígitos numéricos.";
        header("Location: ../views/signup.php");
        exit();
    }
    if (!empty($nif) && !preg_match("/^[0-9]{9}$/", $nif)) {
        $_SESSION['register_error_message'] = "NIF inválido. Deve ter 9 dígitos numéricos.";
        header("Location: ../views/signup.php");
        exit();
    }
    // === FIM DA VALIDAÇÃO DOS DADOS ===

    // === INÍCIO DA VERIFICAÇÃO DE UNICIDADE NA BASE DE DADOS ===
    // Prepara a query para verificar se o email, nome de usuário, telefone ou NIF já existem
    // Usamos uma única query para eficiência
    $stmt = $conn->prepare("SELECT email, nome_usuario, telefone, nif FROM usuarios WHERE email = ? OR nome_usuario = ? OR telefone = ? OR nif = ?");
    $stmt->bind_param("ssss", $email, $nome_usuario, $telefone, $nif);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Encontra qual campo já está em uso para uma mensagem mais específica
        $stmt->bind_result($db_email, $db_nome_usuario, $db_telefone, $db_nif);
        $stmt->fetch();

        if ($db_email === $email) {
            $_SESSION['register_error_message'] = "O email já está em uso.";
        } else if ($db_nome_usuario === $nome_usuario) {
            $_SESSION['register_error_message'] = "O nome de usuário já está em uso.";
        } else if (!empty($db_telefone) && $db_telefone === $telefone) {
            $_SESSION['register_error_message'] = "O número de telemóvel já está em uso.";
        } else if (!empty($db_nif) && $db_nif === $nif) {
            $_SESSION['register_error_message'] = "O NIF já está em uso.";
        } else {
            // Mensagem genérica se a verificação acima não capturar o erro
            $_SESSION['register_error_message'] = "Dados de cadastro já em uso (email, usuário, telefone ou NIF).";
        }
        
        $stmt->close();
        header("Location: ../views/signup.php");
        exit();
    }
    $stmt->close(); 
    // === FIM DA VERIFICAÇÃO DE UNICIDADE NA BASE DE DADOS ===

    // Se as validações passarem, faz o hash da senha antes de guardar na BD
    $senha_hashed = password_hash($senha, PASSWORD_DEFAULT);

    // ******************************************************
    // *** INÍCIO DAS ALTERAÇÕES PARA ATRIBUIR client_id ***
    // ******************************************************

    // 1. Inserir um novo cliente na tabela 'clients' e obter o novo client_id
    // Usamos o nome da empresa como nome inicial do cliente.
    $stmt_client = $conn->prepare("INSERT INTO clients (client_name) VALUES (?)");
    $stmt_client->bind_param("s", $nome_empresa);

    if (!$stmt_client->execute()) {
        $_SESSION['register_error_message'] = "Erro ao criar o registo do cliente: " . $stmt_client->error;
        $stmt_client->close();
        header("Location: ../views/signup.php");
        exit();
    }

    // Compatível com MySQLi e PDO
    $new_client_id = null;
    if ($conn instanceof mysqli) {
        $new_client_id = $conn->insert_id;
    } elseif ($conn instanceof PDO) {
        $new_client_id = $conn->lastInsertId();
    }
    $stmt_client->close(); // Fecha o statement do cliente

    // 2. Insere os dados do novo utilizador na base de dados, INCLUINDO o client_id
    // A coluna 'company_name' na tabela 'usuarios' é opcional. Se quiser, adicione-a.
    // O seu formulário HTML já tem a coluna 'company-name' no formulário.
    $stmt_user_insert = $conn->prepare("INSERT INTO usuarios (nome_completo, email, telefone, nif, nome_usuario, senha, client_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Note o 'i' no 'ssssssi' para indicar que o último parâmetro (client_id) é um inteiro.
    $stmt_user_insert->bind_param("ssssssi", $nome_completo, $email, $telefone, $nif, $nome_usuario, $senha_hashed, $new_client_id); 

    if ($stmt_user_insert->execute()) {
        $_SESSION['register_success_message'] = "Registo realizado com sucesso! Pode agora fazer login.";
        header("Location: ../views/login.php");
        exit();
    } else {
        $_SESSION['register_error_message'] = "Erro ao registar utilizador: " . $stmt_user_insert->error;
        // Se a inserção do utilizador falhar, remove o registo do cliente para manter a integridade
        $conn->query("DELETE FROM clients WHERE client_id = " . $new_client_id); 
        header("Location: ../views/signup.php");
        exit();
    }

    // ******************************************************
    // *** FIM DAS ALTERAÇÕES PARA ATRIBUIR client_id ***
    // ******************************************************

    // O código abaixo é inalcançável devido aos 'exit()' acima.
    // $stmt_user_insert->close();
    // $conn->close();

} else {
    // Redireciona para a página de registo se não for uma requisição POST
    header("Location: ../views/signup.php");
    exit();
}
?>
