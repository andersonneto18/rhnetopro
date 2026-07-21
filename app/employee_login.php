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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portal do Funcionário – Acesso</title>
<link rel="icon" type="image/png" href="../admin/views/images/rh1.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="employee_login.css">
</head>
<body>

<div class="page-wrapper">
    <div class="bg-rotate"></div>
    <div class="form-box">
        <form method="post" action="employee_auth.php" autocomplete="off">
            <div class="form-logo"><img src="../admin/views/images/rh1.png" alt="RHNeto Pro"></div>
            <h2>Portal do Funcionário</h2>
            <p class="form-sub">Introduza os seus dados para aceder</p>

            <?php if ($last_error): ?>
            <div class="alert"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($last_error); ?></span></div>
            <?php endif; ?>

            <div class="field">
                <label for="employee_name">Nome do Funcionário</label>
                <div class="field-wrap"><i class="fas fa-user"></i>
                    <input id="employee_name" name="employee_name" type="text" required autofocus placeholder="Introduza o seu nome completo">
                </div>
            </div>

            <div class="field">
                <label for="pin">PIN</label>
                <div class="field-wrap"><i class="fas fa-lock"></i>
                    <input id="pin" name="pin" type="password" required placeholder="Introduza o seu PIN">
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-arrow-right-to-bracket"></i> Entrar no Portal
            </button>
        </form>
    </div>
</div>
</body>
</html>
