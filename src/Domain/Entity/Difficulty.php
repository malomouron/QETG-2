<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class Difficulty
{
    public function __construct(
        public ?int $id,
        public string $label,
    ) {
    }
}