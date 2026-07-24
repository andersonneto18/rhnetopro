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
function sendTransactionalEmail(string $toEmail, string $toName, string $subject, string $bodyText): array
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

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $bodyText;

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (PHPMailerException $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
