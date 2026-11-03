<?php

declare(strict_types=1);

namespace QETG\Application\Port;

interface OptionalEmailSenderInterface extends EmailSenderInterface
{
    public function isConfigured(): bool;
}
