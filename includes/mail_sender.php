<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Envia um email transacional via SMTP relay (Brevo), configurado por .env
 * (SMTP_HOST, SMTP_PORT, SMTP_LOGIN, SMTP_KEY, MAIL_FROM_EMAIL, MAIL_FROM_NAME).
 *
 * @return array{success: bool, error: ?string}
 */
function sendTransactionalEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainFallback = ''): array
{
    $host = trim((string)(getenv('SMTP_HOST') ?: ''));
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $login = trim((string)(getenv('SMTP_LOGIN') ?: ''));
    $key = trim((string)(getenv('SMTP_KEY') ?: ''));
    $fromEmail = trim((string)(getenv('MAIL_FROM_EMAIL') ?: ''));
    $fromName = trim((string)(getenv('MAIL_FROM_NAME') ?: 'RHNeto Pro'));

    if ($host === '' || $login === '' || $key === '' || $fromEmail === '') {
        return ['success' => false, 'error' => 'Configuração SMTP incompleta (verifique o .env).'];
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Email do destinatário inválido.'];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $login;
        $mail->Password = $key;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $port;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainFallback !== '' ? $plainFallback : trim(strip_tags($htmlBody));

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (PHPMailerException $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Envolve o conteúdo (HTML) na identidade visual RHNeto Pro: cabeçalho azul da
 * marca, cartão branco arredondado, rodapé discreto. Layout em tabelas para
 * compatibilidade com clientes de email (Gmail, Outlook, etc.).
 */
function renderBrandedEmailShell(string $bodyHtml): string
{
    return <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 18px rgba(15,23,42,.08);">
<tr>
<td style="background-color:#1d4ed8;padding:28px 32px;">
<span style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-.02em;">RHNeto Pro</span><br>
<span style="font-size:12px;color:#dbeafe;">Sistema de Gestão de Recursos Humanos</span>
</td>
</tr>
<tr>
<td style="padding:32px;">
{$bodyHtml}
</td>
</tr>
<tr>
<td style="padding:20px 32px;background-color:#f8fafc;border-top:1px solid #e2e8f0;">
<span style="display:block;font-size:12px;color:#94a3b8;">RHNeto Pro &mdash; Sistema de Gestão de Recursos Humanos</span>
<span style="display:block;font-size:11px;color:#cbd5e1;margin-top:4px;">Esta é uma mensagem automática. Por favor não responda a este email.</span>
</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Corpo do email de boas-vindas: nome, restaurante, credenciais de acesso em
 * destaque e botão para aceder à app.
 */
function renderWelcomeEmailBody(string $employeeName, string $clientName, string $pin, string $appLoginUrl, string $employeeEmail = '', string $manualUrl = ''): string
{
    $nome = htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8');
    $restaurante = htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8');
    $pinHtml = htmlspecialchars($pin, ENT_QUOTES, 'UTF-8');
    $link = htmlspecialchars($appLoginUrl, ENT_QUOTES, 'UTF-8');
    $loginLabel = $employeeEmail !== '' ? htmlspecialchars($employeeEmail, ENT_QUOTES, 'UTF-8') : 'o seu email';
    $manualLink = $manualUrl !== '' ? htmlspecialchars($manualUrl, ENT_QUOTES, 'UTF-8') : '';

    $manualHtml = $manualLink !== ''
        ? '<p style="margin:16px 0 0;font-size:13px;color:#64748b;">Primeira vez na app? Consulte o <a href="' . $manualLink . '" style="color:#2563eb;font-weight:700;text-decoration:none;">manual do utilizador</a>.</p>'
        : '';

    return <<<HTML
<h1 style="margin:0 0 6px;font-size:22px;color:#0f172a;">Bem-vindo(a), {$nome}!</h1>
<p style="margin:0 0 24px;font-size:14px;line-height:1.5;color:#475569;">Já faz parte da equipa <strong>{$restaurante}</strong>. Use os dados abaixo para aceder à app, gerir o seu turno, marcar presença e muito mais.</p>

<div style="background-color:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 20px;margin-bottom:28px;">
<p style="margin:0 0 10px;font-size:11px;color:#1d4ed8;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Os seus dados de acesso</p>
<p style="margin:0 0 4px;font-size:14px;color:#0f172a;">Login: <strong>{$loginLabel}</strong></p>
<p style="margin:0;font-size:14px;color:#0f172a;">PIN: <strong style="font-size:19px;letter-spacing:.1em;color:#1d4ed8;">{$pinHtml}</strong></p>
</div>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;">
<tr><td style="border-radius:8px;background-color:#2563eb;">
<a href="{$link}" style="display:inline-block;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 32px;">Aceder à App</a>
</td></tr>
</table>
{$manualHtml}
HTML;
}

/**
 * Corpo do email para uma mensagem simples/livre (sem modelo de boas-vindas),
 * mantendo a mesma moldura visual da marca.
 */
function renderPlainMessageEmailBody(string $messageText): string
{
    $safe = nl2br(htmlspecialchars($messageText, ENT_QUOTES, 'UTF-8'));
    return <<<HTML
<p style="margin:0;font-size:15px;line-height:1.6;color:#1e293b;">{$safe}</p>
HTML;
}
