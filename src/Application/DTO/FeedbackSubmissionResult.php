<?php

declare(strict_types=1);

namespace QETG\Application\DTO;

final readonly class FeedbackSubmissionResult
{
    public function __construct(
        public int $commentId,
        public bool $notificationSent
    ) {
    }
}
