<?php
use PHPMailer\PHPMailer\PHPMailer;

// Legacy compatibility transport. New application code uses PhpMailerEmailSender.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer();
$smtpConfigured = isset($smtp_host, $smtp_username, $smtp_password, $smtp_from)
    && is_string($smtp_host) && $smtp_host !== ''
    && is_string($smtp_username) && $smtp_username !== ''
    && is_string($smtp_password) && $smtp_password !== ''
    && is_string($smtp_from) && filter_var($smtp_from, FILTER_VALIDATE_EMAIL) !== false;

if ($smtpConfigured) {
    $mail->isSMTP();
    $mail->Host = $smtp_host;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_username;
    $mail->Password = $smtp_password;
    $mail->SMTPSecure = isset($smtp_encryption) ? $smtp_encryption : 'ssl';
    $mail->Port = isset($smtp_port) ? $smtp_port : 465;
} else {
    // Missing local SMTP must not prevent a comment from being stored.
    $mail->isMail();
}

$fromAddress = $smtpConfigured ? $smtp_from : (isset($expediteur) && filter_var($expediteur, FILTER_VALIDATE_EMAIL) ? $expediteur : 'no-reply@example.invalid');
$mail->setFrom($fromAddress, 'Quiz En Tout Genre');
$mail->addReplyTo($fromAddress);
if (isset($smtp_bcc) && is_string($smtp_bcc) && filter_var($smtp_bcc, FILTER_VALIDATE_EMAIL)) {
    $mail->addBCC($smtp_bcc);
}
$mail->isHTML(true);
$mail->AltBody = '';
