<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class Quiz
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $welcomeText,
        public int $experiencePoints,
        public int $creatorMemberId,
        public bool $isMemberCreated,
        public ?int $imageId,
        public ?string $source,
        public bool $isComplete,
        public int $questionCount,
        public bool $isPublic,
        public int $viewCount,
    ) {
    }

    public function isVisibleTo(?int $memberId): bool
    {
        return $this->isPublic || ($memberId !== null && $memberId === $this->creatorMemberId);
    }
}