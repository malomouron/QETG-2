<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\UseCase\ReportMember;
use QETG\Domain\Entity\Member;
use QETG\Domain\Entity\UserReport;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\UserReportRepositoryInterface;

function assertReportMember(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$memberRepository = new class implements MemberRepositoryInterface {
    public function findById(int $id): ?Member { return $id === 2 ? new Member(2, 'target', '', '', '', '', false, 'default', false, '', '') : null; }
    public function findByLogin(string $login): ?Member { unset($login); return null; }
    public function findByEmail(string $email): ?Member { unset($email); return null; }
    public function searchOthers(int $excludedMemberId, string $loginTerm, int $limit): array { unset($excludedMemberId, $loginTerm, $limit); return array(); }
    public function save(Member $member): Member { return $member; }
    public function delete(int $id): void { unset($id); }
};
$reportRepository = new class implements UserReportRepositoryInterface {
    public array $saved = array();
    public ?UserReport $existing = null;
    public function findByReporterAndTarget(int $reporterMemberId, int $targetMemberId): ?UserReport { unset($reporterMemberId, $targetMemberId); return $this->existing; }
    public function findByTargetMemberId(int $targetMemberId): array { unset($targetMemberId); return array(); }
    public function save(UserReport $report): UserReport { $this->saved[] = $report; return new UserReport(count($this->saved), $report->reporterMemberId, $report->targetMemberId); }
    public function delete(int $id): void { unset($id); }
};

$useCase = new ReportMember($memberRepository, $reportRepository);
$useCase->execute(1, 2);
assertReportMember(count($reportRepository->saved) === 1 && $reportRepository->saved[0]->targetMemberId === 2, 'A valid report should be saved for the selected member.');

foreach (array(array(2, 2), array(1, 3)) as [$reporterId, $targetId]) {
    try {
        $useCase->execute($reporterId, $targetId);
        throw new RuntimeException('Invalid self-report or missing target should be rejected.');
    } catch (InvalidArgumentException) {
    }
}

$reportRepository->existing = new UserReport(4, 1, 2);
try {
    $useCase->execute(1, 2);
    throw new RuntimeException('Duplicate reports should be rejected.');
} catch (InvalidArgumentException $exception) {
    assertReportMember(str_contains($exception->getMessage(), 'déjà'), 'Duplicate reports should return a useful message.');
}

fwrite(STDOUT, "Member reporting checks passed.\n");
