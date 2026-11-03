<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\QuizReaction;
use QETG\Domain\Repository\QuizReactionRepositoryInterface;

final class MySqlQuizReactionRepository extends MySqlRepository implements QuizReactionRepositoryInterface
{
    public function findById(int $id): ?QuizReaction
    {
        $row = $this->one('SELECT * FROM quiz_like_quiz WHERE id_like = ?', $id);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByQuizAndIp(int $quizId, string $ipAddress): ?QuizReaction
    {
        $row = $this->one('SELECT * FROM quiz_like_quiz WHERE id_quiz = ? AND ip = ? LIMIT 1', $quizId, $ipAddress);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByQuizId(int $quizId): array
    {
        $rows = $this->many('SELECT * FROM quiz_like_quiz WHERE id_quiz = ? ORDER BY id_like', $quizId);
        return array_map(fn (array $row): QuizReaction => $this->hydrate($row), $rows);
    }

    public function save(QuizReaction $reaction): QuizReaction
    {
        $values = array($reaction->quizId, $reaction->ipAddress, $reaction->value, (int) $reaction->isMemberQuiz);
        if ($reaction->id === null) {
            $this->execute('INSERT INTO quiz_like_quiz (id_quiz, ip, likeOrDislike, esQuizUser) VALUES (?, ?, ?, ?)', ...$values);
            return new QuizReaction($this->lastInsertId(), $reaction->quizId, $reaction->ipAddress, $reaction->value, $reaction->isMemberQuiz);
        }
        $this->execute(
            'UPDATE quiz_like_quiz SET id_quiz = ?, ip = ?, likeOrDislike = ?, esQuizUser = ? WHERE id_like = ?',
            ...array_merge($values, array($reaction->id))
        );
        return $reaction;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_like_quiz WHERE id_like = ?', $id);
    }

    private function hydrate(array $row): QuizReaction
    {
        return new QuizReaction((int) $row['id_like'], (int) $row['id_quiz'], (string) $row['ip'], (int) $row['likeOrDislike'], (bool) $row['esQuizUser']);
    }
}