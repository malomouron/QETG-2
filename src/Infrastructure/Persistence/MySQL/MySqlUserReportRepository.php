<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\UserReport;
use QETG\Domain\Repository\UserReportRepositoryInterface;

final class MySqlUserReportRepository extends MySqlRepository implements UserReportRepositoryInterface
{
    public function findByReporterAndTarget(int $reporterMemberId, int $targetMemberId): ?UserReport
    {
        $row = $this->one(
            'SELECT * FROM quiz_signalement WHERE id_user_origine = ? AND id_user_cible = ? LIMIT 1',
            $reporterMemberId,
            $targetMemberId
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByTargetMemberId(int $targetMemberId): array
    {
        $rows = $this->many('SELECT * FROM quiz_signalement WHERE id_user_cible = ? ORDER BY id_signalement DESC', $targetMemberId);
        return array_map(fn (array $row): UserReport => $this->hydrate($row), $rows);
    }

    public function save(UserReport $report): UserReport
    {
        if ($report->id === null) {
            $this->execute(
                'INSERT INTO quiz_signalement (id_user_origine, id_user_cible) VALUES (?, ?)',
                $report->reporterMemberId,
                $report->targetMemberId
            );
            return new UserReport($this->lastInsertId(), $report->reporterMemberId, $report->targetMemberId);
        }
        $this->execute(
            'UPDATE quiz_signalement SET id_user_origine = ?, id_user_cible = ? WHERE id_signalement = ?',
            $report->reporterMemberId,
            $report->targetMemberId,
            $report->id
        );
        return $report;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_signalement WHERE id_signalement = ?', $id);
    }

    private function hydrate(array $row): UserReport
    {
        return new UserReport((int) $row['id_signalement'], (int) $row['id_user_origine'], (int) $row['id_user_cible']);
    }
}