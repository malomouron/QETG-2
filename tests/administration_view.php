<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Domain\Entity\Difficulty;

function renderAdministration(?array $data, int $quizPage = 1, int $reportPage = 1, ?array $flash = null, ?array $draft = null, bool $hasDifficulties = true): DOMXPath
{
    $basePath = '/QETG-2';
    $csrfToken = 'csrf-"<&';
    $siteHealth = array(
        array('label' => 'Base de données', 'state' => $data === null ? 'Indisponible' : 'Opérationnelle', 'level' => $data === null ? 'error' : 'ok', 'detail' => 'État de test.'),
        array('label' => 'Stockage des images', 'state' => 'Accessible', 'level' => 'ok', 'detail' => 'État de test.'),
        array('label' => 'Exécution PHP', 'state' => 'Prête', 'level' => 'ok', 'detail' => 'PHP de test.'),
        array('label' => 'Envoi des e-mails', 'state' => 'Optionnel', 'level' => 'warning', 'detail' => 'Configuration absente.'),
        array('label' => 'Protection reCAPTCHA', 'state' => 'Désactivée', 'level' => 'ok', 'detail' => 'Service optionnel.'),
        array('label' => 'Mesure du trafic', 'state' => 'Active', 'level' => 'ok', 'detail' => 'Agrégats uniquement.'),
    );
    $difficulties = $hasDifficulties ? array(new Difficulty(7, 'Expert </script>')) : array();
    ob_start();
    require dirname(__DIR__) . '/src/Presentation/View/administration.php';
    $html = (string) ob_get_clean();
    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    return new DOMXPath($document);
}

function assertAdministration(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$_SESSION = array('login' => 'gestionnaire', 'admin' => 1, 'csrf_token' => 'session-csrf');
$data = array(
    'stats' => array('members' => 78, 'official' => 41, 'community' => 17, 'reports' => 67, 'completions' => 106),
    'quizzes' => array(
        array('id' => 42, 'title' => 'Quiz <b>HTML</b>', 'questions' => 12, 'public' => true),
        array('id' => 43, 'title' => 'Quiz privé', 'questions' => 1, 'public' => false),
    ),
    'reports' => array(
        array('target_id' => 51, 'target_login' => 'membre <b>signalé</b>', 'target_admin' => 0, 'report_count' => 3, 'reporters' => 'Alice, Bob <b>& co</b>'),
        array('target_id' => 52, 'target_login' => 'gestionnaire', 'target_admin' => 1, 'report_count' => 1),
    ),
    'reportTargets' => 40,
    'traffic' => array(
        'days' => array_map(static fn (int $offset): array => array('date' => (new DateTimeImmutable('2026-11-23'))->modify('-' . (6 - $offset) . ' days')->format('Y-m-d'), 'pageViews' => $offset * 2, 'visitorSessions' => $offset), range(0, 6)),
        'today' => array('pageViews' => 12, 'visitorSessions' => 6),
        'sevenDayTotals' => array('pageViews' => 42, 'visitorSessions' => 21),
        'migrationMissing' => false,
    ),
);
$questions = array(array('text' => '</script><img src=x>', 'difficulty' => 7, 'answers' => array('<a>', 'Oui'), 'correct' => 1, 'explanation' => 'Ça <marche>.'));
$draft = array('title' => 'Brouillon "<quiz>"', 'description' => 'Description </textarea>', 'xp' => '25', 'questions' => $questions);
$view = renderAdministration($data, 2, 2, array('type' => 'error', 'message' => 'Vérifiez <b>le quiz</b>.'), $draft);

assertAdministration($view->query('//header[contains(@class,"home-header")] | //footer[contains(@class,"home-footer")]')->length === 2, 'The shared site header and footer should render.');
foreach (array('home.css', 'member-quizzes.css', 'administration.css') as $stylesheet) {
    assertAdministration($view->query("//link[@href='/QETG-2/assets/css/$stylesheet']")->length === 1, 'Expected dashboard stylesheet: ' . $stylesheet);
}
assertAdministration($view->query('//script[@src="/QETG-2/assets/js/member-quiz-editor.js"]')->length === 1, 'The dashboard must load the shared editor.');
assertAdministration($view->query('//dl[@class="admin-stats"]/div')->length === 5 && trim($view->evaluate('string(//dl[@class="admin-stats"]/div[last()]/dd)')) === '106', 'The dashboard should show the five supplied statistics.');
assertAdministration($view->query('//ul[@class="admin-health-grid"]/li')->length === 6 && str_contains($view->evaluate('string(//*[@id="admin-health-title"])'), 'Santé du site'), 'The dashboard should summarize six operational health checks.');
assertAdministration(str_contains($view->evaluate('string(//*[@id="admin-traffic-title"])'), 'Visiteurs') && $view->query('//dl[@class="admin-traffic-summary"]/div')->length === 4, 'Traffic should include visitors and page views for today and seven days.');
assertAdministration($view->query('//*[@class="admin-traffic-chart" and @role="group"]//*[@class="admin-traffic-day"]')->length === 7 && str_contains($view->evaluate('string(//*[@class="admin-traffic-privacy"])'), 'aucune adresse IP'), 'The chart should expose seven readable days and explain that no IP is retained.');
assertAdministration($view->evaluate('string(//*[@role="alert"])') === 'Vérifiez <b>le quiz</b>.' && $view->query('//*[@role="alert"]/b')->length === 0, 'Flash messages must be escaped.');
assertAdministration($view->evaluate('string(//a[@href="/QETG-2/quiz-officiel?id=42"])') === 'Quiz <b>HTML</b>' && $view->query('//a[@href="/QETG-2/quiz-officiel?id=43"]')->length === 0, 'Public quiz titles must be escaped; non-public records should not advertise a play link.');

$forms = array(
    'quiz/creer' => '//*[@id="creer-quiz"]/form',
    'quiz/supprimer' => '//*[@id="quiz"]//details[not(@open)]/form[input[@name="quiz_id" and @value="42"]]',
    'signalements/supprimer-compte' => '//*[@id="signalements"]//details[not(@open)]/form[input[@name="member_id" and @value="51"]]',
);
foreach ($forms as $endpoint => $selector) {
    $form = $view->query($selector . "[@method='post' and @action='/QETG-2/administration/$endpoint']")->item(0);
    assertAdministration($form !== null && $view->evaluate('string(input[@name="_csrf"]/@value)', $form) === 'csrf-"<&', 'Expected POST endpoint with CSRF: ' . $endpoint);
}
$create = $forms['quiz/creer'];
assertAdministration($view->evaluate("string($create//input[@name='title']/@value)") === $draft['title'], 'The title must survive a validation failure.');
assertAdministration($view->evaluate("string($create//textarea[@name='description'])") === $draft['description'], 'The description must survive a validation failure.');
assertAdministration($view->evaluate("string($create//input[@name='xp']/@value)") === $draft['xp'], 'The XP must survive a validation failure.');
assertAdministration($view->query("$create//*[@id='add-question'] | $create//*[@id='question-list']")->length === 2, 'The editor hooks must be inside the create form.');
assertAdministration(json_decode($view->evaluate('string(//script[@id="initial-question-data"])'), true, 512, JSON_THROW_ON_ERROR) === $questions && $view->query('//img[@src="x"]')->length === 0, 'The editor must receive a safe, lossless draft JSON payload.');
assertAdministration(json_decode($view->evaluate('string(//script[@id="difficulty-data"])'), true, 512, JSON_THROW_ON_ERROR) === array(array('id' => 7, 'label' => 'Expert </script>')), 'The editor must receive difficulty IDs and labels.');

assertAdministration($view->query('//*[@id="signalements"]//form')->length === 1, 'Administrator targets must not have a removal form.');
$deletionWarning = $view->evaluate('string(//*[@id="signalements"]//form/p)');
assertAdministration(str_contains($deletionWarning, 'contenus communautaires') && str_contains($deletionWarning, 'la suppression sera refusée'), 'Moderation must warn that an official quiz owner cannot be deleted.');
assertAdministration(str_contains($view->evaluate('string(//p[@class="admin-sources"])'), 'Alice, Bob <b>& co</b>') && $view->query('//p[@class="admin-sources"]')->length === 1 && $view->query('//p[@class="admin-sources"]/b')->length === 0, 'Only supplied report sources should render, as text.');

foreach (array('quiz_page=1&report_page=2#quiz', 'quiz_page=3&report_page=2#quiz', 'quiz_page=2&report_page=1#signalements') as $query) {
    assertAdministration($view->query("//a[@href='/QETG-2/administration?$query']")->length === 1, 'Pagination must preserve the other section’s page: ' . $query);
}
assertAdministration($view->query('//a[@href="/QETG-2/administration?quiz_page=2&report_page=3#signalements"]')->length === 0, 'Exactly 40 accounts should produce only two pages.');

$unavailable = renderAdministration(null, 1, 1, null, null, false);
assertAdministration(str_contains($unavailable->evaluate('string(//main)'), 'Données indisponibles') && $unavailable->query('//dl[@class="admin-stats"] | //details')->length === 0, 'Unavailable data should not be shown as zero or generate destructive actions.');
assertAdministration(str_contains($unavailable->evaluate('string(//*[@id="admin-health-title"])'), 'Santé du site') && str_contains($unavailable->evaluate('string(//*[@id="admin-traffic-title"])'), 'Visiteurs') && str_contains($unavailable->evaluate('string(//main)'), '202611230004_create_daily_traffic.sql'), 'The dashboard must explain when its traffic data is unavailable.');
assertAdministration($unavailable->query('//*[@id="creer-quiz"]//button[@type="submit" and @disabled]')->length === 1, 'Publishing should be disabled when no difficulties are available.');
assertAdministration($unavailable->evaluate('string(//script[@id="initial-question-data"])') === '[]', 'An absent draft should start with an empty JSON list.');

$data['stats'] = array_fill_keys(array_keys($data['stats']), 0);
$data['quizzes'] = $data['reports'] = array();
$data['reportTargets'] = 0;
$empty = renderAdministration($data);
assertAdministration(str_contains($empty->evaluate('string(//*[@id="quiz"])'), 'Aucun quiz officiel publié') && str_contains($empty->evaluate('string(//*[@id="signalements"])'), 'Aucun compte signalé'), 'Loaded but empty lists should display distinct empty states.');
assertAdministration($empty->query('//nav[@class="admin-pagination"]')->length === 0, 'Single-page lists should not show pagination controls.');
$outOfRange = renderAdministration($data, 3, 5);
assertAdministration($outOfRange->query('//a[@href="/QETG-2/administration?quiz_page=1&report_page=5#quiz"] | //a[@href="/QETG-2/administration?quiz_page=3&report_page=1#signalements"]')->length === 2, 'Out-of-range pages should link back to the last available page.');

fwrite(STDOUT, "Administration view checks passed.\n");