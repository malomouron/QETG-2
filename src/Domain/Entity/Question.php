<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class Question
{
    public function __construct(
        public ?int $id,
        public int $quizId,
        public ?int $imageId,
        public ?string $welcomeText,
        public int $difficultyId,
        public string $text,
        public int $number,
        public int $answerCount,
    ) {
    }
}