<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 3 || !is_file($argv[2]) || !is_readable($argv[2])) {
    fwrite(STDERR, "Usage: php bin/send-newsletter.php <subject> <html-body-file>\n");
    exit(2);
}

require_once dirname(__DIR__) . '/bootstrap/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\Service\NewsletterUnsubscribeToken;
use QETG\Application\UseCase\SendNewsletterCampaign;
use QETG\Infrastructure\Mail\PhpMailerEmailSender;
use QETG\Infrastructure\Persistence\MySQL\MySqlMemberRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;
use QETG\Infrastructure\Persistence\MySQL\MySqlNewsletterRecipientRepository;

try {
    $database = new MySqlConnection($dbhost, $dbuser, $dbpass, $dbname);
    $emailSender = new PhpMailerEmailSender(
        (string) ($smtp_host ?? ''),
        (string) ($smtp_username ?? ''),
        (string) ($smtp_password ?? ''),
        (string) ($smtp_from ?? $expediteur ?? ''),
        (int) ($smtp_port ?? 465),
        (string) ($smtp_encryption ?? 'ssl'),
        null
    );
    $secret = (string) ($myprivatekey ?? '');
    if ($secret === '') {
        $secret = (string) (getenv('APP_PRIVATE_KEY') ?: '');
    }
    $baseUrl = (string) (getenv('APP_URL') ?: $domaine);
    if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
        $baseUrl = 'http://' . trim($baseUrl, '/');
    }
    $html = file_get_contents($argv[2]);
    if (!is_string($html)) {
        throw new RuntimeException('Impossible de lire le fichier de contenu HTML.');
    }
    $campaign = new SendNewsletterCampaign(
        new MySqlNewsletterRecipientRepository($database),
        new MySqlMemberRepository($database),
        $emailSender,
        new NewsletterUnsubscribeToken($secret),
        $baseUrl
    );
    $result = $campaign->execute((string) $argv[1], $html, strip_tags($html));
    fwrite(STDOUT, sprintf("Newsletter envoyée : %d envoyé(s), %d échec(s), %d ignoré(s).\n", $result['sent'], $result['failed'], $result['skipped']));
    exit($result['failed'] > 0 ? 1 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Newsletter non envoyée : ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
