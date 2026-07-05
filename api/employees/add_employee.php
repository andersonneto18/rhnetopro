<?php
session_start(); // Inicia a sessão

require_once '../../config/db_connection.php'; // Inclui a conexão com a base de dados (PDO)


// Verifica se o utilizador está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// **** ADICIONADO: Obtém o client_id do utilizador logado para Multi-Tenancy ****
if (!isset($_SESSION['client_id'])) {
    // Se o client_id não estiver na sessão, força o logout para segurança
    session_unset();
    session_destroy();
    header("Location: login.php?error=sessao_invalida");
    exit();
}
$loggedInClientId = $_SESSION['client_id']; // ID do cliente do utilizador logado
// ******************************************************************************

$message = ''; // Para mensagens de sucesso ou erro (mantido para erro de validação)

// Verifica se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recolhe e sanitiza os dados do formulário
    $name = htmlspecialchars($_POST['name'] ?? '');
    $position = htmlspecialchars($_POST['position'] ?? '');
    $department = htmlspecialchars($_POST['department'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $phone = htmlspecialchars($_POST['phone'] ?? '');
    $startDate = htmlspecialchars($_POST['startDate'] ?? '');
    $status = htmlspecialchars($_POST['status'] ?? 'active'); // Valor padrão

    // novo campo PIN (opcional)
    $pin = trim($_POST['pin'] ?? '');

    // Validação básica
    if (empty($name) || empty($email) || empty($startDate)) {
        $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i>Por favor, preencha todos os campos obrigatórios (Nome, Email, Data de Início).</div>';
    } elseif ($pin !== '' && strlen($pin) < 4) {
        $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i>O PIN deve ter pelo menos 4 caracteres.</div>';
    } else {
        try {
            // gera hash do PIN se preenchido
            $pin_hash = $pin !== '' ? password_hash($pin, PASSWORD_DEFAULT) : null;

            // Consulta INSERT segura com PDO
            // IMPORTANT: incluir coluna pin_hash (assumindo que a tabela tem essa coluna)
            $stmt = $pdo->prepare("INSERT INTO employees (name, position, department, email, phone, startDate, status, client_id, pin_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Executa com o ID do cliente logado no último parâmetro
            $stmt->execute([$name, $position, $department, $email, $phone, $startDate, $status, $loggedInClientId, $pin_hash]);

            // **** ALTERAÇÃO CRÍTICA AQUI: REDIRECIONAMENTO ****
            header("Location: ../../admin/dashboard.php?status=success&action=added");
            exit();

        } catch (PDOException $e) {
            $message = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i>Erro ao adicionar funcionário: ' . $e->getMessage() . '</div>';
            error_log("Erro em add_employee.php: " . $e->getMessage()); // Log para depuração
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Funcionário - RHNeto Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" patternUnits="userSpaceOnUse" width="100" height="100"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.03)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .background-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 50px;
            border-radius: 24px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 8px 25px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
            max-width: 700px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 2px;
        }

        .header h2 {
            color: #2d3748;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .header-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .header-icon i {
            color: white;
            font-size: 2rem;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            font-weight: 500;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert i {
            margin-right: 12px;
            font-size: 1.1rem;
        }

        .alert-success {
            background: linear-gradient(135deg, #68d391, #48bb78);
            color: white;
            border: 1px solid rgba(72, 187, 120, 0.3);
        }

        .alert-error {
            background: linear-gradient(135deg, #fc8181, #e53e3e);
            color: white;
            border: 1px solid rgba(229, 62, 62, 0.3);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .form-group {
            position: relative;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: #4a5568;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        select {
            width: 100%;
            padding: 16px 16px 16px 50px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            color: #2d3748;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            outline: none;
        }

        input:focus,
        select:focus {
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        input:focus + .input-icon,
        select:focus + .input-icon {
            color: #667eea;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px 40px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
        }

        .btn-back {
            display: block;
            text-align: center;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            padding: 15px;
            border-radius: 12px;
            border: 2px solid #667eea;
            background: rgba(102, 126, 234, 0.05);
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        /* Responsividade */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 30px 25px;
                border-radius: 16px;
            }

            .header h2 {
                font-size: 1.8rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .header-icon {
                width: 60px;
                height: 60px;
            }

            .header-icon i {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 25px 20px;
            }

            .header h2 {
                font-size: 1.6rem;
            }

            input[type="text"],
            input[type="email"],
            input[type="date"],
            select {
                padding: 14px 14px 14px 45px;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="background-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="container">
        <div class="header">
            <div class="header-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h2>Adicionar Novo Funcionário</h2>
        </div>

        <?php echo $message; // Exibe mensagens de erro de validação ?>

        <form method="POST" action="add_employee.php">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label for="name">Nome Completo:</label>
                    <div class="input-wrapper">
                        <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="position">Cargo:</label>
                    <div class="input-wrapper">
                        <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>">
                        <i class="fas fa-id-badge input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="department">Departamento:</label>
                    <div class="input-wrapper">
                        <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>">
                        <i class="fas fa-building input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="email">Email:</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Telefone:</label>
                    <div class="input-wrapper">
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        <i class="fas fa-phone input-icon"></i>
                    </div>
                </div>

                <!-- NOVO CAMPO PIN -->
                <div class="form-group">
                    <label for="pin">PIN (opcional)</label>
                    <div class="input-wrapper">
                        <input type="password" id="pin" name="pin" value="<?php echo htmlspecialchars($_POST['pin'] ?? ''); ?>">
                        <i class="fas fa-key input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="startDate">Data de Início:</label>
                    <div class="input-wrapper">
                        <input type="date" id="startDate" name="startDate" required value="<?php echo htmlspecialchars($_POST['startDate'] ?? ''); ?>">
                        <i class="fas fa-calendar-alt input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status:</label>
                    <div class="input-wrapper">
                        <select id="status" name="status">
                            <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] === 'active') ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : ''; ?>>Inativo</option>
                            <option value="ferias" <?php echo (isset($_POST['status']) && $_POST['status'] === 'ferias') ? 'selected' : ''; ?>>Férias</option>
                        </select>
                        <i class="fas fa-toggle-on input-icon"></i>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">Adicionar Funcionário</button>
        </form>
        
        <a href="dashboard.php" class="btn-back">Voltar ao Painel</a>
    </div>
</body>
</html>
