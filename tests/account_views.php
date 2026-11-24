<?php

declare(strict_types=1);

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
ob_start();
require_once dirname(__DIR__) . '/bootstrap/legacy.php';
ob_end_clean();

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
    public function searchOthers(int $excludedMemberId, string $loginTerm, int $limit): array { unset($excludedMemberId, $loginTerm, $limit); return array(); }
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
    public function deleteExpiredBefore(\DateTimeImmutable $cutoff): int { unset($cutoff); return 0; }
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
    public function searchVisibleByName(string $searchTerm, int $limit): array { unset($searchTerm, $limit); return array(); }
    public function findMemberQuizzes(?string $searchTerm, ?int $quizId, int $limit): array { unset($searchTerm, $quizId, $limit); return array(); }
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

if (($argv[1] ?? null) === '--logout-check') {
    $scenario = $argv[2];
    $_SESSION = array('id' => $scenario === 'stale' ? 999 : 42, 'login' => 'member-test', 'csrf_token' => 'session-token');
    $_POST = match ($scenario) {
        'valid' => array('_csrf' => 'session-token'),
        'invalid' => array('_csrf' => 'incorrect-token'),
        'malformed' => array('_csrf' => array('session-token')),
        default => array(),
    };
    ob_start();
    register_shutdown_function(static function (): void {
        ob_end_clean();
        echo json_encode(array(http_response_code(), $_SESSION, session_status()));
    });
    if ($scenario === 'stale') {
        $controller->profileForm();
    } else {
        $controller->logout();
    }
    exit;
}

ob_start();
$controller->loginForm();
$loginHtml = (string) ob_get_clean();
assertContainsText('Connexion', $loginHtml, 'Login page title must render.');
assertContainsText('/QETG-2/connexion', $loginHtml, 'Login form must use the routed endpoint under a subdirectory.');
assertContainsText('name="_csrf"', $loginHtml, 'Login form must include a CSRF token.');
if (str_contains($loginHtml, '/deconnexion')) {
    throw new RuntimeException('Guest navigation must not show the logout form.');
}

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
if (preg_match('~<form\b[^>]*action="/QETG-2/deconnexion" method="post">(.*?)</form>~s', $profileHtml, $logoutForm) !== 1) {
    throw new RuntimeException('The shared header must submit logout with POST.');
}
assertContainsText('type="hidden" name="_csrf" value="' . $_SESSION['csrf_token'] . '"', $logoutForm[1], 'The logout form must carry the session CSRF token.');
if (str_contains($profileHtml, 'href="/QETG-2/deconnexion"')) {
    throw new RuntimeException('Logout must not be exposed as a GET link.');
}
assertContainsText('Mes informations', $profileHtml, 'Profile page title must render.');
assertContainsText('Membre depuis 2026-01-01', $profileHtml, 'Profile summary must show member registration date.');
assertContainsText('Modifier le mot de passe', $profileHtml, 'Password settings must be accessible from the profile page.');
assertContainsText('Mes succès', $profileHtml, 'Profile must show member achievements.');
assertContainsText('Quiz effectués', $profileHtml, 'Profile must show quiz history.');
assertContainsText('Mon avatar', $profileHtml, 'Profile must provide avatar settings.');
assertContainsText('type="file" name="avatar"', $profileHtml, 'The shared image picker must retain the avatar upload input.');
assertContainsText('2 Mo maximum', $profileHtml, 'The avatar image picker should display the correct limit.');
assertContainsText('Mes quiz', $profileHtml, 'Profile must list quizzes created by the member.');
assertContainsText('Supprimer mon compte', $profileHtml, 'Profile must preserve account deletion controls.');
assertContainsText('profile-account-actions', $profileHtml, 'Password settings should be a compact inline profile action.');
if (strpos($profileHtml, 'account-aside--profile') !== false) {
    throw new RuntimeException('The profile must not render a large password-information sidebar.');
}
assertContainsText('name="first_name"', $profileHtml, 'Profile must include editable member information.');
$profileViewSource = file_get_contents(dirname(__DIR__) . '/src/Presentation/View/account.php');
assertContainsText('action="<?= $escape($basePath) ?>/compte/favori" method="post"', $profileViewSource, 'Favorite actions must use the dedicated POST endpoint.');
assertContainsText('name="_csrf"', $profileViewSource, 'Favorite forms must carry a CSRF token.');
if (strpos($profileViewSource, 'changer-en-favoris.php') !== false || strpos($profileViewSource, 'changer-en-non-favoris.php') !== false) {
    throw new RuntimeException('The migrated profile must not link to legacy favorite mutation scripts.');
}

$homeHtml = file_get_contents(dirname(__DIR__) . '/src/Presentation/View/home.php');
assertContainsText('compte/profil', $homeHtml, 'Home account menu must link to the profile.');
if (strpos($homeHtml, 'home-account-link" href="<?= homeEscape($basePath) ?>/compte/mot-de-passe') !== false) {
    throw new RuntimeException('Password link must not appear directly in the home header.');
}

foreach (array('missing', 'invalid', 'malformed', 'valid', 'stale') as $scenario) {
    $output = array();
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --logout-check ' . escapeshellarg($scenario), $output, $exitCode);
    $actual = json_decode(implode("\n", $output), true);
    $expected = in_array($scenario, array('valid', 'stale'), true)
        ? array(303, array(), PHP_SESSION_NONE)
        : array(403, array('id' => 42, 'login' => 'member-test', 'csrf_token' => 'session-token'), PHP_SESSION_ACTIVE);
    if ($exitCode !== 0 || $actual !== $expected) {
        throw new RuntimeException('Unexpected logout result for ' . $scenario . ': ' . implode("\n", $output));
    }
}

fwrite(STDOUT, "Account page rendering checks passed.\n");
