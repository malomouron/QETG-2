<?php

declare(strict_types=1);

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/functions.php';

use QETG\Application\Port\EmailSenderInterface;
use QETG\Domain\Entity\PendingRegistration;
use QETG\Domain\Entity\PasswordReset;
use QETG\Domain\Entity\Member;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\PendingRegistrationRepositoryInterface;
use QETG\Domain\Repository\PasswordResetRepositoryInterface;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;
use QETG\Domain\Entity\QuizStatistic;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Entity\Image;
use QETG\Domain\Entity\Achievement;
use QETG\Domain\Entity\MemberAchievement;
use QETG\Domain\Entity\QuizReaction;
use QETG\Domain\Repository\QuizRepositoryInterface;
use QETG\Domain\Repository\ImageRepositoryInterface;
use QETG\Domain\Repository\AchievementRepositoryInterface;
use QETG\Domain\Repository\MemberAchievementRepositoryInterface;
use QETG\Domain\Repository\QuizReactionRepositoryInterface;
use QETG\Presentation\Http\Controller\AccountController;

function assertContainsText(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
}

$members = new class implements MemberRepositoryInterface {
    public function findById(int $id): ?Member
    {
        if ($id !== 42) {
            return null;
        }
        return new Member(42, 'member-test', 'hash', 'member@example.invalid', 'Test', 'Membre', false, 'default', true, '127.0.0.1', '2026-01-01');
    }
    public function findByLogin(string $login): ?Member { unset($login); return null; }
    public function findByEmail(string $email): ?Member { unset($email); return null; }
    public function save(Member $member): Member { return $member; }
    public function delete(int $id): void { unset($id); }
};

$registrations = new class implements PendingRegistrationRepositoryInterface {
    public function findById(int $id): ?PendingRegistration { unset($id); return null; }
    public function findByConfirmationToken(string $token): ?PendingRegistration { unset($token); return null; }
    public function findByLogin(string $login): ?PendingRegistration { unset($login); return null; }
    public function findByEmail(string $email): ?PendingRegistration { unset($email); return null; }
    public function save(PendingRegistration $registration): PendingRegistration { return $registration; }
    public function delete(int $id): void { unset($id); }
};

$passwordResets = new class implements PasswordResetRepositoryInterface {
    public function findByTokenHash(string $tokenHash): ?PasswordReset { unset($tokenHash); return null; }
    public function deleteByMemberId(int $memberId): void { unset($memberId); }
    public function save(PasswordReset $passwordReset): PasswordReset { return $passwordReset; }
    public function delete(int $id): void { unset($id); }
};

$statistics = new class implements QuizStatisticRepositoryInterface {
    public function findByMemberAndQuiz(int $memberId, int $quizId): ?QuizStatistic { unset($memberId, $quizId); return null; }
    public function findByMemberId(int $memberId): array { unset($memberId); return array(); }
    public function findFavoritesByMemberId(int $memberId): array { unset($memberId); return array(); }
    public function countOfficialByMemberId(int $memberId): int { unset($memberId); return 0; }
    public function save(QuizStatistic $statistic): QuizStatistic { return $statistic; }
    public function deleteByMemberAndQuiz(int $memberId, int $quizId): void { unset($memberId, $quizId); }
};

$quizzes = new class implements QuizRepositoryInterface {
    public function findById(int $id): ?Quiz { unset($id); return null; }
    public function findPublic(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countPublic(): int { return 0; }
    public function findOfficial(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countOfficial(): int { return 0; }
    public function recordView(int $id): void { unset($id); }
    public function findByCreator(int $memberId): array { unset($memberId); return array(); }
    public function save(Quiz $quiz): Quiz { return $quiz; }
    public function delete(int $id): void { unset($id); }
};

$images = new class implements ImageRepositoryInterface {
    public function findById(int $id): ?Image { unset($id); return null; }
    public function findAll(): array { return array(); }
    public function save(Image $image): Image { return $image; }
    public function delete(int $id): void { unset($id); }
};

$achievements = new class implements AchievementRepositoryInterface {
    public function findById(int $id): ?Achievement { unset($id); return null; }
    public function findAll(): array { return array(); }
    public function save(Achievement $achievement): Achievement { return $achievement; }
    public function delete(int $id): void { unset($id); }
};

$memberAchievements = new class implements MemberAchievementRepositoryInterface {
    public function hasAchievement(int $memberId, int $achievementId): bool { unset($memberId, $achievementId); return false; }
    public function findByMemberId(int $memberId): array { unset($memberId); return array(); }
    public function save(MemberAchievement $memberAchievement): MemberAchievement { return $memberAchievement; }
    public function delete(int $id): void { unset($id); }
};

$reactions = new class implements QuizReactionRepositoryInterface {
    public function findById(int $id): ?QuizReaction { unset($id); return null; }
    public function findByQuizAndIp(int $quizId, string $ipAddress): ?QuizReaction { unset($quizId, $ipAddress); return null; }
    public function findByQuizId(int $quizId): array { unset($quizId); return array(); }
    public function save(QuizReaction $reaction): QuizReaction { return $reaction; }
    public function delete(int $id): void { unset($id); }
};

$emailSender = new class implements EmailSenderInterface {
    public function send(string $recipient, string $subject, string $htmlBody, string $textBody): void
    {
        unset($recipient, $subject, $htmlBody, $textBody);
    }
};

$_SESSION = array();
$controller = new AccountController($members, $registrations, $passwordResets, $statistics, $quizzes, $images, $achievements, $memberAchievements, $reactions, $emailSender, '/QETG-2');

ob_start();
$controller->loginForm();
$loginHtml = (string) ob_get_clean();
assertContainsText('Connexion', $loginHtml, 'Login page title must render.');
assertContainsText('/QETG-2/connexion', $loginHtml, 'Login form must use the routed endpoint under a subdirectory.');
assertContainsText('name="_csrf"', $loginHtml, 'Login form must include a CSRF token.');

ob_start();
$controller->registerForm();
$registerHtml = (string) ob_get_clean();
assertContainsText('Créer un compte', $registerHtml, 'Registration page title must render.');
assertContainsText('/QETG-2/inscription', $registerHtml, 'Registration form must use the routed endpoint under a subdirectory.');
assertContainsText('name="password_confirmation"', $registerHtml, 'Registration form must require password confirmation.');
assertContainsText('name="newsletter"', $registerHtml, 'Registration form must expose the newsletter preference.');

$_SESSION['id'] = 42;
$_SESSION['login'] = 'member-test';
ob_start();
$controller->profileForm();
$profileHtml = (string) ob_get_clean();
assertContainsText('Mes informations', $profileHtml, 'Profile page title must render.');
assertContainsText('Membre depuis 2026-01-01', $profileHtml, 'Profile summary must show member registration date.');
assertContainsText('Modifier le mot de passe', $profileHtml, 'Password settings must be accessible from the profile page.');
assertContainsText('Mes succès', $profileHtml, 'Profile must show member achievements.');
assertContainsText('Quiz effectués', $profileHtml, 'Profile must show quiz history.');
assertContainsText('Mon avatar', $profileHtml, 'Profile must provide avatar settings.');
assertContainsText('Mes quiz', $profileHtml, 'Profile must list quizzes created by the member.');
assertContainsText('Supprimer mon compte', $profileHtml, 'Profile must preserve account deletion controls.');
assertContainsText('profile-account-actions', $profileHtml, 'Password settings should be a compact inline profile action.');
if (strpos($profileHtml, 'account-aside--profile') !== false) {
    throw new RuntimeException('The profile must not render a large password-information sidebar.');
}
assertContainsText('name="first_name"', $profileHtml, 'Profile must include editable member information.');

$homeHtml = file_get_contents(dirname(__DIR__) . '/src/Presentation/View/home.php');
assertContainsText('compte/profil', $homeHtml, 'Home account menu must link to the profile.');
if (strpos($homeHtml, 'home-account-link" href="<?= homeEscape($basePath) ?>/compte/mot-de-passe') !== false) {
    throw new RuntimeException('Password link must not appear directly in the home header.');
}

fwrite(STDOUT, "Account page rendering checks passed.\n");
