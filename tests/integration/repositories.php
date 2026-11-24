<?php

declare(strict_types=1);

use QETG\Domain\Entity\Achievement;
use QETG\Domain\Entity\Answer;
use QETG\Domain\Entity\Comment;
use QETG\Domain\Entity\CommentReply;
use QETG\Domain\Entity\Difficulty;
use QETG\Domain\Entity\Image;
use QETG\Domain\Entity\MemberAchievement;
use QETG\Domain\Entity\MemberSubscription;
use QETG\Domain\Entity\PasswordReset;
use QETG\Domain\Entity\Question;
use QETG\Domain\Entity\QuizProgress;
use QETG\Domain\Entity\QuizReaction;
use QETG\Domain\Entity\QuizStatistic;
use QETG\Domain\Entity\UserReport;
use QETG\Infrastructure\Persistence\MySQL\MySqlAchievementRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlAnswerRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlCommentReplyRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlCommentRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;
use QETG\Infrastructure\Persistence\MySQL\MySqlDifficultyRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlImageRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlMemberAchievementRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlMemberSubscriptionRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlNewsletterRecipientRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlPasswordResetRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuestionRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizProgressRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizReactionRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizStatisticRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlUserReportRepository;

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

function assertRepositoryIntegration(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertRepositorySame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function repositoryId(?int $id, string $entity): int
{
    assertRepositoryIntegration($id !== null && $id > 0, $entity . ' save must return an inserted identifier.');
    return $id;
}

$database = new MySqlConnection('db', 'qetg_ci', 'ci-app-only', 'test');

// The reset-token table is an explicit production migration and is not in QETG.sql.
// Create only that table in the disposable Compose schema when the migration has not run.
$database->execute(
    'CREATE TABLE IF NOT EXISTS quiz_password_reset (' .
    'id INT NOT NULL AUTO_INCREMENT, ' .
    'member_id INT NOT NULL, ' .
    'token_hash CHAR(64) NOT NULL, ' .
    'expires_at DATETIME NOT NULL, ' .
    'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
    'PRIMARY KEY (id), ' .
    'UNIQUE KEY uq_quiz_password_reset_token_hash (token_hash), ' .
    'KEY idx_quiz_password_reset_member (member_id), ' .
    'CONSTRAINT fk_quiz_password_reset_member FOREIGN KEY (member_id) REFERENCES quiz_membre (id) ON DELETE CASCADE' .
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$achievements = new MySqlAchievementRepository($database);
$answers = new MySqlAnswerRepository($database);
$comments = new MySqlCommentRepository($database);
$commentReplies = new MySqlCommentReplyRepository($database);
$difficulties = new MySqlDifficultyRepository($database);
$images = new MySqlImageRepository($database);
$memberAchievements = new MySqlMemberAchievementRepository($database);
$subscriptions = new MySqlMemberSubscriptionRepository($database);
$newsletterRecipients = new MySqlNewsletterRecipientRepository($database);
$passwordResets = new MySqlPasswordResetRepository($database);
$questions = new MySqlQuestionRepository($database);
$progress = new MySqlQuizProgressRepository($database);
$reactions = new MySqlQuizReactionRepository($database);
$statistics = new MySqlQuizStatisticRepository($database);
$reports = new MySqlUserReportRepository($database);

$suffix = bin2hex(random_bytes(6));
$database->beginTransaction();
try {
    // Parent fixtures are inserted first to respect every foreign-key dependency.
    $memberIds = array();
    foreach (array('a', 'b', 'c') as $index => $tag) {
        $database->execute(
            'INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, newletter, ip, date) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)',
            'repo-ci-' . $suffix . '-' . $tag,
            'test-hash-' . $suffix,
            'repo-ci-' . $suffix . '-' . $tag . '@example.invalid',
            'Repository',
            'Fixture ' . $tag,
            'default',
            $index === 0 ? 1 : 0,
            '192.0.2.' . (40 + $index),
            '2026-11-24'
        );
        $memberIds[] = $database->lastInsertId();
    }
    [$memberA, $memberB, $memberC] = $memberIds;

    $image = $images->save(new Image(null, 'tests/repositories/' . $suffix . '.png', 'Repository fixture image'));
    $imageId = repositoryId($image->id, 'Image');
    assertRepositorySame('Repository fixture image', $images->findById($imageId)?->altText, 'Image findById hydrates alt text.');
    assertRepositoryIntegration(
        in_array($imageId, array_map(static fn (Image $row): ?int => $row->id, $images->findAll()), true),
        'Image findAll includes the saved image.'
    );
    $updatedImage = new Image($imageId, 'tests/repositories/' . $suffix . '-updated.png', 'Updated repository image');
    $images->save($updatedImage);
    assertRepositorySame('Updated repository image', $images->findById($imageId)?->altText, 'Image save updates an existing row.');

    $difficulty = $difficulties->save(new Difficulty(null, 'Repository ' . $suffix));
    $difficultyId = repositoryId($difficulty->id, 'Difficulty');
    assertRepositorySame('Repository ' . $suffix, $difficulties->findById($difficultyId)?->label, 'Difficulty findById hydrates its label.');
    assertRepositoryIntegration(
        in_array($difficultyId, array_map(static fn (Difficulty $row): ?int => $row->id, $difficulties->findAll()), true),
        'Difficulty findAll includes the saved difficulty.'
    );
    $difficulties->save(new Difficulty($difficultyId, 'Updated repository ' . $suffix));
    assertRepositorySame('Updated repository ' . $suffix, $difficulties->findById($difficultyId)?->label, 'Difficulty save updates an existing row.');

    $achievementA = $achievements->save(new Achievement(null, 'Repository ' . $suffix . ' A', 3, 'Initial fixture achievement.'));
    $achievementAId = repositoryId($achievementA->id, 'Achievement');
    $achievementB = $achievements->save(new Achievement(null, 'Repository ' . $suffix . ' B', 5, 'Second fixture achievement.'));
    $achievementBId = repositoryId($achievementB->id, 'Achievement');
    assertRepositorySame($achievementAId, $achievements->findById($achievementAId)?->id, 'Achievement findById loads the inserted row.');
    assertRepositoryIntegration(
        in_array($achievementAId, array_map(static fn (Achievement $row): ?int => $row->id, $achievements->findAll()), true),
        'Achievement findAll includes fixture achievements.'
    );
    $achievements->save(new Achievement($achievementAId, 'Updated repository ' . $suffix . ' A', 8, 'Updated fixture achievement.'));
    assertRepositorySame('Updated repository ' . $suffix . ' A', $achievements->findById($achievementAId)?->name, 'Achievement save updates an existing row.');

    // The quiz depends on the fixture member and image; question depends on quiz, image and difficulty.
    $database->execute(
        'INSERT INTO quiz (id_image_quiz, nom_quiz, bienvenue, xp, id_user, id_quiz_user, src, quizComplet, nbrQuestion, portee, vue) VALUES (?, ?, ?, 5, ?, 0, ?, 1, 2, 1, 0)',
        $imageId,
        'Repo ' . $suffix,
        'Repository fixture quiz',
        $memberA,
        'tests/repositories/' . $suffix . '.png'
    );
    $quizId = $database->lastInsertId();
    $questionOne = $questions->save(new Question(null, $quizId, $imageId, 'Fixture welcome', $difficultyId, 'Fixture question one', 1, 2));
    $questionOneId = repositoryId($questionOne->id, 'Question');
    $questionTwo = $questions->save(new Question(null, $quizId, $imageId, null, $difficultyId, 'Fixture question two', 2, 1));
    $questionTwoId = repositoryId($questionTwo->id, 'Question');
    assertRepositorySame($questionOneId, $questions->findById($questionOneId)?->id, 'Question findById loads the inserted row.');
    assertRepositorySame($questionOneId, $questions->findByQuizAndNumber($quizId, 1)?->id, 'Question lookup by quiz and number returns the matching row.');
    assertRepositorySame(2, count($questions->findByQuizId($quizId)), 'Question lookup by quiz returns both fixture questions.');
    $questions->save(new Question($questionOneId, $quizId, $imageId, 'Updated welcome', $difficultyId, 'Updated fixture question one', 1, 2));
    assertRepositorySame('Updated fixture question one', $questions->findById($questionOneId)?->text, 'Question save updates an existing row.');

    $answerOne = $answers->save(new Answer(null, $questionOneId, $imageId, 'Fixture correct', true, 'Correct fixture answer.', 1));
    $answerOneId = repositoryId($answerOne->id, 'Answer');
    $answerTwo = $answers->save(new Answer(null, $questionOneId, null, 'Fixture alternative', false, null, 2));
    $answerTwoId = repositoryId($answerTwo->id, 'Answer');
    assertRepositorySame($answerOneId, $answers->findById($answerOneId)?->id, 'Answer findById loads the inserted row.');
    assertRepositorySame(2, count($answers->findByQuestionId($questionOneId)), 'Answer lookup by question returns both fixture answers.');
    $answers->save(new Answer($answerOneId, $questionOneId, $imageId, 'Updated fixture correct', true, 'Updated explanation.', 1));
    assertRepositorySame('Updated fixture correct', $answers->findById($answerOneId)?->choice, 'Answer save updates an existing row.');

    $comment = $comments->save(new Comment(null, 'Fixture', 'Comment', 'repo-' . $suffix . '@example.invalid', 'Initial repository comment.', '2026-11-24', '192.0.2.50'));
    $commentId = repositoryId($comment->id, 'Comment');
    assertRepositorySame($commentId, $comments->findById($commentId)?->id, 'Comment findById loads the inserted row.');
    assertRepositoryIntegration(
        in_array($commentId, array_map(static fn (Comment $row): ?int => $row->id, $comments->findRecent(100, 0)), true),
        'Comment findRecent includes the fixture comment.'
    );
    $comments->save(new Comment($commentId, 'Fixture', 'Comment', 'repo-' . $suffix . '@example.invalid', 'Updated repository comment.', '2026-11-24', '192.0.2.50'));
    assertRepositorySame('Updated repository comment.', $comments->findById($commentId)?->content, 'Comment save updates an existing row.');

    $reply = $commentReplies->save(new CommentReply(null, $commentId, $memberA, 'Initial member reply.', '192.0.2.51'));
    $replyId = repositoryId($reply->id, 'Comment reply');
    assertRepositorySame($replyId, $commentReplies->findById($replyId)?->id, 'Comment reply findById loads the inserted row.');
    assertRepositorySame(1, count($commentReplies->findByCommentId($commentId)), 'Comment reply lookup returns its fixture reply.');
    $commentReplies->save(new CommentReply($replyId, $commentId, $memberA, 'Updated member reply.', '192.0.2.52'));
    assertRepositorySame('Updated member reply.', $commentReplies->findById($replyId)?->content, 'Comment reply save updates an existing row.');

    $memberAchievement = $memberAchievements->save(new MemberAchievement(null, $achievementAId, $memberA, '24/11/2026'));
    $memberAchievementId = repositoryId($memberAchievement->id, 'Member achievement');
    assertRepositorySame(true, $memberAchievements->hasAchievement($memberA, $achievementAId), 'Member achievement existence lookup sees a grant.');
    assertRepositorySame(1, count($memberAchievements->findByMemberId($memberA)), 'Member achievement lookup returns the member grant.');
    $memberAchievements->save(new MemberAchievement($memberAchievementId, $achievementBId, $memberA, '25/11/2026'));
    assertRepositorySame(false, $memberAchievements->hasAchievement($memberA, $achievementAId), 'Member achievement update removes the previous award relation.');
    assertRepositorySame(true, $memberAchievements->hasAchievement($memberA, $achievementBId), 'Member achievement update stores the new award relation.');

    $subscription = $subscriptions->save(new MemberSubscription(null, $memberA, $memberB, null));
    $subscriptionId = repositoryId($subscription->id, 'Subscription');
    assertRepositorySame($subscriptionId, $subscriptions->findByMembers($memberA, $memberB)?->id, 'Subscription lookup by member pair finds the row.');
    assertRepositorySame(1, count($subscriptions->findFollowing($memberA)), 'Subscription following lookup returns the fixture row.');
    assertRepositorySame(1, count($subscriptions->findFollowers($memberB)), 'Subscription follower lookup returns the fixture row.');
    $subscriptionDate = new DateTimeImmutable('2026-11-24 12:34:56');
    $subscriptions->save(new MemberSubscription($subscriptionId, $memberA, $memberC, $subscriptionDate));
    assertRepositorySame(null, $subscriptions->findByMembers($memberA, $memberB), 'Subscription update replaces the followed member.');
    assertRepositorySame($subscriptionId, $subscriptions->findByMembers($memberA, $memberC)?->id, 'Subscription update is persisted.');

    $recipients = $newsletterRecipients->findSubscribedRecipients();
    $fixtureRecipient = null;
    foreach ($recipients as $recipient) {
        if ($recipient->memberId === $memberA) {
            $fixtureRecipient = $recipient;
            break;
        }
    }
    assertRepositoryIntegration($fixtureRecipient !== null, 'Newsletter recipients include the subscribed fixture member.');
    assertRepositorySame('repo-ci-' . $suffix . '-a', $fixtureRecipient->login, 'Newsletter recipient maps the member login.');
    assertRepositorySame('repo-ci-' . $suffix . '-a@example.invalid', $fixtureRecipient->email, 'Newsletter recipient maps the member email.');

    $resetTokenA = hash('sha256', 'repository-reset-a-' . $suffix);
    $resetTokenB = hash('sha256', 'repository-reset-b-' . $suffix);
    $resetA = $passwordResets->save(new PasswordReset(null, $memberA, $resetTokenA, new DateTimeImmutable('2026-11-25 12:00:00')));
    $resetAId = repositoryId($resetA->id, 'Password reset');
    assertRepositorySame($resetAId, $passwordResets->findByTokenHash($resetTokenA)?->id, 'Password reset lookup finds a saved token hash.');
    $resetB = $passwordResets->save(new PasswordReset(null, $memberA, $resetTokenB, new DateTimeImmutable('2026-11-26 12:00:00')));
    $resetBId = repositoryId($resetB->id, 'Password reset');
    $passwordResets->delete($resetAId);
    assertRepositorySame(null, $passwordResets->findByTokenHash($resetTokenA), 'Password reset delete removes the selected token.');
    $passwordResets->deleteByMemberId($memberA);
    assertRepositorySame(null, $passwordResets->findByTokenHash($resetTokenB), 'Password reset deleteByMemberId removes remaining member tokens.');

    $progressRow = $progress->save(new QuizProgress(null, $memberA, $questionOneId));
    $progressId = repositoryId($progressRow->id, 'Quiz progress');
    assertRepositorySame($questionOneId, $progress->findByMemberAndQuiz($memberA, $quizId)?->questionId, 'Quiz progress lookup resolves its quiz question.');
    assertRepositorySame(1, count($progress->findByMemberId($memberA)), 'Quiz progress lookup returns the member row.');
    $progress->save(new QuizProgress($progressId, $memberA, $questionTwoId));
    assertRepositorySame($questionTwoId, $progress->findByMemberAndQuiz($memberA, $quizId)?->questionId, 'Quiz progress save updates the current question.');
    $progress->deleteByMemberAndQuiz($memberA, $quizId);
    assertRepositorySame(null, $progress->findByMemberAndQuiz($memberA, $quizId), 'Quiz progress delete clears the member quiz progress.');

    $reaction = $reactions->save(new QuizReaction(null, $quizId, '192.0.2.60', 1, false));
    $reactionId = repositoryId($reaction->id, 'Quiz reaction');
    assertRepositorySame($reactionId, $reactions->findById($reactionId)?->id, 'Quiz reaction findById loads the inserted row.');
    assertRepositorySame($reactionId, $reactions->findByQuizAndIp($quizId, '192.0.2.60')?->id, 'Quiz reaction lookup by quiz and IP finds the row.');
    assertRepositorySame(1, count($reactions->findByQuizId($quizId)), 'Quiz reaction lookup by quiz returns the fixture row.');
    $reactions->save(new QuizReaction($reactionId, $quizId, '192.0.2.61', 0, true));
    assertRepositorySame(0, $reactions->findById($reactionId)?->value, 'Quiz reaction save updates an existing row.');

    $statistic = new QuizStatistic($memberA, $quizId, '2026-11-24', 7, false, false);
    $statistics->save($statistic);
    assertRepositorySame(7, $statistics->findByMemberAndQuiz($memberA, $quizId)?->score, 'Quiz statistic lookup finds the inserted result.');
    $statistics->save(new QuizStatistic($memberA, $quizId, '2026-11-25', 9, true, false));
    assertRepositorySame(9, $statistics->findByMemberAndQuiz($memberA, $quizId)?->score, 'Quiz statistic save upserts an existing member/quiz result.');
    assertRepositorySame(1, count($statistics->findByMemberId($memberA)), 'Quiz statistic lookup returns the member result.');
    assertRepositorySame(1, count($statistics->findFavoritesByMemberId($memberA)), 'Quiz statistic favorites lookup returns the favorite.');
    assertRepositorySame(1, $statistics->countOfficialByMemberId($memberA), 'Quiz statistic count identifies the official fixture quiz.');
    $statistics->deleteByMemberAndQuiz($memberA, $quizId);
    assertRepositorySame(null, $statistics->findByMemberAndQuiz($memberA, $quizId), 'Quiz statistic delete removes the result.');

    $report = $reports->save(new UserReport(null, $memberA, $memberB));
    $reportId = repositoryId($report->id, 'User report');
    assertRepositorySame($reportId, $reports->findByReporterAndTarget($memberA, $memberB)?->id, 'User report lookup finds the reporter/target pair.');
    assertRepositorySame(1, count($reports->findByTargetMemberId($memberB)), 'User report target lookup returns its fixture report.');
    $reports->save(new UserReport($reportId, $memberA, $memberC));
    assertRepositorySame(null, $reports->findByReporterAndTarget($memberA, $memberB), 'User report update replaces the target.');
    assertRepositorySame($reportId, $reports->findByReporterAndTarget($memberA, $memberC)?->id, 'User report update is persisted.');
    $reports->delete($reportId);
    assertRepositorySame(null, $reports->findByReporterAndTarget($memberA, $memberC), 'User report delete removes the fixture report.');

    // Delete children before parents; all fixture changes are rolled back in finally.
    $reactions->delete($reactionId);
    assertRepositorySame(null, $reactions->findById($reactionId), 'Quiz reaction delete removes the row.');
    $memberAchievements->delete($memberAchievementId);
    assertRepositorySame(false, $memberAchievements->hasAchievement($memberA, $achievementBId), 'Member achievement delete removes the grant.');
    $subscriptions->delete($memberA, $memberC);
    assertRepositorySame(null, $subscriptions->findByMembers($memberA, $memberC), 'Subscription delete removes the member pair.');
    $commentReplies->delete($replyId);
    assertRepositorySame(null, $commentReplies->findById($replyId), 'Comment reply delete removes the row.');
    $comments->delete($commentId);
    assertRepositorySame(null, $comments->findById($commentId), 'Comment delete removes the row.');
    $answers->delete($answerOneId);
    assertRepositorySame(null, $answers->findById($answerOneId), 'Answer delete removes the selected row.');
    $answers->deleteByQuestionId($questionOneId);
    assertRepositorySame(0, count($answers->findByQuestionId($questionOneId)), 'Answer deleteByQuestionId removes remaining answers.');
    $questions->delete($questionOneId);
    assertRepositorySame(null, $questions->findById($questionOneId), 'Question delete removes the selected row.');
    $questions->deleteByQuizId($quizId);
    assertRepositorySame(0, count($questions->findByQuizId($quizId)), 'Question deleteByQuizId removes remaining quiz questions.');
    $achievements->delete($achievementAId);
    $achievements->delete($achievementBId);
    assertRepositorySame(null, $achievements->findById($achievementAId), 'Achievement delete removes the fixture row.');
    $database->execute('DELETE FROM quiz WHERE id_quiz = ?', $quizId);
    $images->delete($imageId);
    assertRepositorySame(null, $images->findById($imageId), 'Image delete removes the no-longer-referenced fixture image.');
    $difficulties->delete($difficultyId);
    assertRepositorySame(null, $difficulties->findById($difficultyId), 'Difficulty delete removes the no-longer-referenced fixture difficulty.');

    // Keep the variable referenced so static analyzers catch the delete-by-ID path above.
    unset($resetBId);
} finally {
    $database->rollback();
    $database->close();
}

fwrite(STDOUT, "MySQL repository integration checks passed.\n");