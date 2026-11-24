<?php

declare(strict_types=1);

use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (getenv('QETG_ENV_CONFIG') !== '1' || getenv('DB_HOST') !== 'db'
    || getenv('DB_DATABASE') !== 'test' || getenv('DB_USERNAME') !== 'qetg_ci'
    || getenv('DB_PASSWORD') !== 'ci-app-only'
) {
    throw new RuntimeException('Run this fixture through the isolated CI Compose runner.');
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class OfficialQuizBrowser
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
        // Apache is reached from inside the isolated app container; no host ports or redirects are used.
        $stream = fopen('http://127.0.0.1' . $path, 'r', false, stream_context_create(['http' => $options]));
        check($stream !== false, "No HTTP response for $method $path.");
        $responseHeaders = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $body = stream_get_contents($stream);
        fclose($stream);
        check($body !== false, "Could not read the HTTP response for $method $path.");

        $statusLine = is_array($responseHeaders) ? ($responseHeaders[0] ?? '(missing status)') : '(missing status)';
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

function answerForm(DOMXPath $page, int $answerId): array
{
    $form = "//form[@action='/quiz/reponse' and @method='post']";
    $token = formToken($page, $form);
    $quizId = $page->evaluate("string($form/input[@name='id_quiz']/@value)");
    $questionId = $page->evaluate("string($form/input[@name='id_question']/@value)");
    check(ctype_digit($quizId) && ctype_digit($questionId), 'The answer form must identify its quiz and question.');
    check($page->query("$form//input[@name='id_reponse' and @value='$answerId']")->length === 1, 'Expected answer ID must be rendered as a choice.');
    return ['_csrf' => $token, 'id_quiz' => $quizId, 'id_question' => $questionId, 'id_reponse' => (string) $answerId];
}

function login(OfficialQuizBrowser $browser, string $identity, string $password): void
{
    $page = html($browser->request('/connexion', 200));
    $form = "//form[@action='/connexion' and @method='post']";
    $browser->request('/connexion', 303, [
        '_csrf' => formToken($page, $form),
        'identity' => $identity,
        'password' => $password,
    ], '/accueil');
}

function idsClause(array $ids): string
{
    check($ids !== [] && count(array_filter($ids, static fn (mixed $id): bool => is_int($id) && $id > 0)) === count($ids), 'Expected positive fixture IDs.');
    return implode(',', array_fill(0, count($ids), '?'));
}

$db = new MySqlConnection('db', 'qetg_ci', (string) getenv('DB_PASSWORD'), 'test');
$suffix = bin2hex(random_bytes(6));
$loginName = 'http-official-' . $suffix;
$password = 'CI-only!' . $suffix;
$memberId = null;
$quizIds = [];
$questionIds = [];
$answerIds = [];
$insertedAchievementId = null;
$testFailure = null;

try {
    $difficulty = $db->fetchOne("SELECT id_difficulte, libelle FROM quiz_difficulte WHERE libelle = 'Intermédiaire' ORDER BY id_difficulte LIMIT 1");
    check($difficulty !== null && (int) $difficulty['id_difficulte'] > 0, 'Expected a real seeded quiz difficulty.');
    $difficultyId = (int) $difficulty['id_difficulte'];

    $achievement = $db->fetchOne('SELECT id_succes FROM quiz_succes WHERE id_succes = 3');
    if ($achievement === null) {
        $db->execute(
            'INSERT INTO quiz_succes (id_succes, nom_succes, xp_succes, descri_succes) VALUES (3, ?, 0, ?)',
            'CI HTTP achievement ' . $suffix,
            'Disposable achievement for the first official quiz completion test.'
        );
        $insertedAchievementId = 3;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    check(is_string($passwordHash), 'Could not hash the fixture member password.');
    $db->execute(
        'INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, ip, date) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)',
        $loginName,
        $passwordHash,
        $loginName . '@example.invalid',
        'HTTP fixture',
        'Official quiz',
        'default',
        '',
        ''
    );
    $memberId = $db->lastInsertId();

    $addOfficialQuiz = static function (string $name, int $questionCount) use ($db, $memberId): int {
        $db->execute(
            'INSERT INTO quiz (id_image_quiz, nom_quiz, bienvenue, xp, id_user, id_quiz_user, src, quizComplet, nbrQuestion, portee, vue) VALUES (NULL, ?, ?, 10, ?, 0, NULL, 1, ?, 1, 0)',
            $name,
            'Disposable HTTP integration quiz.',
            $memberId,
            $questionCount
        );
        return $db->lastInsertId();
    };
    $addQuestion = static function (int $quizId, int $number, int $difficultyId, string $text, array $choices) use ($db, &$questionIds, &$answerIds): array {
        $db->execute(
            'INSERT INTO quiz_question (id_quiz, id_image_question, texte_bienvenue, id_difficulte, question_texte, numero_question, nbrReponse) VALUES (?, NULL, NULL, ?, ?, ?, ?)',
            $quizId,
            $difficultyId,
            $text,
            $number,
            count($choices)
        );
        $questionId = $db->lastInsertId();
        $questionIds[] = $questionId;
        $createdAnswers = [];
        foreach ($choices as $index => $choice) {
            $db->execute(
                'INSERT INTO quiz_reponse (id_question, id_image_reponse, choix_possible_quiz, reponseOK, texte_reponse_explicatif, num_reponse) VALUES (?, NULL, ?, ?, ?, ?)',
                $questionId,
                $choice['text'],
                $choice['correct'] ? '1' : '0',
                $choice['correct'] ? 'Correct fixture answer.' : 'Incorrect fixture answer.',
                $index + 1
            );
            $answerId = $db->lastInsertId();
            $answerIds[] = $answerId;
            $createdAnswers[] = $answerId;
        }
        return ['question' => $questionId, 'answers' => $createdAnswers];
    };

    $quizId = $addOfficialQuiz('CI official ' . $suffix, 2);
    $quizIds[] = $quizId;
    $first = $addQuestion($quizId, 1, $difficultyId, 'CI first official question ' . $suffix, [
        ['text' => 'First wrong option', 'correct' => false],
        ['text' => 'First correct option', 'correct' => true],
    ]);
    $second = $addQuestion($quizId, 2, $difficultyId, 'CI second official question ' . $suffix, [
        ['text' => 'Second wrong option', 'correct' => false],
        ['text' => 'Second correct option', 'correct' => true],
    ]);

    // A second disposable official quiz supplies genuine foreign question and answer IDs for mismatch checks.
    $foreignQuizId = $addOfficialQuiz('CI foreign ' . $suffix, 1);
    $quizIds[] = $foreignQuizId;
    $foreign = $addQuestion($foreignQuizId, 1, $difficultyId, 'CI foreign question ' . $suffix, [
        ['text' => 'Foreign correct option', 'correct' => true],
        ['text' => 'Foreign wrong option', 'correct' => false],
    ]);

    $guest = new OfficialQuizBrowser();
    $presentationPath = '/quiz-officiel?id=' . $quizId;
    $startPath = '/quiz/question?id_quiz=' . $quizId . '&numero_question=1';
    $presentation = html($guest->request($presentationPath, 200));
    check($presentation->evaluate('string(//main//h1)') === 'CI official ' . $suffix, 'The guest must see the official quiz presentation.');
    check(str_contains($presentation->evaluate('string(//main)'), '2 questions'), 'The presentation must identify the complete two-question fixture.');
    check($presentation->evaluate('string(//a[contains(@class,"official-start-button")]/@href)') === $startPath, 'The guest presentation must link to question one.');

    $firstPage = html($guest->request($startPath, 200));
    check($firstPage->evaluate('string(//main//h1)') === 'Question 1 / 2', 'Starting the quiz must render its first question.');
    check(str_contains($firstPage->evaluate('string(//main)'), (string) $difficulty['libelle']), 'The question must render its real seeded difficulty.');
    check($firstPage->query('//form[@action="/quiz/reponse"]//input[@name="id_reponse"]')->length === 2, 'The first question must render both answer choices.');
    $correctFirst = answerForm($firstPage, $first['answers'][1]);
    $wrongFirst = array_replace($correctFirst, ['id_reponse' => (string) $first['answers'][0]]);

    $guestSnapshot = static function () use ($db, $quizIds, $memberId): array {
        $quizRows = $db->fetchAll('SELECT id_quiz, vue FROM quiz WHERE id_quiz IN (' . idsClause($quizIds) . ') ORDER BY id_quiz', ...$quizIds);
        return [
            'quizzes' => $quizRows,
            'progress' => $db->fetchAll('SELECT id_prog, id_user, id_question FROM quiz_progression WHERE id_user = ? ORDER BY id_prog', $memberId),
            'statistics' => $db->fetchAll('SELECT id_user, id_quiz, `date`, note, favoris, quiz_utilisateur FROM quiz_statistique WHERE id_user = ? ORDER BY id_quiz', $memberId),
        ];
    };
    $beforeInvalidIds = $guestSnapshot();
    $guest->request('/quiz-officiel?id=999999999', 404);
    $guest->request('/quiz/question?id_quiz=999999999&numero_question=1', 404);
    check($guestSnapshot() === $beforeInvalidIds, 'Unknown official quiz IDs must not alter persisted data.');

    $guest->request('/quiz/reponse', 303, array_replace($correctFirst, ['_csrf' => 'invalid-csrf']), $startPath);
    $rejectedCsrfPage = html($guest->request($startPath, 200));
    check($rejectedCsrfPage->evaluate('string(//main//h1)') === 'Question 1 / 2', 'A rejected CSRF submission must not advance the guest quiz.');
    check($rejectedCsrfPage->evaluate('string(//main//*[@role="status"])') === 'La session du quiz a expiré. Recommencez la question.', 'The guest must receive the CSRF rejection message.');

    $wrongResult = html($guest->request('/quiz/reponse', 200, $wrongFirst));
    check($wrongResult->evaluate('string(//main//h1)') === 'Ce n’est pas la bonne réponse.', 'Choosing the wrong option must render a wrong-answer result.');
    check($wrongResult->query('//section[contains(@class,"answer-result--wrong")]//a[normalize-space(.)="Recommencer depuis le début"]')->length === 1, 'The wrong-answer result must offer a restart.');
    $afterWrong = html($guest->request($presentationPath, 200));
    check($afterWrong->evaluate('string(//a[contains(@class,"official-start-button")]/@href)') === $startPath, 'A wrong guest answer must clear session progress and restart at question one.');

    $guestFirst = html($guest->request($startPath, 200));
    $guestCorrectFirst = answerForm($guestFirst, $first['answers'][1]);
    $guestFirstResult = html($guest->request('/quiz/reponse', 200, $guestCorrectFirst));
    check($guestFirstResult->evaluate('string(//main//h1)') === 'Bonne réponse !', 'A correct first answer must be accepted.');
    $nextPath = '/quiz/question?id_quiz=' . $quizId . '&numero_question=2';
    check($guestFirstResult->evaluate('string(//div[contains(@class,"result-actions")]/a[1]/@href)') === $nextPath, 'A correct answer must advance to question two.');
    $guestSecond = html($guest->request($nextPath, 200));
    check($guestSecond->evaluate('string(//main//h1)') === 'Question 2 / 2', 'The guest must reach the second question.');
    $guestCorrectSecond = answerForm($guestSecond, $second['answers'][1]);
    $beforeMismatchedSubmissions = $guestSnapshot();
    foreach ([
        ['id_quiz' => (string) $foreignQuizId, 'id_question' => (string) $second['question'], 'id_reponse' => (string) $second['answers'][1]],
        ['id_quiz' => (string) $quizId, 'id_question' => (string) $second['question'], 'id_reponse' => (string) $foreign['answers'][0]],
        ['id_quiz' => (string) $quizId, 'id_question' => (string) $foreign['question'], 'id_reponse' => (string) $foreign['answers'][0]],
    ] as $mismatchedIds) {
        $guest->request('/quiz/reponse', 404, array_replace(['_csrf' => $guestCorrectSecond['_csrf']], $mismatchedIds));
        check($guestSnapshot() === $beforeMismatchedSubmissions, 'A foreign or mismatched quiz, question, or answer ID must not alter persisted data.');
    }
    $stillOnQuestionTwo = html($guest->request($nextPath, 200));
    check($stillOnQuestionTwo->evaluate('string(//main//h1)') === 'Question 2 / 2', 'Rejected mismatched IDs must not clear or advance guest session progression.');
    $guestFinal = html($guest->request('/quiz/reponse', 200, $guestCorrectSecond));
    check($guestFinal->evaluate('string(//main//h1)') === 'Bonne réponse !', 'The guest must complete the official quiz.');
    check(str_contains($guestFinal->evaluate('string(//main)'), 'Quiz terminé : 2 réponses justes sur 2.'), 'The guest completion result must report the full score.');

    $member = new OfficialQuizBrowser();
    login($member, $loginName, $password);
    $memberPresentation = html($member->request($presentationPath, 200));
    check($memberPresentation->evaluate('string(//main//h1)') === 'CI official ' . $suffix, 'The authenticated member must see the same official quiz.');
    $memberFirst = html($member->request($startPath, 200));
    check($memberFirst->evaluate('string(//main//h1)') === 'Question 1 / 2', 'The member must start at question one.');
    $progress = $db->fetchOne('SELECT p.id_question, q.numero_question FROM quiz_progression p INNER JOIN quiz_question q ON q.id_question = p.id_question WHERE p.id_user = ? AND q.id_quiz = ?', $memberId, $quizId);
    check($progress !== null && (int) $progress['id_question'] === $first['question'] && (int) $progress['numero_question'] === 1, 'Starting as a member must persist question-one progression.');

    $memberCorrectFirst = answerForm($memberFirst, $first['answers'][1]);
    $member->request('/quiz/reponse', 303, array_replace($memberCorrectFirst, ['_csrf' => 'invalid-csrf']), $startPath);
    $progressAfterCsrf = $db->fetchOne('SELECT p.id_question, q.numero_question FROM quiz_progression p INNER JOIN quiz_question q ON q.id_question = p.id_question WHERE p.id_user = ? AND q.id_quiz = ?', $memberId, $quizId);
    check($progressAfterCsrf !== null && (int) $progressAfterCsrf['id_question'] === $first['question'] && (int) $progressAfterCsrf['numero_question'] === 1, 'Rejected member CSRF must leave progression unchanged.');
    $memberRetry = html($member->request($startPath, 200));
    check($memberRetry->evaluate('string(//main//*[@role="status"])') === 'La session du quiz a expiré. Recommencez la question.', 'The member must receive the CSRF rejection message.');

    $memberFirstResult = html($member->request('/quiz/reponse', 200, $memberCorrectFirst));
    check($memberFirstResult->evaluate('string(//main//h1)') === 'Bonne réponse !', 'The member must be able to submit a valid first answer.');
    $progress = $db->fetchOne('SELECT p.id_question, q.numero_question FROM quiz_progression p INNER JOIN quiz_question q ON q.id_question = p.id_question WHERE p.id_user = ? AND q.id_quiz = ?', $memberId, $quizId);
    check($progress !== null && (int) $progress['id_question'] === $second['question'] && (int) $progress['numero_question'] === 2, 'A correct member answer must advance persisted progression to question two.');
    $resume = html($member->request($presentationPath, 200));
    check($resume->evaluate('string(//main//h1)') === 'CI official ' . $suffix, 'The member presentation must remain available while progression is saved.');

    $memberSecond = html($member->request($nextPath, 200));
    check($memberSecond->evaluate('string(//main//h1)') === 'Question 2 / 2', 'The member must reach question two.');
    $memberCorrectSecond = answerForm($memberSecond, $second['answers'][1]);
    $memberFinal = html($member->request('/quiz/reponse', 200, $memberCorrectSecond));
    check($memberFinal->evaluate('string(//main//h1)') === 'Bonne réponse !', 'The member must complete the official quiz.');
    check(str_contains($memberFinal->evaluate('string(//main)'), 'Quiz terminé : 2 réponses justes sur 2.'), 'The member completion result must report the full score.');

    $remainingProgress = $db->fetchOne('SELECT p.id_prog FROM quiz_progression p INNER JOIN quiz_question q ON q.id_question = p.id_question WHERE p.id_user = ? AND q.id_quiz = ? LIMIT 1', $memberId, $quizId);
    check($remainingProgress === null, 'Completing the quiz must clear the member progression row.');
    $statistic = $db->fetchOne('SELECT id_user, id_quiz, `date`, note, favoris, quiz_utilisateur FROM quiz_statistique WHERE id_user = ? AND id_quiz = ?', $memberId, $quizId);
    check($statistic !== null && (int) $statistic['note'] === 2 && (int) $statistic['quiz_utilisateur'] === 0
        && (int) $statistic['favoris'] === 0 && trim((string) $statistic['date']) !== '', 'Completion must save the official quiz score and result statistic.');
    $memberAchievement = $db->fetchOne('SELECT id_succes FROM quiz_membre_succes WHERE id_membre = ? AND id_succes = 3', $memberId);
    check($memberAchievement !== null, 'First official completion must verify and award achievement 3 when available.');

    echo "Official quiz HTTP integration checks passed.\n";
} catch (Throwable $exception) {
    $testFailure = $exception;
    throw $exception;
} finally {
    $cleanupErrors = [];
    $cleanup = static function (callable $operation) use (&$cleanupErrors): void {
        try {
            $operation();
        } catch (Throwable $exception) {
            $cleanupErrors[] = $exception;
        }
    };
    if ($memberId !== null) {
        $cleanup(static fn () => $db->execute('DELETE FROM quiz_membre_succes WHERE id_membre = ?', $memberId));
        $cleanup(static fn () => $db->execute('DELETE FROM quiz_statistique WHERE id_user = ?', $memberId));
        if ($questionIds !== []) {
            $cleanup(static fn () => $db->execute('DELETE FROM quiz_progression WHERE id_user = ? AND id_question IN (' . idsClause($questionIds) . ')', $memberId, ...$questionIds));
        }
        $cleanup(static fn () => $db->execute('DELETE FROM quiz_signalement WHERE id_user_origine = ? OR id_user_cible = ?', $memberId, $memberId));
        $cleanup(static fn () => $db->execute('DELETE FROM quiz_subscription_membre WHERE idUser = ? OR idUserAbonnement = ?', $memberId, $memberId));
    }
    if ($questionIds !== []) {
        $cleanup(static fn () => $db->execute('DELETE FROM quiz_reponse WHERE id_question IN (' . idsClause($questionIds) . ')', ...$questionIds));
    }
    if ($quizIds !== []) {
        $cleanup(static fn () => $db->execute('DELETE FROM quiz_like_quiz WHERE id_quiz IN (' . idsClause($quizIds) . ')', ...$quizIds));
        $cleanup(static fn () => $db->execute('DELETE FROM quiz_statistique WHERE id_quiz IN (' . idsClause($quizIds) . ')', ...$quizIds));
        $cleanup(static fn () => $db->execute('DELETE FROM quiz_question WHERE id_quiz IN (' . idsClause($quizIds) . ')', ...$quizIds));
        $cleanup(static fn () => $db->execute('DELETE FROM quiz WHERE id_quiz IN (' . idsClause($quizIds) . ')', ...$quizIds));
    }
    if ($memberId !== null) {
        $cleanup(static fn () => $db->execute('DELETE FROM quiz_membre WHERE id = ? AND login = ?', $memberId, $loginName));
    }
    if ($insertedAchievementId !== null) {
        $cleanup(static fn () => $db->execute('DELETE FROM quiz_succes WHERE id_succes = ? AND nom_succes = ?', $insertedAchievementId, 'CI HTTP achievement ' . $suffix));
    }
    if ($cleanupErrors !== []) {
        if ($testFailure !== null) {
            foreach ($cleanupErrors as $cleanupError) {
                error_log('Official quiz HTTP fixture cleanup failed after test failure: ' . $cleanupError->getMessage());
            }
        } else {
            throw $cleanupErrors[0];
        }
    }
}
