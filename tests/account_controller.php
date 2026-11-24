<?php

declare(strict_types=1);

/** Deterministic controller branch checks. Exit-based routes run in isolated child PHP processes. */
session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
ob_start();
require_once dirname(__DIR__) . '/bootstrap/legacy.php';
ob_end_clean();

global $domaine;
$domaine = 'https://qetg.example.invalid';

use QETG\Application\Port\EmailSenderInterface;
use QETG\Domain\Entity\Achievement;
use QETG\Domain\Entity\Image;
use QETG\Domain\Entity\Member;
use QETG\Domain\Entity\MemberAchievement;
use QETG\Domain\Entity\PasswordReset;
use QETG\Domain\Entity\PendingRegistration;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Entity\QuizReaction;
use QETG\Domain\Entity\QuizStatistic;
use QETG\Domain\Repository\AchievementRepositoryInterface;
use QETG\Domain\Repository\ImageRepositoryInterface;
use QETG\Domain\Repository\MemberAchievementRepositoryInterface;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\PasswordResetRepositoryInterface;
use QETG\Domain\Repository\PendingRegistrationRepositoryInterface;
use QETG\Domain\Repository\QuizReactionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;
use QETG\Domain\Service\PasswordHasher;
use QETG\Presentation\Http\Controller\AccountController;

function accountControllerAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function accountControllerMember(int $id, string $hash, string $login = 'member-test', string $email = 'member@example.invalid'): Member
{
    return new Member($id, $login, $hash, $email, 'Test', 'Membre', false, 'default', true, '127.0.0.1', '2026-01-01');
}

$scenario = (string) ($argv[2] ?? '');
$state = array(
    'member' => accountControllerMember(42, password_hash('current-secret', PASSWORD_BCRYPT, array('cost' => 4))),
    'loginMatch' => null,
    'emailMatch' => null,
    'saveDomainException' => false,
    'membersSaved' => array(),
    'membersDeleted' => array(),
    'pending' => null,
    'pendingSaved' => array(),
    'pendingDeleted' => array(),
    'reset' => null,
    'resetsSaved' => array(),
    'resetsDeleted' => array(),
    'resetMemberDeletes' => array(),
    'emailFailure' => false,
    'emails' => array(),
    'quiz' => null,
    'quizzesDeleted' => array(),
);

$members = new class implements MemberRepositoryInterface {
    public function findById(int $id): ?Member
    {
        return $GLOBALS['state']['member'] !== null && $GLOBALS['state']['member']->id === $id ? $GLOBALS['state']['member'] : null;
    }
    public function findByLogin(string $login): ?Member
    {
        if (isset($GLOBALS['state']['loginMatch']) && $GLOBALS['state']['loginMatch'] instanceof Member && $GLOBALS['state']['loginMatch']->login === $login) {
            return $GLOBALS['state']['loginMatch'];
        }
        $member = $GLOBALS['state']['member'];
        return $member instanceof Member && $member->login === $login ? $member : null;
    }
    public function findByEmail(string $email): ?Member
    {
        if (isset($GLOBALS['state']['emailMatch']) && $GLOBALS['state']['emailMatch'] instanceof Member && $GLOBALS['state']['emailMatch']->email === $email) {
            return $GLOBALS['state']['emailMatch'];
        }
        $member = $GLOBALS['state']['member'];
        return $member instanceof Member && $member->email === $email ? $member : null;
    }
    public function searchOthers(int $excludedMemberId, string $loginTerm, int $limit): array
    {
        unset($excludedMemberId, $loginTerm, $limit);
        return array();
    }
    public function save(Member $member): Member
    {
        if ($GLOBALS['state']['saveDomainException']) {
            throw new DomainException('duplicate identity');
        }
        $GLOBALS['state']['membersSaved'][] = $member;
        if ($member->id !== null) {
            $GLOBALS['state']['member'] = $member;
        } else {
            $GLOBALS['state']['member'] = new Member(42, $member->login, $member->passwordHash, $member->email, $member->lastName, $member->firstName, $member->isAdministrator, $member->profileImage, $member->newsletterEnabled, $member->registrationIp, $member->registeredAt);
        }
        return $GLOBALS['state']['member'];
    }
    public function delete(int $id): void
    {
        $GLOBALS['state']['membersDeleted'][] = $id;
    }
};

$registrations = new class implements PendingRegistrationRepositoryInterface {
    public function findById(int $id): ?PendingRegistration
    {
        $pending = $GLOBALS['state']['pending'];
        return $pending instanceof PendingRegistration && $pending->id === $id ? $pending : null;
    }
    public function findByConfirmationToken(string $token): ?PendingRegistration
    {
        $pending = $GLOBALS['state']['pending'];
        return $pending instanceof PendingRegistration && $pending->confirmationToken === $token ? $pending : null;
    }
    public function findByLogin(string $login): ?PendingRegistration
    {
        $pending = $GLOBALS['state']['pending'];
        return $pending instanceof PendingRegistration && $pending->login === $login ? $pending : null;
    }
    public function findByEmail(string $email): ?PendingRegistration
    {
        $pending = $GLOBALS['state']['pending'];
        return $pending instanceof PendingRegistration && $pending->email === $email ? $pending : null;
    }
    public function save(PendingRegistration $registration): PendingRegistration
    {
        if ($registration->id === null) {
            $registration = new PendingRegistration(73, $registration->confirmationToken, $registration->login, $registration->passwordHash, $registration->email, $registration->lastName, $registration->firstName, $registration->newsletterEnabled, $registration->createdOn);
        }
        $GLOBALS['state']['pending'] = $registration;
        $GLOBALS['state']['pendingSaved'][] = $registration;
        return $registration;
    }
    public function delete(int $id): void
    {
        $GLOBALS['state']['pendingDeleted'][] = $id;
        $GLOBALS['state']['pending'] = null;
    }
    public function deleteExpiredBefore(DateTimeImmutable $cutoff): int
    {
        unset($cutoff);
        return 0;
    }
};

$passwordResets = new class implements PasswordResetRepositoryInterface {
    public function findByTokenHash(string $tokenHash): ?PasswordReset
    {
        $reset = $GLOBALS['state']['reset'];
        return $reset instanceof PasswordReset && hash_equals($reset->tokenHash, $tokenHash) ? $reset : null;
    }
    public function deleteByMemberId(int $memberId): void
    {
        $GLOBALS['state']['resetMemberDeletes'][] = $memberId;
        $GLOBALS['state']['reset'] = null;
    }
    public function save(PasswordReset $passwordReset): PasswordReset
    {
        $passwordReset = new PasswordReset(91, $passwordReset->memberId, $passwordReset->tokenHash, $passwordReset->expiresAt, $passwordReset->createdAt);
        $GLOBALS['state']['reset'] = $passwordReset;
        $GLOBALS['state']['resetsSaved'][] = $passwordReset;
        return $passwordReset;
    }
    public function delete(int $id): void
    {
        $GLOBALS['state']['resetsDeleted'][] = $id;
        $GLOBALS['state']['reset'] = null;
    }
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
    public function findById(int $id): ?Quiz { return $GLOBALS['state']['quiz'] instanceof Quiz && $GLOBALS['state']['quiz']->id === $id ? $GLOBALS['state']['quiz'] : null; }
    public function findPublic(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countPublic(): int { return 0; }
    public function findOfficial(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countOfficial(): int { return 0; }
    public function searchVisibleByName(string $searchTerm, int $limit): array { unset($searchTerm, $limit); return array(); }
    public function findMemberQuizzes(?string $searchTerm, ?int $quizId, int $limit): array { unset($searchTerm, $quizId, $limit); return array(); }
    public function recordView(int $id): void { unset($id); }
    public function findByCreator(int $memberId): array { unset($memberId); return array(); }
    public function save(Quiz $quiz): Quiz { return $quiz; }
    public function delete(int $id): void { $GLOBALS['state']['quizzesDeleted'][] = $id; }
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
        $GLOBALS['state']['emails'][] = array('recipient' => $recipient, 'subject' => $subject, 'html' => $htmlBody, 'text' => $textBody);
        if ($GLOBALS['state']['emailFailure']) {
            throw new RuntimeException('simulated SMTP outage');
        }
    }
};

$controller = new AccountController($members, $registrations, $passwordResets, $statistics, $quizzes, $images, $achievements, $memberAchievements, $reactions, $emailSender, '/QETG-2');
$_SESSION = array('csrf_token' => 'csrf-test');
$_POST = array();
$_GET = array();
$_FILES = array();

$token = str_repeat('a', 64);
$validRegistration = static function (string $createdOn) use ($token): PendingRegistration {
    return new PendingRegistration(73, $token, 'new-member', password_hash('registration-secret', PASSWORD_BCRYPT, array('cost' => 4)), 'new@example.invalid', 'Nouveau', 'Membre', true, $createdOn);
};

if (($argv[1] ?? '') === '--scenario') {
    $GLOBALS['rendered'] = '';
    register_shutdown_function(static function (): void {
        $coverageDirectory = getenv('QETG_COVERAGE_DIR');
        if (is_string($coverageDirectory) && $coverageDirectory !== '' && function_exists('xdebug_get_code_coverage')) {
            file_put_contents(
                rtrim($coverageDirectory, '/\\') . '/account-controller-' . getmypid() . '.ser',
                serialize(xdebug_get_code_coverage()),
                LOCK_EX
            );
        }
        $state = $GLOBALS['state'];
        $encodeMembers = static fn (array $items): array => array_map(static fn (Member $member): array => array('id' => $member->id, 'login' => $member->login, 'email' => $member->email, 'hash' => $member->passwordHash, 'firstName' => $member->firstName, 'lastName' => $member->lastName, 'avatar' => $member->profileImage, 'newsletter' => $member->newsletterEnabled), $items);
        $snapshot = array(
            'http' => http_response_code(),
            'session' => $_SESSION,
            'member' => $state['member'] instanceof Member ? array('id' => $state['member']->id, 'login' => $state['member']->login, 'hash' => $state['member']->passwordHash, 'avatar' => $state['member']->profileImage, 'firstName' => $state['member']->firstName, 'newsletter' => $state['member']->newsletterEnabled) : null,
            'membersSaved' => $encodeMembers($state['membersSaved']),
            'membersDeleted' => $state['membersDeleted'],
            'pendingSaved' => count($state['pendingSaved']),
            'pendingDeleted' => $state['pendingDeleted'],
            'resetsSaved' => $state['resetsSaved'],
            'resetsDeleted' => $state['resetsDeleted'],
            'resetMemberDeletes' => $state['resetMemberDeletes'],
            'emails' => $state['emails'],
            'quizzesDeleted' => $state['quizzesDeleted'],
            'rendered' => $GLOBALS['rendered'],
        );
        echo json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    });

    switch ($scenario) {
        case 'login_valid':
        case 'login_rehash':
            $secret = 'account-login-secret';
            $hash = $scenario === 'login_rehash'
                ? password_hash($secret, PASSWORD_BCRYPT, array('cost' => 4))
                : (new PasswordHasher())->hash($secret);
            $state['member'] = accountControllerMember(42, $hash, 'login-target', 'login@example.invalid');
            $_POST = array('_csrf' => 'csrf-test', 'identity' => 'login-target', 'password' => $secret);
            $controller->login();
            break;
        case 'login_no_match':
            $state['member'] = null;
            $_POST = array('_csrf' => 'csrf-test', 'identity' => 'missing', 'password' => 'wrong-secret');
            $controller->login();
            break;
        case 'login_duplicate':
            $duplicateHash = password_hash('duplicate-secret', PASSWORD_BCRYPT, array('cost' => 4));
            $state['member'] = null;
            $state['loginMatch'] = accountControllerMember(10, $duplicateHash, 'duplicate', 'one@example.invalid');
            $state['emailMatch'] = accountControllerMember(11, $duplicateHash, 'other', 'duplicate');
            $_POST = array('_csrf' => 'csrf-test', 'identity' => 'duplicate', 'password' => 'duplicate-secret');
            $controller->login();
            break;
        case 'login_csrf':
            $_POST = array('_csrf' => 'wrong', 'identity' => 'member-test', 'password' => 'current-secret');
            $controller->login();
            break;
        case 'register_invalid':
            $_POST = array('_csrf' => 'csrf-test', 'login' => 'bad login', 'first_name' => 'A', 'last_name' => 'B', 'email' => 'bad', 'password' => 'short', 'password_confirmation' => 'short');
            $controller->register();
            break;
        case 'register_duplicate':
            $_POST = array('_csrf' => 'csrf-test', 'login' => 'member-test', 'first_name' => 'A', 'last_name' => 'B', 'email' => 'new@example.invalid', 'password' => 'long-password', 'password_confirmation' => 'long-password');
            $controller->register();
            break;
        case 'register_email_failure':
        case 'register_success':
            $state['member'] = null;
            $state['emailFailure'] = $scenario === 'register_email_failure';
            $_POST = array('_csrf' => 'csrf-test', 'login' => 'fresh-user', 'first_name' => 'First', 'last_name' => 'Last', 'email' => 'fresh@example.invalid', 'password' => 'long-password', 'password_confirmation' => 'long-password', 'newsletter' => '1');
            $controller->register();
            break;
        case 'confirm_invalid':
            $_GET = array('token' => 'not-a-token');
            ob_start(); $controller->confirmRegistration(); $GLOBALS['rendered'] = (string) ob_get_clean();
            break;
        case 'confirm_expired':
            $state['pending'] = $validRegistration(date('Y-m-d', strtotime('-3 days')));
            $_GET = array('token' => $token);
            ob_start(); $controller->confirmRegistration(); $GLOBALS['rendered'] = (string) ob_get_clean();
            break;
        case 'confirm_race':
            $state['pending'] = $validRegistration(date('Y-m-d'));
            $state['loginMatch'] = accountControllerMember(44, 'other-hash', 'new-member', 'other@example.invalid');
            $_GET = array('token' => $token);
            ob_start(); $controller->confirmRegistration(); $GLOBALS['rendered'] = (string) ob_get_clean();
            break;
        case 'confirm_domain_exception':
            $state['pending'] = $validRegistration(date('Y-m-d'));
            $state['saveDomainException'] = true;
            $_GET = array('token' => $token);
            ob_start(); $controller->confirmRegistration(); $GLOBALS['rendered'] = (string) ob_get_clean();
            break;
        case 'confirm_success':
            $state['member'] = null;
            $state['pending'] = $validRegistration(date('Y-m-d'));
            $_GET = array('token' => $token);
            ob_start(); $controller->confirmRegistration(); $GLOBALS['rendered'] = (string) ob_get_clean();
            break;
        case 'reset_request_unknown':
        case 'reset_request_known':
            if ($scenario === 'reset_request_unknown') { $state['member'] = null; }
            $_POST = array('_csrf' => 'csrf-test', 'email' => 'member@example.invalid');
            $controller->requestPasswordReset();
            break;
        case 'reset_form_invalid':
            $_GET = array('token' => 'invalid');
            ob_start(); $controller->resetPasswordForm(); $GLOBALS['rendered'] = (string) ob_get_clean();
            break;
        case 'reset_form_valid':
            $state['reset'] = new PasswordReset(91, 42, hash('sha256', $token), new DateTimeImmutable('+1 hour'));
            $_GET = array('token' => $token);
            ob_start(); $controller->resetPasswordForm(); $GLOBALS['rendered'] = (string) ob_get_clean();
            break;
        case 'reset_post_success':
            $state['reset'] = new PasswordReset(91, 42, hash('sha256', $token), new DateTimeImmutable('+1 hour'));
            $_POST = array('_csrf' => 'csrf-test', 'token' => $token, 'new_password' => 'new-password-long', 'password_confirmation' => 'new-password-long');
            $controller->resetPassword();
            break;
        case 'reset_post_invalid':
            $state['reset'] = new PasswordReset(91, 42, hash('sha256', $token), new DateTimeImmutable('+1 hour'));
            $_POST = array('_csrf' => 'csrf-test', 'token' => $token, 'new_password' => 'short', 'password_confirmation' => 'different');
            $controller->resetPassword();
            break;
        case 'change_password_success':
            $_SESSION['id'] = 42;
            $_POST = array('_csrf' => 'csrf-test', 'current_password' => 'current-secret', 'new_password' => 'changed-password', 'password_confirmation' => 'changed-password');
            $controller->changePassword();
            break;
        case 'profile_update':
            $_SESSION['id'] = 42;
            $_POST = array('_csrf' => 'csrf-test', 'first_name' => 'Updated', 'last_name' => 'Person', 'newsletter' => '1');
            $controller->updateProfile();
            break;
        case 'avatar_preset':
            $_SESSION['id'] = 42;
            $_POST = array('_csrf' => 'csrf-test', 'avatar_preset' => '30000_m.png');
            $controller->updateAvatar();
            break;
        case 'delete_account_success':
            $_SESSION['id'] = 42;
            $_POST = array('_csrf' => 'csrf-test', 'current_password' => 'current-secret');
            ob_start(); $controller->deleteAccount(); $GLOBALS['rendered'] = (string) ob_get_clean();
            break;
        case 'delete_quiz_success':
            $_SESSION['id'] = 42;
            $state['quiz'] = new Quiz(55, 'Owned', '', 0, 42, true, null, null, true, 0, true, 0);
            $_POST = array('_csrf' => 'csrf-test', 'quiz_id' => '55');
            $controller->deleteCreatedQuiz();
            break;
        case 'delete_quiz_not_owned':
            $_SESSION['id'] = 42;
            $state['quiz'] = new Quiz(55, 'Not owned', '', 0, 43, true, null, null, true, 0, true, 0);
            $_POST = array('_csrf' => 'csrf-test', 'quiz_id' => '55');
            $controller->deleteCreatedQuiz();
            break;
        default:
            throw new RuntimeException('Unknown child scenario: ' . $scenario);
    }
    exit;
}

function runAccountControllerScenario(string $name): array
{
    $process = proc_open(
        array(PHP_BINARY, __FILE__, '--scenario', $name),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not launch scenario ' . $name);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $result = json_decode((string) $stdout, true);
    accountControllerAssert($exitCode === 0 && is_array($result), 'Scenario ' . $name . ' failed: ' . trim((string) $stderr . ' ' . (string) $stdout));
    return $result;
}

function checkAccountControllerScenario(string $name, callable $assertion): array
{
    $result = runAccountControllerScenario($name);
    $assertion($result);
    return $result;
}

$redirected = static function (array $result): bool { return $result['http'] === 303; };
checkAccountControllerScenario('login_valid', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && ($result['session']['id'] ?? null) === 42 && ($result['session']['login'] ?? '') === 'login-target', 'Valid login should establish the member session and redirect.');
    accountControllerAssert($result['membersSaved'] === array(), 'A current password hash should not be rewritten.');
});
checkAccountControllerScenario('login_rehash', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && count($result['membersSaved']) === 1, 'Successful legacy-cost login should save one upgraded hash.');
    accountControllerAssert(password_verify('account-login-secret', $result['membersSaved'][0]['hash']), 'The rehashed credential must still verify.');
    accountControllerAssert(!(new PasswordHasher())->needsRehash($result['membersSaved'][0]['hash']), 'The login rehash should use current PasswordHasher defaults.');
});
checkAccountControllerScenario('login_no_match', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && ($result['session']['account_login_value'] ?? '') === 'missing', 'Failed login should preserve the submitted identity.');
    accountControllerAssert(($result['session']['account_flash']['type'] ?? '') === 'error', 'Failed login should flash an error.');
});
checkAccountControllerScenario('login_duplicate', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && str_contains($result['session']['account_flash']['message'] ?? '', 'Plusieurs comptes'), 'Ambiguous login identities should be rejected explicitly.');
});
checkAccountControllerScenario('login_csrf', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && ($result['session']['account_flash']['type'] ?? '') === 'error', 'Invalid login CSRF token should be rejected.');
});
checkAccountControllerScenario('register_invalid', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && ($result['pendingSaved'] ?? 0) === 0, 'Invalid registration input should not create a pending registration.');
    accountControllerAssert(($result['session']['account_register_values']['login'] ?? '') === 'bad login', 'Rejected registration should preserve form values.');
});
checkAccountControllerScenario('register_duplicate', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && ($result['pendingSaved'] ?? 0) === 0, 'An existing login should block registration.');
});
checkAccountControllerScenario('register_email_failure', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && $result['pendingSaved'] === 1 && $result['pendingDeleted'] === array(73), 'SMTP failure should remove the newly saved pending registration.');
    accountControllerAssert(count($result['emails']) === 1 && ($result['session']['account_flash']['type'] ?? '') === 'error', 'Registration mail failure should be handled and flashed.');
});
checkAccountControllerScenario('register_success', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && $result['pendingSaved'] === 1 && $result['pendingDeleted'] === array(), 'Successful registration should retain one pending confirmation.');
    accountControllerAssert(($result['session']['account_flash']['type'] ?? '') === 'success' && !isset($result['session']['account_register_values']), 'Successful registration should clear saved form values.');
    accountControllerAssert(str_contains($result['emails'][0]['html'] ?? '', 'https://qetg.example.invalid/QETG-2/confirmation-email?token='), 'Confirmation email should contain the mounted absolute URL.');
});
checkAccountControllerScenario('confirm_invalid', static function (array $result): void {
    accountControllerAssert(str_contains($result['rendered'], 'Lien de confirmation invalide ou expiré'), 'Malformed confirmation tokens should render the invalid-link state.');
});
checkAccountControllerScenario('confirm_expired', static function (array $result): void {
    accountControllerAssert(str_contains($result['rendered'], 'a expiré') && $result['pendingDeleted'] === array(73), 'Expired registration should be deleted and reported.');
});
checkAccountControllerScenario('confirm_race', static function (array $result): void {
    accountControllerAssert(str_contains($result['rendered'], 'Un compte utilise déjà') && $result['pendingDeleted'] === array(73), 'A newly conflicting account should invalidate the pending registration.');
});
checkAccountControllerScenario('confirm_domain_exception', static function (array $result): void {
    accountControllerAssert(str_contains($result['rendered'], 'Un compte utilise déjà') && $result['pendingDeleted'] === array(73), 'A uniqueness race from persistence should be handled as a duplicate.');
});
checkAccountControllerScenario('confirm_success', static function (array $result): void {
    accountControllerAssert(str_contains($result['rendered'], 'Votre adresse est confirmée') && $result['pendingDeleted'] === array(73), 'Valid confirmation should create the account and consume the pending registration.');
    accountControllerAssert(($result['session']['id'] ?? null) === 42 && ($result['session']['admin'] ?? null) === 0, 'Confirmation should sign in the new non-administrator account.');
});
$unknownReset = checkAccountControllerScenario('reset_request_unknown', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && count($result['emails']) === 0 && count($result['resetsSaved']) === 0, 'Unknown addresses should not produce a reset or email.');
});
$knownReset = checkAccountControllerScenario('reset_request_known', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && count($result['emails']) === 1 && count($result['resetsSaved']) === 1, 'Known addresses should create and email one reset.');
    accountControllerAssert($result['resetsSaved'][0]['memberId'] === 42 && count($result['resetMemberDeletes']) === 1, 'A reset request should replace existing reset tokens for its member.');
});
accountControllerAssert(($unknownReset['session']['account_flash']['message'] ?? null) === ($knownReset['session']['account_flash']['message'] ?? null), 'Reset request responses must not reveal whether the address exists.');
checkAccountControllerScenario('reset_form_invalid', static function (array $result): void {
    accountControllerAssert(str_contains($result['rendered'], 'invalide ou a expiré'), 'Invalid reset token should render the unavailable state.');
});
checkAccountControllerScenario('reset_form_valid', static function (array $result): void {
    accountControllerAssert(str_contains($result['rendered'], 'name="new_password"') && !str_contains($result['rendered'], 'invalide ou a expiré'), 'A valid reset token should render the password form.');
});
checkAccountControllerScenario('reset_post_success', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && str_contains($result['session']['account_flash']['message'] ?? '', 'réinitialisé'), 'Valid reset should redirect with a success flash.');
    accountControllerAssert(password_verify('new-password-long', $result['membersSaved'][0]['hash']) && $result['resetMemberDeletes'] === array(42), 'Reset should persist the new hash and consume member reset tokens.');
});
checkAccountControllerScenario('reset_post_invalid', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && ($result['session']['account_flash']['type'] ?? '') === 'error', 'Invalid new password should be flashed and redirected back to the token form.');
});
checkAccountControllerScenario('change_password_success', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && password_verify('changed-password', $result['membersSaved'][0]['hash']), 'Change password should validate the current password and save a replacement hash.');
});
checkAccountControllerScenario('profile_update', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && $result['member']['firstName'] === 'Updated' && $result['member']['newsletter'] === true, 'Profile update should persist trimmed identity and newsletter preferences.');
});
checkAccountControllerScenario('avatar_preset', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && $result['member']['avatar'] === 'assets/images/avatars/presets/30000_m.png', 'Allowed preset avatar should be stored as an application-relative asset path.');
});
checkAccountControllerScenario('delete_account_success', static function (array $result): void {
    accountControllerAssert(str_contains($result['rendered'], 'Votre compte et vos quiz personnels ont été supprimés') && $result['membersDeleted'] === array(42), 'Account deletion should require the correct password and render its confirmation.');
});
checkAccountControllerScenario('delete_quiz_success', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && $result['quizzesDeleted'] === array(55), 'An owned member-created quiz should be deleted.');
});
checkAccountControllerScenario('delete_quiz_not_owned', static function (array $result) use ($redirected): void {
    accountControllerAssert($redirected($result) && $result['quizzesDeleted'] === array() && str_contains($result['session']['account_flash']['message'] ?? '', 'ne vous appartient'), 'A quiz owned by another member must not be deleted.');
});

fwrite(STDOUT, "Account controller scenario checks passed (26 isolated scenarios).\n");
