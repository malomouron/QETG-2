<?php

declare(strict_types=1);

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/legacy.php';

use QETG\Domain\Entity\Image;
use QETG\Domain\Entity\Member;
use QETG\Domain\Entity\MemberSubscription;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Entity\QuizReaction;
use QETG\Domain\Repository\ImageRepositoryInterface;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\MemberSubscriptionRepositoryInterface;
use QETG\Domain\Repository\QuizReactionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;
use QETG\Presentation\Http\Controller\MemberQuizController;

function assertMemberQuizPage(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$quiz = new Quiz(31, 'Quiz communautaire', 'Description du quiz', 5, 9, true, 4, 'uploads/quizzes/community.png', true, 2, true, 12);
$quizRepository = new class($quiz) implements QuizRepositoryInterface {
    public ?string $searchTerm = null;
    public ?int $privateId = null;
    public function __construct(private Quiz $quiz) {}
    public function findById(int $id): ?Quiz { unset($id); return null; }
    public function findPublic(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countPublic(): int { return 0; }
    public function findOfficial(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countOfficial(): int { return 0; }
    public function searchVisibleByName(string $searchTerm, int $limit): array { unset($searchTerm, $limit); return array(); }
    public function findMemberQuizzes(?string $searchTerm, ?int $quizId, int $limit): array
    {
        $this->searchTerm = $searchTerm;
        $this->privateId = $quizId;
        return array($this->quiz);
    }
    public function recordView(int $id): void { unset($id); }
    public function findByCreator(int $memberId): array { unset($memberId); return array($this->quiz); }
    public function save(Quiz $quiz): Quiz { return $quiz; }
    public function delete(int $id): void { unset($id); }
};
$imageRepository = new class implements ImageRepositoryInterface {
    public function findById(int $id): ?Image { return $id === 4 ? new Image(4, 'assets/images/community/community.png', 'Image communautaire') : null; }
    public function findAll(): array { return array(); }
    public function save(Image $image): Image { return $image; }
    public function delete(int $id): void { unset($id); }
};
$memberRepository = new class implements MemberRepositoryInterface {
    public ?string $lastSearchTerm = null;
    public function findById(int $id): ?Member { return $id === 9 ? new Member(9, 'auteur-test', '', '', '', '', false, 'default', false, '', '') : null; }
    public function findByLogin(string $login): ?Member { unset($login); return null; }
    public function findByEmail(string $email): ?Member { unset($email); return null; }
    public function searchOthers(int $excludedMemberId, string $loginTerm, int $limit): array
    {
        unset($excludedMemberId, $limit);
        $this->lastSearchTerm = $loginTerm;
        return array(new Member(11, 'membre-decouvert', '', '', '', '', false, 'default', false, '', ''));
    }
    public function save(Member $member): Member { return $member; }
    public function delete(int $id): void { unset($id); }
};
$reactionRepository = new class implements QuizReactionRepositoryInterface {
    public function findById(int $id): ?QuizReaction { unset($id); return null; }
    public function findByQuizAndIp(int $quizId, string $ipAddress): ?QuizReaction { unset($quizId, $ipAddress); return null; }
    public function findByQuizId(int $quizId): array { return $quizId === 31 ? array(new QuizReaction(1, 31, '192.0.2.1', 1, true)) : array(); }
    public function save(QuizReaction $reaction): QuizReaction { return $reaction; }
    public function delete(int $id): void { unset($id); }
};
$subscriptionRepository = new class implements MemberSubscriptionRepositoryInterface {
    public function findByMembers(int $subscriberMemberId, int $followedMemberId): ?MemberSubscription { unset($subscriberMemberId, $followedMemberId); return null; }
    public function findFollowing(int $subscriberMemberId): array { unset($subscriberMemberId); return array(); }
    public function findFollowers(int $memberId): array { unset($memberId); return array(); }
    public function save(MemberSubscription $subscription): MemberSubscription { return $subscription; }
    public function delete(int $subscriberMemberId, int $followedMemberId): void { unset($subscriberMemberId, $followedMemberId); }
};

$_SESSION = array();
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
$_GET = array('tab' => 'catalog', 's' => 'quiz');
$controller = new MemberQuizController($quizRepository, $imageRepository, $memberRepository, $reactionRepository, $subscriptionRepository, '/QETG-2');
ob_start();
$controller->index();
$html = (string) ob_get_clean();
assertMemberQuizPage($quizRepository->searchTerm === 'quiz', 'Catalog search should reach the repository.');
assertMemberQuizPage(str_contains($html, 'Quiz communautaire'), 'The public catalog should show member quiz titles.');
assertMemberQuizPage(str_contains($html, 'auteur-test'), 'The catalog should link the quiz author.');
assertMemberQuizPage(str_contains($html, 'name="_csrf"'), 'Public quiz reactions should carry CSRF tokens.');
assertMemberQuizPage(str_contains($html, '/quiz-membres/jouer?id=31'), 'Member quiz cards should use the routed play endpoint.');
assertMemberQuizPage(str_contains($html, 'reaction-link is-liked'), 'A quiz liked from the current IP should use the main-menu selected like style.');
assertMemberQuizPage(str_contains($html, 'aria-pressed="true"'), 'The current reaction state should be exposed to assistive technology.');
assertMemberQuizPage(str_contains($html, 'reaction-link reaction-link--down" type="submit" aria-pressed="false"'), 'The opposite reaction should retain the main-menu dislike style and remain unselected.');

$_GET = array('tab' => 'catalog', 's' => '31', 'rech_by_id' => 'on');
ob_start();
$controller->index();
$idSearchHtml = (string) ob_get_clean();
assertMemberQuizPage($quizRepository->privateId === 31, 'ID search should use a dedicated repository lookup for completed private quizzes.');
assertMemberQuizPage(str_contains($idSearchHtml, 'Quiz communautaire'), 'ID search should render its found quiz.');

$_SESSION = array('id' => 42, 'login' => 'membre');
$_GET = array('tab' => 'mine');
ob_start();
$controller->index();
$mineHtml = (string) ob_get_clean();
assertMemberQuizPage(str_contains($mineHtml, 'Mes quiz'), 'Authenticated users should have a personal quiz tab.');
assertMemberQuizPage(str_contains($mineHtml, '/compte/quiz/supprimer'), 'Personal quizzes should use the dedicated delete endpoint.');
assertMemberQuizPage(str_contains($mineHtml, 'name="_csrf"'), 'Personal quiz deletion must carry a CSRF token.');

$_GET = array('tab' => 'members', 'b' => 'alice');
ob_start();
$controller->index();
$membersHtml = (string) ob_get_clean();
assertMemberQuizPage($memberRepository->lastSearchTerm === 'alice', 'Member discovery should pass the search term to the repository.');
assertMemberQuizPage(str_contains($membersHtml, 'membre-decouvert'), 'Member discovery should render the found member without undefined-variable warnings.');
assertMemberQuizPage(str_contains($membersHtml, '/profil-public?id=11'), 'Discovered members should link to the public profile route.');

fwrite(STDOUT, "Member quiz page checks passed.\n");
