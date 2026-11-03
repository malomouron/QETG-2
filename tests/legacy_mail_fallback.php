<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/mail.php';

if (!isset($mail) || !$mail instanceof PHPMailer\PHPMailer\PHPMailer) {
    throw new RuntimeException('Legacy mail transport did not initialize.');
}
if (($smtp_host ?? '') === '' && $mail->Mailer !== 'mail') {
    throw new RuntimeException('When SMTP is not configured, the legacy transport must fall back without throwing.');
}

fwrite(STDOUT, "Legacy mail fallback checks passed.\n");
