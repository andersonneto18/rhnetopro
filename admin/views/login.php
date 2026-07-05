<?php
session_start();
$openSignUp = isset($_SESSION['register_error_message']) || isset($_SESSION['register_success_message']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RHNeto Pro – Acesso</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

<div class="page-wrapper">
    <div class="bg-rotate"></div>
    <div class="container <?= $openSignUp ? 'active' : '' ?>" id="container" data-register-success="<?= isset($_SESSION['register_success_message']) ? '1' : '' ?>">

        <!-- ── Formulário de Cadastro ── -->
        <div class="form-box sign-up">
        
            <form action="../controllers/register_process.php" method="post">
                <div class="form-logo"><img src="images/image.png" alt="RHNeto Pro"></div>
                <h2>Criar conta</h2>
                <p class="form-sub">Registe o administrador do sistema</p>

                <?php if (isset($_SESSION['register_success_message'])): ?>
                    <div class="alert alert--ok"><i class="fas fa-check-circle"></i><span><?= htmlspecialchars($_SESSION['register_success_message']) ?></span></div>
                    <?php unset($_SESSION['register_success_message']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['register_error_message'])): ?>
                    <div class="alert alert--err"><i class="fas fa-exclamation-triangle"></i><span><?= htmlspecialchars($_SESSION['register_error_message']) ?></span></div>
                    <?php unset($_SESSION['register_error_message']); ?>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="field">
                        <label>Nome do Admin</label>
                        <div class="field-wrap"><i class="fas fa-user-cog"></i>
                            <input type="text" name="full-name" placeholder="Nome completo" required>
                        </div>
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <div class="field-wrap"><i class="fas fa-envelope"></i>
                            <input type="email" name="email" placeholder="email@empresa.com" required>
                        </div>
                    </div>
                    <div class="field">
                        <label>Telemóvel</label>
                        <div class="field-wrap tel-wrap">
                            <input type="tel" id="reg-tel" name="telefone">
                        </div>
                    </div>
                    <div class="field">
                        <label>NIF</label>
                        <div class="field-wrap"><i class="fas fa-id-card"></i>
                            <input type="number" name="nif" placeholder="N.º fiscal">
                        </div>
                    </div>
                    <div class="field">
                        <label>Empresa</label>
                        <div class="field-wrap"><i class="fas fa-building"></i>
                            <input type="text" name="username-login" placeholder="Nome da empresa" required>
                        </div>
                    </div>
                    <div class="field">
                        <label>Utilizador</label>
                        <div class="field-wrap"><i class="fas fa-user"></i>
                            <input type="text" name="company-name" placeholder="Nome de utilizador" required>
                        </div>
                    </div>
                    <div class="field">
                        <label>Senha</label>
                        <div class="field-wrap"><i class="fas fa-lock"></i>
                            <input type="password" name="new-password" placeholder="Crie uma senha" required>
                        </div>
                    </div>
                    <div class="field">
                        <label>Confirmar Senha</label>
                        <div class="field-wrap"><i class="fas fa-lock"></i>
                            <input type="password" name="confirm-password" placeholder="Confirme a senha" required>
                        </div>
                    </div>
                </div>

                <div class="terms-row">
                    <label class="chk">
                        <input type="checkbox" name="terms" required>
                        <span>Aceito os <a href="#">Termos</a> e <a href="#">Privacidade</a></span>
                    </label>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Criar conta
                </button>
                <p class="mobile-link">Já tem conta? <a href="#" id="mobileSignIn">Entrar</a></p>
            </form>
        </div>

        <!-- ── Formulário de Login ── -->
        <div class="form-box sign-in">
            <form action="../controllers/login_process.php" method="post">
                <div class="form-logo"><img src="images/image.png" alt="RHNeto Pro"></div>
                <h2>Entrar na conta</h2>
                <p class="form-sub">Use as suas credenciais para aceder ao sistema</p>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert--err"><i class="fas fa-exclamation-triangle"></i><span><?= htmlspecialchars($_SESSION['error_message']) ?></span></div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert--ok"><i class="fas fa-check-circle"></i><span><?= htmlspecialchars($_SESSION['success_message']) ?></span></div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <div class="field">
                    <label>Email ou utilizador</label>
                    <div class="field-wrap"><i class="fas fa-user-cog"></i>
                        <input type="text" name="username" placeholder="exemplo@empresa.com" required>
                    </div>
                </div>
                <div class="field">
                    <label>Senha</label>
                    <div class="field-wrap"><i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="A sua senha" required>
                    </div>
                </div>

                <div class="row-split">
                    <label class="chk">
                        <input type="checkbox" name="remember-me">
                        <span>Lembrar de mim</span>
                    </label>
                    <a href="esqueci_senha.php" class="link-muted">Esqueceu?</a>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-arrow-right-to-bracket"></i> Entrar no painel
                </button>
                <p class="mobile-link">Não tem conta? <a href="#" id="mobileSignUp">Criar conta</a></p>
            </form>
        </div>

        <!-- ── Toggle / Painel deslizante ── -->
        <div class="toggle-box">
            <div class="toggle-track">
                <!-- Esquerdo: visível quando active (modo signup) → botão para voltar ao login -->
                <div class="toggle-panel tgl-left">
                    <div class="tgl-logo"><img src="images/rh1.png" alt="Logo"></div>
                    <h2>Bem-vindo de volta!</h2>
                    <p>Já tem uma conta? Aceda ao painel de administração.</p>
                    <button type="button" id="signIn" class="btn-ghost">
                        <i class="fas fa-arrow-right-to-bracket"></i> Entrar
                    </button>
                </div>
                <!-- Direito: visível por defeito (modo login) → botão para criar conta -->
                <div class="toggle-panel tgl-right">
                    <div class="tgl-logo"><img src="images/rh1.png" alt="Logo"></div>
                    <h2>RHNeto Pro</h2>
                    <p>Registe a sua empresa e gira a sua equipa de forma profissional.</p>
                    <button type="button" id="signUp" class="btn-ghost">
                        <i class="fas fa-user-plus"></i> Criar conta
                    </button>
                </div>
            </div>
        </div>

    </div><!-- /.container -->
</div><!-- /.page-wrapper -->



<script>
(function () {
    var container  = document.getElementById('container');
    var signUpBtn  = document.getElementById('signUp');
    var signInBtn  = document.getElementById('signIn');
    var mobileUp   = document.getElementById('mobileSignUp');
    var mobileIn   = document.getElementById('mobileSignIn');

    function goSignUp() { container.classList.add('active'); }
    function goSignIn() { container.classList.remove('active'); }

    signUpBtn.addEventListener('click', goSignUp);
    signInBtn.addEventListener('click', goSignIn);
    if (mobileUp) mobileUp.addEventListener('click', function (e) { e.preventDefault(); goSignUp(); });
    if (mobileIn) mobileIn.addEventListener('click', function (e) { e.preventDefault(); goSignIn(); });

    // Alterna automaticamente para o login se houve sucesso no registo
    if (container.dataset.registerSuccess === '1') {
        setTimeout(function() {
            goSignIn();
        }, 100); // pequeno delay para garantir renderização
    }

    /* intl-tel-input */
    var telEl = document.getElementById('reg-tel');
    if (telEl && typeof intlTelInput !== 'undefined') {
        intlTelInput(telEl, {
            allowDropdown: true,
            separateDialCode: true,
            initialCountry: 'pt',
            preferredCountries: ['pt', 'br', 'es', 'gb', 'us']
        });
    }
})();
</script>
</body>
</html>
