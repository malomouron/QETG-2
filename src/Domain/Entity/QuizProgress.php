<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class QuizProgress
{
    public function __construct(
        public ?int $id,
        public int $memberId,
        public int $questionId,
    ) {
    }
}