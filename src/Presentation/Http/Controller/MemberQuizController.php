<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use QETG\Domain\Entity\Quiz;
use QETG\Domain\Repository\ImageRepositoryInterface;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\MemberSubscriptionRepositoryInterface;
use QETG\Domain\Repository\QuizReactionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;

final class MemberQuizController
{
    public function __construct(
        private QuizRepositoryInterface $quizzes,
        private ImageRepositoryInterface $images,
        private MemberRepositoryInterface $members,
        private QuizReactionRepositoryInterface $reactions,
        private MemberSubscriptionRepositoryInterface $subscriptions,
        private string $basePath
    ) {
    }

    public function index(): void
    {
        $memberId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
        $tab = $_GET['tab'] ?? 'catalog';
        $allowedTabs = array('catalog', 'mine', 'ranking', 'liked', 'following', 'members');
        if (!is_string($tab) || !in_array($tab, $allowedTabs, true)) {
            $tab = 'catalog';
        }
        if ($memberId === null && !in_array($tab, array('catalog', 'ranking'), true)) {
            $tab = 'catalog';
        }

        $searchTerm = trim((string) ($_GET['s'] ?? ''));
        $privateId = null;
        if (isset($_GET['rech_by_id'])) {
            $validatedId = filter_var($_GET['s'] ?? null, FILTER_VALIDATE_INT);
            if ($validatedId !== false && $validatedId !== null && $validatedId > 0) {
                $privateId = $validatedId;
            } else {
                $searchTerm = '';
            }
        }
        $searchTerm = function_exists('mb_substr') ? mb_substr($searchTerm, 0, 100, 'UTF-8') : substr($searchTerm, 0, 100);

        $publicQuizzes = $this->quizzes->findMemberQuizzes(null, null, 100);
        $searchedQuizzes = $privateId !== null
            ? $this->quizzes->findMemberQuizzes(null, $privateId, 20)
            : ($searchTerm !== '' ? $this->quizzes->findMemberQuizzes($searchTerm, null, 50) : $publicQuizzes);
        $catalogCards = $this->cards($searchedQuizzes);
        $mineCards = $memberId === null ? array() : $this->cards($this->quizzes->findByCreator($memberId));

        $rankingCards = $this->cards($publicQuizzes);
        usort($rankingCards, static function (array $left, array $right): int {
            $scoreCompare = ($right['likes'] - $right['dislikes']) <=> ($left['likes'] - $left['dislikes']);
            return $scoreCompare !== 0 ? $scoreCompare : ((int) $right['quiz']->id <=> (int) $left['quiz']->id);
        });
        $viewRankingCards = $this->cards($publicQuizzes);
        usort($viewRankingCards, static fn (array $left, array $right): int => $right['quiz']->viewCount <=> $left['quiz']->viewCount);

        $likedCards = array();
        if ($memberId !== null) {
            foreach ($this->cards($publicQuizzes) as $card) {
                if ($card['myReaction'] === 1) {
                    $likedCards[] = $card;
                }
            }
        }

        $followingMembers = array();
        $followedIds = array();
        if ($memberId !== null) {
            foreach ($this->subscriptions->findFollowing($memberId) as $subscription) {
                $followedIds[$subscription->followedMemberId] = true;
                $followedMember = $this->members->findById($subscription->followedMemberId);
                if ($followedMember !== null && ($searchFollowed = trim((string) ($_GET['a'] ?? ''))) !== ''
                    && stripos($followedMember->login, $searchFollowed) === false
                ) {
                    continue;
                }
                if ($followedMember !== null) {
                    $followingMembers[] = $followedMember;
                }
            }
        }

        $memberSearch = trim((string) ($_GET['b'] ?? ''));
        $memberSearch = function_exists('mb_substr') ? mb_substr($memberSearch, 0, 100, 'UTF-8') : substr($memberSearch, 0, 100);
        $discoverMembers = $memberId === null
            ? array()
            : $this->members->searchOthers($memberId, $memberSearch, 50);

        $basePath = $this->basePath;
        $interactionCsrf = $this->interactionCsrf();
        $communityCsrf = $this->communityCsrf();
        $accountCsrf = $this->accountCsrf();
        $flash = $_SESSION['community_flash'] ?? null;
        unset($_SESSION['community_flash']);
        require dirname(__DIR__, 2) . '/View/member-quizzes.php';
    }

    /** @param list<Quiz> $quizList
     *  @return list<array{quiz: Quiz, image: mixed, owner: mixed, likes: int, dislikes: int, myReaction: ?int}>
     */
    private function cards(array $quizList): array
    {
        $cards = array();
        $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        foreach ($quizList as $quiz) {
            $likes = 0;
            $dislikes = 0;
            $myReaction = null;
            foreach ($this->reactions->findByQuizId((int) $quiz->id) as $reaction) {
                if ($reaction->value === 1) {
                    $likes++;
                } else {
                    $dislikes++;
                }
                if ($reaction->ipAddress === $ipAddress) {
                    $myReaction = $reaction->value;
                }
            }
            $cards[] = array(
                'quiz' => $quiz,
                'image' => $quiz->imageId === null ? null : $this->images->findById($quiz->imageId),
                'owner' => $this->members->findById($quiz->creatorMemberId),
                'likes' => $likes,
                'dislikes' => $dislikes,
                'myReaction' => $myReaction,
            );
        }
        return $cards;
    }

    private function interactionCsrf(): string
    {
        if (!isset($_SESSION['interaction_csrf']) || !is_string($_SESSION['interaction_csrf'])) {
            $_SESSION['interaction_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['interaction_csrf'];
    }

    private function communityCsrf(): string
    {
        if (!isset($_SESSION['community_csrf']) || !is_string($_SESSION['community_csrf'])) {
            $_SESSION['community_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['community_csrf'];
    }

    private function accountCsrf(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
