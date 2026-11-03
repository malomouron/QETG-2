<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\Port\OptionalEmailSenderInterface;
use QETG\Application\UseCase\SubmitFeedback;
use QETG\Application\UseCase\ToggleQuizReaction;
use QETG\Domain\Entity\Comment;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Entity\QuizReaction;
use QETG\Domain\Repository\CommentRepositoryInterface;
use QETG\Domain\Repository\QuizReactionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;

function assertInteractionSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$commentRepository = new class implements CommentRepositoryInterface {
    /** @param list<Comment> $rows */
    public array $rows = array();
    public function findById(int $id): ?Comment { unset($id); return null; }
    public function findRecent(int $limit, int $offset): array { unset($limit, $offset); return $this->rows; }
    public function save(Comment $comment): Comment
    {
        $saved = new Comment(count($this->rows) + 1, $comment->lastName, $comment->firstName, $comment->email, $comment->content, $comment->createdAt, $comment->ipAddress);
        $this->rows[] = $saved;
        return $saved;
    }
    public function delete(int $id): void { unset($id); }
};

$emailSender = new class implements OptionalEmailSenderInterface {
    public function isConfigured(): bool { return false; }
    public function send(string $recipient, string $subject, string $htmlBody, string $textBody): void
    {
        unset($recipient, $subject, $htmlBody, $textBody);
        throw new RuntimeException('Unconfigured transport must never be invoked.');
    }
};

$feedback = new SubmitFeedback($commentRepository, $emailSender);
$result = $feedback->execute('Malo', 'Malo', 'malo@example.invalid', 'Commentaire de démonstration', '192.0.2.1', true);
assertInteractionSame(1, $result->commentId, 'Feedback is stored without SMTP.');
assertInteractionSame(false, $result->notificationSent, 'Missing SMTP is reported as an unavailable notification.');
assertInteractionSame(1, count($commentRepository->rows), 'Exactly one feedback row is persisted.');
assertInteractionSame('Commentaire de démonstration', $commentRepository->rows[0]->content, 'Feedback content is preserved.');

$quiz = new Quiz(7, 'Quiz test', '', 5, 1, false, null, null, true, 2, true, 0);
$quizRepository = new class($quiz) implements QuizRepositoryInterface {
    public function __construct(private Quiz $quiz) {}
    public function findById(int $id): ?Quiz { return $id === $this->quiz->id ? $this->quiz : null; }
    public function findPublic(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countPublic(): int { return 1; }
    public function findOfficial(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countOfficial(): int { return 1; }
    public function recordView(int $id): void { unset($id); }
    public function findByCreator(int $memberId): array { unset($memberId); return array(); }
    public function save(Quiz $quiz): Quiz { return $quiz; }
    public function delete(int $id): void { unset($id); }
};

 $reactionRepository = new class implements QuizReactionRepositoryInterface {
    public ?QuizReaction $state = null;
    public function findById(int $id): ?QuizReaction { return $this->state?->id === $id ? $this->state : null; }
    public function findByQuizAndIp(int $quizId, string $ipAddress): ?QuizReaction
    {
        return $this->state !== null && $this->state->quizId === $quizId && $this->state->ipAddress === $ipAddress ? $this->state : null;
    }
    public function findByQuizId(int $quizId): array { return $this->state?->quizId === $quizId ? array($this->state) : array(); }
    public function save(QuizReaction $reaction): QuizReaction
    {
        $this->state = new QuizReaction($reaction->id ?? 1, $reaction->quizId, $reaction->ipAddress, $reaction->value, $reaction->isMemberQuiz);
        return $this->state;
    }
    public function delete(int $id): void
    {
        if ($this->state?->id === $id) {
            $this->state = null;
        }
    }
};

$toggleReaction = new ToggleQuizReaction($quizRepository, $reactionRepository);
assertInteractionSame(1, $toggleReaction->execute(7, '192.0.2.5', 1), 'First like is stored.');
assertInteractionSame(0, $toggleReaction->execute(7, '192.0.2.5', 0), 'Reaction switches from like to dislike.');
assertInteractionSame(null, $toggleReaction->execute(7, '192.0.2.5', 0), 'Repeating a reaction removes it.');
assertInteractionSame(null, $reactionRepository->state, 'Removed reaction no longer exists.');

fwrite(STDOUT, "Interaction use-case checks passed.\n");
