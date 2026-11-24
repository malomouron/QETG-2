<?php

declare(strict_types=1);

use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
if (PHP_SAPI !== 'cli' || getenv('QETG_ENV_CONFIG') !== '1' || getenv('DB_HOST') !== 'db'
    || getenv('DB_DATABASE') !== 'test' || getenv('DB_USERNAME') !== 'qetg_ci' || getenv('DB_PASSWORD') !== 'ci-app-only') {
    throw new RuntimeException('Run this fixture only through the isolated tests Compose environment.');
}
function pagesHttpCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
final class PagesHttpBrowser
{
    public function request(string $method, string $path, int $status): string
    {
        $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
        $stream = @fopen('http://127.0.0.1' . $path, 'r', false, $context);
        pagesHttpCheck($stream !== false, "$method $path did not respond.");
        $headers = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $body = stream_get_contents($stream);
        fclose($stream);
        $line = $headers[0] ?? '(missing status)';
        pagesHttpCheck(preg_match('~^HTTP/\S+ (\d{3})\b~', $line, $match) === 1 && (int) $match[1] === $status, "$method $path expected $status, got $line.");
        return (string) $body;
    }
}
$db = new MySqlConnection('db', 'qetg_ci', 'ci-app-only', 'test');
$suffix = bin2hex(random_bytes(6));
$login = 'http-pages-' . $suffix;
$memberId = null;
$officialQuizId = null;
$publicMemberQuizId = null;
$privateMemberQuizId = null;
try {
    $db->execute('INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, ip, date) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)',
        $login, 'page-test-hash', $login . '@example.invalid', 'Pages', 'Fixture', 'default', '', date('d/m/Y'));
    $memberId = $db->lastInsertId();
    $addQuiz = static function (string $title, int $memberCreated, int $public) use ($db, $memberId): int {
        $db->execute('INSERT INTO quiz (nom_quiz, bienvenue, xp, id_user, id_quiz_user, quizComplet, nbrQuestion, portee) VALUES (?, ?, 5, ?, ?, 1, 0, ?)',
            $title, 'Search HTTP fixture', $memberId, $memberCreated, $public);
        return $db->lastInsertId();
    };
    $title = 'HTTP searchable title ' . $suffix;
    $officialQuizId = $addQuiz($title, 0, 1);
    $publicMemberQuizId = $addQuiz('HTTP public member ' . $suffix, 1, 1);
    $privateMemberQuizId = $addQuiz('HTTP private member ' . $suffix, 1, 0);

    $browser = new PagesHttpBrowser();
    foreach (['/aide' => 'Centre d’aide', '/a-propos' => 'Quiz en tout genre', '/recherche?search=' . rawurlencode($suffix) => $title] as $path => $expected) {
        $body = $browser->request('GET', $path, 200);
        pagesHttpCheck(str_contains($body, $expected), "$path must render its expected content.");
    }
    $search = $browser->request('GET', '/recherche?search=' . rawurlencode($suffix), 200);
    pagesHttpCheck(str_contains($search, '/quiz-officiel?id=' . $officialQuizId), 'Search should link to the matching official quiz.');
    pagesHttpCheck(str_contains($search, '/quiz-membres/jouer?id=' . $publicMemberQuizId), 'Search should link to a matching public member quiz.');
    pagesHttpCheck(!str_contains($search, '/quiz-membres/jouer?id=' . $privateMemberQuizId), 'Search must not expose a private member quiz.');
    $browser->request('GET', '/route-that-does-not-exist-' . $suffix, 404);
    $browser->request('POST', '/aide', 405);
    echo "Information, search and routing HTTP integration checks passed.\n";
} finally {
    if ($officialQuizId !== null) $db->execute('DELETE FROM quiz WHERE id_quiz = ?', $officialQuizId);
    if ($publicMemberQuizId !== null) $db->execute('DELETE FROM quiz WHERE id_quiz = ?', $publicMemberQuizId);
    if ($privateMemberQuizId !== null) $db->execute('DELETE FROM quiz WHERE id_quiz = ?', $privateMemberQuizId);
    if ($memberId !== null) $db->execute('DELETE FROM quiz_membre WHERE id = ?', $memberId);
    $db->close();
}
