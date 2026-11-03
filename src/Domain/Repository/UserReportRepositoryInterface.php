<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\UserReport;

interface UserReportRepositoryInterface
{
    public function findByReporterAndTarget(int $reporterMemberId, int $targetMemberId): ?UserReport;

    /** @return list<UserReport> Reports received by a member. */
    public function findByTargetMemberId(int $targetMemberId): array;

    public function save(UserReport $report): UserReport;

    public function delete(int $id): void;
}