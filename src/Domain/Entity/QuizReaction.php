<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class QuizReaction
{
    public function __construct(
        public ?int $id,
        public int $quizId,
        public string $ipAddress,
        public int $value,
        public bool $isMemberQuiz,
    ) {
    }
}