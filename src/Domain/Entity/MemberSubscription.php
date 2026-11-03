<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

use DateTimeImmutable;

final readonly class MemberSubscription
{
    public function __construct(
        public ?int $id,
        public int $subscriberMemberId,
        public int $followedMemberId,
        public ?DateTimeImmutable $createdAt,
    ) {
    }
}