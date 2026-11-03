<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\MemberAchievement;

interface MemberAchievementRepositoryInterface
{
    public function hasAchievement(int $memberId, int $achievementId): bool;

    /** @return list<MemberAchievement> */
    public function findByMemberId(int $memberId): array;

    public function save(MemberAchievement $memberAchievement): MemberAchievement;

    public function delete(int $id): void;
}