<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../admin/views/login.php?error=sessao_invalida');
    exit();
}

$companyName = $_SESSION['company_name'] ?? $_SESSION['client_name'] ?? 'RHNeto Pro';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento confirmado - <?php echo htmlspecialchars($companyName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { color-scheme: dark; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #08111f;
            color: #e5eefc;
            font-family: Arial, Helvetica, sans-serif;
            text-align: center;
            padding: 24px;
        }
        .card {
            max-width: 420px;
            background: rgba(15, 23, 42, 0.82);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 16px;
            padding: 40px 32px;
        }
        .icon {
            font-size: 48px;
            color: #22c55e;
            margin-bottom: 16px;
        }
        h1 { font-size: 22px; margin: 0 0 12px; }
        p { color: rgba(229, 238, 252, 0.74); line-height: 1.5; margin: 0 0 24px; }
        a.button {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 10px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><i class="fas fa-circle-check"></i></div>
        <h1>Pagamento confirmado</h1>
        <p>A sua assinatura foi processada com sucesso. Pode levar alguns instantes para ativar no painel.</p>
        <a class="button" href="../admin/dashboard.php">Ir para o painel</a>
    </div>
</body>
</html>
