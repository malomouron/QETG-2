<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class PendingRegistration
{
    public function __construct(
        public ?int $id,
        public string $confirmationToken,
        public string $login,
        public string $passwordHash,
        public string $email,
        public string $lastName,
        public string $firstName,
        public bool $newsletterEnabled,
        public string $createdOn,
    ) {
    }
}