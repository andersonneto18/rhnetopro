<?php

session_start();
if (isset($_SESSION['employee_id'])) {
    header('Location: employee_portal.php');
    exit;
}
$last_error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!doctype html>
<html lang="pt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login Funcionário</title>
<style>
body{font-family:Arial;padding:2rem;background:#f5f7fb}
.card{max-width:420px;margin:2rem auto;padding:1.25rem;background:#fff;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.06)}
label{display:block;margin:0.5rem 0 0.25rem}
input{width:100%;padding:.6rem;border:1px solid #ddd;border-radius:6px}
button{margin-top:1rem;width:100%;padding:.6rem;background:#0ea5e9;border:0;color:#fff;border-radius:6px;font-weight:700;cursor:pointer}
.small{font-size:.9rem;color:#555;margin-top:.75rem}
.alert{margin-top:.75rem;padding:.5rem;border-radius:6px;background:#fee2e2;color:#9b1c1c}
</style>
</head>
<body>
<div class="card">
  <h2>Login Funcionário</h2>

  <?php if ($last_error): ?>
    <div class="alert"><?php echo htmlspecialchars($last_error); ?></div>
  <?php endif; ?>

  <form method="post" action="employee_auth.php" autocomplete="off">
    <label for="employee_name">Nome do Funcionário</label>
    <input id="employee_name" name="employee_name" required autofocus>

    <label for="pin">PIN</label>
    <input id="pin" name="pin" type="password" required>

    <button type="submit">Entrar</button>
  </form>

  <div class="small">
    <a href="marcar_assiduidade_public.php">Portal público (sem login)</a>
  </div>
</div>
</body>
</html>