<?php
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.inc.php';

foreach (array('smtp_host', 'smtp_username', 'smtp_password', 'smtp_from') as $parameter) {
    if (!isset($$parameter) || !is_string($$parameter) || $$parameter === '') {
        http_response_code(500);
        throw new RuntimeException('Paramètre SMTP manquant dans config.inc.php : ' . $parameter);
    }
}

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

$mail = new PHPMailer();
$mail->isSMTP();
$mail->Host = $smtp_host;
$mail->SMTPAuth = true;
$mail->Username = $smtp_username;
$mail->Password = $smtp_password;
$mail->SMTPSecure = isset($smtp_encryption) ? $smtp_encryption : 'ssl';
$mail->Port = isset($smtp_port) ? $smtp_port : 465;
$mail->setFrom($smtp_from, 'Quiz En Tout Genre');
$mail->addReplyTo($smtp_from);
$mail->addBCC(isset($smtp_bcc) ? $smtp_bcc : $smtp_from);
$mail->isHTML(true);
$mail->AltBody = '';
