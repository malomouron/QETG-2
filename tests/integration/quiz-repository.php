<?php

declare(strict_types=1);

use QETG\Domain\Entity\Quiz;
use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizRepository;

if (PHP_SAPI !== 'cli'
    || getenv('QETG_ENV_CONFIG') !== '1'
    || getenv('DB_HOST') !== 'db'
    || getenv('DB_DATABASE') !== 'test'
    || getenv('DB_USERNAME') !== 'qetg_ci'
    || getenv('DB_PASSWORD') !== 'ci-app-only'
) {
    throw new RuntimeException('Run this fixture only through the isolated tests Compose environment.');
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function assertQuizRepositoryIntegration(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertQuizRepositorySame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

/** @param list<Quiz> $quizzes
 *  @return list<int>
 */
function quizRepositoryIds(array $quizzes): array
{
    return array_map(static fn (Quiz $quiz): int => (int) $quiz->id, $quizzes);
}

function quizRepositoryFixtureId(?int $id, string $description): int
{
    assertQuizRepositoryIntegration($id !== null && $id > 0, $description . ' must return an inserted identifier.');
    return $id;
}

$database = new MySqlConnection('db', 'qetg_ci', 'ci-app-only', 'test');
$quizzes = new MySqlQuizRepository($database);
$suffix = bin2hex(random_bytes(6));
$database->beginTransaction();

try {
    $database->execute(
        'INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, newletter, ip, date) VALUES (?, ?, ?, ?, ?, 0, ?, 0, ?, ?)',
        'quiz-repo-' . $suffix . '-a',
        'fixture-hash-' . $suffix,
        'quiz-repo-' . $suffix . '-a@example.invalid',
        'Quiz repository',
        'Fixture A',
        'default',
        '192.0.2.71',
        '2026-11-24'
    );
    $memberA = $database->lastInsertId();
    $database->execute(
        'INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, newletter, ip, date) VALUES (?, ?, ?, ?, ?, 0, ?, 0, ?, ?)',
        'quiz-repo-' . $suffix . '-b',
        'fixture-hash-' . $suffix,
        'quiz-repo-' . $suffix . '-b@example.invalid',
        'Quiz repository',
        'Fixture B',
        'default',
        '192.0.2.72',
        '2026-11-24'
    );
    $memberB = $database->lastInsertId();

    $publicCountBefore = $quizzes->countPublic();
    $officialCountBefore = $quizzes->countOfficial();
    $saveFixture = static function (
        string $name,
        int $creatorId,
        bool $memberCreated,
        bool $complete,
        bool $public,
        int $views = 0
    ) use ($quizzes): int {
        $saved = $quizzes->save(new Quiz(
            null,
            $name,
            'Fixture welcome text',
            7,
            $creatorId,
            $memberCreated,
            null,
            'fixture-' . $name . '.png',
            $complete,
            3,
            $public,
            $views
        ));
        return quizRepositoryFixtureId($saved->id, 'Quiz insert');
    };

    $alphaId = $saveFixture('Fixture ' . $suffix . ' needle alpha', $memberA, true, true, true, 4);
    $betaId = $saveFixture('Fixture ' . $suffix . ' needle beta', $memberA, true, true, true);
    $gammaId = $saveFixture('Fixture ' . $suffix . ' needle gamma', $memberA, true, true, true);
    $quotedId = $saveFixture("Fixture O'Reilly needle 100% _", $memberA, true, true, true);
    $otherCreatorId = $saveFixture('Fixture ' . $suffix . ' needle other', $memberB, true, true, true);
    $privateCompleteId = $saveFixture('Fixture ' . $suffix . ' needle private', $memberA, true, true, false);
    $incompletePublicId = $saveFixture('Fixture ' . $suffix . ' needle incomplete', $memberA, true, false, true);
    $officialPublicId = $saveFixture('Fixture ' . $suffix . ' needle official', $memberA, false, true, true);
    $officialHiddenId = $saveFixture('Fixture ' . $suffix . ' needle official hidden', $memberA, false, false, false);

    assertQuizRepositorySame(null, $quizzes->findById(2147483647), 'findById returns null for an absent ID.');
    assertQuizRepositorySame($alphaId, $quizzes->findById($alphaId)?->id, 'findById hydrates a fixture quiz.');
    assertQuizRepositorySame($publicCountBefore + 6, $quizzes->countPublic(), 'countPublic includes only completed public fixture quizzes.');
    assertQuizRepositorySame($officialCountBefore + 2, $quizzes->countOfficial(), 'countOfficial includes both official fixture quizzes.');

    $publicFixtureIds = array($alphaId, $betaId, $gammaId, $quotedId, $otherCreatorId, $officialPublicId);
    rsort($publicFixtureIds);
    assertQuizRepositorySame(
        array_slice($publicFixtureIds, 0, 2),
        quizRepositoryIds($quizzes->findPublic(2, 0)),
        'findPublic orders by descending ID and applies its limit.'
    );
    assertQuizRepositorySame(
        array_slice($publicFixtureIds, 1, 2),
        quizRepositoryIds($quizzes->findPublic(2, 1)),
        'findPublic applies its offset after sorting.'
    );
    assertQuizRepositorySame(
        array($officialHiddenId, $officialPublicId),
        quizRepositoryIds($quizzes->findOfficial(2, 0)),
        'findOfficial includes official quizzes regardless of completion or public visibility.'
    );

    $visibleSearchIds = array($alphaId, $betaId, $gammaId, $quotedId, $otherCreatorId, $officialPublicId, $officialHiddenId);
    rsort($visibleSearchIds);
    assertQuizRepositorySame(
        $visibleSearchIds,
        quizRepositoryIds($quizzes->searchVisibleByName('needle', 100)),
        'searchVisibleByName includes official quizzes and only completed public member quizzes.'
    );
    assertQuizRepositorySame(
        array_slice($visibleSearchIds, 0, 2),
        quizRepositoryIds($quizzes->searchVisibleByName('needle', 2)),
        'searchVisibleByName applies its requested result limit.'
    );
    assertQuizRepositorySame(
        array_slice($visibleSearchIds, 0, 1),
        quizRepositoryIds($quizzes->searchVisibleByName('needle', 0)),
        'searchVisibleByName clamps a non-positive limit to one.'
    );
    assertQuizRepositorySame(
        array($quotedId),
        quizRepositoryIds($quizzes->searchVisibleByName("O'Reilly", 10)),
        'searchVisibleByName safely binds quote-containing search text.'
    );
    assertQuizRepositorySame(
        array(),
        $quizzes->searchVisibleByName("needle' OR 1=1 --", 10),
        'searchVisibleByName treats SQL-looking input as search text.'
    );

    $memberPublicIds = array($alphaId, $betaId, $gammaId, $quotedId, $otherCreatorId);
    rsort($memberPublicIds);
    assertQuizRepositorySame(
        $memberPublicIds,
        quizRepositoryIds($quizzes->findMemberQuizzes(null, null, 200)),
        'findMemberQuizzes without a term returns completed public member quizzes only.'
    );
    assertQuizRepositorySame(
        $memberPublicIds,
        quizRepositoryIds($quizzes->findMemberQuizzes('  needle  ', null, 200)),
        'findMemberQuizzes trims a non-empty term and excludes private and incomplete quizzes.'
    );
    assertQuizRepositorySame(
        $memberPublicIds,
        quizRepositoryIds($quizzes->findMemberQuizzes('   ', null, 200)),
        'findMemberQuizzes treats a whitespace-only term like an empty search.'
    );
    assertQuizRepositorySame(
        array_slice($memberPublicIds, 0, 1),
        quizRepositoryIds($quizzes->findMemberQuizzes(null, null, 0)),
        'findMemberQuizzes clamps a non-positive limit to one.'
    );
    assertQuizRepositorySame(
        array($privateCompleteId),
        quizRepositoryIds($quizzes->findMemberQuizzes('no-match', $privateCompleteId, 20)),
        'findMemberQuizzes can find a completed private quiz by ID, regardless of search text.'
    );
    assertQuizRepositorySame(
        array(),
        $quizzes->findMemberQuizzes(null, $incompletePublicId, 20),
        'findMemberQuizzes by ID still excludes incomplete member quizzes.'
    );
    assertQuizRepositorySame(
        array(),
        $quizzes->findMemberQuizzes(null, $officialPublicId, 20),
        'findMemberQuizzes by ID excludes official quizzes.'
    );

    $creatorAIds = array($alphaId, $betaId, $gammaId, $quotedId, $privateCompleteId, $incompletePublicId);
    rsort($creatorAIds);
    assertQuizRepositorySame(
        $creatorAIds,
        quizRepositoryIds($quizzes->findByCreator($memberA)),
        'findByCreator returns all and only member-created quizzes owned by the requested member.'
    );
    assertQuizRepositorySame(
        array($otherCreatorId),
        quizRepositoryIds($quizzes->findByCreator($memberB)),
        'findByCreator does not mix fixture owners.'
    );

    // Exercise both upper LIMIT clamps with a large but entirely transactional fixture set.
    $capFixtureIds = array();
    for ($index = 1; $index <= 201; $index++) {
        $capFixtureIds[] = $saveFixture(
            'Fixture ' . $suffix . ' cap-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            $memberA,
            true,
            true,
            true
        );
    }
    rsort($capFixtureIds);
    assertQuizRepositorySame(
        array_slice($capFixtureIds, 0, 100),
        quizRepositoryIds($quizzes->searchVisibleByName('cap-', 1000)),
        'searchVisibleByName caps oversized limits at 100 results.'
    );
    assertQuizRepositorySame(
        array_slice($capFixtureIds, 0, 200),
        quizRepositoryIds($quizzes->findMemberQuizzes(null, null, 1000)),
        'findMemberQuizzes caps oversized limits at 200 results.'
    );

    $quizzes->recordView($alphaId);
    assertQuizRepositorySame(5, $quizzes->findById($alphaId)?->viewCount, 'recordView increments the persisted view count once.');
    $quizzes->recordView(2147483647);

    $updatedQuiz = new Quiz(
        $alphaId,
        'Fixture ' . $suffix . ' updated title',
        'Updated fixture welcome',
        11,
        $memberA,
        true,
        null,
        'fixture-updated.png',
        true,
        8,
        true,
        12
    );
    assertQuizRepositorySame($updatedQuiz, $quizzes->save($updatedQuiz), 'save returns the supplied entity on update.');
    $persistedUpdate = $quizzes->findById($alphaId);
    assertQuizRepositorySame('Fixture ' . $suffix . ' updated title', $persistedUpdate?->name, 'save updates the quiz name.');
    assertQuizRepositorySame('Updated fixture welcome', $persistedUpdate?->welcomeText, 'save updates the welcome text.');
    assertQuizRepositorySame(11, $persistedUpdate?->experiencePoints, 'save updates experience points.');
    assertQuizRepositorySame('fixture-updated.png', $persistedUpdate?->source, 'save updates the source path.');
    assertQuizRepositorySame(8, $persistedUpdate?->questionCount, 'save updates question count.');
    assertQuizRepositorySame(12, $persistedUpdate?->viewCount, 'save updates the view count.');

    // Create a dedicated quiz graph; deleting its root must cascade through quiz, question, answer and interaction rows.
    $database->execute('INSERT INTO quiz_difficulte (libelle) VALUES (?)', 'Quiz repository ' . $suffix);
    $difficultyId = $database->lastInsertId();
    $cascadeQuizId = $saveFixture('Fixture ' . $suffix . ' cascade', $memberA, true, true, true);
    $database->execute(
        'INSERT INTO quiz_question (id_quiz, id_image_question, texte_bienvenue, id_difficulte, question_texte, numero_question, nbrReponse) VALUES (?, NULL, NULL, ?, ?, 1, 2)',
        $cascadeQuizId,
        $difficultyId,
        'Fixture cascade question'
    );
    $questionId = $database->lastInsertId();
    $database->execute(
        'INSERT INTO quiz_reponse (id_question, id_image_reponse, choix_possible_quiz, reponseOK, texte_reponse_explicatif, num_reponse) VALUES (?, NULL, ?, ?, ?, 1)',
        $questionId,
        'Fixture answer',
        '1',
        'Fixture explanation'
    );
    $database->execute(
        'INSERT INTO quiz_progression (id_user, id_question) VALUES (?, ?)',
        $memberA,
        $questionId
    );
    $database->execute(
        'INSERT INTO quiz_like_quiz (id_quiz, ip, likeOrDislike, esQuizUser) VALUES (?, ?, 1, 1)',
        $cascadeQuizId,
        '192.0.2.73'
    );
    $database->execute(
        'INSERT INTO quiz_statistique (id_user, id_quiz, date, note, favoris, quiz_utilisateur) VALUES (?, ?, ?, 9, 1, 1)',
        $memberA,
        $cascadeQuizId,
        '2026-11-24'
    );
    assertQuizRepositorySame(1, (int) $database->fetchOne('SELECT COUNT(*) AS total FROM quiz_reponse WHERE id_question = ?', $questionId)['total'], 'Cascade fixture answer is present before deletion.');
    assertQuizRepositorySame(1, (int) $database->fetchOne('SELECT COUNT(*) AS total FROM quiz_progression WHERE id_question = ?', $questionId)['total'], 'Cascade fixture progress is present before deletion.');
    assertQuizRepositorySame(1, (int) $database->fetchOne('SELECT COUNT(*) AS total FROM quiz_like_quiz WHERE id_quiz = ?', $cascadeQuizId)['total'], 'Cascade fixture reaction is present before deletion.');
    assertQuizRepositorySame(1, (int) $database->fetchOne('SELECT COUNT(*) AS total FROM quiz_statistique WHERE id_quiz = ?', $cascadeQuizId)['total'], 'Cascade fixture statistic is present before deletion.');

    $quizzes->delete($cascadeQuizId);
    assertQuizRepositorySame(null, $quizzes->findById($cascadeQuizId), 'delete removes the requested quiz.');
    assertQuizRepositorySame(0, (int) $database->fetchOne('SELECT COUNT(*) AS total FROM quiz_question WHERE id_quiz = ?', $cascadeQuizId)['total'], 'delete cascades to quiz questions.');
    assertQuizRepositorySame(0, (int) $database->fetchOne('SELECT COUNT(*) AS total FROM quiz_reponse WHERE id_question = ?', $questionId)['total'], 'delete cascades through questions to answers.');
    assertQuizRepositorySame(0, (int) $database->fetchOne('SELECT COUNT(*) AS total FROM quiz_progression WHERE id_question = ?', $questionId)['total'], 'delete cascades through questions to member progress.');
    assertQuizRepositorySame(0, (int) $database->fetchOne('SELECT COUNT(*) AS total FROM quiz_like_quiz WHERE id_quiz = ?', $cascadeQuizId)['total'], 'delete cascades to quiz reactions.');
    assertQuizRepositorySame(0, (int) $database->fetchOne('SELECT COUNT(*) AS total FROM quiz_statistique WHERE id_quiz = ?', $cascadeQuizId)['total'], 'delete cascades to quiz statistics.');
} finally {
    $database->rollback();
    $database->close();
}

fwrite(STDOUT, "MySQL quiz repository integration checks passed.\n");
