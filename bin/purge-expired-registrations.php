<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\UseCase\PurgeExpiredRegistrations;
use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;
use QETG\Infrastructure\Persistence\MySQL\MySqlPendingRegistrationRepository;

try {
    $database = new MySqlConnection($dbhost, $dbuser, $dbpass, $dbname);
    $purged = (new PurgeExpiredRegistrations(new MySqlPendingRegistrationRepository($database)))->execute();
    fwrite(STDOUT, sprintf("Purged %d expired registration(s).\n", $purged));
} catch (Throwable $exception) {
    fwrite(STDERR, "Registration cleanup failed: " . $exception->getMessage() . PHP_EOL);
    exit(1);
}
