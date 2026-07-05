<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registo - RHNeto Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>
    <link rel="stylesheet" href="../assets/css/signup.css">
</head>
<body>
    <div class="signup-wrapper">
        <div class="signup-container">

            <aside class="brand-panel">
                <div class="brand-mark">
                    <img src="images/rh1.png" alt="Logo RHNeto Pro">
                </div>
                <p class="brand-tag">Criar nova conta</p>
                <h1>RHNeto Pro</h1>
                <p class="brand-subtitle">Registe a sua empresa e comece a gerir a sua equipa de forma profissional.</p>

                <ul class="brand-points" aria-hidden="true">
                    <li><i class="fas fa-check-circle"></i> Configuracao rapida e simples</li>
                    <li><i class="fas fa-check-circle"></i> Gestao completa de funcionarios</li>
                    <li><i class="fas fa-check-circle"></i> Folha de pagamento integrada</li>
                </ul>
            </aside>

            <section class="signup-panel">
                <div class="panel-header">
                    <h2>Criar conta</h2>
                    <p>Preencha os dados para registar o administrador do sistema.</p>
                </div>

                <div class="signup-form">
                    <?php
                    if (isset($_SESSION['register_success_message'])) {
                        echo '<div class="success-message">';
                        echo '<i class="fas fa-check-circle"></i>';
                        echo '<span>' . htmlspecialchars($_SESSION['register_success_message']) . '</span>';
                        echo '</div>';
                        unset($_SESSION['register_success_message']);
                    }
                    if (isset($_SESSION['register_error_message'])) {
                        echo '<div class="error-message">';
                        echo '<i class="fas fa-exclamation-triangle"></i>';
                        echo '<span>' . htmlspecialchars($_SESSION['register_error_message']) . '</span>';
                        echo '</div>';
                        unset($_SESSION['register_error_message']);
                    }
                    ?>

                    <form action="../controllers/register_process.php" method="POST">
                        <div class="form-grid">
                            <div class="input-group">
                                <label for="full-name">Nome do Administrador</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user-cog"></i>
                                    <input type="text" id="full-name" name="full-name" placeholder="Nome completo" required>
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="email">Email</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" id="email" name="email" placeholder="exemplo@empresa.com" required>
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="telefone">Telemóvel</label>
                                <div class="input-wrapper tel-wrapper">
                                    <input type="tel" id="telefone" name="telefone" placeholder="">
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="nif">NIF</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-id-card"></i>
                                    <input type="number" id="nif" name="nif" placeholder="Número de identificação fiscal">
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="username-login">Nome da Empresa</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-building"></i>
                                    <input type="text" id="username-login" name="username-login" placeholder="Nome da empresa" required>
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="company-name">Nome do Utilizador</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="company-name" name="company-name" placeholder="Nome de utilizador" required>
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="new-password">Senha</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="new-password" name="new-password" placeholder="Crie uma senha" required>
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="confirm-password">Confirmar Senha</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="confirm-password" name="confirm-password" placeholder="Confirme a senha" required>
                                </div>
                            </div>
                        </div>

                        <div class="checkbox-group">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="terms" required>
                                <span>Eu concordo com os <a href="#">Termos de Serviço</a> e <a href="#">Política de Privacidade</a></span>
                            </label>
                        </div>

                        <button type="submit" class="signup-button">
                            <i class="fas fa-user-plus"></i>
                            Criar conta
                        </button>
                    </form>

                    <div class="login-link">
                        Ja tem uma conta? <a href="login.php">Entrar</a>
                    </div>
                </div>
            </section>

        </div>
    </div>

    

    <script>
        var input = document.querySelector("#telefone");
        intlTelInput(input, {
            allowDropdown: true,
            separateDialCode: true,
            initialCountry: "pt",
            preferredCountries: ["pt", "br", "es", "gb", "us"]
        });
    </script>
</body>
</html>