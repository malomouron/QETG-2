<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\CommentReply;

interface CommentReplyRepositoryInterface
{
    public function findById(int $id): ?CommentReply;

    /** @return list<CommentReply> */
    public function findByCommentId(int $commentId): array;

    public function save(CommentReply $reply): CommentReply;

    public function delete(int $id): void;
}