<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\UseCase\SubmitCommentReply;
use QETG\Domain\Entity\Comment;
use QETG\Domain\Entity\CommentReply;
use QETG\Domain\Entity\Member;
use QETG\Domain\Repository\CommentReplyRepositoryInterface;
use QETG\Domain\Repository\CommentRepositoryInterface;
use QETG\Domain\Repository\MemberRepositoryInterface;

function assertCommentReplySame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$comments = new class implements CommentRepositoryInterface {
    public function findById(int $id): ?Comment
    {
        return $id === 8 ? new Comment(8, 'Nom', 'Prénom', 'private@example.invalid', 'Commentaire initial', '2026-11-10', '192.0.2.2') : null;
    }
    public function findRecent(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function save(Comment $comment): Comment { return $comment; }
    public function delete(int $id): void { unset($id); }
};
$members = new class implements MemberRepositoryInterface {
    public function findById(int $id): ?Member { return $id === 3 ? new Member(3, 'membre', '', '', '', '', false, 'default', false, '', '') : null; }
    public function findByLogin(string $login): ?Member { unset($login); return null; }
    public function findByEmail(string $email): ?Member { unset($email); return null; }
    public function searchOthers(int $excludedMemberId, string $loginTerm, int $limit): array { unset($excludedMemberId, $loginTerm, $limit); return array(); }
    public function save(Member $member): Member { return $member; }
    public function delete(int $id): void { unset($id); }
};
$replies = new class implements CommentReplyRepositoryInterface {
    /** @var list<CommentReply> */
    public array $saved = array();
    public function findById(int $id): ?CommentReply { unset($id); return null; }
    public function findByCommentId(int $commentId): array { return array_values(array_filter($this->saved, static fn (CommentReply $reply): bool => $reply->commentId === $commentId)); }
    public function save(CommentReply $reply): CommentReply
    {
        $saved = new CommentReply(count($this->saved) + 1, $reply->commentId, $reply->memberId, $reply->content, $reply->ipAddress);
        $this->saved[] = $saved;
        return $saved;
    }
    public function delete(int $id): void { unset($id); }
};

$useCase = new SubmitCommentReply($comments, $members, $replies);
$reply = $useCase->execute(8, 3, '  Merci pour votre message.  ', '192.0.2.1');
assertCommentReplySame(1, $reply->id, 'A reply should be persisted.');
assertCommentReplySame('Merci pour votre message.', $reply->content, 'Reply content should be trimmed.');
assertCommentReplySame(8, $reply->commentId, 'Reply should reference the selected comment.');
assertCommentReplySame(3, $reply->memberId, 'Reply should reference the authenticated member.');
assertCommentReplySame(1, count($replies->saved), 'Exactly one reply should be stored.');

foreach (array(
    array(999, 3, 'Réponse', '192.0.2.1', 'Ce commentaire n’existe plus.'),
    array(8, 999, 'Réponse', '192.0.2.1', 'Connectez-vous pour répondre à un commentaire.'),
    array(8, 3, '   ', '192.0.2.1', 'La réponse doit contenir entre 1 et 2000 caractères.'),
    array(8, 3, str_repeat('x', 2001), '192.0.2.1', 'La réponse doit contenir entre 1 et 2000 caractères.'),
    array(8, 3, 'Réponse', 'not-an-ip', 'Adresse réseau invalide.'),
) as [$commentId, $memberId, $content, $ip, $expectedMessage]) {
    try {
        $useCase->execute($commentId, $memberId, $content, $ip);
        throw new RuntimeException('Invalid reply data should be rejected.');
    } catch (InvalidArgumentException $exception) {
        assertCommentReplySame($expectedMessage, $exception->getMessage(), 'Invalid reply should expose the expected validation error.');
    }
}

assertCommentReplySame(1, count($replies->saved), 'Invalid replies must not be persisted.');
fwrite(STDOUT, "Comment reply use-case checks passed.\n");
