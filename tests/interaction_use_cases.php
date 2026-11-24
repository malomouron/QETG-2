<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\Port\CaptchaVerifierInterface;
use QETG\Application\Port\OptionalEmailSenderInterface;
use QETG\Application\UseCase\SubmitFeedback;
use QETG\Application\UseCase\ToggleQuizReaction;
use QETG\Domain\Entity\Comment;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Entity\QuizReaction;
use QETG\Domain\Repository\CommentRepositoryInterface;
use QETG\Domain\Repository\QuizReactionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;
use QETG\Presentation\Http\Controller\InteractionController;

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

$homeViewSource = file_get_contents(dirname(__DIR__) . '/src/Presentation/View/home.php');
if (strpos($homeViewSource, '/interactions/reponse-commentaire') === false
    || strpos($homeViewSource, 'name="_csrf" value="<?= homeEscape($interactionCsrf) ?>"') === false
    || strpos($homeViewSource, 'name="comment_id"') === false
) {
    throw new RuntimeException('Comment reply form must use the dedicated POST route and carry CSRF protection.');
}

$quiz = new Quiz(7, 'Quiz test', '', 5, 1, false, null, null, true, 2, true, 0);
$quizRepository = new class($quiz) implements QuizRepositoryInterface {
    public function __construct(private Quiz $quiz) {}
    public function findById(int $id): ?Quiz { return $id === $this->quiz->id ? $this->quiz : null; }
    public function findPublic(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countPublic(): int { return 1; }
    public function findOfficial(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countOfficial(): int { return 1; }
    public function searchVisibleByName(string $searchTerm, int $limit): array { unset($searchTerm, $limit); return array(); }
    public function findMemberQuizzes(?string $searchTerm, ?int $quizId, int $limit): array { unset($searchTerm, $quizId, $limit); return array(); }
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

$scenario = $argv[1] ?? null;
if ($scenario !== null) {
    $captchaMisconfigured = str_ends_with($scenario, 'partial');
    $_SESSION = array('interaction_csrf' => 'test-csrf');

    if (str_starts_with($scenario, 'view-')) {
        $siteKey = $scenario === 'view-disabled' ? '' : 'public&key';
        $basePath = '';
        $homeUrl = '/accueil';
        $searchUrl = '/recherche';
        $loggedIn = true;
        $member = null;
        $displayLogin = 'Test';
        $completedOfficialQuizzes = 0;
        $quizCards = array(array('quiz' => $quiz, 'image' => null, 'likes' => 0, 'dislikes' => 0, 'myReaction' => null, 'isFavorite' => false));
        $page = $totalPages = $totalQuizzes = 1;
        $commentCards = array(array('comment' => $commentRepository->rows[0], 'replies' => array()));
        $interactionFlash = null;
        $interactionCsrf = $_SESSION['interaction_csrf'];

        ob_start();
        require dirname(__DIR__) . '/src/Presentation/View/home.php';
        $html = ob_get_clean();
        assertInteractionSame(!$captchaMisconfigured, str_contains($html, 'id="home-comment-form"'), 'Only partial configuration hides feedback.');
        assertInteractionSame($captchaMisconfigured, str_contains($html, 'la vérification anti-robot est mal configurée'), 'Partial configuration explains the outage.');
        assertInteractionSame($scenario === 'view-enabled', str_contains($html, 'data-sitekey="public&amp;key"'), 'Only enabled CAPTCHA renders the escaped public key.');
        assertInteractionSame(false, str_contains($html, 'google.com/recaptcha/api.js'), 'The view must not bypass the consent-managed CAPTCHA loader.');
        assertInteractionSame(true, str_contains($html, 'action="/interactions/reaction"'), 'Quiz reactions remain visible.');
        assertInteractionSame(true, str_contains($html, 'action="/interactions/reponse-commentaire"'), 'Member replies remain visible.');
        exit;
    }

    $verifier = new class implements CaptchaVerifierInterface {
        public array $calls = array();
        public function verify(string $responseToken, string $remoteAddress): bool
        {
            $this->calls[] = array($responseToken, $remoteAddress);
            return $responseToken === 'accepted' && $remoteAddress === '192.0.2.5';
        }
    };
    $controller = new InteractionController(
        $feedback,
        $toggleReaction,
        '',
        str_starts_with($scenario, 'enabled-') ? $verifier : null,
        $captchaMisconfigured
    );
    $_SERVER['REMOTE_ADDR'] = '192.0.2.5';
    $_POST = array(
        '_csrf' => $scenario === 'disabled-no-csrf' ? '' : 'test-csrf',
        'user_name' => 'Test',
        'user_prenom' => 'Test',
        'user_mail' => 'test@example.invalid',
        'user_message' => 'Feedback under test',
        'quiz_id' => '7',
        'value' => '1',
        'g-recaptcha-response' => match ($scenario) {
            'enabled-valid' => ' accepted ',
            'enabled-invalid' => 'rejected',
            'enabled-malformed' => array('accepted'),
            default => null,
        },
    );

    // Both actions end with a redirect and exit. Check state after the action exits.
    register_shutdown_function(static function () use ($scenario, $commentRepository, $reactionRepository, $verifier): void {
        $saved = in_array($scenario, array('disabled', 'enabled-valid'), true);
        $reacted = $scenario === 'reaction-partial';
        $expectedCalls = match ($scenario) {
            'enabled-valid' => array(array('accepted', '192.0.2.5')),
            'enabled-invalid' => array(array('rejected', '192.0.2.5')),
            default => array(),
        };
        $flash = $_SESSION['interaction_flash'] ?? array();
        assertInteractionSame(303, http_response_code(), 'Action redirects with See Other.');
        assertInteractionSame($saved ? 2 : 1, count($commentRepository->rows), 'Feedback persistence respects CAPTCHA and CSRF.');
        assertInteractionSame($reacted ? 1 : null, $reactionRepository->state?->value, 'Partial CAPTCHA does not block reactions.');
        assertInteractionSame($expectedCalls, $verifier->calls, 'Only nonempty, well-formed tokens reach the verifier.');
        assertInteractionSame($saved || $reacted ? 'success' : 'error', $flash['type'] ?? null, 'Action reports the outcome.');
        if ($scenario === 'feedback-partial') {
            assertInteractionSame(true, str_contains($flash['message'], 'la vérification anti-robot est mal configurée'), 'Blocked POST explains the outage.');
        }
    });
    if ($scenario === 'reaction-partial') {
        $controller->reaction();
    }
    $controller->comment();
}

foreach (array('view-disabled', 'view-enabled', 'view-partial', 'disabled', 'disabled-no-csrf', 'feedback-partial', 'reaction-partial', 'enabled-valid', 'enabled-invalid', 'enabled-missing', 'enabled-malformed') as $scenario) {
    $process = proc_open(array(PHP_BINARY, __FILE__, $scenario), array(1 => array('pipe', 'w'), 2 => array('redirect', 1)), $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start interaction check: ' . $scenario);
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    assertInteractionSame(0, proc_close($process), 'Interaction scenario ' . $scenario . ': ' . $output);
    assertInteractionSame('', $output, 'Interaction scenario should not produce warnings: ' . $scenario);
}

fwrite(STDOUT, "Interaction use-case checks passed.\n");
