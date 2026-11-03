<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\Image;

interface ImageRepositoryInterface
{
    public function findById(int $id): ?Image;

    /** @return list<Image> */
    public function findAll(): array;

    public function save(Image $image): Image;

    public function delete(int $id): void;
}