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
        throw new RuntimeException('Erro ao preparar tabela de recuperacao de senha.');
    }
}

function findValidToken(mysqli $conn, string $token): ?array {
    $tokenHash = hash('sha256', $token);

    $stmt = $conn->prepare(
        'SELECT id, user_id, email, expires_at FROM admin_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at >= NOW() LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Erro ao validar token.');
    }

    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$flashError = '';
$tokenIsValid = false;

try {
    // Aceita tanto MySQLi quanto PDO/PDOWrapper
    $db = $pdo ?? $conn;
    if ($db instanceof mysqli) {
        ensurePasswordResetTable($db);
    }

    if ($token !== '') {
        $db = $pdo ?? $conn;
        if ($db instanceof mysqli) {
            $tokenRow = findValidToken($db, $token);
        } else {
            $tokenRow = null;
        }
        $tokenIsValid = $tokenRow !== null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($token === '' || !$tokenIsValid) {
            $flashError = 'O link de recuperacao e invalido ou expirou.';
        } elseif (strlen($newPassword) < 6) {
            $flashError = 'A nova palavra-passe deve ter pelo menos 6 caracteres.';
        } elseif ($newPassword !== $confirmPassword) {
            $flashError = 'A confirmacao da palavra-passe nao coincide.';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $updateUser = $conn->prepare('UPDATE usuarios SET senha = ? WHERE id = ? LIMIT 1');
            if (!$updateUser) {
                throw new RuntimeException('Erro ao atualizar palavra-passe.');
            }
            $updateUser->bind_param('si', $passwordHash, $tokenRow['user_id']);
            $updateUser->execute();
            $updateUser->close();

            $tokenHash = hash('sha256', $token);
            $markUsed = $conn->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE token_hash = ? AND used_at IS NULL');
            if (!$markUsed) {
                throw new RuntimeException('Erro ao finalizar token de recuperacao.');
            }
            $markUsed->bind_param('s', $tokenHash);
            $markUsed->execute();
            $markUsed->close();

            $_SESSION['success_message'] = 'Palavra-passe redefinida com sucesso. Inicie sessão com a nova palavra-passe.';
            header('Location: ../views/login.php');
            exit();
        }
    }
} catch (Throwable $e) {
    error_log('Erro em reset_password.php: ' . $e->getMessage());
    $flashError = 'Nao foi possivel processar a redefinicao agora. Tente novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Palavra-passe - RHNeto Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink-950: #091426;
            --ink-500: #5f7595;
            --line: #d8e0ec;
            --brand: #0b6ea8;
            --brand-2: #0a4f86;
            --danger-bg: #fff2f3;
            --danger-bd: #ffc8cd;
            --danger-ink: #b4232d;
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
            color: #2a3f5e;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
        }
        input[type="password"] {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 11px;
            padding: .75rem .85rem;
            font-size: .95rem;
            font-family: inherit;
            margin-bottom: .8rem;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(11,110,168,.12);
        }
        .btn {
            width: 100%;
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
            background: var(--danger-bg);
            border: 1px solid var(--danger-bd);
            color: var(--danger-ink);
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
    </style>
</head>
<body>
    <div class="card">
        <h1>Redefinir palavra-passe</h1>

        <?php if (!$tokenIsValid): ?>
            <div class="alert">O link de recuperacao e invalido ou expirou. Solicite um novo link.</div>
            <div class="links"><a href="esqueci_senha.php">Pedir novo link</a></div>
        <?php else: ?>
            <p>Introduza a nova palavra-passe para a sua conta de administrador.</p>

            <?php if ($flashError !== ''): ?>
                <div class="alert"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <form method="post" action="reset_password.php" autocomplete="off">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <label for="new_password">Nova palavra-passe</label>
                <input id="new_password" type="password" name="new_password" required minlength="6" placeholder="Minimo 6 caracteres">

                <label for="confirm_password">Confirmar palavra-passe</label>
                <input id="confirm_password" type="password" name="confirm_password" required minlength="6" placeholder="Repita a nova palavra-passe">

                <button class="btn" type="submit">Guardar nova palavra-passe</button>
            </form>

            <div class="links"><a href="login.php">Voltar ao login</a></div>
        <?php endif; ?>
    </div>
</body>
</html>
