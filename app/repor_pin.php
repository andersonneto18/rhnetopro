<?php
session_start();
date_default_timezone_set('Europe/Lisbon');
require_once '../config/db_connection.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = false;

if ($token === '') {
    $error = 'Link inválido.';
} else {
    $tokenHash = hash('sha256', $token);

    try {
        $stmt = $pdo->prepare(
            "SELECT id, employee_id, used_at, expires_at FROM employee_pin_resets
             WHERE token_hash = ? LIMIT 1"
        );
        $stmt->execute([$tokenHash]);
        $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetRow) {
            $error = 'Link inválido ou já utilizado.';
        } elseif ($resetRow['used_at'] !== null) {
            $error = 'Este link já foi utilizado. Peça um novo em "Esqueceu o PIN?".';
        } elseif (strtotime((string)$resetRow['expires_at']) < time()) {
            $error = 'Este link expirou. Peça um novo em "Esqueceu o PIN?".';
        }
    } catch (Throwable $e) {
        error_log('Erro em repor_pin.php (validação): ' . $e->getMessage());
        $error = 'Não foi possível validar o link agora. Tente novamente.';
    }
}

if ($error === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPin = trim($_POST['new_pin'] ?? '');
    $confirmPin = trim($_POST['confirm_pin'] ?? '');

    if (strlen($newPin) < 4) {
        $error = 'O PIN deve ter pelo menos 4 caracteres.';
    } elseif ($newPin !== $confirmPin) {
        $error = 'Os PINs não coincidem.';
    } else {
        try {
            $pdo->beginTransaction();

            $pinHash = password_hash($newPin, PASSWORD_DEFAULT);
            $updateEmp = $pdo->prepare('UPDATE employees SET pin_hash = ?, pin = NULL WHERE id = ?');
            $updateEmp->execute([$pinHash, (int)$resetRow['employee_id']]);

            $markUsed = $pdo->prepare('UPDATE employee_pin_resets SET used_at = NOW() WHERE id = ?');
            $markUsed->execute([(int)$resetRow['id']]);

            $pdo->commit();
            $success = true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Erro em repor_pin.php (gravação): ' . $e->getMessage());
            $error = 'Não foi possível repor o PIN agora. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Repor PIN — Portal do Funcionário</title>
<link rel="icon" type="image/png" href="../admin/views/images/rh1.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="employee_login.css">
</head>
<body>

<div class="page-wrapper">
    <div class="bg-rotate"></div>
    <div class="form-box">
        <div class="form-logo"><img src="../admin/views/images/rh1.png" alt="RHNeto Pro"></div>
        <h2>Repor o PIN</h2>

        <?php if ($success): ?>
            <p class="form-sub">O seu PIN foi reposto com sucesso.</p>
            <div class="alert alert-ok"><i class="fas fa-circle-check"></i><span>Já pode entrar no portal com o novo PIN.</span></div>
            <a href="employee_login.php" class="btn-primary" style="text-decoration:none;margin-top:1rem;">
                <i class="fas fa-arrow-right-to-bracket"></i> Ir para o login
            </a>
        <?php elseif ($error !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
            <p class="form-sub">Não foi possível continuar</p>
            <div class="alert"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            <a href="esqueci_pin.php" class="btn-primary" style="text-decoration:none;margin-top:1rem;">
                <i class="fas fa-rotate"></i> Pedir novo link
            </a>
        <?php else: ?>
            <p class="form-sub">Escolha o seu novo PIN de acesso</p>

            <?php if ($error !== ''): ?>
            <div class="alert"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            <?php endif; ?>

            <form method="post" action="repor_pin.php?token=<?php echo urlencode($token); ?>" autocomplete="off">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="field">
                    <label for="new_pin">Novo PIN</label>
                    <div class="field-wrap"><i class="fas fa-lock"></i>
                        <input id="new_pin" name="new_pin" type="password" required minlength="4" placeholder="Mínimo 4 caracteres">
                    </div>
                </div>

                <div class="field">
                    <label for="confirm_pin">Confirmar PIN</label>
                    <div class="field-wrap"><i class="fas fa-lock"></i>
                        <input id="confirm_pin" name="confirm_pin" type="password" required minlength="4" placeholder="Repita o novo PIN">
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Repor PIN
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
