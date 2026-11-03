<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\Comment;
use QETG\Domain\Repository\CommentRepositoryInterface;

final class MySqlCommentRepository extends MySqlRepository implements CommentRepositoryInterface
{
    public function findById(int $id): ?Comment
    {
        $row = $this->one('SELECT * FROM quiz_commentaire WHERE id = ?', $id);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findRecent(int $limit, int $offset): array
    {
        $rows = $this->many('SELECT * FROM quiz_commentaire ORDER BY id DESC LIMIT ? OFFSET ?', $limit, $offset);
        return array_map(fn (array $row): Comment => $this->hydrate($row), $rows);
    }

    public function save(Comment $comment): Comment
    {
        $values = array($comment->lastName, $comment->firstName, $comment->email, $comment->content, $comment->createdAt, $comment->ipAddress);
        if ($comment->id === null) {
            $this->execute(
                'INSERT INTO quiz_commentaire (nom, prenom, mail, commentaire, date_creation, ip_utilisateur) VALUES (?, ?, ?, ?, ?, ?)',
                ...$values
            );
            return new Comment($this->lastInsertId(), ...$values);
        }
        $this->execute(
            'UPDATE quiz_commentaire SET nom = ?, prenom = ?, mail = ?, commentaire = ?, date_creation = ?, ip_utilisateur = ? WHERE id = ?',
            ...array_merge($values, array($comment->id))
        );
        return $comment;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_commentaire WHERE id = ?', $id);
    }

    private function hydrate(array $row): Comment
    {
        return new Comment(
            (int) $row['id'],
            (string) $row['nom'],
            (string) $row['prenom'],
            (string) $row['mail'],
            (string) $row['commentaire'],
            (string) $row['date_creation'],
            $row['ip_utilisateur'] === null ? null : (string) $row['ip_utilisateur']
        );
    }
}