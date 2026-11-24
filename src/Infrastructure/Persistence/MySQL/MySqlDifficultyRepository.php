<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\Difficulty;
use QETG\Domain\Repository\DifficultyRepositoryInterface;

final class MySqlDifficultyRepository extends MySqlRepository implements DifficultyRepositoryInterface
{
    public function findById(int $id): ?Difficulty
    {
        $row = $this->one('SELECT * FROM quiz_difficulte WHERE id_difficulte = ?', $id);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findAll(): array
    {
        $rows = $this->many(
            "SELECT * FROM quiz_difficulte ORDER BY CASE libelle WHEN 'Très simple' THEN 1 WHEN 'Simple' THEN 2 WHEN 'Intermédiaire' THEN 3 WHEN 'Difficile' THEN 4 WHEN 'Très difficile' THEN 5 ELSE 99 END, id_difficulte"
        );
        return array_map(fn (array $row): Difficulty => $this->hydrate($row), $rows);
    }

    public function save(Difficulty $difficulty): Difficulty
    {
        if ($difficulty->id === null) {
            $this->execute('INSERT INTO quiz_difficulte (libelle) VALUES (?)', $difficulty->label);
            return new Difficulty($this->lastInsertId(), $difficulty->label);
        }
        $this->execute('UPDATE quiz_difficulte SET libelle = ? WHERE id_difficulte = ?', $difficulty->label, $difficulty->id);
        return $difficulty;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_difficulte WHERE id_difficulte = ?', $id);
    }

    private function hydrate(array $row): Difficulty
    {
        return new Difficulty((int) $row['id_difficulte'], (string) $row['libelle']);
    }
}