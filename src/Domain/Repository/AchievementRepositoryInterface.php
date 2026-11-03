<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\Achievement;

interface AchievementRepositoryInterface
{
    public function findById(int $id): ?Achievement;

    /** @return list<Achievement> */
    public function findAll(): array;

    public function save(Achievement $achievement): Achievement;

    public function delete(int $id): void;
}