<?php

declare(strict_types=1);

use QETG\Infrastructure\Persistence\MySQL\MySqlAdministrationRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;
use QETG\Infrastructure\Persistence\MySQL\MySqlMemberRepository;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (getenv('DB_HOST') !== 'db' || getenv('DB_DATABASE') !== 'test' || getenv('DB_USERNAME') !== 'qetg_ci') {
    throw new RuntimeException('Run this fixture through the isolated CI Compose runner.');
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectRefusal(callable $action, string $messagePart): void
{
    try {
        $action();
    } catch (DomainException $exception) {
        check(str_contains($exception->getMessage(), $messagePart), 'Unexpected refusal: ' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('Expected refusal mentioning ' . $messagePart);
}

$db = new MySqlConnection('db', 'qetg_ci', (string) getenv('DB_PASSWORD'), 'test');
$admin = new MySqlAdministrationRepository($db);
$members = new MySqlMemberRepository($db);
$baseline = $admin->dashboard(1, 1)['stats'];

$addMember = static function (string $login, int $role = 0, string $avatar = 'default') use ($db): int {
    $db->execute(
        'INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, ip, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        $login, 'hash', $login . '@example.invalid', 'Test', 'CI', $role, $avatar, '', ''
    );
    return $db->lastInsertId();
};
$addReport = static fn (int $from, int $to): int => $db->execute(
    'INSERT INTO quiz_signalement (id_user_origine, id_user_cible) VALUES (?, ?)', $from, $to
);
$actor = $addMember('admin-actor', 1);
$otherAdmin = $addMember('admin-protected', 1);
$reporter = $addMember('admin-reporter');
$owner = $addMember('admin-official-owner', 1);
$unreported = $addMember('admin-unreported');
$difficulty = (int) $db->fetchOne('SELECT MIN(id_difficulte) AS id FROM quiz_difficulte')['id'];
$question = ['text' => ' Question ? ', 'difficulty' => $difficulty, 'answers' => [' Non ', ' Oui '], 'correct' => 1, 'explanation' => ' Explication '];

$official = $admin->publishQuiz($actor, ' Quiz officiel ', ' Description ', 12, [$question, $question]);
$quiz = $db->fetchOne('SELECT * FROM quiz WHERE id_quiz = ?', $official);
check($quiz['nom_quiz'] === 'Quiz officiel' && $quiz['bienvenue'] === 'Description' && (int) $quiz['xp'] === 12, 'Quiz metadata must be persisted.');
check((int) $quiz['id_user'] === $actor && (int) $quiz['id_quiz_user'] === 0 && (int) $quiz['quizComplet'] === 1 && (int) $quiz['portee'] === 1, 'The quiz must be playable and officially owned by its author.');
check($quiz['src'] === null && $quiz['id_image_quiz'] === null && (int) $quiz['nbrQuestion'] === 2, 'Official publication does not require a cover.');
$savedQuestions = $db->fetchAll('SELECT * FROM quiz_question WHERE id_quiz = ? ORDER BY numero_question', $official);
check(array_map('intval', array_column($savedQuestions, 'numero_question')) === [1, 2], 'Question numbering must be consecutive.');
$questionId = (int) $savedQuestions[0]['id_question'];
check($savedQuestions[0]['question_texte'] === 'Question ?' && (int) $savedQuestions[0]['id_difficulte'] === $difficulty, 'Question text and difficulty must be persisted.');
$answers = $db->fetchAll('SELECT * FROM quiz_reponse WHERE id_question = ? ORDER BY num_reponse', $questionId);
check(array_column($answers, 'choix_possible_quiz') === ['Non', 'Oui'] && array_map('intval', array_column($answers, 'reponseOK')) === [0, 1], 'Answers must preserve their order and the correct choice.');
check(array_column($answers, 'texte_reponse_explicatif') === [null, 'Explication'], 'Only the correct answer stores the explanation.');

expectRefusal(fn () => $admin->publishQuiz($reporter, 'Denied', '', 0, [$question]), 'administrateurs');
expectRefusal(fn () => $admin->publishQuiz(0, 'Unknown actor', '', 0, [$question]), 'administrateurs');
expectRefusal(fn () => $admin->publishQuiz($actor, 'Bad XP', '', -1, [$question]), 'XP');
expectRefusal(fn () => $admin->publishQuiz($actor, 'Bad answer', '', 0, [array_replace($question, ['correct' => 9])]), 'bonne réponse');
expectRefusal(fn () => $admin->publishQuiz($actor, 'Bad difficulty', '', 0, [array_replace($question, ['difficulty' => 0])]), 'difficulté');
$before = $admin->dashboard(1, 1)['stats']['official'];
$questionCount = (int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_question')['n'];
$answerCount = (int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_reponse')['n'];
// The second question reaches an utf8mb3 answer column after earlier inserts have succeeded.
expectRefusal(fn () => $admin->publishQuiz($actor, 'Rollback', '', 0, [$question, array_replace($question, ['answers' => ['a', "\u{1F600}"]])]), 'caractères');
check($admin->dashboard(1, 1)['stats']['official'] === $before
    && (int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_question')['n'] === $questionCount
    && (int) $db->fetchOne('SELECT COUNT(*) AS n FROM quiz_reponse')['n'] === $answerCount, 'A failed insert must roll back quiz, questions, and answers.');

$protectedQuiz = $admin->publishQuiz($owner, 'Official to preserve', '', 0, [$question]);
$db->execute('UPDATE quiz_membre SET admin = 0 WHERE id = ?', $owner);
expectRefusal(fn () => $admin->deleteQuiz($owner, $official), 'administrateurs');
expectRefusal(fn () => $admin->deleteReportedMember($owner, $reporter), 'administrateurs');
$addReport($reporter, $owner);
$addReport($reporter, $otherAdmin);
$addReport($reporter, $actor);
expectRefusal(fn () => $admin->deleteReportedMember($actor, $owner), 'quiz officiels');
expectRefusal(fn () => $members->delete($owner), 'quiz officiels');
expectRefusal(fn () => $admin->deleteReportedMember($actor, $otherAdmin), 'compte administrateur');
expectRefusal(fn () => $admin->deleteReportedMember($actor, $actor), 'compte administrateur');
$addReport($reporter, $unreported);
$db->execute('DELETE FROM quiz_signalement WHERE id_user_cible = ?', $unreported);
expectRefusal(fn () => $admin->deleteReportedMember($actor, $unreported), 'signalement');
check($members->findById($unreported) !== null && $members->findById($otherAdmin) !== null && $members->findById($owner) !== null, 'Refusals must leave accounts intact.');
$members->delete($unreported);
check($members->findById($unreported) === null, 'Standalone member deletion must commit without throwing.');

$avatar = tempnam(sys_get_temp_dir(), 'qetg-avatar-');
$cover = tempnam(sys_get_temp_dir(), 'qetg-cover-');
check(is_string($avatar) && is_string($cover), 'Could not create fixture files.');
try {
    $target = $addMember('admin-target', 0, $avatar);
    $addReport($reporter, $target);
    $addReport($actor, $target);
    $addReport($target, $reporter);
    $db->execute('INSERT INTO quiz (nom_quiz, bienvenue, id_user, id_quiz_user, src, nbrQuestion, portee) VALUES (?, ?, ?, 1, ?, 1, 0)', 'Community draft', '', $target, $cover);
    $community = $db->lastInsertId();
    expectRefusal(fn () => $admin->deleteQuiz($actor, $community), 'quiz officiel');
    $db->execute('INSERT INTO quiz_question (id_quiz, id_difficulte, question_texte, numero_question) VALUES (?, ?, ?, 1)', $community, $difficulty, 'Community question');
    $communityQuestion = $db->lastInsertId();
    $db->execute('INSERT INTO quiz_reponse (id_question, choix_possible_quiz, reponseOK) VALUES (?, ?, ?)', $communityQuestion, 'Answer', '1');
    $db->execute('INSERT INTO quiz_statistique (id_user, id_quiz, date) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?)', $target, $protectedQuiz, '', $reporter, $community, '', $reporter, $official, '');
    $db->execute('INSERT INTO quiz_progression (id_user, id_question) VALUES (?, ?), (?, ?)', $reporter, $questionId, $target, $questionId);
    $db->execute('INSERT INTO quiz_like_quiz (id_quiz, ip) VALUES (?, ?)', $official, '');
    $db->execute('INSERT INTO quiz_subscription_membre (idUser, idUserAbonnement) VALUES (?, ?), (?, ?)', $target, $reporter, $reporter, $target);

    $extraQuizzes = [];
    $extraTargets = [];
    for ($i = 1; $i <= 21; ++$i) {
        $extraQuizzes[] = $admin->publishQuiz($actor, 'Page quiz ' . $i, '', 0, [$question]);
        $extraTargets[] = $addMember('admin-page-target-' . $i);
        $addReport($reporter, $extraTargets[$i - 1]);
    }

    $page1 = $admin->dashboard(1, 1);
    $page2 = $admin->dashboard(2, 2);
    check($page1['stats'] === [
        'members' => $baseline['members'] + 26, 'official' => $baseline['official'] + 23,
        'community' => $baseline['community'] + 1, 'reports' => $baseline['reports'] + 27,
        'completions' => $baseline['completions'] + 3,
    ], 'Dashboard totals must include private community quizzes and all recorded reports/results.');
    check(count($page1['quizzes']) === 20 && count($page1['reports']) === 20 && $page1['reportTargets'] === 26, 'Both lists are independently paginated; report targets are distinct.');
    check(array_column($page1['quizzes'], 'id') === array_reverse(array_slice($extraQuizzes, 1)), 'Quiz ordering must be newest first.');
    check($page2['quizzes'][0] === ['id' => $extraQuizzes[0], 'title' => 'Page quiz 1', 'questions' => 1, 'public' => true], 'The second quiz page must expose the expected row shape.');
    check($page1['reports'][0] === ['target_id' => $target, 'target_login' => 'admin-target', 'target_admin' => false, 'report_count' => 2, 'reporters' => 'admin-actor, admin-reporter'], 'Reports must be grouped and sorted by count with stable reporter names.');
    check($page1['reports'][1]['target_id'] === $actor && $page1['reports'][1]['target_admin'], 'Ties must sort by target ID and preserve administrator status.');
    check(array_column($page2['reports'], 'target_id') === array_slice($extraTargets, -6), 'The second report page must continue the deterministic order.');
    $mixed = $admin->dashboard(2, 1);
    check($mixed['quizzes'] === $page2['quizzes'] && $mixed['reports'] === $page1['reports'], 'Changing the quiz page must not change the report page.');
    $far = $admin->dashboard(PHP_INT_MAX, PHP_INT_MAX);
    check($far['quizzes'] === [] && $far['reports'] === [], 'Out-of-range pages must be empty without offset overflow.');

    $admin->deleteReportedMember($actor, $target);
    check($members->findById($target) === null, 'Reported member must be deleted.');
    check($db->fetchOne('SELECT id_user FROM quiz_statistique WHERE id_user = ? OR id_quiz = ?', $target, $community) === null, 'Account and community quiz results must be deleted.');
    check($db->fetchOne('SELECT idAbonnement FROM quiz_subscription_membre WHERE idUser = ? OR idUserAbonnement = ?', $target, $target) === null, 'Subscriptions in both directions must be deleted.');
    check($db->fetchOne('SELECT id_signalement FROM quiz_signalement WHERE id_user_cible = ? OR id_user_origine = ?', $target, $target) === null, 'Reports in both directions must cascade.');
    check($db->fetchOne('SELECT id_quiz FROM quiz WHERE id_quiz = ?', $community) === null
        && $db->fetchOne('SELECT id_question FROM quiz_question WHERE id_question = ?', $communityQuestion) === null
        && $db->fetchOne('SELECT id_reponse FROM quiz_reponse WHERE id_question = ?', $communityQuestion) === null, 'Community quizzes and their content must cascade.');
    check($db->fetchOne('SELECT id_quiz FROM quiz WHERE id_quiz = ?', $protectedQuiz) !== null && is_file($avatar) && is_file($cover), 'Unrelated official quizzes and filesystem uploads must remain.');

    $admin->deleteQuiz($actor, $official);
    expectRefusal(fn () => $admin->deleteQuiz($actor, $official), 'quiz officiel');
    check($db->fetchOne('SELECT id_quiz FROM quiz WHERE id_quiz = ?', $official) === null
        && $db->fetchOne('SELECT id_question FROM quiz_question WHERE id_quiz = ?', $official) === null
        && $db->fetchOne('SELECT id_reponse FROM quiz_reponse WHERE id_question = ?', $questionId) === null
        && $db->fetchOne('SELECT id_prog FROM quiz_progression WHERE id_question = ?', $questionId) === null
        && $db->fetchOne('SELECT id_like FROM quiz_like_quiz WHERE id_quiz = ?', $official) === null
        && $db->fetchOne('SELECT id_user FROM quiz_statistique WHERE id_quiz = ?', $official) === null, 'Official deletion must cascade through quiz content, progress, reactions and results.');
} finally {
    unlink($avatar);
    unlink($cover);
}

echo "Administration MySQL integration checks passed.\n";