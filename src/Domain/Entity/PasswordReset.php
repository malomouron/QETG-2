<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

use DateTimeImmutable;

final readonly class PasswordReset
{
    public function __construct(
        public ?int $id,
        public int $memberId,
        public string $tokenHash,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $createdAt = null
    ) {
    }
}
