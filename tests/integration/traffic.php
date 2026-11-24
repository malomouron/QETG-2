<?php

declare(strict_types=1);

use QETG\Infrastructure\Persistence\MySQL\MySqlAdministrationRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (getenv('DB_HOST') !== 'db' || getenv('DB_DATABASE') !== 'test' || getenv('DB_USERNAME') !== 'qetg_ci') {
    throw new RuntimeException('Run this fixture through the isolated CI Compose runner.');
}

function checkTraffic(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new MySqlConnection('db', 'qetg_ci', (string) getenv('DB_PASSWORD'), 'test');
$admin = new MySqlAdministrationRepository($db);
$db->execute('DELETE FROM quiz_daily_traffic');

$columns = $db->fetchAll(
    "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_daily_traffic' ORDER BY ORDINAL_POSITION"
);
checkTraffic(array_column($columns, 'COLUMN_NAME') === ['daily', 'page_views', 'visitor_sessions'], 'The traffic table must contain only daily aggregate columns.');
checkTraffic($columns[0]['DATA_TYPE'] === 'date', 'The daily key must use the DATE type.');
checkTraffic($columns[1]['DATA_TYPE'] === 'bigint' && str_contains($columns[1]['COLUMN_TYPE'], 'unsigned'), 'Page views must use an unsigned BIGINT.');
checkTraffic($columns[2]['DATA_TYPE'] === 'bigint' && str_contains($columns[2]['COLUMN_TYPE'], 'unsigned'), 'Visitor sessions must use an unsigned BIGINT.');
$primaryKey = $db->fetchAll(
    "SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_daily_traffic' AND INDEX_NAME = 'PRIMARY' ORDER BY SEQ_IN_INDEX"
);
checkTraffic(array_column($primaryKey, 'COLUMN_NAME') === ['daily'], 'Daily must be the table primary key.');

$today = (string) $db->fetchOne('SELECT CURRENT_DATE AS today')['today'];
$yesterday = (string) $db->fetchOne('SELECT DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY) AS yesterday')['yesterday'];
$admin->recordPageView(false);
$admin->recordPageView(false);
$admin->recordPageView(true);
$db->execute(
    'INSERT INTO quiz_daily_traffic (daily, page_views, visitor_sessions) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE page_views = VALUES(page_views), visitor_sessions = VALUES(visitor_sessions)',
    $yesterday,
    5,
    2
);

$dashboard = $admin->dashboard(1, 1);
$traffic = $dashboard['traffic'];
checkTraffic($traffic['migrationMissing'] === false, 'Traffic migration should be detected as installed.');
checkTraffic(count($traffic['days']) === 7, 'Traffic must include seven calendar days.');
checkTraffic(array_column($traffic['days'], 'date') === array_map(
    static fn (int $daysAgo): string => (new DateTimeImmutable($today))->modify('-' . $daysAgo . ' days')->format('Y-m-d'),
    [6, 5, 4, 3, 2, 1, 0]
), 'Traffic days must be ordered from the oldest calendar day through today.');
$todayData = $traffic['days'][6];
$yesterdayData = $traffic['days'][5];
checkTraffic($todayData === ['date' => $today, 'pageViews' => 3, 'visitorSessions' => 1], 'The caller boolean must add at most one unique session for the day.');
checkTraffic($yesterdayData === ['date' => $yesterday, 'pageViews' => 5, 'visitorSessions' => 2], 'Daily aggregate counts must be returned for historical days.');
checkTraffic($traffic['days'][0]['pageViews'] === 0 && $traffic['days'][0]['visitorSessions'] === 0, 'Unrecorded days must be included with zero counts.');
checkTraffic($traffic['today'] === ['pageViews' => 3, 'visitorSessions' => 1], 'Today totals must match the current calendar date.');
checkTraffic($traffic['sevenDayTotals'] === ['pageViews' => 8, 'visitorSessions' => 3], 'Seven-day totals must aggregate all returned calendar days.');

try {
    $db->execute('DROP TABLE quiz_daily_traffic');
    $withoutMigration = $admin->dashboard(1, 1);
    checkTraffic($withoutMigration['traffic']['migrationMissing'] === true, 'A missing migration must be reported without failing the dashboard.');
    checkTraffic(count($withoutMigration['traffic']['days']) === 7 && $withoutMigration['traffic']['sevenDayTotals'] === ['pageViews' => 0, 'visitorSessions' => 0], 'Missing traffic storage must return a zero-filled seven-day range.');
    checkTraffic(array_keys($withoutMigration['stats']) === array_keys($dashboard['stats']), 'The other dashboard statistics must remain available when traffic storage is missing.');
    checkTraffic(isset($withoutMigration['quizzes'], $withoutMigration['reports'], $withoutMigration['reportTargets']), 'Dashboard lists must remain available when traffic storage is missing.');
} finally {
    $db->execute(
        'CREATE TABLE IF NOT EXISTS quiz_daily_traffic (daily DATE NOT NULL, page_views BIGINT UNSIGNED NOT NULL DEFAULT 0, visitor_sessions BIGINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (daily)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3'
    );
    $db->execute('DELETE FROM quiz_daily_traffic');
}

echo "Daily traffic integration checks passed.\n";
