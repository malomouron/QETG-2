<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\Difficulty;

interface DifficultyRepositoryInterface
{
    public function findById(int $id): ?Difficulty;

    /** @return list<Difficulty> */
    public function findAll(): array;

    public function save(Difficulty $difficulty): Difficulty;

    public function delete(int $id): void;
}