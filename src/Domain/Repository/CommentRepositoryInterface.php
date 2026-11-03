<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\Comment;

interface CommentRepositoryInterface
{
    public function findById(int $id): ?Comment;

    /** @return list<Comment> */
    public function findRecent(int $limit, int $offset): array;

    public function save(Comment $comment): Comment;

    public function delete(int $id): void;
}