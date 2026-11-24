<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class NewsletterRecipient
{
    public function __construct(
        public int $memberId,
        public string $login,
        public string $email,
    ) {
    }
}
