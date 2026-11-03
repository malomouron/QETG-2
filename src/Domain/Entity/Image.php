<?php

declare(strict_types=1);

namespace QETG\Domain\Entity;

final readonly class Image
{
    public function __construct(
        public ?int $id,
        public string $source,
        public string $altText,
    ) {
    }
}