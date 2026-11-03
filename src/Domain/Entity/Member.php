<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class Member
{
    public function __construct(
        public ?int $id,
        public string $login,
        public string $passwordHash,
        public string $email,
        public string $lastName,
        public string $firstName,
        public bool $isAdministrator,
        public string $profileImage,
        public bool $newsletterEnabled,
        public string $registrationIp,
        public string $registeredAt,
    ) {
    }
}