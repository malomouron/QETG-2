<?php

declare(strict_types=1);

namespace QETG\Application\Port;

interface CaptchaVerifierInterface
{
    public function verify(string $responseToken, string $remoteAddress): bool;
}
