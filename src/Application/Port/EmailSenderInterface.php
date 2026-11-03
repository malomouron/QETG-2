<?php

declare(strict_types=1);

namespace QETG\Application\Port;

interface EmailSenderInterface
{
    public function send(string $recipient, string $subject, string $htmlBody, string $textBody): void;
}
