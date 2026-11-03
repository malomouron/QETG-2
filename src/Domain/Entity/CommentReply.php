<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class CommentReply
{
    public function __construct(
        public ?int $id,
        public int $commentId,
        public int $memberId,
        public string $content,
        public string $ipAddress,
    ) {
    }
}