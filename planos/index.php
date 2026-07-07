<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../admin/views/login.php?error=sessao_invalida');
    exit();
}

$companyName = $_SESSION['company_name'] ?? $_SESSION['client_name'] ?? 'RHNeto Pro';
$planName = 'Plano Unico Pro';
$planPrice = '2,00';
$planBilling = 'por mes';
$planFeatures = [
    'Funcionarios ilimitados',
    'Turnos, presenca e gorjetas',
    'Relatorios e notificacoes incluidos',
    'Suporte prioritario',
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planos - <?php echo htmlspecialchars($companyName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #08111f;
            --bg-soft: #0f172a;
            --card: rgba(15, 23, 42, 0.82);
            --border: rgba(255, 255, 255, 0.10);
            --text: #e5eefc;
            --muted: rgba(229, 238, 252, 0.74);
            --accent: #3b82f6;
            --accent-2: #2563eb;
            --success: #22c55e;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(59, 130, 246, 0.28), transparent 30%),
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.24), transparent 28%),
                linear-gradient(180deg, var(--bg) 0%, #0b1220 100%);
            min-height: 100vh;
        }

        .shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 28px 20px 48px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 30px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-badge {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 16px 30px rgba(37, 99, 235, 0.34);
            font-size: 20px;
        }

        .brand small {
            display: block;
            color: var(--muted);
            margin-top: 2px;
        }

        .back-link {
            color: var(--text);
            text-decoration: none;
            font-weight: 700;
            opacity: 0.9;
        }

        .hero {
            padding: 28px;
            border: 1px solid var(--border);
            border-radius: 28px;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.92), rgba(15, 23, 42, 0.66));
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(59, 130, 246, 0.14);
            color: #bfdbfe;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 16px 0 12px;
            font-family: 'Poppins', sans-serif;
            font-size: clamp(34px, 5vw, 58px);
            line-height: 1;
        }

        .subtitle {
            max-width: 680px;
            margin: 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
        }

        .plan-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .plan-card {
            border: 1px solid var(--border);
            border-radius: 26px;
            background: var(--card);
            padding: 26px;
            position: relative;
            overflow: hidden;
        }

        .plan-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.10), transparent 35%);
            pointer-events: none;
        }

        .plan-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(34, 197, 94, 0.14);
            color: #bbf7d0;
            font-size: 12px;
            font-weight: 800;
            position: relative;
        }

        .plan-name {
            position: relative;
            margin: 16px 0 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
        }

        .price {
            position: relative;
            display: flex;
            align-items: flex-end;
            gap: 10px;
            margin: 10px 0 18px;
        }

        .price strong {
            font-size: 54px;
            line-height: 1;
        }

        .price span {
            color: var(--muted);
            padding-bottom: 8px;
        }

        ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            color: var(--text);
        }

        li i {
            color: var(--success);
            margin-top: 3px;
        }

        .cta {
            position: relative;
            margin-top: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 14px;
            border: none;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 16px 30px rgba(37, 99, 235, 0.30);
            cursor: pointer;
        }

        .note {
            margin-top: 16px;
            color: var(--muted);
            font-size: 13px;
        }

        @media (max-width: 900px) {
            .plan-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <div class="brand">
                <div class="brand-badge"><i class="fas fa-crown"></i></div>
                <div>
                    <strong><?php echo htmlspecialchars($companyName); ?></strong>
                    <small>Escolha o unico plano disponivel</small>
                </div>
            </div>
            <a class="back-link" href="../admin/dashboard.php"><i class="fas fa-arrow-left"></i> Voltar ao painel</a>
        </div>

        <?php if (!empty($_GET['trial_expirado'])): ?>
            <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.4);color:#fecaca;border-radius:12px;padding:14px 18px;margin-bottom:24px;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-triangle-exclamation"></i>
                <span>O seu periodo de teste de 7 dias terminou. Assine o plano abaixo para continuar a usar o painel.</span>
            </div>
        <?php endif; ?>

        <section class="hero">
            <div class="eyebrow"><i class="fas fa-bolt"></i> Planos</div>
            <h1>Um plano simples, sem complicacao.</h1>
            <p class="subtitle">
                Criamos uma oferta unica para acelerar a ativacao. Sem menu confuso, sem comparacao desnecessaria.
                Selecione o plano abaixo e avance para a proxima etapa.
            </p>

            <div class="plan-grid">
                <article class="plan-card">
                    <div class="plan-tag"><i class="fas fa-star"></i> Mais escolhido</div>
                    <div class="plan-name"><?php echo htmlspecialchars($planName); ?></div>
                    <div class="price">
                        <strong><?php echo htmlspecialchars($planPrice); ?></strong>
                        <span><?php echo htmlspecialchars($planBilling); ?></span>
                    </div>
                    <ul>
                        <?php foreach ($planFeatures as $feature): ?>
                            <li><i class="fas fa-check-circle"></i> <span><?php echo htmlspecialchars($feature); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="POST" action="create-checkout-session.php" id="checkoutForm">
                        <button type="submit" class="cta" id="checkoutBtn">
                            <i class="fab fa-stripe"></i>
                            <span id="checkoutBtnLabel">Assinar Plano Premium</span>
                        </button>
                    </form>
                    <div class="note">Sera redirecionado para o checkout seguro da Stripe.</div>
                    <div id="checkoutError" style="display:none;margin-top:10px;color:#f87171;font-size:13px;"></div>
                </article>
            </div>
        </section>
    </div>
    <script>
    document.getElementById('checkoutForm').addEventListener('submit', function() {
        const btn = document.getElementById('checkoutBtn');
        const label = document.getElementById('checkoutBtnLabel');
        btn.disabled = true;
        label.textContent = 'A preparar pagamento...';
        // Deixa o formulario submeter normalmente: o navegador segue o
        // redirect 303 da Stripe nativamente, preservando o fragmento (#...)
        // da URL que o Checkout precisa para carregar a sessao. Um fetch()
        // manual + window.location.href quebra esse fragmento.
    });
    </script>
</body>
</html>
