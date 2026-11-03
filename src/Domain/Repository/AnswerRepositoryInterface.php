<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\Answer;

interface AnswerRepositoryInterface
{
    public function findById(int $id): ?Answer;

    /** @return list<Answer> Answers ordered by their position in the question. */
    public function findByQuestionId(int $questionId): array;

    public function save(Answer $answer): Answer;

    public function delete(int $id): void;

    public function deleteByQuestionId(int $questionId): void;
}