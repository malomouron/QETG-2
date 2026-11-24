<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use mysqli_sql_exception;
use QETG\Application\Port\AdministrationRepositoryInterface;
use QETG\Application\Service\QuizQuestions;
use QETG\Domain\Entity\Quiz;
use RuntimeException;

final class MySqlAdministrationRepository extends MySqlRepository implements AdministrationRepositoryInterface
{
    private const PAGE_SIZE = 20;

    private MySqlTransactionManager $transactions;
    private MySqlQuizRepository $quizzes;
    private MySqlMemberRepository $members;
    private QuizQuestions $questions;

    public function __construct(MySqlConnection $database)
    {
        parent::__construct($database);
        $this->transactions = new MySqlTransactionManager($database);
        $this->quizzes = new MySqlQuizRepository($database);
        $this->members = new MySqlMemberRepository($database);
        $this->questions = new QuizQuestions(
            new MySqlQuestionRepository($database),
            new MySqlAnswerRepository($database),
            new MySqlDifficultyRepository($database)
        );
    }

    public function dashboard(int $quizPage, int $reportPage): array
    {
        $totals = $this->one(
            'SELECT (SELECT COUNT(*) FROM quiz_membre) AS members,
                    (SELECT COUNT(*) FROM quiz WHERE id_quiz_user = 0) AS official,
                    (SELECT COUNT(*) FROM quiz WHERE id_quiz_user = 1) AS community,
                    (SELECT COUNT(*) FROM quiz_statistique) AS completions,
                    COUNT(*) AS reports, COUNT(DISTINCT id_user_cible) AS report_targets
             FROM quiz_signalement'
        );
        $quizOffset = self::offset($quizPage);
        $reportOffset = self::offset($reportPage);
        $quizzes = $quizOffset >= (int) $totals['official'] ? [] : $this->many(
            'SELECT q.id_quiz, q.nom_quiz, q.portee,
                    (SELECT COUNT(*) FROM quiz_question WHERE id_quiz = q.id_quiz) AS question_count
             FROM quiz q WHERE q.id_quiz_user = 0 ORDER BY q.id_quiz DESC LIMIT ? OFFSET ?',
            self::PAGE_SIZE,
            $quizOffset
        );
        $reports = $reportOffset >= (int) $totals['report_targets'] ? [] : $this->many(
            "SELECT target.id AS target_id, target.login AS target_login, target.admin AS target_admin,
                    COUNT(*) AS report_count,
                    GROUP_CONCAT(DISTINCT reporter.login ORDER BY reporter.login SEPARATOR ', ') AS reporters
             FROM quiz_signalement report
             JOIN quiz_membre target ON target.id = report.id_user_cible
             JOIN quiz_membre reporter ON reporter.id = report.id_user_origine
             GROUP BY target.id, target.login, target.admin
             ORDER BY report_count DESC, target.id ASC LIMIT ? OFFSET ?",
            self::PAGE_SIZE,
            $reportOffset
        );
        $traffic = $this->trafficMetrics();

        return [
            'stats' => [
                'members' => (int) $totals['members'],
                'official' => (int) $totals['official'],
                'community' => (int) $totals['community'],
                'reports' => (int) $totals['reports'],
                'completions' => (int) $totals['completions'],
            ],
            'traffic' => $traffic,
            'quizzes' => array_map(static fn (array $quiz): array => [
                'id' => (int) $quiz['id_quiz'],
                'title' => (string) $quiz['nom_quiz'],
                'questions' => (int) $quiz['question_count'],
                'public' => (bool) $quiz['portee'],
            ], $quizzes),
            'reports' => array_map(static fn (array $report): array => [
                'target_id' => (int) $report['target_id'],
                'target_login' => (string) $report['target_login'],
                'target_admin' => (bool) $report['target_admin'],
                'report_count' => (int) $report['report_count'],
                'reporters' => (string) $report['reporters'],
            ], $reports),
            'reportTargets' => (int) $totals['report_targets'],
        ];
    }

    public function recordPageView(bool $newSessionToday): void
    {
        $newSession = (int) $newSessionToday;
        $this->execute(
            'INSERT INTO quiz_daily_traffic (daily, page_views, visitor_sessions) VALUES (CURRENT_DATE, 1, ?) ON DUPLICATE KEY UPDATE page_views = page_views + 1, visitor_sessions = visitor_sessions + ?',
            $newSession,
            $newSession
        );
    }

    /** @return array{days: list<array{date: string, pageViews: int, visitorSessions: int}>, today: array{pageViews: int, visitorSessions: int}, sevenDayTotals: array{pageViews: int, visitorSessions: int}, migrationMissing: bool} */
    private function trafficMetrics(): array
    {
        $today = (string) ($this->one('SELECT CURRENT_DATE AS today')['today'] ?? '');
        $todayDate = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
        if ($todayDate === false || $todayDate->format('Y-m-d') !== $today) {
            throw new RuntimeException('MySQL returned an invalid current date.');
        }

        $startDate = $todayDate->modify('-6 days')->format('Y-m-d');
        $daysByDate = [];
        for ($offset = 0; $offset < 7; ++$offset) {
            $date = $todayDate->modify('-' . (6 - $offset) . ' days')->format('Y-m-d');
            $daysByDate[$date] = ['date' => $date, 'pageViews' => 0, 'visitorSessions' => 0];
        }

        try {
            $rows = $this->many(
                'SELECT daily, page_views, visitor_sessions FROM quiz_daily_traffic WHERE daily BETWEEN ? AND ? ORDER BY daily',
                $startDate,
                $today
            );
        } catch (RuntimeException $exception) {
            $cause = $exception->getPrevious();
            if (!$cause instanceof mysqli_sql_exception || $cause->getCode() !== 1146) {
                throw $exception;
            }
            return self::formatTraffic($daysByDate, $today, true);
        }

        foreach ($rows as $row) {
            $date = (string) $row['daily'];
            if (isset($daysByDate[$date])) {
                $daysByDate[$date]['pageViews'] = (int) $row['page_views'];
                $daysByDate[$date]['visitorSessions'] = (int) $row['visitor_sessions'];
            }
        }

        return self::formatTraffic($daysByDate, $today, false);
    }

    /** @param array<string, array{date: string, pageViews: int, visitorSessions: int}> $daysByDate */
    private static function formatTraffic(array $daysByDate, string $today, bool $migrationMissing): array
    {
        $days = array_values($daysByDate);
        $sevenDayTotals = ['pageViews' => 0, 'visitorSessions' => 0];
        foreach ($days as $day) {
            $sevenDayTotals['pageViews'] += $day['pageViews'];
            $sevenDayTotals['visitorSessions'] += $day['visitorSessions'];
        }

        $todayMetrics = $daysByDate[$today] ?? ['pageViews' => 0, 'visitorSessions' => 0];
        return [
            'days' => $days,
            'today' => ['pageViews' => $todayMetrics['pageViews'], 'visitorSessions' => $todayMetrics['visitorSessions']],
            'sevenDayTotals' => $sevenDayTotals,
            'migrationMissing' => $migrationMissing,
        ];
    }

    public function publishQuiz(int $actorId, string $title, string $description, int $xp, array $questions): int
    {
        return $this->transactions->run(function () use ($actorId, $title, $description, $xp, $questions): int {
            $this->lockAdministrator($actorId);
            $title = trim($title);
            $description = trim($description);
            if ($title === '' || strlen($title) > 50) {
                throw new DomainException('Le nom du quiz est obligatoire et ne peut pas dépasser 50 caractères.');
            }
            if (strlen($description) > 5000) {
                throw new DomainException('La description du quiz ne peut pas dépasser 5 000 caractères.');
            }
            if ($xp < 0 || $xp > 2147483647) {
                throw new DomainException('Le gain d’XP doit être compris entre 0 et 2 147 483 647.');
            }
            try {
                $questions = $this->questions->validate($questions);
            } catch (InvalidArgumentException $exception) {
                throw new DomainException($exception->getMessage(), 0, $exception);
            }

            try {
                $quiz = $this->quizzes->save(new Quiz(
                    null, $title, $description, $xp, $actorId, false, null, null, true, count($questions), true, 0
                ));
                if ($quiz->id === null || $quiz->id < 1) {
                    throw new RuntimeException('Le quiz n’a pas pu être créé.');
                }
                $this->questions->insert($quiz->id, $questions);
            } catch (RuntimeException $exception) {
                $cause = $exception->getPrevious();
                // Legacy quiz columns use utf8mb3; prepared parameters may fail conversion before insertion.
                if ($cause instanceof mysqli_sql_exception && in_array($cause->getCode(), [1366, 3988], true)) {
                    throw new DomainException('Le quiz contient des caractères non pris en charge.', 0, $exception);
                }
                throw $exception;
            }

            return $quiz->id;
        });
    }

    public function deleteQuiz(int $actorId, int $quizId): void
    {
        $this->transactions->run(function () use ($actorId, $quizId): void {
            $this->lockAdministrator($actorId);
            if ($this->database->execute('DELETE FROM quiz WHERE id_quiz = ? AND id_quiz_user = 0', $quizId) !== 1) {
                throw new DomainException('Ce quiz officiel n’existe pas.');
            }
        });
    }

    public function deleteReportedMember(int $actorId, int $memberId): void
    {
        $this->transactions->run(function () use ($actorId, $memberId): void {
            $this->lockAdministrator($actorId);
            $target = $this->one('SELECT admin FROM quiz_membre WHERE id = ? FOR UPDATE', $memberId);
            if ($target === null) {
                throw new DomainException('Ce compte n’existe pas.');
            }
            if ((bool) $target['admin']) {
                throw new DomainException('Un compte administrateur ne peut pas être supprimé par modération.');
            }
            if ($this->one('SELECT id_signalement FROM quiz_signalement WHERE id_user_cible = ? LIMIT 1 FOR UPDATE', $memberId) === null) {
                throw new DomainException('Ce compte ne fait plus l’objet d’aucun signalement.');
            }
            $this->members->deleteInTransaction($memberId);
        });
    }

    private function lockAdministrator(int $actorId): void
    {
        $actor = $this->one('SELECT admin FROM quiz_membre WHERE id = ? FOR UPDATE', $actorId);
        if ($actor === null || (int) $actor['admin'] !== 1) {
            throw new DomainException('Cette action est réservée aux administrateurs.');
        }
    }

    private static function offset(int $page): int
    {
        return min(max(1, $page) - 1, intdiv(PHP_INT_MAX, self::PAGE_SIZE)) * self::PAGE_SIZE;
    }
}