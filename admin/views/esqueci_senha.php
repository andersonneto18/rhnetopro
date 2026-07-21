<?php
session_start();
require_once '../../config/db_connect.php';

function ensurePasswordResetTable(mysqli $conn): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS admin_password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token_hash (token_hash),
            INDEX idx_email (email),
            INDEX idx_expires_at (expires_at),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Erro ao preparar tabela de recuperação de palavra-passe.');
    }
}

function getBaseUrl(): string {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/admin/esqueci_senha.php';
    $adminPath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    $projectPath = preg_replace('#/admin$#', '', $adminPath);
    if ($projectPath === null) {
        $projectPath = '';
    }

    return $scheme . '://' . $host . $projectPath;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    unset($_SESSION['recover_error']);
    unset($_SESSION['recover_success']);
    unset($_SESSION['recover_debug_link']);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['recover_error'] = 'Indique um email válido para recuperar a palavra-passe.';
        header('Location: esqueci_senha.php');
        exit();
    }

    try {
        // Aceita tanto MySQLi quanto PDO/PDOWrapper
        $db = $pdo ?? $conn;
        if ($db instanceof mysqli) {
            ensurePasswordResetTable($db);
        }

        $stmt = $conn->prepare('SELECT id, email FROM usuarios WHERE email = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Erro ao preparar consulta de utilizador.');
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        $_SESSION['recover_success'] = 'Se o email existir no sistema, enviamos um link para redefinir a palavra-passe.';

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

            $insert = $conn->prepare(
                'INSERT INTO admin_password_resets (user_id, email, token_hash, expires_at, ip_address) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), ?)'
            );
            if (!$insert) {
                throw new RuntimeException('Erro ao preparar token de recuperacao.');
            }
            $insert->bind_param('isss', $user['id'], $user['email'], $tokenHash, $ipAddress);
            $insert->execute();
            $insert->close();

            $resetLink = rtrim(getBaseUrl(), '/') . '/admin/reset_password.php?token=' . urlencode($token);

            $subject = 'Recuperacao de palavra-passe - RHNeto Pro';
            $message = "Ola,\n\n" .
                "Recebemos um pedido para redefinir a sua palavra-passe.\n" .
                "Use o link abaixo (valido por 1 hora):\n\n" .
                $resetLink . "\n\n" .
                "Se nao pediu esta alteracao, ignore este email.\n\n" .
                "RHNeto Pro";

            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'From: RHNeto Pro <no-reply@localhost>',
                'Reply-To: no-reply@localhost',
                'X-Mailer: PHP/' . phpversion()
            ];

            $sent = @mail($user['email'], $subject, $message, implode("\r\n", $headers));

            // Em ambientes locais (XAMPP sem SMTP), guardamos o link para testes.
            if (!$sent) {
                $_SESSION['recover_debug_link'] = $resetLink;
            }
        }

        header('Location: esqueci_senha.php');
        exit();
    } catch (Throwable $e) {
        error_log('Erro em esqueci_senha.php: ' . $e->getMessage());
        $_SESSION['recover_error'] = 'Nao foi possivel processar o pedido agora. Tente novamente.';
        header('Location: esqueci_senha.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Palavra-passe - RHNeto Pro</title>
    <link rel="icon" type="image/png" href="images/rh1.png">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink-950: #091426;
            --ink-700: #2a3f5e;
            --ink-500: #5f7595;
            --line: #d8e0ec;
            --brand: #0b6ea8;
            --brand-2: #0a4f86;
            --danger-bg: #fff2f3;
            --danger-bd: #ffc8cd;
            --danger-ink: #b4232d;
            --ok-bg: #f0fdf6;
            --ok-bd: #a7f0c6;
            --ok-ink: #16794c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1rem;
            font-family: 'Manrope', 'Segoe UI', sans-serif;
            color: var(--ink-950);
            background:
                radial-gradient(circle at 18% 20%, #c6ecff 0%, transparent 38%),
                radial-gradient(circle at 82% 78%, #d6f6e9 0%, transparent 30%),
                linear-gradient(140deg, #eef4fb 0%, #dce8f6 100%);
        }
        .card {
            width: min(520px, 100%);
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 1.4rem;
            box-shadow: 0 16px 38px rgba(8, 35, 68, .15);
        }
        h1 {
            margin: 0 0 .35rem;
            font-size: 1.35rem;
            letter-spacing: -.02em;
        }
        p {
            margin: 0 0 1rem;
            color: var(--ink-500);
            font-size: .94rem;
        }
        label {
            display: block;
            margin-bottom: .35rem;
            color: var(--ink-700);
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
        }
        input[type="email"] {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 11px;
            padding: .75rem .85rem;
            font-size: .95rem;
            font-family: inherit;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(11,110,168,.12);
        }
        .btn {
            width: 100%;
            margin-top: .9rem;
            border: 0;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
            color: #fff;
            padding: .75rem .95rem;
            font-size: .95rem;
            font-weight: 800;
            cursor: pointer;
        }
        .alert {
            margin: 0 0 .85rem;
            border-radius: 10px;
            padding: .6rem .75rem;
            font-size: .86rem;
            line-height: 1.4;
            border: 1px solid transparent;
        }
        .alert.err {
            background: var(--danger-bg);
            border-color: var(--danger-bd);
            color: var(--danger-ink);
        }
        .alert.ok {
            background: var(--ok-bg);
            border-color: var(--ok-bd);
            color: var(--ok-ink);
        }
        .links {
            margin-top: .9rem;
            text-align: center;
            font-size: .88rem;
        }
        .links a {
            color: var(--brand-2);
            text-decoration: none;
            font-weight: 700;
        }
        .debug-link {
            margin-top: .75rem;
            padding: .6rem .75rem;
            border-radius: 10px;
            background: #fff8e8;
            border: 1px solid #f8d89a;
            color: #6f4f08;
            font-size: .82rem;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Recuperar palavra-passe</h1>
        <p>Indique o email da conta de administrador. Iremos enviar um link de redefinicao.</p>

        <?php if (isset($_SESSION['recover_error'])): ?>
            <div class="alert err"><?= htmlspecialchars($_SESSION['recover_error']) ?></div>
            <?php unset($_SESSION['recover_error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['recover_success'])): ?>
            <div class="alert ok"><?= htmlspecialchars($_SESSION['recover_success']) ?></div>
            <?php unset($_SESSION['recover_success']); ?>
        <?php endif; ?>

        <form method="post" action="esqueci_senha.php" autocomplete="off">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" placeholder="admin@empresa.com" required>
            <button class="btn" type="submit">Enviar link de recuperacao</button>
        </form>

        <?php if (isset($_SESSION['recover_debug_link'])): ?>
            <div class="debug-link">
                Ambiente local sem SMTP: use este link para testar a redefinicao:<br>
                <a href="<?= htmlspecialchars($_SESSION['recover_debug_link']) ?>"><?= htmlspecialchars($_SESSION['recover_debug_link']) ?></a>
            </div>
            <?php unset($_SESSION['recover_debug_link']); ?>
        <?php endif; ?>

        <div class="links"><a href="login.php">Voltar ao login</a></div>
    </div>
</body>
</html>
