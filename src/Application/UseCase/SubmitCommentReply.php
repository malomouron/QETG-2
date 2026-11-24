<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use InvalidArgumentException;
use QETG\Domain\Entity\CommentReply;
use QETG\Domain\Repository\CommentReplyRepositoryInterface;
use QETG\Domain\Repository\CommentRepositoryInterface;
use QETG\Domain\Repository\MemberRepositoryInterface;

final class SubmitCommentReply
{
    public function __construct(
        private CommentRepositoryInterface $comments,
        private MemberRepositoryInterface $members,
        private CommentReplyRepositoryInterface $replies
    ) {
    }

    public function execute(int $commentId, int $memberId, string $content, string $ipAddress): CommentReply
    {
        $content = trim($content);
        if ($commentId < 1 || $memberId < 1) {
            throw new InvalidArgumentException('Le commentaire ou le compte est invalide.');
        }
        if ($this->comments->findById($commentId) === null) {
            throw new InvalidArgumentException('Ce commentaire n’existe plus.');
        }
        if ($this->members->findById($memberId) === null) {
            throw new InvalidArgumentException('Connectez-vous pour répondre à un commentaire.');
        }
        if ($content === '' || strlen($content) > 2000) {
            throw new InvalidArgumentException('La réponse doit contenir entre 1 et 2000 caractères.');
        }
        if ($ipAddress === '' || strlen($ipAddress) > 45 || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Adresse réseau invalide.');
        }

        return $this->replies->save(new CommentReply(null, $commentId, $memberId, $content, $ipAddress));
    }
}
