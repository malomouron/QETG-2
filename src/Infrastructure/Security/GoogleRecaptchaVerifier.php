<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Security;

use Closure;
use QETG\Application\Port\CaptchaVerifierInterface;

final class GoogleRecaptchaVerifier implements CaptchaVerifierInterface
{
    private ?Closure $requester;

    public function __construct(private string $secretKey, private int $timeoutSeconds = 4, ?callable $requester = null)
    {
        $this->requester = $requester === null ? null : Closure::fromCallable($requester);
    }

    public function verify(string $responseToken, string $remoteAddress): bool
    {
        if ($this->secretKey === '' || $responseToken === '') {
            return false;
        }

        $body = http_build_query(array(
            'secret' => $this->secretKey,
            'response' => $responseToken,
            'remoteip' => $remoteAddress,
        ));
        $context = stream_context_create(array('http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => max(1, min(10, $this->timeoutSeconds)),
            'ignore_errors' => true,
        )));

        $timeout = max(1, min(10, $this->timeoutSeconds));
        $response = $this->requester !== null
            ? ($this->requester)($body, $timeout)
            : @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        if (!\is_string($response)) {
            return false;
        }

        $result = json_decode($response, true);
        return \is_array($result) && ($result['success'] ?? false) === true;
    }
}
