<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class Answer
{
    public function __construct(
        public ?int $id,
        public int $questionId,
        public ?int $imageId,
        public string $choice,
        public bool $isCorrect,
        public ?string $explanation,
        public ?int $number,
    ) {
    }
}