<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\QuizProgress;
use QETG\Domain\Repository\QuizProgressRepositoryInterface;

final class MySqlQuizProgressRepository extends MySqlRepository implements QuizProgressRepositoryInterface
{
    public function findByMemberAndQuiz(int $memberId, int $quizId): ?QuizProgress
    {
        $row = $this->one(
            'SELECT p.* FROM quiz_progression p INNER JOIN quiz_question q ON q.id_question = p.id_question WHERE p.id_user = ? AND q.id_quiz = ? ORDER BY p.id_prog DESC LIMIT 1',
            $memberId,
            $quizId
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByMemberId(int $memberId): array
    {
        $rows = $this->many('SELECT * FROM quiz_progression WHERE id_user = ? ORDER BY id_prog', $memberId);
        return array_map(fn (array $row): QuizProgress => $this->hydrate($row), $rows);
    }

    public function save(QuizProgress $progress): QuizProgress
    {
        if ($progress->id === null) {
            $this->execute('INSERT INTO quiz_progression (id_user, id_question) VALUES (?, ?)', $progress->memberId, $progress->questionId);
            return new QuizProgress($this->lastInsertId(), $progress->memberId, $progress->questionId);
        }
        $this->execute('UPDATE quiz_progression SET id_user = ?, id_question = ? WHERE id_prog = ?', $progress->memberId, $progress->questionId, $progress->id);
        return $progress;
    }

    public function deleteByMemberAndQuiz(int $memberId, int $quizId): void
    {
        $this->execute(
            'DELETE p FROM quiz_progression p INNER JOIN quiz_question q ON q.id_question = p.id_question WHERE p.id_user = ? AND q.id_quiz = ?',
            $memberId,
            $quizId
        );
    }

    private function hydrate(array $row): QuizProgress
    {
        return new QuizProgress((int) $row['id_prog'], (int) $row['id_user'], (int) $row['id_question']);
    }
}