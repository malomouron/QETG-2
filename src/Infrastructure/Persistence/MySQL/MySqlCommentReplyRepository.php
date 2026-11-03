<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\CommentReply;
use QETG\Domain\Repository\CommentReplyRepositoryInterface;

final class MySqlCommentReplyRepository extends MySqlRepository implements CommentReplyRepositoryInterface
{
    public function findById(int $id): ?CommentReply
    {
        $row = $this->one('SELECT * FROM quiz_reponse_commentaire WHERE id_reponse_commentaire = ?', $id);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByCommentId(int $commentId): array
    {
        $rows = $this->many('SELECT * FROM quiz_reponse_commentaire WHERE id_commentaire = ? ORDER BY id_reponse_commentaire', $commentId);
        return array_map(fn (array $row): CommentReply => $this->hydrate($row), $rows);
    }

    public function save(CommentReply $reply): CommentReply
    {
        $values = array($reply->commentId, $reply->memberId, $reply->content, $reply->ipAddress);
        if ($reply->id === null) {
            $this->execute(
                'INSERT INTO quiz_reponse_commentaire (id_commentaire, id_user, reponse_val, ip_rep) VALUES (?, ?, ?, ?)',
                ...$values
            );
            return new CommentReply($this->lastInsertId(), ...$values);
        }
        $this->execute(
            'UPDATE quiz_reponse_commentaire SET id_commentaire = ?, id_user = ?, reponse_val = ?, ip_rep = ? WHERE id_reponse_commentaire = ?',
            ...array_merge($values, array($reply->id))
        );
        return $reply;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_reponse_commentaire WHERE id_reponse_commentaire = ?', $id);
    }

    private function hydrate(array $row): CommentReply
    {
        return new CommentReply(
            (int) $row['id_reponse_commentaire'],
            (int) $row['id_commentaire'],
            (int) $row['id_user'],
            (string) $row['reponse_val'],
            (string) $row['ip_rep']
        );
    }
}