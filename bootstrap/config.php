<?php

declare(strict_types=1);

if (getenv('QETG_ENV_CONFIG') === '1') {
    $servername = getenv('DB_HOST') ?: 'db';
    $username = getenv('DB_USERNAME') ?: 'qetg';
    $password = getenv('DB_PASSWORD') ?: '';
    $dbname = getenv('DB_DATABASE') ?: '';
    $domaine = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/') . '/';
    $expediteur = getenv('MAIL_FROM') ?: 'no-reply@example.invalid';
    $site_key = (string) getenv('RECAPTCHA_SITE_KEY');
    $recaptcha_secret_key = (string) getenv('RECAPTCHA_SECRET_KEY');
    $os = 'linux';
    $myprivatekey = getenv('APP_PRIVATE_KEY') ?: '';
    $nombreDeQuizAfficher = (int) (getenv('QUIZZES_PER_PAGE') ?: 5);
    $smtp_host = getenv('SMTP_HOST') ?: '';
    $smtp_username = getenv('SMTP_USERNAME') ?: '';
    $smtp_password = getenv('SMTP_PASSWORD') ?: '';
    $smtp_from = $expediteur;
    $smtp_port = (int) (getenv('SMTP_PORT') ?: 465);
    $smtp_encryption = getenv('SMTP_ENCRYPTION') ?: 'ssl';
    $smtp_bcc = getenv('SMTP_BCC') ?: $smtp_from;
} else {
    require_once dirname(__DIR__) . '/config.inc.php';
}

$dbpass = $password;
$dbuser = $username;
$dbhost = $servername;
