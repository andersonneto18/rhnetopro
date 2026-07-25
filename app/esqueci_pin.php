<?php
session_start();
date_default_timezone_set('Europe/Lisbon');
require_once '../config/db_connection.php';
require_once '../includes/mail_sender.php';

function ensureEmployeePinResetsTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS employee_pin_resets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            client_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token_hash (token_hash),
            INDEX idx_employee_id (employee_id),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function buildPortalBaseUrl(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/app/esqueci_pin.php')), '/');
    return $scheme . '://' . $host . $scriptDir;
}

$genericSuccess = 'Se o email existir no sistema, enviámos um link para repor o PIN.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    unset($_SESSION['pin_recover_error']);
    unset($_SESSION['pin_recover_success']);
    unset($_SESSION['pin_recover_debug_link']);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['pin_recover_error'] = 'Indique um email válido para repor o PIN.';
        header('Location: esqueci_pin.php');
        exit;
    }

    try {
        ensureEmployeePinResetsTable($pdo);

        $stmt = $pdo->prepare('SELECT id, name, email, client_id FROM employees WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([$email]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);

        $_SESSION['pin_recover_success'] = $genericSuccess;

        if ($emp) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

            $insert = $pdo->prepare(
                'INSERT INTO employee_pin_resets (employee_id, client_id, email, token_hash, expires_at, ip_address)
                 VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), ?)'
            );
            $insert->execute([(int)$emp['id'], (int)$emp['client_id'], $emp['email'], $tokenHash, $ipAddress]);

            $resetLink = buildPortalBaseUrl() . '/repor_pin.php?token=' . urlencode($token);

            $bodyHtml = '<h1 style="margin:0 0 6px;font-size:22px;color:#0f172a;">Repor o seu PIN</h1>'
                . '<p style="margin:0 0 20px;font-size:14px;line-height:1.5;color:#475569;">Olá ' . htmlspecialchars((string)$emp['name'], ENT_QUOTES, 'UTF-8') . ', recebemos um pedido para repor o PIN de acesso ao Portal do Funcionário. Use o botão abaixo (válido por 1 hora):</p>'
                . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;">'
                . '<tr><td style="border-radius:8px;background-color:#2563eb;">'
                . '<a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 32px;">Repor PIN</a>'
                . '</td></tr></table>'
                . '<p style="margin:0;font-size:12px;color:#94a3b8;">Se não pediu esta alteração, ignore este email — o seu PIN atual continua válido.</p>';

            $emailResult = sendTransactionalEmail(
                $emp['email'],
                (string)$emp['name'],
                'Repor PIN — RHNeto Pro',
                renderBrandedEmailShell($bodyHtml)
            );

            if (!$emailResult['success']) {
                error_log('esqueci_pin: falha ao enviar email — ' . ($emailResult['error'] ?? ''));
                $_SESSION['pin_recover_debug_link'] = $resetLink;
            }
        }

        header('Location: esqueci_pin.php');
        exit;
    } catch (Throwable $e) {
        error_log('Erro em esqueci_pin.php: ' . $e->getMessage());
        $_SESSION['pin_recover_error'] = 'Não foi possível processar o pedido agora. Tente novamente.';
        header('Location: esqueci_pin.php');
        exit;
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
        <form method="post" action="esqueci_pin.php" autocomplete="off">
            <div class="form-logo-wrap">
                <div class="form-logo"><img src="../admin/views/images/rh1.png" alt="RHNeto Pro"></div>
                <div class="form-logo-badge"><i class="fas fa-envelope"></i></div>
            </div>
            <h2>Repor o PIN</h2>
            <p class="form-sub">Indique o seu email e enviamos um link para definir um novo PIN</p>

            <?php if (!empty($_SESSION['pin_recover_error'])): ?>
            <div class="alert"><i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($_SESSION['pin_recover_error']); ?></span></div>
            <?php unset($_SESSION['pin_recover_error']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['pin_recover_success'])): ?>
            <div class="alert alert-ok"><i class="fas fa-circle-check"></i><span><?php echo htmlspecialchars($_SESSION['pin_recover_success']); ?></span></div>
            <?php unset($_SESSION['pin_recover_success']); ?>
            <?php endif; ?>

            <div class="field">
                <label for="email">Email</label>
                <div class="field-wrap"><i class="fas fa-envelope"></i>
                    <input id="email" name="email" type="email" required autofocus placeholder="Introduza o seu email">
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-paper-plane"></i> Enviar link de reposição
            </button>

            <?php if (!empty($_SESSION['pin_recover_debug_link'])): ?>
            <div class="debug-box">
                <i class="fas fa-flask"></i>
                <span>Ambiente sem SMTP configurado — link de teste: <a href="<?php echo htmlspecialchars($_SESSION['pin_recover_debug_link']); ?>"><?php echo htmlspecialchars($_SESSION['pin_recover_debug_link']); ?></a></span>
            </div>
            <?php unset($_SESSION['pin_recover_debug_link']); ?>
            <?php endif; ?>

            <p class="back-link"><a href="employee_login.php" class="link-muted"><i class="fas fa-arrow-left"></i> Voltar ao login</a></p>
        </form>
    </div>
</div>

</body>
</html>
