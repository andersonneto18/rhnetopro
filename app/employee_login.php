<?php

session_start();
if (isset($_SESSION['employee_id'])) {
    header('Location: portal.php');
    exit;
}
$last_error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login Funcionário - RH Neto</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.login-container {
    background: white;
    max-width: 420px;
    width: 100%;
    padding: 2.5rem 2rem;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.logo {
    text-align: center;
    margin-bottom: 2rem;
}

.logo i {
    font-size: 4rem;
    color: #667eea;
    margin-bottom: 1rem;
}

.logo h1 {
    color: #2c3e50;
    font-size: 1.8rem;
    margin-bottom: 0.5rem;
}

.logo p {
    color: #7f8c8d;
    font-size: 0.95rem;
}

.alert {
    background: #fee2e2;
    color: #991b1b;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-left: 4px solid #dc2626;
}

.alert i {
    font-size: 1.2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.input-wrapper {
    position: relative;
}

.input-wrapper i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
}

.form-group input {
    width: 100%;
    padding: 0.875rem 1rem 0.875rem 3rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-login {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    border-radius: 8px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
}

.btn-login:active {
    transform: translateY(0);
}

.footer-links {
    text-align: center;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.footer-links a {
    color: #667eea;
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.3s;
}

.footer-links a:hover {
    color: #764ba2;
}
</style>
</head>
<body>
<div class="login-container">
    <div class="logo">
        <i class="fas fa-user-tie"></i>
        <h1>Portal do Funcionário</h1>
        <p>RH Neto ProWeb</p>
    </div>

    <?php if ($last_error): ?>
    <div class="alert">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($last_error); ?></span>
    </div>
    <?php endif; ?>

    <form method="post" action="employee_auth.php" autocomplete="off">
        <div class="form-group">
            <label for="employee_name">Nome do Funcionário</label>
            <div class="input-wrapper">
                <i class="fas fa-user"></i>
                <input id="employee_name" name="employee_name" type="text" required autofocus placeholder="Digite seu nome completo">
            </div>
        </div>

        <div class="form-group">
            <label for="pin">PIN</label>
            <div class="input-wrapper">
                <i class="fas fa-lock"></i>
                <input id="pin" name="pin" type="password" required placeholder="Digite seu PIN">
            </div>
        </div>

        <button type="submit" class="btn-login">
            <i class="fas fa-sign-in-alt"></i>
            Entrar no Portal
        </button>
    </form>
</div>
</body>
</html>