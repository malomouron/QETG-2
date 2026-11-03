<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\Achievement;
use QETG\Domain\Repository\AchievementRepositoryInterface;

final class MySqlAchievementRepository extends MySqlRepository implements AchievementRepositoryInterface
{
    public function findById(int $id): ?Achievement
    {
        $row = $this->one('SELECT * FROM quiz_succes WHERE id_succes = ?', $id);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findAll(): array
    {
        return array_map(fn (array $row): Achievement => $this->hydrate($row), $this->many('SELECT * FROM quiz_succes ORDER BY id_succes'));
    }

    public function save(Achievement $achievement): Achievement
    {
        if ($achievement->id === null) {
            $this->execute(
                'INSERT INTO quiz_succes (nom_succes, xp_succes, descri_succes) VALUES (?, ?, ?)',
                $achievement->name,
                $achievement->experienceReward,
                $achievement->description
            );
            return new Achievement($this->lastInsertId(), $achievement->name, $achievement->experienceReward, $achievement->description);
        }
        $this->execute(
            'UPDATE quiz_succes SET nom_succes = ?, xp_succes = ?, descri_succes = ? WHERE id_succes = ?',
            $achievement->name,
            $achievement->experienceReward,
            $achievement->description,
            $achievement->id
        );
        return $achievement;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_succes WHERE id_succes = ?', $id);
    }

    private function hydrate(array $row): Achievement
    {
        return new Achievement((int) $row['id_succes'], (string) $row['nom_succes'], (int) $row['xp_succes'], (string) $row['descri_succes']);
    }
}