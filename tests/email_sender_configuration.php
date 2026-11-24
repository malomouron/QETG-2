<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Infrastructure\Mail\PhpMailerEmailSender;

$sender = new PhpMailerEmailSender('', '', '', 'no-reply@example.invalid');
if ($sender->isConfigured()) {
    throw new RuntimeException('An empty SMTP configuration must not be reported as ready.');
}
try {
    $sender->send('member@example.invalid', 'Subject', '<p>Message</p>', 'Message');
    throw new RuntimeException('The transport must not send when SMTP is not configured.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'Le service e-mail n’est pas configuré.') {
        throw $exception;
    }
}

$loopbackSender = new PhpMailerEmailSender(
    '127.0.0.1',
    'coverage-fixture',
    'coverage-fixture',
    'no-reply@example.invalid',
    1,
    '',
    'audit@example.invalid'
);
if (!$loopbackSender->isConfigured()) {
    throw new RuntimeException('Complete SMTP fields and a valid sender address should be reported as configured.');
}
try {
    $loopbackSender->send('member@example.invalid', 'No external send', '<p>Fixture</p>', 'Fixture');
    throw new RuntimeException('A closed loopback SMTP port must not accept a message.');
} catch (Throwable $exception) {
    if ($exception instanceof RuntimeException && $exception->getMessage() === 'A closed loopback SMTP port must not accept a message.') {
        throw $exception;
    }
    if (str_contains($exception->getMessage(), 'Le service e-mail n’est pas configuré.')) {
        throw $exception;
    }
}

fwrite(STDOUT, "Email sender loopback failure checks passed.\n");
