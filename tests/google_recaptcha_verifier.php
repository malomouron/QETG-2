<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\Port\CaptchaVerifierInterface;
use QETG\Infrastructure\Security\GoogleRecaptchaVerifier;

function assertRecaptcha(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$requests = [];
$responses = [
    '{"success":true}',
    '{"success":false,"error-codes":["invalid-input-response"]}',
    'not-json',
    false,
];
$transport = static function (string $body, int $timeout) use (&$requests, &$responses): string|false {
    $requests[] = [$body, $timeout];
    return array_shift($responses);
};

$withoutSecret = new GoogleRecaptchaVerifier('', 4, $transport);
assertRecaptcha($withoutSecret instanceof CaptchaVerifierInterface, 'The verifier must implement its application port.');
assertRecaptcha(!$withoutSecret->verify('token', '192.0.2.1'), 'A missing secret must fail closed.');
assertRecaptcha($requests === [], 'Missing configuration must not call Google.');

$verifier = new GoogleRecaptchaVerifier('test-secret', 99, $transport);
assertRecaptcha(!$verifier->verify('', '192.0.2.2'), 'An empty response token must fail closed.');
assertRecaptcha($requests === [], 'An empty token must not call Google.');
assertRecaptcha($verifier->verify('accepted-token', '192.0.2.3'), 'A valid success response should be accepted.');
parse_str($requests[0][0], $payload);
assertRecaptcha($payload === ['secret' => 'test-secret', 'response' => 'accepted-token', 'remoteip' => '192.0.2.3'], 'The verification request must send the secret, token, and visitor address.');
assertRecaptcha($requests[0][1] === 10, 'The timeout must be clamped to ten seconds.');
assertRecaptcha(!$verifier->verify('rejected-token', '192.0.2.4'), 'A Google rejection must be rejected.');
assertRecaptcha(!$verifier->verify('malformed-response', '192.0.2.5'), 'Malformed JSON must be rejected.');
assertRecaptcha(!$verifier->verify('transport-error', '192.0.2.6'), 'A transport failure must be rejected.');

$minimumTimeout = null;
$minimum = new GoogleRecaptchaVerifier('test-secret', 0, static function (string $body, int $timeout) use (&$minimumTimeout): string {
    unset($body);
    $minimumTimeout = $timeout;
    return '{"success":true}';
});
assertRecaptcha($minimum->verify('accepted-token', '192.0.2.7') && $minimumTimeout === 1, 'The timeout must be clamped to at least one second.');

fwrite(STDOUT, "Google reCAPTCHA verifier checks passed.\n");
