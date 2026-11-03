<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\Image;
use QETG\Domain\Repository\ImageRepositoryInterface;

final class MySqlImageRepository extends MySqlRepository implements ImageRepositoryInterface
{
    public function findById(int $id): ?Image
    {
        $row = $this->one('SELECT * FROM quiz_image WHERE id_image = ?', $id);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findAll(): array
    {
        return array_map(fn (array $row): Image => $this->hydrate($row), $this->many('SELECT * FROM quiz_image ORDER BY id_image'));
    }

    public function save(Image $image): Image
    {
        if ($image->id === null) {
            $this->execute('INSERT INTO quiz_image (src, alt) VALUES (?, ?)', $image->source, $image->altText);
            return new Image($this->lastInsertId(), $image->source, $image->altText);
        }
        $this->execute('UPDATE quiz_image SET src = ?, alt = ? WHERE id_image = ?', $image->source, $image->altText, $image->id);
        return $image;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_image WHERE id_image = ?', $id);
    }

    private function hydrate(array $row): Image
    {
        return new Image((int) $row['id_image'], (string) $row['src'], (string) $row['alt']);
    }
}