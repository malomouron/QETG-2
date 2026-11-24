<?php

declare(strict_types=1);

use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (getenv('QETG_ENV_CONFIG') !== '1' || getenv('DB_HOST') !== 'db'
    || getenv('DB_DATABASE') !== 'test' || getenv('DB_USERNAME') !== 'qetg_ci') {
    throw new RuntimeException('Run this fixture through the isolated CI Compose runner.');
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class AdministrationBrowser
{
    private array $cookies = [];

    public function request(string $path, int $status, ?array $form = null, ?string $location = null): string
    {
        check(str_starts_with($path, '/') && !str_starts_with($path, '//'), 'Expected an app-relative path.');
        $method = $form === null ? 'GET' : 'POST';
        $headers = [];
        if ($this->cookies !== []) {
            $headers[] = 'Cookie: ' . implode('; ', $this->cookies);
        }
        $options = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10];
        if ($form !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = http_build_query($form);
        }
        $options['header'] = $headers;
        // Apache is reached from inside the app container; no host ports or redirects are used.
        $stream = fopen('http://127.0.0.1' . $path, 'r', false, stream_context_create(['http' => $options]));
        check($stream !== false, "No HTTP response for $method $path.");
        $responseHeaders = stream_get_meta_data($stream)['wrapper_data'];
        $body = stream_get_contents($stream);
        fclose($stream);
        check($body !== false, "Could not read the HTTP response for $method $path.");

        $statusLine = $responseHeaders[0] ?? '(missing status)';
        check(preg_match('~^HTTP/\S+ (\d{3})\b~', $statusLine, $statusMatch) === 1 && (int) $statusMatch[1] === $status,
            "$method $path: expected HTTP $status, got $statusLine.");
        $actualLocation = null;
        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Location:') === 0) {
                $actualLocation = trim(substr($header, strlen('Location:')));
            }
            if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $header, $match) === 1) {
                $this->cookies[$match[1]] = $match[1] . '=' . $match[2];
            }
        }
        if ($location !== null) {
            check($actualLocation === $location, "$method $path: expected Location $location, got " . ($actualLocation ?? '(missing)') . '.');
        }
        return $body;
    }
}

function html(string $body): DOMXPath
{
    $document = new DOMDocument();
    check($document->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING), 'Expected an HTML document.');
    return new DOMXPath($document);
}

function formToken(DOMXPath $page, string $formSelector): string
{
    $token = $page->evaluate("string($formSelector/input[@type='hidden' and @name='_csrf']/@value)");
    check(is_string($token) && $token !== '', "Missing CSRF token for $formSelector.");
    return $token;
}

function login(AdministrationBrowser $browser, string $identity, string $password): void
{
    $page = html($browser->request('/connexion', 200));
    $token = formToken($page, "//form[@action='/connexion' and @method='post']");
    $browser->request('/connexion', 303, ['_csrf' => $token, 'identity' => $identity, 'password' => $password], '/accueil');
}

$dashboard = '/administration';
$create = $dashboard . '/quiz/creer';
$delete = $dashboard . '/quiz/supprimer';
$moderate = $dashboard . '/signalements/supprimer-compte';
$guest = new AdministrationBrowser();
$guest->request($dashboard, 303, location: '/connexion');
foreach ([$create, $delete, $moderate] as $route) {
    $guest->request($route, 303, [], '/connexion');
}

// Seed only accounts and reports; login, publication and moderation go through Apache.
$db = new MySqlConnection('db', 'qetg_ci', (string) getenv('DB_PASSWORD'), 'test');
$suffix = bin2hex(random_bytes(6));
$password = 'CI-only!' . $suffix;
$hash = password_hash($password, PASSWORD_DEFAULT);
$addMember = static function (string $login, int $role = 0) use ($db, $hash): int {
    $db->execute(
        'INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, ip, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        $login, $hash, $login . '@example.invalid', 'HTTP', 'CI', $role, 'default', '', ''
    );
    return $db->lastInsertId();
};
$adminLogin = 'http-admin-' . $suffix;
$memberLogin = 'http-member-' . $suffix;
$adminId = $addMember($adminLogin, 1);
$memberId = $addMember($memberLogin);
$targetId = $addMember('http-target-' . $suffix);
$protectedId = $addMember('http-protected-' . $suffix, 1);
// Two reports per fixture keep both rows on the first dashboard page after the repository suite.
foreach ([$targetId, $protectedId] as $target) {
    foreach ([$adminId, $memberId] as $reporter) {
        $db->execute('INSERT INTO quiz_signalement (id_user_origine, id_user_cible) VALUES (?, ?)', $reporter, $target);
    }
}

$member = new AdministrationBrowser();
login($member, $memberLogin, $password);
$denial = $member->request($dashboard, 403);
check(str_contains($denial, 'réservé aux administrateurs'), 'The dashboard must deny a signed-in non-admin.');
foreach ([$create, $delete, $moderate] as $route) {
    check($member->request($route, 403, []) === $denial, "$route must check the member role before CSRF.");
}

$admin = new AdministrationBrowser();
login($admin, $adminLogin, $password);
$page = html($admin->request($dashboard, 200));
check($page->query('//main//h1[@id="admin-title"]')->length === 1 && $page->query('//dl[@class="admin-stats"]/div')->length === 5, 'The dashboard heading and statistics must render.');
$trafficBefore = $db->fetchOne('SELECT page_views, visitor_sessions FROM quiz_daily_traffic WHERE daily = CURRENT_DATE');
$visitor = new AdministrationBrowser();
$visitor->request('/accueil', 200);
$visitor->request('/accueil', 200);
$trafficAfter = $db->fetchOne('SELECT page_views, visitor_sessions FROM quiz_daily_traffic WHERE daily = CURRENT_DATE');
check($trafficAfter !== null
    && (int) $trafficAfter['page_views'] === (int) ($trafficBefore['page_views'] ?? 0) + 2
    && (int) $trafficAfter['visitor_sessions'] === (int) ($trafficBefore['visitor_sessions'] ?? 0) + 1, 'Two public requests from the same browser session must count as two page views and one visitor session.');
$trafficBeforeAdmin = $db->fetchOne('SELECT page_views, visitor_sessions FROM quiz_daily_traffic WHERE daily = CURRENT_DATE');
$admin->request($dashboard, 200);
$trafficAfterAdmin = $db->fetchOne('SELECT page_views, visitor_sessions FROM quiz_daily_traffic WHERE daily = CURRENT_DATE');
check($trafficAfterAdmin === $trafficBeforeAdmin, 'Admin dashboard views must not be included in visitor statistics.');
$csrf = formToken($page, "//form[@action='$create' and @method='post']");
$targetProfile = '/profil-public?id=' . $targetId;
$protectedProfile = '/profil-public?id=' . $protectedId;
$moderationForm = "//section[@id='signalements']//form[@action='$moderate' and @method='post'][input[@name='member_id' and @value='$targetId']]";
check(formToken($page, $moderationForm) === $csrf, 'The moderation form must carry the admin session token.');
$protectedRow = "//section[@id='signalements']//li[.//a[@href='$protectedProfile']]";
check($page->query("$protectedRow//span[contains(@class,'admin-badge')]")->length === 1
    && $page->query("$protectedRow//form")->length === 0, 'The reported administrator must appear without a deletion form.');

$difficulties = json_decode($page->evaluate('string(//script[@id="difficulty-data"])'), true, 512, JSON_THROW_ON_ERROR);
$difficulty = $difficulties[0]['id'] ?? null;
check(is_int($difficulty) && $difficulty > 0, 'The dashboard editor must expose a seeded difficulty.');
$title = 'CI HTTP quiz ' . $suffix;
$question = 'Which answer came from the admin form?';
$publication = [
    '_csrf' => $csrf, 'title' => $title, 'description' => 'Isolated Compose fixture', 'xp' => '12',
    'questions' => [['text' => $question, 'difficulty' => $difficulty, 'answers' => ['First choice', 'Second choice'], 'correct' => 0, 'explanation' => 'CI explanation']],
];
$rejectedTitle = 'Rejected ' . $title;
$admin->request($create, 403, array_replace($publication, ['_csrf' => 'wrong-token', 'title' => $rejectedTitle]));
$before = html($guest->request('/accueil', 200));
check($before->query("//a[normalize-space(.)='$rejectedTitle']")->length === 0, 'Rejected publication must not reach the catalog.');

$admin->request($create, 303, $publication, $dashboard . '#quiz');
$page = html($admin->request($dashboard, 200));
check($page->evaluate('string(//main//*[@role="status"])') === 'Le quiz officiel a été publié.', 'Publication must report success.');
$playPath = $page->evaluate("string(//section[@id='quiz']//a[normalize-space(.)='$title']/@href)");
check(preg_match('~^/quiz-officiel\?id=([1-9][0-9]*)$~D', $playPath, $quizMatch) === 1, 'The dashboard must link to the published official quiz.');
$quizId = $quizMatch[1];
$catalog = html($guest->request('/accueil', 200));
check($catalog->query("//a[@href='$playPath' and normalize-space(.)='$title']")->length === 1, 'The public catalog must link to the same quiz.');
$deleteForm = "//form[@action='$delete' and @method='post'][input[@name='quiz_id' and @value='$quizId']]";
check(formToken($page, $deleteForm) === $csrf, 'The quiz deletion form must carry the admin session token.');
$admin->request($delete, 403, ['_csrf' => 'wrong-token', 'quiz_id' => $quizId]);
$play = html($guest->request($playPath, 200));
$start = $play->evaluate('string(//a[contains(@class,"official-start-button")]/@href)');
check($start === "/quiz/question?id_quiz=$quizId&numero_question=1", 'The published quiz must offer a start link.');
$firstQuestion = html($guest->request($start, 200));
check(str_contains($firstQuestion->evaluate('string(//main)'), $question)
    && $firstQuestion->query('//form[@action="/quiz/reponse"]//input[@name="id_reponse"]')->length === 2, 'The first question and both answer controls must be playable.');

$admin->request($delete, 303, ['_csrf' => $csrf, 'quiz_id' => $quizId], $dashboard . '#quiz');
$page = html($admin->request($dashboard, 200));
check($page->evaluate('string(//main//*[@role="status"])') === 'Le quiz officiel a été supprimé.', 'Quiz deletion must report success.');
$guest->request($playPath, 404);
$guest->request($start, 404);
$catalog = html($guest->request('/accueil', 200));
check($catalog->query("//a[@href='$playPath']")->length === 0, 'The deleted quiz must disappear from the public catalog.');

$admin->request($moderate, 403, ['_csrf' => 'wrong-token', 'member_id' => $targetId]);
$guest->request($targetProfile, 200);
$admin->request($moderate, 303, ['_csrf' => $csrf, 'member_id' => $protectedId], $dashboard . '#signalements');
$page = html($admin->request($dashboard, 200));
check(str_contains($page->evaluate('string(//main//*[@role="alert"])'), 'compte administrateur'), 'A direct POST with valid CSRF must still refuse administrator deletion.');
$guest->request($protectedProfile, 200);

$admin->request($moderate, 303, ['_csrf' => $csrf, 'member_id' => $targetId], $dashboard . '#signalements');
$page = html($admin->request($dashboard, 200));
check($page->evaluate('string(//main//*[@role="status"])') === 'Le compte signalé a été supprimé.', 'Moderation deletion must report success.');
check($page->query("//section[@id='signalements']//a[@href='$targetProfile']")->length === 0, 'The deleted account must disappear from moderation.');
$guest->request($targetProfile, 404);

echo "Administration HTTP integration checks passed.\n";