<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\MemberAchievement;
use QETG\Domain\Repository\MemberAchievementRepositoryInterface;

final class MySqlMemberAchievementRepository extends MySqlRepository implements MemberAchievementRepositoryInterface
{
    public function hasAchievement(int $memberId, int $achievementId): bool
    {
        return $this->one(
            'SELECT id_membre_succes FROM quiz_membre_succes WHERE id_membre = ? AND id_succes = ? LIMIT 1',
            $memberId,
            $achievementId
        ) !== null;
    }

    public function findByMemberId(int $memberId): array
    {
        $rows = $this->many('SELECT * FROM quiz_membre_succes WHERE id_membre = ? ORDER BY id_membre_succes', $memberId);
        return array_map(fn (array $row): MemberAchievement => $this->hydrate($row), $rows);
    }

    public function save(MemberAchievement $memberAchievement): MemberAchievement
    {
        if ($memberAchievement->id === null) {
            $this->execute(
                'INSERT INTO quiz_membre_succes (id_succes, id_membre, date_succes) VALUES (?, ?, ?)',
                $memberAchievement->achievementId,
                $memberAchievement->memberId,
                $memberAchievement->awardedAt
            );
            return new MemberAchievement($this->lastInsertId(), $memberAchievement->achievementId, $memberAchievement->memberId, $memberAchievement->awardedAt);
        }
        $this->execute(
            'UPDATE quiz_membre_succes SET id_succes = ?, id_membre = ?, date_succes = ? WHERE id_membre_succes = ?',
            $memberAchievement->achievementId,
            $memberAchievement->memberId,
            $memberAchievement->awardedAt,
            $memberAchievement->id
        );
        return $memberAchievement;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_membre_succes WHERE id_membre_succes = ?', $id);
    }

    private function hydrate(array $row): MemberAchievement
    {
        return new MemberAchievement((int) $row['id_membre_succes'], (int) $row['id_succes'], (int) $row['id_membre'], (string) $row['date_succes']);
    }
}