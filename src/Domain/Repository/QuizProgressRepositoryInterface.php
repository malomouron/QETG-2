<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\QuizProgress;

interface QuizProgressRepositoryInterface
{
    public function findByMemberAndQuiz(int $memberId, int $quizId): ?QuizProgress;

    /** @return list<QuizProgress> */
    public function findByMemberId(int $memberId): array;

    public function save(QuizProgress $progress): QuizProgress;

    public function deleteByMemberAndQuiz(int $memberId, int $quizId): void;
}