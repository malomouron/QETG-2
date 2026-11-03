<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\QuizStatistic;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;

final class MySqlQuizStatisticRepository extends MySqlRepository implements QuizStatisticRepositoryInterface
{
    public function findByMemberAndQuiz(int $memberId, int $quizId): ?QuizStatistic
    {
        $row = $this->one('SELECT * FROM quiz_statistique WHERE id_user = ? AND id_quiz = ?', $memberId, $quizId);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByMemberId(int $memberId): array
    {
        $rows = $this->many('SELECT * FROM quiz_statistique WHERE id_user = ? ORDER BY `date` DESC', $memberId);
        return array_map(fn (array $row): QuizStatistic => $this->hydrate($row), $rows);
    }

    public function findFavoritesByMemberId(int $memberId): array
    {
        $rows = $this->many('SELECT * FROM quiz_statistique WHERE id_user = ? AND favoris = 1 ORDER BY `date` DESC', $memberId);
        return array_map(fn (array $row): QuizStatistic => $this->hydrate($row), $rows);
    }

    public function countOfficialByMemberId(int $memberId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS total FROM quiz_statistique s INNER JOIN quiz q ON q.id_quiz = s.id_quiz WHERE s.id_user = ? AND q.id_quiz_user = 0',
            $memberId
        );
        return (int) ($row['total'] ?? 0);
    }

    public function save(QuizStatistic $statistic): QuizStatistic
    {
        $this->execute(
            'INSERT INTO quiz_statistique (id_user, id_quiz, `date`, note, favoris, quiz_utilisateur) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `date` = VALUES(`date`), note = VALUES(note), favoris = VALUES(favoris), quiz_utilisateur = VALUES(quiz_utilisateur)',
            $statistic->memberId,
            $statistic->quizId,
            $statistic->playedAt,
            $statistic->score,
            (int) $statistic->isFavorite,
            (int) $statistic->isMemberQuiz
        );
        return $statistic;
    }

    public function deleteByMemberAndQuiz(int $memberId, int $quizId): void
    {
        $this->execute('DELETE FROM quiz_statistique WHERE id_user = ? AND id_quiz = ?', $memberId, $quizId);
    }

    private function hydrate(array $row): QuizStatistic
    {
        return new QuizStatistic(
            (int) $row['id_user'],
            (int) $row['id_quiz'],
            (string) $row['date'],
            (int) $row['note'],
            (bool) $row['favoris'],
            (bool) $row['quiz_utilisateur']
        );
    }
}