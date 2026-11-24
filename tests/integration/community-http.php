<?php

declare(strict_types=1);

use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (getenv('QETG_ENV_CONFIG') !== '1' || getenv('DB_HOST') !== 'db'
    || getenv('DB_DATABASE') !== 'test' || getenv('DB_USERNAME') !== 'qetg_ci') {
    throw new RuntimeException('Run this fixture through the isolated CI Compose runner.');
}

function communityCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class CommunityHttpBrowser
{
    private array $cookies = [];

    /** @param array<string, string>|null $form */
    public function request(string $path, int $status, ?array $form = null, ?string $location = null): string
    {
        communityCheck(str_starts_with($path, '/') && !str_starts_with($path, '//'), 'Expected an app-relative path.');
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
        $stream = @fopen('http://127.0.0.1' . $path, 'r', false, stream_context_create(['http' => $options]));
        communityCheck($stream !== false, "No HTTP response for $method $path.");
        $responseHeaders = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $body = stream_get_contents($stream);
        fclose($stream);
        communityCheck($body !== false, "Could not read the HTTP response for $method $path.");

        $statusLine = $responseHeaders[0] ?? '(missing status)';
        communityCheck(
            preg_match('~^HTTP/\\S+ (\\d{3})\\b~', $statusLine, $statusMatch) === 1 && (int) $statusMatch[1] === $status,
            "$method $path: expected HTTP $status, got $statusLine."
        );
        $actualLocation = null;
        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Location:') === 0) {
                $actualLocation = trim(substr($header, strlen('Location:')));
            }
            if (preg_match('/^Set-Cookie:\\s*([^=;]+)=([^;]*)/i', $header, $match) === 1) {
                $this->cookies[$match[1]] = $match[1] . '=' . $match[2];
            }
        }
        if ($location !== null) {
            communityCheck($actualLocation === $location, "$method $path: expected Location $location, got " . ($actualLocation ?? '(missing)') . '.');
        }
        return $body;
    }
}

function communityHtml(string $body): DOMXPath
{
    $document = new DOMDocument();
    communityCheck($document->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING), 'Expected an HTML document.');
    return new DOMXPath($document);
}

function communityFormToken(DOMXPath $page, string $formSelector): string
{
    $token = $page->evaluate("string($formSelector/input[@type='hidden' and @name='_csrf']/@value)");
    communityCheck(is_string($token) && $token !== '', "Missing CSRF token for $formSelector.");
    return $token;
}

function communityLogin(CommunityHttpBrowser $browser, string $identity, string $password): void
{
    $page = communityHtml($browser->request('/connexion', 200));
    $form = "//form[@action='/connexion' and @method='post']";
    $token = communityFormToken($page, $form);
    $browser->request('/connexion', 303, ['_csrf' => $token, 'identity' => $identity, 'password' => $password], '/accueil');
}

$db = new MySqlConnection('db', 'qetg_ci', (string) getenv('DB_PASSWORD'), 'test');
$suffix = bin2hex(random_bytes(6));
$password = 'CI-only!' . $suffix;
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$addMember = static function (string $role) use ($db, $suffix, $passwordHash): array {
    $login = 'http-community-' . $role . '-' . $suffix;
    $db->execute(
        'INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, ip, date) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)',
        $login,
        $passwordHash,
        $login . '@example.invalid',
        'Community HTTP',
        'Test',
        'default',
        '',
        date('Y-m-d')
    );
    return ['id' => $db->lastInsertId(), 'login' => $login];
};

$reporter = $addMember('reporter');
$target = $addMember('target');
$csrfTarget = $addMember('csrf-target');
$reporterId = (int) $reporter['id'];
$targetId = (int) $target['id'];
$csrfTargetId = (int) $csrfTarget['id'];

$quizTitles = [
    'public' => 'HTTP public ' . $suffix,
    'private' => 'HTTP private ' . $suffix,
    'incomplete' => 'HTTP incomplete ' . $suffix,
];
$insertQuiz = static function (string $title, int $complete, int $public) use ($db, $targetId): int {
    $db->execute(
        'INSERT INTO quiz (id_image_quiz, nom_quiz, bienvenue, xp, id_user, id_quiz_user, src, quizComplet, nbrQuestion, portee, vue) VALUES (NULL, ?, ?, 5, ?, 1, NULL, ?, 1, ?, 0)',
        $title,
        'Isolated community HTTP fixture',
        $targetId,
        $complete,
        $public
    );
    return $db->lastInsertId();
};
$publicQuizId = $insertQuiz($quizTitles['public'], 1, 1);
$privateQuizId = $insertQuiz($quizTitles['private'], 1, 0);
$incompleteQuizId = $insertQuiz($quizTitles['incomplete'], 0, 1);

$commentText = 'HTTP community comment ' . $suffix;
$db->execute(
    'INSERT INTO quiz_commentaire (nom, prenom, mail, commentaire, date_creation, ip_utilisateur) VALUES (?, ?, ?, ?, ?, ?)',
    'Community HTTP',
    'Fixture',
    'community-' . $suffix . '@example.invalid',
    $commentText,
    date('Y-m-d H:i:s'),
    '127.0.0.1'
);
$commentId = $db->lastInsertId();

// Each browser retains only its own session cookie; login itself uses the routed CSRF form.
$reporterBrowser = new CommunityHttpBrowser();
$targetBrowser = new CommunityHttpBrowser();
$guestBrowser = new CommunityHttpBrowser();
communityLogin($reporterBrowser, (string) $reporter['login'], $password);
communityLogin($targetBrowser, (string) $target['login'], $password);

// Subscription create/remove over the routed endpoint, with state asserted directly in MySQL.
$profilePath = '/profil-public?id=' . $targetId;
$profile = communityHtml($reporterBrowser->request($profilePath, 200));
$subscriptionForm = "//form[@action='/communaute/abonnement' and @method='post']";
$communityCsrf = communityFormToken($profile, $subscriptionForm);
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_subscription_membre WHERE idUser = ? AND idUserAbonnement = ?', $reporterId, $targetId)['n'] === 0, 'The fixture must start without this subscription.');
$reporterBrowser->request('/communaute/abonnement', 303, [
    '_csrf' => $communityCsrf,
    'member_id' => (string) $targetId,
    'subscribe' => '1',
    'return_tab' => 'profile',
    'profile_id' => (string) $targetId,
], $profilePath);
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_subscription_membre WHERE idUser = ? AND idUserAbonnement = ?', $reporterId, $targetId)['n'] === 1, 'Successful follow must create exactly one subscription row.');
$profile = communityHtml($reporterBrowser->request($profilePath, 200));
communityCheck($profile->evaluate('string(//*[@role="status"])') === 'Vous suivez maintenant ce membre.', 'Follow success must be shown on the public profile.');
$unsubscribeForm = "//form[@action='/communaute/abonnement' and @method='post']";
$unsubscribeToken = communityFormToken($profile, $unsubscribeForm);
communityCheck($profile->evaluate("string($unsubscribeForm/input[@name='subscribe']/@value)") === '0', 'The profile must offer the unsubscribe action after following.');
$reporterBrowser->request('/communaute/abonnement', 303, [
    '_csrf' => $unsubscribeToken,
    'member_id' => (string) $targetId,
    'subscribe' => '0',
    'return_tab' => 'profile',
    'profile_id' => (string) $targetId,
], $profilePath);
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_subscription_membre WHERE idUser = ? AND idUserAbonnement = ?', $reporterId, $targetId)['n'] === 0, 'Unfollow must remove the subscription row.');
$profile = communityHtml($reporterBrowser->request($profilePath, 200));
communityCheck($profile->evaluate('string(//*[@role="status"])') === 'Vous ne suivez plus ce membre.', 'Unfollow success must be shown on the public profile.');

// Reports: one success, then duplicate/self/CSRF failures must leave counts unchanged.
$reportForm = "//form[@action='/communaute/signalement' and @method='post']";
$reportCsrf = communityFormToken($profile, $reportForm);
$reporterBrowser->request('/communaute/signalement', 303, ['_csrf' => $reportCsrf, 'member_id' => (string) $targetId], $profilePath . '#signaler');
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_signalement WHERE id_user_origine = ? AND id_user_cible = ?', $reporterId, $targetId)['n'] === 1, 'A successful report must insert one report row.');
$profile = communityHtml($reporterBrowser->request($profilePath, 200));
communityCheck($profile->evaluate('string(//*[@role="status"])') === 'Le profil a été signalé à l’équipe de modération.', 'A successful report must show its confirmation.');
$reportCsrf = communityFormToken($profile, $reportForm);
$reporterBrowser->request('/communaute/signalement', 303, ['_csrf' => $reportCsrf, 'member_id' => (string) $targetId], $profilePath . '#signaler');
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_signalement WHERE id_user_origine = ? AND id_user_cible = ?', $reporterId, $targetId)['n'] === 1, 'Duplicate reporting must not insert another row.');
$profile = communityHtml($reporterBrowser->request($profilePath, 200));
communityCheck(str_contains($profile->evaluate('string(//*[@role="status"])'), 'déjà signalé'), 'Duplicate reports must show the validation message.');
$reporterBrowser->request('/communaute/signalement', 303, ['_csrf' => $reportCsrf, 'member_id' => (string) $reporterId], '/profil-public?id=' . $reporterId . '#signaler');
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_signalement WHERE id_user_origine = ? AND id_user_cible = ?', $reporterId, $reporterId)['n'] === 0, 'Self-reporting must not write a row.');
$profile = communityHtml($reporterBrowser->request('/profil-public?id=' . $reporterId, 200));
communityCheck(str_contains($profile->evaluate('string(//*[@role="status"])'), 'ne pouvez pas signaler'), 'Self-reporting must show the validation message.');
$reporterBrowser->request('/communaute/signalement', 303, ['_csrf' => 'invalid-' . $suffix, 'member_id' => (string) $csrfTargetId], '/profil-public?id=' . $csrfTargetId . '#signaler');
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_signalement WHERE id_user_origine = ? AND id_user_cible = ?', $reporterId, $csrfTargetId)['n'] === 0, 'A report with invalid CSRF must not write a row.');
$csrfProfile = communityHtml($reporterBrowser->request('/profil-public?id=' . $csrfTargetId, 200));
communityCheck(str_contains($csrfProfile->evaluate('string(//*[@role="status"])'), 'session a expiré'), 'Invalid report CSRF must show an error.');

// Public profile and the public member-quiz catalog must hide private and incomplete fixtures.
$guestProfile = communityHtml($guestBrowser->request($profilePath, 200));
communityCheck(str_contains((string) $guestProfile->evaluate('string(/html/body)'), $quizTitles['public']), 'Public profile must show the completed public quiz.');
communityCheck(!str_contains((string) $guestProfile->evaluate('string(/html/body)'), $quizTitles['private']) && !str_contains((string) $guestProfile->evaluate('string(/html/body)'), $quizTitles['incomplete']), 'Public profile must omit private and incomplete quizzes.');
$catalog = communityHtml($guestBrowser->request('/quiz-membres?tab=catalog', 200));
communityCheck(str_contains((string) $catalog->evaluate('string(/html/body)'), $quizTitles['public']), 'Member quiz catalog must show the completed public fixture.');
communityCheck(!str_contains((string) $catalog->evaluate('string(/html/body)'), $quizTitles['private']) && !str_contains((string) $catalog->evaluate('string(/html/body)'), $quizTitles['incomplete']), 'Member quiz catalog must omit private and incomplete quizzes.');

// Reactions traverse the actual catalog form and routed POST endpoint: positive, negative, then toggle off.
$reporterCatalog = communityHtml($reporterBrowser->request('/quiz-membres?tab=catalog', 200));
$reactionForm = "//form[@action='/interactions/reaction' and @method='post'][input[@name='quiz_id' and @value='$publicQuizId']]";
$reactionCsrf = communityFormToken($reporterCatalog, $reactionForm);
$reactionPost = static function (int $value) use ($reporterBrowser, $reactionCsrf, $publicQuizId): void {
    $reporterBrowser->request('/interactions/reaction', 303, [
        '_csrf' => $reactionCsrf,
        'quiz_id' => (string) $publicQuizId,
        'value' => (string) $value,
        'return_to' => '/quiz-membres?tab=catalog',
    ], '/quiz-membres?tab=catalog');
};
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_like_quiz WHERE id_quiz = ?', $publicQuizId)['n'] === 0, 'Reaction fixture must start without reactions.');
$reactionPost(1);
$reactionAfter = communityHtml($reporterBrowser->request('/accueil', 200));
$reaction = $db->fetchOne('SELECT likeOrDislike, esQuizUser FROM quiz_like_quiz WHERE id_quiz = ?', $publicQuizId);
communityCheck($reaction !== null && (int) $reaction['likeOrDislike'] === 1 && (int) $reaction['esQuizUser'] === 1, 'Positive reaction must be persisted as a member-quiz like; got ' . var_export($reaction, true) . '; flash=' . $reactionAfter->evaluate('string(//*[@role="status"])') . ' error=' . $reactionAfter->evaluate('string(//*[@role="alert"])'));
$reactionPost(0);
$reaction = $db->fetchOne('SELECT likeOrDislike FROM quiz_like_quiz WHERE id_quiz = ?', $publicQuizId);
communityCheck($reaction !== null && (int) $reaction['likeOrDislike'] === 0, 'Negative reaction must replace the existing positive reaction.');
$reactionPost(0);
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_like_quiz WHERE id_quiz = ?', $publicQuizId)['n'] === 0, 'Repeating a reaction must toggle it off.');
$reporterBrowser->request('/interactions/reaction', 303, [
    '_csrf' => $reactionCsrf,
    'quiz_id' => (string) $publicQuizId,
    'value' => '2',
    'return_to' => '/quiz-membres?tab=catalog',
], '/quiz-membres?tab=catalog');
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_like_quiz WHERE id_quiz = ?', $publicQuizId)['n'] === 0, 'Rejected reaction values must not write a row.');

// Replies: accepted content persists, while invalid content and invalid CSRF leave the DB unchanged.
$home = communityHtml($reporterBrowser->request('/accueil', 200));
$replyForm = "//form[@action='/interactions/reponse-commentaire' and @method='post'][input[@name='comment_id' and @value='$commentId']]";
$replyCsrf = communityFormToken($home, $replyForm);
$replyContent = 'HTTP reply ' . $suffix;
$replyPost = static function (string $token, string $content) use ($reporterBrowser, $commentId): void {
    $reporterBrowser->request('/interactions/reponse-commentaire', 303, [
        '_csrf' => $token,
        'comment_id' => (string) $commentId,
        'reply' => $content,
    ], '/accueil#commentaires');
};
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_reponse_commentaire WHERE id_commentaire = ?', $commentId)['n'] === 0, 'Reply fixture must start without replies.');
$replyPost($replyCsrf, $replyContent);
$replyRow = $db->fetchOne('SELECT id_user, reponse_val FROM quiz_reponse_commentaire WHERE id_commentaire = ?', $commentId);
communityCheck($replyRow !== null && (int) $replyRow['id_user'] === $reporterId && $replyRow['reponse_val'] === $replyContent, 'A successful reply must persist the member and exact content.');
$home = communityHtml($reporterBrowser->request('/accueil', 200));
communityCheck($home->evaluate('string(//*[@role="status"])') === 'Votre réponse a été publiée.', 'A successful reply must show its confirmation.');
$replyPost($replyCsrf, '   ');
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_reponse_commentaire WHERE id_commentaire = ?', $commentId)['n'] === 1, 'Blank reply validation must not create another row.');
$replyPost('invalid-' . $suffix, 'Rejected reply ' . $suffix);
communityCheck((int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_reponse_commentaire WHERE id_commentaire = ?', $commentId)['n'] === 1, 'Invalid reply CSRF must not create another row.');
$home = communityHtml($reporterBrowser->request('/accueil', 200));
communityCheck(str_contains($home->evaluate('string(//*[@role="status"])'), 'session du formulaire a expiré'), 'Invalid reply CSRF must show an error.');

// Ensure the separate authenticated browser has its own identity and session.
$targetPage = communityHtml($targetBrowser->request('/accueil', 200));
communityCheck(str_contains((string) $targetPage->evaluate('string(/html/body)'), (string) $target['login']), 'The second cookie client must retain its own authenticated member session.');
communityCheck($publicQuizId !== $privateQuizId && $privateQuizId !== $incompleteQuizId, 'Fixture quiz IDs must be unique.');

fwrite(STDOUT, "Community HTTP integration checks passed.\n");
