<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use QETG\Domain\Entity\Member;
use QETG\Domain\Repository\AchievementRepositoryInterface;
use QETG\Domain\Repository\ImageRepositoryInterface;
use QETG\Domain\Repository\MemberAchievementRepositoryInterface;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\MemberSubscriptionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;

final class PublicProfileController
{
    public function __construct(
        private MemberRepositoryInterface $members,
        private QuizStatisticRepositoryInterface $statistics,
        private QuizRepositoryInterface $quizzes,
        private ImageRepositoryInterface $images,
        private AchievementRepositoryInterface $achievements,
        private MemberAchievementRepositoryInterface $memberAchievements,
        private MemberSubscriptionRepositoryInterface $subscriptions,
        private string $basePath
    ) {
    }

    public function show(): void
    {
        $memberId = filter_var($_GET['id'] ?? $_GET['u'] ?? null, FILTER_VALIDATE_INT);
        $member = $memberId === false || $memberId === null || $memberId < 1
            ? null
            : $this->members->findById((int) $memberId);
        if (!$member instanceof Member) {
            $this->notFound();
        }

        $playedQuizzes = array();
        $experience = 0;
        foreach ($this->statistics->findByMemberId((int) $member->id) as $statistic) {
            $quiz = $this->quizzes->findById($statistic->quizId);
            if ($quiz === null) {
                continue;
            }
            $experience += $quiz->experiencePoints;
            if ($quiz->isMemberCreated && (!$quiz->isComplete || !$quiz->isPublic)) {
                continue;
            }
            $playedQuizzes[] = array(
                'statistic' => $statistic,
                'quiz' => $quiz,
                'image' => $quiz->imageId === null ? null : $this->images->findById($quiz->imageId),
            );
        }

        $earnedAchievements = array();
        foreach ($this->memberAchievements->findByMemberId((int) $member->id) as $grant) {
            $achievement = $this->achievements->findById($grant->achievementId);
            if ($achievement !== null) {
                $experience += $achievement->experienceReward;
                $earnedAchievements[] = array('grant' => $grant, 'achievement' => $achievement);
            }
        }

        $createdQuizzes = array();
        foreach ($this->quizzes->findByCreator((int) $member->id) as $quiz) {
            if (!$quiz->isComplete || !$quiz->isPublic) {
                continue;
            }
            $createdQuizzes[] = array(
                'quiz' => $quiz,
                'image' => $quiz->imageId === null ? null : $this->images->findById($quiz->imageId),
            );
        }

        $currentMemberId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
        $isFollowing = $currentMemberId !== null
            && $currentMemberId !== (int) $member->id
            && $this->subscriptions->findByMembers($currentMemberId, (int) $member->id) !== null;
        $profileCsrf = $this->communityCsrf();
        $reportCsrf = $this->reportCsrf();
        $basePath = $this->basePath;
        $level = intdiv($experience, 15) + 1;
        $experienceInLevel = $experience - (($level - 1) * 15);
        $flash = $_SESSION['community_flash'] ?? null;
        unset($_SESSION['community_flash']);
        require dirname(__DIR__, 2) . '/View/public-profile.php';
    }

    private function communityCsrf(): string
    {
        if (!isset($_SESSION['community_csrf']) || !is_string($_SESSION['community_csrf'])) {
            $_SESSION['community_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['community_csrf'];
    }

    private function reportCsrf(): string
    {
        if (!isset($_SESSION['user_report_csrf']) || !is_string($_SESSION['user_report_csrf'])) {
            $_SESSION['user_report_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['user_report_csrf'];
    }

    private function notFound(): never
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Profil introuvable.';
        exit;
    }
}
