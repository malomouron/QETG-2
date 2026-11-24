<?php

declare(strict_types=1);

use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (getenv('QETG_ENV_CONFIG') !== '1' || getenv('DB_HOST') !== 'db'
    || getenv('DB_DATABASE') !== 'test' || getenv('DB_USERNAME') !== 'qetg_ci') {
    throw new RuntimeException('Run this fixture through the isolated CI Compose runner.');
}

function memberQuizCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class MemberQuizBrowser
{
    private array $cookies = [];

    /** @param array<string, mixed>|null $form
     *  @param array{field: string, path: string, filename: string, mime: string}|null $upload
     */
    public function request(string $path, int $status, ?array $form = null, ?string $location = null, ?array $upload = null): string
    {
        memberQuizCheck(str_starts_with($path, '/') && !str_starts_with($path, '//'), 'Expected an app-relative path.');
        memberQuizCheck($upload === null || $form !== null, 'A multipart upload requires a POST form.');
        $method = $form === null ? 'GET' : 'POST';
        $headers = ['Connection: close'];
        if ($this->cookies !== []) {
            $headers[] = 'Cookie: ' . implode('; ', $this->cookies);
        }
        $options = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10];
        if ($form !== null && $upload === null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = http_build_query($form);
        } elseif ($form !== null && $upload !== null) {
            $boundary = '----------------qetg' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
            $body = '';
            $fields = [];
            foreach ($form as $name => $value) {
                $this->appendFields($fields, (string) $name, $value);
            }
            foreach ($fields as [$name, $value]) {
                $body .= '--' . $boundary . "\r\n";
                $body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
                $body .= $value . "\r\n";
            }
            $fileContents = file_get_contents($upload['path']);
            memberQuizCheck($fileContents !== false, 'Could not read the generated PNG fixture.');
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . $upload['field'] . '"; filename="' . $upload['filename'] . '"' . "\r\n";
            $body .= 'Content-Type: ' . $upload['mime'] . "\r\n\r\n" . $fileContents . "\r\n";
            $body .= '--' . $boundary . "--\r\n";
            $options['content'] = $body;
        }
        $options['header'] = $headers;
        // Apache is reached from inside the isolated app container; host ports and redirects are not used.
        $stream = @fopen('http://127.0.0.1' . $path, 'r', false, stream_context_create(['http' => $options]));
        memberQuizCheck($stream !== false, "No HTTP response for $method $path.");
        $responseHeaders = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $body = stream_get_contents($stream);
        fclose($stream);
        memberQuizCheck($body !== false, "Could not read the HTTP response for $method $path.");

        $statusLine = $responseHeaders[0] ?? '(missing status)';
        memberQuizCheck(preg_match('~^HTTP/\S+ (\d{3})\b~', $statusLine, $statusMatch) === 1 && (int) $statusMatch[1] === $status,
            "$method $path: expected HTTP $status, got $statusLine. Response: " . substr(strip_tags((string) $body), 0, 400));
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
            memberQuizCheck($actualLocation === $location, "$method $path: expected Location $location, got " . ($actualLocation ?? '(missing)') . '.');
        }
        return $body;
    }

    /** @param list<array{0: string, 1: string}> $fields */
    private function appendFields(array &$fields, string $name, mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $nestedValue) {
                $this->appendFields($fields, $name . '[' . (string) $key . ']', $nestedValue);
            }
            return;
        }
        $fields[] = [$name, is_bool($value) ? ($value ? '1' : '0') : (string) $value];
    }
}

function memberQuizHtml(string $body): DOMXPath
{
    $document = new DOMDocument();
    memberQuizCheck($document->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING), 'Expected an HTML document.');
    return new DOMXPath($document);
}

function memberQuizFormToken(DOMXPath $page, string $formSelector): string
{
    $token = $page->evaluate("string($formSelector/input[@type='hidden' and @name='_csrf']/@value)");
    memberQuizCheck(is_string($token) && $token !== '', "Missing CSRF token for $formSelector.");
    return $token;
}

function memberQuizLogin(MemberQuizBrowser $browser, string $identity, string $password): void
{
    $page = memberQuizHtml($browser->request('/connexion', 200));
    $token = memberQuizFormToken($page, "//form[@action='/connexion' and @method='post']");
    $browser->request('/connexion', 303, ['_csrf' => $token, 'identity' => $identity, 'password' => $password], '/accueil');
}

/** @return array{quiz: array<string, mixed>|null, questions: list<array<string, mixed>>, answers: list<array<string, mixed>>} */
function memberQuizSnapshot(MySqlConnection $db, int $quizId): array
{
    return [
        'quiz' => $db->fetchOne(
            'SELECT id_quiz, id_image_quiz, nom_quiz, bienvenue, xp, id_user, id_quiz_user, src, quizComplet, nbrQuestion, portee, vue FROM quiz WHERE id_quiz = ?',
            $quizId
        ),
        'questions' => $db->fetchAll(
            'SELECT id_question, id_quiz, id_difficulte, question_texte, numero_question, nbrReponse FROM quiz_question WHERE id_quiz = ? ORDER BY numero_question',
            $quizId
        ),
        'answers' => $db->fetchAll(
            'SELECT r.id_reponse, r.id_question, r.choix_possible_quiz, r.reponseOK, r.texte_reponse_explicatif, r.num_reponse FROM quiz_reponse r INNER JOIN quiz_question q ON q.id_question = r.id_question WHERE q.id_quiz = ? ORDER BY q.numero_question, r.num_reponse, r.id_reponse',
            $quizId
        ),
    ];
}

$db = new MySqlConnection('db', 'qetg_ci', (string) getenv('DB_PASSWORD'), 'test');
$suffix = bin2hex(random_bytes(6));
$password = 'CI-only!' . $suffix;
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$memberLogin = 'http-quiz-owner-' . $suffix;
$playerLogin = 'http-quiz-player-' . $suffix;
$memberId = null;
$playerId = null;
$quizId = null;
$title = 'CI member quiz ' . $suffix;
$uploadTempPath = null;
$relativeUploadPath = null;

try {
    $addMember = static function (string $login) use ($db, $passwordHash): int {
        $db->execute(
            'INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, ip, date) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)',
            $login,
            $passwordHash,
            $login . '@example.invalid',
            'HTTP',
            'CI',
            'default',
            '',
            ''
        );
        return $db->lastInsertId();
    };
    $memberId = $addMember($memberLogin);
    $playerId = $addMember($playerLogin);
    memberQuizCheck(password_verify($password, (string) $db->fetchOne('SELECT password_hash FROM quiz_membre WHERE id = ?', $memberId)['password_hash']), 'The fixture account must use a real password hash.');

    $difficultyRow = $db->fetchOne('SELECT id_difficulte FROM quiz_difficulte ORDER BY id_difficulte LIMIT 1');
    $difficultyId = (int) ($difficultyRow['id_difficulte'] ?? 0);
    memberQuizCheck($difficultyId > 0, 'The isolated database must contain its existing difficulty fixture.');

    $owner = new MemberQuizBrowser();
    memberQuizLogin($owner, $memberLogin, $password);
    $editor = memberQuizHtml($owner->request('/quiz-membres/editeur', 200));
    $difficultyData = json_decode($editor->evaluate('string(//script[@id="difficulty-data"])'), true, 512, JSON_THROW_ON_ERROR);
    memberQuizCheck(in_array($difficultyId, array_column($difficultyData, 'id'), true), 'The editor must expose the existing database difficulty fixture.');
    $editorCsrf = memberQuizFormToken($editor, "//form[@action='/quiz-membres/enregistrer' and @method='post']");

    $questionData = [
        ['text' => 'Which color is the test sky?', 'difficulty' => (string) $difficultyId, 'answers' => ['Blue', 'Green'], 'correct' => '0', 'explanation' => 'The fixture answer is blue.'],
        ['text' => 'How many test moons?', 'difficulty' => (string) $difficultyId, 'answers' => ['One', 'Two', 'Three'], 'correct' => '1', 'explanation' => 'The fixture answer is two.'],
    ];
    $createForm = [
        '_csrf' => $editorCsrf,
        'title' => $title,
        'description' => 'Original HTTP fixture description.',
        'visibility' => 'public',
        'questions' => $questionData,
    ];

    $rejectedTitle = 'Rejected member quiz ' . $suffix;
    $owner->request('/quiz-membres/enregistrer', 303, array_replace($createForm, ['_csrf' => 'invalid-token', 'title' => $rejectedTitle]), '/quiz-membres/editeur');
    memberQuizCheck((int) ($db->fetchOne('SELECT COUNT(*) AS total FROM quiz WHERE id_user = ? AND nom_quiz = ?', $memberId, $rejectedTitle)['total'] ?? 0) === 0,
        'An invalid CSRF token must not create a quiz.');

    $draftTitle = 'Incomplete member quiz ' . $suffix;
    $owner->request('/quiz-membres/enregistrer', 303, array_replace($createForm, ['title' => $draftTitle, 'questions' => []]), '/quiz-membres/editeur');
    memberQuizCheck((int) ($db->fetchOne('SELECT COUNT(*) AS total FROM quiz WHERE id_user = ? AND nom_quiz = ?', $memberId, $draftTitle)['total'] ?? 0) === 0,
        'The editor does not persist incomplete drafts without at least one valid question.');

    $pngDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/rKQAAAAASUVORK5CYII=';
    $pngBytes = base64_decode(substr($pngDataUri, strpos($pngDataUri, ',') + 1), true);
    memberQuizCheck($pngBytes !== false, 'Could not decode the generated PNG data URI.');
    $uploadTempPath = tempnam(sys_get_temp_dir(), 'qetg-member-quiz-');
    memberQuizCheck($uploadTempPath !== false && file_put_contents($uploadTempPath, $pngBytes) === strlen($pngBytes), 'Could not write the generated PNG fixture.');
    $imageInfo = getimagesize($uploadTempPath);
    memberQuizCheck($imageInfo !== false && $imageInfo['mime'] === 'image/png', 'The generated cover must be a valid PNG image.');

    $owner->request('/quiz-membres/enregistrer', 303, $createForm, '/quiz-membres?tab=mine', [
        'field' => 'cover', 'path' => $uploadTempPath, 'filename' => 'member-quiz-cover.png', 'mime' => 'image/png',
    ]);
    $quizRow = $db->fetchOne('SELECT id_quiz, src FROM quiz WHERE id_user = ? AND id_quiz_user = 1 AND nom_quiz = ?', $memberId, $title);
    memberQuizCheck($quizRow !== null, 'The complete multipart member quiz must be created over HTTP.');
    $quizId = (int) $quizRow['id_quiz'];
    $relativeUploadPath = (string) $quizRow['src'];
    memberQuizCheck(preg_match('~^uploads/quizzes/[a-f0-9]{32}\\.png$~D', $relativeUploadPath) === 1, 'The uploaded cover must be stored under its generated quiz-upload name.');
    $absoluteUploadPath = dirname(__DIR__, 2) . '/' . $relativeUploadPath;
    memberQuizCheck(is_file($absoluteUploadPath), 'The uploaded PNG must exist at the persisted source path.');

    $initialState = memberQuizSnapshot($db, $quizId);
    memberQuizCheck($initialState['quiz'] !== null
        && (int) $initialState['quiz']['id_user'] === $memberId
        && (int) $initialState['quiz']['id_quiz_user'] === 1
        && (int) $initialState['quiz']['quizComplet'] === 1
        && (int) $initialState['quiz']['nbrQuestion'] === 2
        && (int) $initialState['quiz']['portee'] === 1
        && $initialState['quiz']['bienvenue'] === 'Original HTTP fixture description.', 'The quiz owner, completion, public visibility, question count and description must persist.');
    memberQuizCheck(count($initialState['questions']) === 2 && count($initialState['answers']) === 5, 'The created quiz must persist both questions and all five answers.');
    memberQuizCheck((int) $initialState['questions'][0]['id_difficulte'] === $difficultyId
        && $initialState['questions'][0]['question_texte'] === $questionData[0]['text']
        && $initialState['answers'][0]['choix_possible_quiz'] === 'Blue'
        && (string) $initialState['answers'][0]['reponseOK'] === '1'
        && $initialState['answers'][0]['texte_reponse_explicatif'] === $questionData[0]['explanation'], 'Question difficulty, text, answer correctness and explanation must persist from the form.');

    $mine = memberQuizHtml($owner->request('/quiz-membres?tab=mine', 200));
    memberQuizCheck($mine->query("//a[@href='/quiz-membres/jouer?id=$quizId' and normalize-space(.)='$title']")->length === 1,
        'The owner catalog must link to the newly created quiz.');
    $guest = new MemberQuizBrowser();
    $catalog = memberQuizHtml($guest->request('/quiz-membres', 200));
    memberQuizCheck($catalog->query("//a[@href='/quiz-membres/jouer?id=$quizId' and normalize-space(.)='$title']")->length === 1,
        'A public member quiz must appear in the public community catalog.');
    $profile = memberQuizHtml($guest->request('/profil-public?id=' . $memberId, 200));
    memberQuizCheck($profile->query("//section[contains(@class,'public-profile-section')][.//h2[normalize-space(.)='Quiz publiés']]//a[@href='/quiz-membres/jouer?id=$quizId' and normalize-space(.)='$title']")->length === 1,
        'A public member quiz must appear in its creator profile.');

    $player = new MemberQuizBrowser();
    memberQuizLogin($player, $playerLogin, $password);
    $play = memberQuizHtml($player->request('/quiz-membres/jouer?id=' . $quizId, 200));
    $playToken = memberQuizFormToken($play, "//form[@action='/quiz-membres/reponse' and @method='post']");
    $questionIds = array_map(static fn (array $row): int => (int) $row['id_question'], $initialState['questions']);
    $answerRows = $db->fetchAll(
        'SELECT r.id_reponse, r.id_question FROM quiz_reponse r INNER JOIN quiz_question q ON q.id_question = r.id_question WHERE q.id_quiz = ? AND r.reponseOK = ?',
        $quizId,
        '1'
    );
    $correctAnswers = [];
    foreach ($answerRows as $answerRow) {
        $correctAnswers[(int) $answerRow['id_question']] = (int) $answerRow['id_reponse'];
    }
    memberQuizCheck(count($correctAnswers) === 2, 'The created quiz must have one correct answer per question.');
    $result = $player->request('/quiz-membres/reponse', 200, [
        '_csrf' => $playToken,
        'quiz_id' => (string) $quizId,
        'answers' => [$questionIds[0] => (string) $correctAnswers[$questionIds[0]], $questionIds[1] => (string) $correctAnswers[$questionIds[1]]],
    ]);
    memberQuizCheck(str_contains($result, 'Votre score : 2/2') && str_contains($result, 'The fixture answer is blue.'), 'The HTTP play submission must render its score and correct-answer explanation.');
    $statistic = $db->fetchOne('SELECT note, quiz_utilisateur FROM quiz_statistique WHERE id_user = ? AND id_quiz = ?', $playerId, $quizId);
    memberQuizCheck($statistic !== null && (int) $statistic['note'] === 2 && (int) $statistic['quiz_utilisateur'] === 1,
        'A completed member quiz must record its score and member-quiz statistic.');

    $ownerEditor = memberQuizHtml($owner->request('/quiz-membres/editeur?id=' . $quizId, 200));
    $ownerCsrf = memberQuizFormToken($ownerEditor, "//form[@action='/quiz-membres/enregistrer' and @method='post']");
    $editQuestionData = [
        ['text' => 'Which color is the updated test sky?', 'difficulty' => (string) $difficultyId, 'answers' => ['Azure', 'Green'], 'correct' => '0', 'explanation' => 'The updated fixture answer is azure.'],
        ['text' => 'How many test moons?', 'difficulty' => (string) $difficultyId, 'answers' => ['One', 'Two', 'Three'], 'correct' => '1', 'explanation' => 'The fixture answer is two.'],
    ];
    $editForm = [
        '_csrf' => $ownerCsrf,
        'quiz_id' => (string) $quizId,
        'title' => $title,
        'description' => 'Original HTTP fixture description.',
        'visibility' => 'public',
        'questions' => $editQuestionData,
    ];
    $owner->request('/quiz-membres/enregistrer', 303, $editForm, '/quiz-membres?tab=mine');
    $editedState = memberQuizSnapshot($db, $quizId);
    memberQuizCheck($editedState['quiz'] !== null
        && $editedState['quiz']['src'] === $initialState['quiz']['src']
        && $editedState['quiz']['bienvenue'] === $initialState['quiz']['bienvenue']
        && $editedState['questions'][0]['question_texte'] === $editQuestionData[0]['text']
        && $editedState['answers'][0]['choix_possible_quiz'] === 'Azure'
        && $editedState['answers'][0]['texte_reponse_explicatif'] === $editQuestionData[0]['explanation'],
        'Editing without a new cover must preserve its source and description while replacing question answers.');

    $beforeInvalidEdits = memberQuizSnapshot($db, $quizId);
    $invalidDifficulty = $editForm;
    $invalidDifficulty['questions'][0]['difficulty'] = '999999';
    $owner->request('/quiz-membres/enregistrer', 303, $invalidDifficulty, '/quiz-membres/editeur?id=' . $quizId);
    memberQuizCheck(memberQuizSnapshot($db, $quizId) === $beforeInvalidEdits, 'An unknown difficulty must be rejected without mutating the quiz, questions or answers.');
    $invalidQuestion = $editForm;
    $invalidQuestion['questions'][0]['answers'] = ['Only one answer'];
    $owner->request('/quiz-membres/enregistrer', 303, $invalidQuestion, '/quiz-membres/editeur?id=' . $quizId);
    memberQuizCheck(memberQuizSnapshot($db, $quizId) === $beforeInvalidEdits, 'An invalid question with fewer than two answers must be rejected without mutation.');

    $playerEditor = memberQuizHtml($player->request('/quiz-membres/editeur', 200));
    $playerCsrf = memberQuizFormToken($playerEditor, "//form[@action='/quiz-membres/enregistrer' and @method='post']");
    $player->request('/quiz-membres/editeur?id=' . $quizId, 404);
    $foreignEdit = $editForm;
    $foreignEdit['_csrf'] = $playerCsrf;
    $foreignEdit['title'] = 'Unauthorized update ' . $suffix;
    $player->request('/quiz-membres/enregistrer', 303, $foreignEdit, '/quiz-membres/editeur?id=' . $quizId);
    memberQuizCheck(memberQuizSnapshot($db, $quizId) === $beforeInvalidEdits, 'A different member must not modify the quiz or its question data.');

    $ownerEditor = memberQuizHtml($owner->request('/quiz-membres/editeur?id=' . $quizId, 200));
    $ownerCsrf = memberQuizFormToken($ownerEditor, "//form[@action='/quiz-membres/enregistrer' and @method='post']");
    $privateForm = $editForm;
    $privateForm['_csrf'] = $ownerCsrf;
    $privateForm['visibility'] = 'private';
    $owner->request('/quiz-membres/enregistrer', 303, $privateForm, '/quiz-membres?tab=mine');
    $privateState = memberQuizSnapshot($db, $quizId);
    memberQuizCheck($privateState['quiz'] !== null && (int) $privateState['quiz']['portee'] === 0, 'The visibility edit must persist a private quiz.');
    $publicCatalog = memberQuizHtml($guest->request('/quiz-membres', 200));
    memberQuizCheck($publicCatalog->query("//a[@href='/quiz-membres/jouer?id=$quizId' and normalize-space(.)='$title']")->length === 0,
        'A private member quiz must be excluded from the public catalog.');
    $privateById = memberQuizHtml($guest->request('/quiz-membres?tab=catalog&s=' . $quizId . '&rech_by_id=on', 200));
    memberQuizCheck($privateById->query("//a[@href='/quiz-membres/jouer?id=$quizId' and normalize-space(.)='$title']")->length === 1,
        'A completed private quiz must remain discoverable through the explicit identifier search.');
    $privateProfile = memberQuizHtml($guest->request('/profil-public?id=' . $memberId, 200));
    memberQuizCheck($privateProfile->query("//section[contains(@class,'public-profile-section')][.//h2[normalize-space(.)='Quiz publiés']]//a[@href='/quiz-membres/jouer?id=$quizId']")->length === 0,
        'A private member quiz must not appear among the creator profile public creations.');
    $guest->request('/quiz-membres/jouer?id=' . $quizId, 200);

    echo "Member quiz HTTP integration checks passed.\n";
} finally {
    if ($uploadTempPath !== null && is_file($uploadTempPath)) {
        @unlink($uploadTempPath);
    }
    if ($db instanceof MySqlConnection) {
        $ownedQuizRows = $memberId === null ? [] : $db->fetchAll(
            'SELECT id_quiz, src FROM quiz WHERE id_user = ? AND id_quiz_user = 1 AND nom_quiz = ?',
            $memberId,
            $title
        );
        foreach ($ownedQuizRows as $ownedQuizRow) {
            $ownedQuizId = (int) $ownedQuizRow['id_quiz'];
            $db->execute('DELETE FROM quiz_statistique WHERE id_quiz = ?', $ownedQuizId);
            $db->execute('DELETE FROM quiz WHERE id_quiz = ? AND id_user = ? AND id_quiz_user = 1', $ownedQuizId, $memberId);
            $source = (string) ($ownedQuizRow['src'] ?? '');
            if (preg_match('~^uploads/quizzes/([a-f0-9]{32}\\.png)$~D', $source, $uploadMatch) === 1) {
                $ownedUpload = dirname(__DIR__, 2) . '/uploads/quizzes/' . $uploadMatch[1];
                if (is_file($ownedUpload)) {
                    @unlink($ownedUpload);
                }
            }
        }
        if ($playerId !== null) {
            $db->execute('DELETE FROM quiz_statistique WHERE id_user = ?', $playerId);
            $db->execute('DELETE FROM quiz_membre WHERE id = ?', $playerId);
        }
        if ($memberId !== null) {
            $db->execute('DELETE FROM quiz_statistique WHERE id_user = ?', $memberId);
            $db->execute('DELETE FROM quiz_membre WHERE id = ?', $memberId);
        }
    }
}
