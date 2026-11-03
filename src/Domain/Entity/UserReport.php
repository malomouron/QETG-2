<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class UserReport
{
    public function __construct(
        public ?int $id,
        public int $reporterMemberId,
        public int $targetMemberId,
    ) {
    }
}