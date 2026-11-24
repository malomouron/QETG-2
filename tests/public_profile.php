<?php

declare(strict_types=1);

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/legacy.php';

use QETG\Domain\Entity\Achievement;
use QETG\Domain\Entity\Image;
use QETG\Domain\Entity\Member;
use QETG\Domain\Entity\MemberAchievement;
use QETG\Domain\Entity\MemberSubscription;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Entity\QuizStatistic;
use QETG\Domain\Repository\AchievementRepositoryInterface;
use QETG\Domain\Repository\ImageRepositoryInterface;
use QETG\Domain\Repository\MemberAchievementRepositoryInterface;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\MemberSubscriptionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;
use QETG\Presentation\Http\Controller\PublicProfileController;

function assertPublicProfile(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$member = new Member(7, 'public-member', '', 'private@example.invalid', 'Nom', 'Prénom', false, 'default', false, '', '2026-01-02');
$members = new class($member) implements MemberRepositoryInterface {
    public function __construct(private Member $member) {}
    public function findById(int $id): ?Member { return $id === $this->member->id ? $this->member : null; }
    public function findByLogin(string $login): ?Member { unset($login); return null; }
    public function findByEmail(string $email): ?Member { unset($email); return null; }
    public function searchOthers(int $excludedMemberId, string $loginTerm, int $limit): array { unset($excludedMemberId, $loginTerm, $limit); return array(); }
    public function save(Member $member): Member { return $member; }
    public function delete(int $id): void { unset($id); }
};
$statistics = new class implements QuizStatisticRepositoryInterface {
    public function findByMemberAndQuiz(int $memberId, int $quizId): ?QuizStatistic { unset($memberId, $quizId); return null; }
    public function findByMemberId(int $memberId): array { return $memberId === 7 ? array(new QuizStatistic(7, 3, '2026-11-01', 2, false, false)) : array(); }
    public function findFavoritesByMemberId(int $memberId): array { unset($memberId); return array(); }
    public function countOfficialByMemberId(int $memberId): int { unset($memberId); return 0; }
    public function save(QuizStatistic $statistic): QuizStatistic { return $statistic; }
    public function deleteByMemberAndQuiz(int $memberId, int $quizId): void { unset($memberId, $quizId); }
};
$quizRepository = new class implements QuizRepositoryInterface {
    public function findById(int $id): ?Quiz { return $id === 3 ? new Quiz(3, 'Quiz public', '', 5, 1, false, 4, null, true, 2, true, 10) : null; }
    public function findPublic(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countPublic(): int { return 0; }
    public function findOfficial(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countOfficial(): int { return 0; }
    public function searchVisibleByName(string $searchTerm, int $limit): array { unset($searchTerm, $limit); return array(); }
    public function findMemberQuizzes(?string $searchTerm, ?int $quizId, int $limit): array { unset($searchTerm, $quizId, $limit); return array(); }
    public function recordView(int $id): void { unset($id); }
    public function findByCreator(int $memberId): array { unset($memberId); return array(); }
    public function save(Quiz $quiz): Quiz { return $quiz; }
    public function delete(int $id): void { unset($id); }
};
$images = new class implements ImageRepositoryInterface {
    public function findById(int $id): ?Image { return $id === 4 ? new Image(4, 'assets/images/community/image.png', 'Image') : null; }
    public function findAll(): array { return array(); }
    public function save(Image $image): Image { return $image; }
    public function delete(int $id): void { unset($id); }
};
$achievements = new class implements AchievementRepositoryInterface {
    public function findById(int $id): ?Achievement { return $id === 2 ? new Achievement(2, 'Curieux', 5, 'A terminé un quiz.') : null; }
    public function findAll(): array { return array(); }
    public function save(Achievement $achievement): Achievement { return $achievement; }
    public function delete(int $id): void { unset($id); }
};
$memberAchievements = new class implements MemberAchievementRepositoryInterface {
    public function hasAchievement(int $memberId, int $achievementId): bool { unset($memberId, $achievementId); return false; }
    public function findByMemberId(int $memberId): array { return $memberId === 7 ? array(new MemberAchievement(1, 2, 7, '2026-11-01')) : array(); }
    public function save(MemberAchievement $memberAchievement): MemberAchievement { return $memberAchievement; }
    public function delete(int $id): void { unset($id); }
};
$subscriptions = new class implements MemberSubscriptionRepositoryInterface {
    public function findByMembers(int $subscriberMemberId, int $followedMemberId): ?MemberSubscription { unset($subscriberMemberId, $followedMemberId); return null; }
    public function findFollowing(int $subscriberMemberId): array { unset($subscriberMemberId); return array(); }
    public function findFollowers(int $memberId): array { unset($memberId); return array(); }
    public function save(MemberSubscription $subscription): MemberSubscription { return $subscription; }
    public function delete(int $subscriberMemberId, int $followedMemberId): void { unset($subscriberMemberId, $followedMemberId); }
};

$_SESSION = array('id' => 42, 'login' => 'viewer');
$_GET = array('id' => '7');
$controller = new PublicProfileController($members, $statistics, $quizRepository, $images, $achievements, $memberAchievements, $subscriptions, '/QETG-2');
ob_start();
$controller->show();
$html = (string) ob_get_clean();
assertPublicProfile(str_contains($html, 'public-member'), 'Public profile should display the member login.');
assertPublicProfile(str_contains($html, 'Quiz public'), 'Public profile should display public quiz history.');
assertPublicProfile(str_contains($html, 'Curieux'), 'Public profile should display achievements.');
assertPublicProfile(str_contains($html, '/communaute/abonnement'), 'Another member profile should offer the dedicated follow action.');
assertPublicProfile(str_contains($html, 'type="button" aria-haspopup="dialog" command="show-modal" commandfor="report-dialog"'), 'The report trigger should open the dialog without submitting.');
assertPublicProfile(preg_match('/<dialog id="report-dialog"[^>]*>(.*?)<\/dialog>/s', $html, $report) === 1, 'Reporting should use a native dialog.');
assertPublicProfile(str_contains($report[0], 'aria-labelledby="report-dialog-title"') && !str_contains($report[0], ' open>'), 'The dialog should be labelled and start closed.');
assertPublicProfile(str_contains($report[1], 'action="/QETG-2/communaute/signalement" method="post"'), 'Confirmation should submit through the same routed endpoint.');
assertPublicProfile(str_contains($report[1], 'name="_csrf" value="' . $_SESSION['user_report_csrf'] . '"'), 'The report form should use the dedicated report CSRF token.');
assertPublicProfile(str_contains($report[1], 'name="member_id" value="7"'), 'The report form should submit the selected member.');
assertPublicProfile(str_contains($report[1], 'type="button" command="close" commandfor="report-dialog" autofocus>Annuler') && str_contains($report[1], 'type="submit">Envoyer le signalement'), 'Cancel should be focused by default and separate from confirmation.');
assertPublicProfile(!str_contains($html, 'private@example.invalid'), 'The public profile must not expose the member email.');

foreach (array(array(), array('id' => 7, 'login' => 'public-member')) as $session) {
    $_SESSION = $session;
    ob_start();
    $controller->show();
    $html = (string) ob_get_clean();
    assertPublicProfile(!str_contains($html, 'id="signaler"'), 'Guests and profile owners should not see the report action.');
}

fwrite(STDOUT, "Public profile checks passed.\n");
