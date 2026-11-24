<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use InvalidArgumentException;
use QETG\Domain\Entity\UserReport;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\UserReportRepositoryInterface;

final class ReportMember
{
    public function __construct(
        private MemberRepositoryInterface $members,
        private UserReportRepositoryInterface $reports
    ) {
    }

    public function execute(int $reporterMemberId, int $targetMemberId): void
    {
        if ($reporterMemberId < 1 || $targetMemberId < 1 || $reporterMemberId === $targetMemberId) {
            throw new InvalidArgumentException('Vous ne pouvez pas signaler ce profil.');
        }
        if ($this->members->findById($targetMemberId) === null) {
            throw new InvalidArgumentException('Ce membre n’existe pas.');
        }
        if ($this->reports->findByReporterAndTarget($reporterMemberId, $targetMemberId) !== null) {
            throw new InvalidArgumentException('Vous avez déjà signalé ce profil.');
        }

        $this->reports->save(new UserReport(null, $reporterMemberId, $targetMemberId));
    }
}
