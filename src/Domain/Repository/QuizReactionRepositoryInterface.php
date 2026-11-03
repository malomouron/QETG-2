<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\QuizReaction;

interface QuizReactionRepositoryInterface
{
    public function findById(int $id): ?QuizReaction;

    public function findByQuizAndIp(int $quizId, string $ipAddress): ?QuizReaction;

    /** @return list<QuizReaction> */
    public function findByQuizId(int $quizId): array;

    public function save(QuizReaction $reaction): QuizReaction;

    public function delete(int $id): void;
}