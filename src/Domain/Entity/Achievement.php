<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class Achievement
{
    public function __construct(
        public ?int $id,
        public string $name,
        public int $experienceReward,
        public string $description,
    ) {
    }
}