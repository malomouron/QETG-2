<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\Quiz;
use QETG\Domain\Repository\QuizRepositoryInterface;

final class MySqlQuizRepository extends MySqlRepository implements QuizRepositoryInterface
{
    public function findById(int $id): ?Quiz
    {
        $row = $this->one('SELECT * FROM quiz WHERE id_quiz = ?', $id);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findPublic(int $limit, int $offset): array
    {
        $rows = $this->many(
            'SELECT * FROM quiz WHERE portee = 1 AND quizComplet = 1 ORDER BY id_quiz DESC LIMIT ? OFFSET ?',
            $limit,
            $offset
        );
        return array_map(fn (array $row): Quiz => $this->hydrate($row), $rows);
    }

    public function countPublic(): int
    {
        $row = $this->one('SELECT COUNT(*) AS total FROM quiz WHERE portee = 1 AND quizComplet = 1');
        return (int) ($row['total'] ?? 0);
    }

    public function findOfficial(int $limit, int $offset): array
    {
        $rows = $this->many(
            'SELECT * FROM quiz WHERE id_quiz_user = 0 ORDER BY id_quiz DESC LIMIT ? OFFSET ?',
            $limit,
            $offset
        );
        return array_map(fn (array $row): Quiz => $this->hydrate($row), $rows);
    }

    public function countOfficial(): int
    {
        $row = $this->one('SELECT COUNT(*) AS total FROM quiz WHERE id_quiz_user = 0');
        return (int) ($row['total'] ?? 0);
    }

    public function recordView(int $id): void
    {
        $this->execute('UPDATE quiz SET vue = vue + 1 WHERE id_quiz = ?', $id);
    }

    public function findByCreator(int $memberId): array
    {
        $rows = $this->many('SELECT * FROM quiz WHERE id_user = ? AND id_quiz_user = 1 ORDER BY id_quiz DESC', $memberId);
        return array_map(fn (array $row): Quiz => $this->hydrate($row), $rows);
    }

    public function save(Quiz $quiz): Quiz
    {
        $values = array(
            $quiz->imageId,
            $quiz->name,
            $quiz->welcomeText,
            $quiz->experiencePoints,
            $quiz->creatorMemberId,
            (int) $quiz->isMemberCreated,
            $quiz->source,
            (int) $quiz->isComplete,
            $quiz->questionCount,
            (int) $quiz->isPublic,
            $quiz->viewCount,
        );

        if ($quiz->id === null) {
            $this->execute(
                'INSERT INTO quiz (id_image_quiz, nom_quiz, bienvenue, xp, id_user, id_quiz_user, src, quizComplet, nbrQuestion, portee, vue) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                ...$values
            );
            return new Quiz(
                $this->lastInsertId(),
                $quiz->name,
                $quiz->welcomeText,
                $quiz->experiencePoints,
                $quiz->creatorMemberId,
                $quiz->isMemberCreated,
                $quiz->imageId,
                $quiz->source,
                $quiz->isComplete,
                $quiz->questionCount,
                $quiz->isPublic,
                $quiz->viewCount
            );
        }

        $this->execute(
            'UPDATE quiz SET id_image_quiz = ?, nom_quiz = ?, bienvenue = ?, xp = ?, id_user = ?, id_quiz_user = ?, src = ?, quizComplet = ?, nbrQuestion = ?, portee = ?, vue = ? WHERE id_quiz = ?',
            ...array_merge($values, array($quiz->id))
        );
        return $quiz;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz WHERE id_quiz = ?', $id);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Quiz
    {
        return new Quiz(
            (int) $row['id_quiz'],
            (string) $row['nom_quiz'],
            (string) $row['bienvenue'],
            (int) $row['xp'],
            (int) $row['id_user'],
            (bool) $row['id_quiz_user'],
            $row['id_image_quiz'] === null ? null : (int) $row['id_image_quiz'],
            $row['src'] === null ? null : (string) $row['src'],
            (bool) $row['quizComplet'],
            (int) $row['nbrQuestion'],
            (bool) $row['portee'],
            (int) $row['vue']
        );
    }
}