<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class Comment
{
    public function __construct(
        public ?int $id,
        public string $lastName,
        public string $firstName,
        public string $email,
        public string $content,
        public string $createdAt,
        public ?string $ipAddress,
    ) {
    }
}