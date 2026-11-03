<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class QuizStatistic
{
    public function __construct(
        public int $memberId,
        public int $quizId,
        public string $playedAt,
        public int $score,
        public bool $isFavorite,
        public bool $isMemberQuiz,
    ) {
    }
}