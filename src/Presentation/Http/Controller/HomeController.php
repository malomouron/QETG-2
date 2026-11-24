<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use QETG\Domain\Repository\CommentReplyRepositoryInterface;
use QETG\Domain\Repository\CommentRepositoryInterface;
use QETG\Domain\Repository\ImageRepositoryInterface;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\QuizReactionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;

final class HomeController
{
    public function __construct(
        private QuizRepositoryInterface $quizzes,
        private ImageRepositoryInterface $images,
        private QuizReactionRepositoryInterface $reactions,
        private QuizStatisticRepositoryInterface $statistics,
        private MemberRepositoryInterface $members,
        private CommentRepositoryInterface $comments,
        private CommentReplyRepositoryInterface $commentReplies,
        private int $quizzesPerPage,
        private string $basePath,
        private string $captchaSiteKey,
        private bool $captchaMisconfigured
    ) {
    }

    public function index(): void
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
        $page = \is_int($page) && $page > 0 ? $page : 1;
        $perPage = max(1, min(30, $this->quizzesPerPage));
        $totalQuizzes = $this->quizzes->countOfficial();
        $totalPages = max(1, (int) ceil($totalQuizzes / $perPage));
        $page = min($page, $totalPages);
        $quizEntities = $this->quizzes->findOfficial($perPage, ($page - 1) * $perPage);
        $memberId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
        $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        $quizCards = array();
        foreach ($quizEntities as $quiz) {
            $image = $quiz->imageId === null ? null : $this->images->findById($quiz->imageId);
            $reactions = $this->reactions->findByQuizId((int) $quiz->id);
            $likes = 0;
            $dislikes = 0;
            $myReaction = null;
            foreach ($reactions as $reaction) {
                if ($reaction->value === 1) {
                    $likes++;
                } else {
                    $dislikes++;
                }
                if ($reaction->ipAddress === $remoteAddress) {
                    $myReaction = $reaction->value;
                }
            }
            $statistic = $memberId === null ? null : $this->statistics->findByMemberAndQuiz($memberId, (int) $quiz->id);
            $quizCards[] = array(
                'quiz' => $quiz,
                'image' => $image,
                'likes' => $likes,
                'dislikes' => $dislikes,
                'myReaction' => $myReaction,
                'isFavorite' => $statistic !== null && $statistic->isFavorite,
            );
        }

        $member = $memberId === null ? null : $this->members->findById($memberId);
        $statistics = $memberId === null ? array() : $this->statistics->findByMemberId($memberId);
        $completedOfficialQuizzes = \count(array_filter($statistics, static fn ($statistic): bool => !$statistic->isMemberQuiz));
        $comments = $this->comments->findRecent(8, 0);
        $commentCards = array_map(function ($comment): array {
            return array('comment' => $comment, 'replies' => $this->commentReplies->findByCommentId((int) $comment->id));
        }, $comments);

        $basePath = $this->basePath;
        $homeUrl = $basePath . '/accueil';
        $searchUrl = $basePath . '/recherche';
        $loggedIn = $member !== null;
        $isAdmin = $loggedIn && $member->isAdministrator;
        $displayLogin = $loggedIn ? $member->login : '';
        $displayName = $loggedIn ? trim($member->firstName . ' ' . $member->lastName) : '';
        $siteKey = $this->captchaSiteKey;
        $captchaMisconfigured = $this->captchaMisconfigured;
        $interactionFlash = $_SESSION['interaction_flash'] ?? null;
        unset($_SESSION['interaction_flash']);
        if (!isset($_SESSION['interaction_csrf']) || !\is_string($_SESSION['interaction_csrf'])) {
            $_SESSION['interaction_csrf'] = bin2hex(random_bytes(32));
        }
        $interactionCsrf = $_SESSION['interaction_csrf'];

        require dirname(__DIR__, 2) . '/View/home.php';
    }
}