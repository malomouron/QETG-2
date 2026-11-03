<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class MemberAchievement
{
    public function __construct(
        public ?int $id,
        public int $achievementId,
        public int $memberId,
        public string $awardedAt,
    ) {
    }
}