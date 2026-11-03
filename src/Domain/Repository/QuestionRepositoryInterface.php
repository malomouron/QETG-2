<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\Question;

interface QuestionRepositoryInterface
{
    public function findById(int $id): ?Question;

    public function findByQuizAndNumber(int $quizId, int $number): ?Question;

    /** @return list<Question> Questions ordered by their position in the quiz. */
    public function findByQuizId(int $quizId): array;

    public function save(Question $question): Question;

    public function delete(int $id): void;

    public function deleteByQuizId(int $quizId): void;
}