<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\QuizStatistic;

interface QuizStatisticRepositoryInterface
{
    public function findByMemberAndQuiz(int $memberId, int $quizId): ?QuizStatistic;

    /** @return list<QuizStatistic> */
    public function findByMemberId(int $memberId): array;

    /** @return list<QuizStatistic> */
    public function findFavoritesByMemberId(int $memberId): array;

    public function countOfficialByMemberId(int $memberId): int;

    public function save(QuizStatistic $statistic): QuizStatistic;

    public function deleteByMemberAndQuiz(int $memberId, int $quizId): void;
}