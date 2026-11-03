<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use QETG\Application\Port\OptionalEmailSenderInterface;
use RuntimeException;

final class PhpMailerEmailSender implements OptionalEmailSenderInterface
{
    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private string $from,
        private int $port = 465,
        private string $encryption = 'ssl',
        private ?string $bcc = null
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->username !== '' && $this->password !== ''
            && filter_var($this->from, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function send(string $recipient, string $subject, string $htmlBody, string $textBody): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Le service e-mail n’est pas configuré.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->host;
        $mail->SMTPAuth = true;
        $mail->Username = $this->username;
        $mail->Password = $this->password;
        $mail->SMTPSecure = $this->encryption;
        $mail->Port = $this->port;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($this->from, 'Quiz en tout genre');
        if ($this->bcc !== null && $this->bcc !== '') {
            $mail->addBCC($this->bcc);
        }
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();
    }
}
