<?php

declare(strict_types=1);

use QETG\Application\Port\EmailSenderInterface;
use QETG\Domain\Entity\Member;
use QETG\Domain\Entity\PendingRegistration;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Infrastructure\Persistence\MySQL as Storage;
use QETG\Presentation\Http\Controller\AccountController;

require dirname(__DIR__, 2) . '/bootstrap/legacy.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function httpPage(string $path): array
{
    $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true, 'follow_location' => 0]]);
    $body = @file_get_contents('http://127.0.0.1' . $path, false, $context);
    $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : ($http_response_header ?? []);
    return [$headers[0] ?? '', (string) $body];
}

function member(?int $id, string $login, string $email, string $name = 'Test'): Member
{
    return new Member($id, $login, 'test-hash', $email, $name, 'CI', false, 'default', false, '', date('d/m/Y'));
}

function expectDuplicate(callable $write): void
{
    try {
        $write();
    } catch (DomainException $exception) {
        $cause = $exception->getPrevious()?->getPrevious();
        check($cause instanceof mysqli_sql_exception && $cause->getCode() === 1062, 'Expected MySQL duplicate-key error.');
        return;
    }
    throw new RuntimeException('An identity collision was accepted.');
}

function pending(string $login, string $email): PendingRegistration
{
    return new PendingRegistration(null, bin2hex(random_bytes(32)), $login, 'test-hash', $email, 'Test', 'CI', false, date('Y-m-d'));
}

$database = new Storage\MySqlConnection($dbhost, $dbuser, $dbpass, $dbname);
$members = new Storage\MySqlMemberRepository($database);
$registrations = new Storage\MySqlPendingRegistrationRepository($database);
$difficulties = new Storage\MySqlDifficultyRepository($database);
check(array_column($difficulties->findAll(), 'label') === ['Très simple', 'Simple', 'Intermédiaire', 'Difficile', 'Très difficile'], 'Fresh schema must contain the five ordered baseline difficulties.');

// A manual start or slow Apache startup must not make the smoke test flaky.
for ($attempt = 0; $attempt < 20; ++$attempt) {
    [$status, $home] = httpPage('/accueil');
    if ($status !== '') {
        break;
    }
    usleep(250_000);
}
check(str_contains($status, ' 200 ') && str_contains($home, 'Quiz en tout genre'), 'The routed home page must load over Apache: ' . $status);
[$status, $loginForm] = httpPage('/connexion');
check(str_contains($status, ' 200 ') && str_contains($loginForm, 'name="_csrf"'), 'The routed login form must contain its CSRF field.');

$first = $members->save(member(null, 'First', 'first@example.invalid'));
$second = $members->save(member(null, 'Second', 'second@example.invalid'));
expectDuplicate(fn () => $members->save(member(null, 'first', 'unused@example.invalid')));
expectDuplicate(fn () => $members->save(member(null, 'unused', 'FIRST@example.invalid')));
expectDuplicate(fn () => $members->save(member($second->id, 'Second', 'FIRST@example.invalid')));
check($members->findById($second->id)?->email === $second->email, 'A rejected update must preserve the existing member.');

$failure = null;
try {
    $members->save(member(null, 'too-long-name', 'unused@example.invalid', str_repeat('x', 51)));
} catch (RuntimeException $exception) {
    $failure = $exception->getPrevious();
}
check($failure instanceof mysqli_sql_exception && $failure->getCode() === 1406, 'Other database errors must propagate unchanged.');

$registration = $registrations->save(pending('new-member', 'new@example.invalid'));
$url = '/confirmation-email?token=' . $registration->confirmationToken;
[$status, $confirmation] = httpPage($url);
check(str_contains($status, ' 200 ') && str_contains($confirmation, 'Votre adresse est confirmée.'), 'Confirmation must create the account over HTTP.');
check($members->findByLogin('new-member') !== null, 'The confirmed member must be persisted.');
[$status, $replayed] = httpPage($url);
check(str_contains($status, ' 200 ') && str_contains($replayed, 'Ce lien a déjà été utilisé'), 'Confirmation tokens must be consumed.');

// Hide the winning row from both prechecks to deterministically exercise the race at INSERT.
$racingMembers = new class($members) implements MemberRepositoryInterface {
    public function __construct(private Storage\MySqlMemberRepository $inner) {}
    public function findById(int $id): ?Member { return $this->inner->findById($id); }
    public function findByLogin(string $login): ?Member { return null; }
    public function findByEmail(string $email): ?Member { return null; }
    public function searchOthers(int $excludedMemberId, string $loginTerm, int $limit): array { return []; }
    public function save(Member $member): Member { return $this->inner->save($member); }
    public function delete(int $id): void { $this->inner->delete($id); }
};
$emailSender = new class implements EmailSenderInterface {
    public function send(string $recipient, string $subject, string $htmlBody, string $textBody): void
    {
        throw new LogicException('Confirmation must not send email.');
    }
};
$controller = new AccountController(
    $racingMembers,
    $registrations,
    new Storage\MySqlPasswordResetRepository($database),
    new Storage\MySqlQuizStatisticRepository($database),
    new Storage\MySqlQuizRepository($database),
    new Storage\MySqlImageRepository($database),
    new Storage\MySqlAchievementRepository($database),
    new Storage\MySqlMemberAchievementRepository($database),
    new Storage\MySqlQuizReactionRepository($database),
    $emailSender,
    ''
);
session_start();
$loser = $registrations->save(pending('race-loser', 'FIRST@example.invalid'));
$_GET['token'] = $loser->confirmationToken;
ob_start();
$controller->confirmRegistration();
$conflict = (string) ob_get_clean();
check(str_contains($conflict, 'Un compte utilise déjà ces informations.'), 'The losing confirmation must render the conflict instead of failing.');
check($registrations->findById($loser->id) === null, 'The losing token must be consumed.');
check(!isset($_SESSION['id']) && $members->findByLogin('race-loser') === null, 'The losing confirmation must not create or authenticate a member.');
check($members->findByEmail('FIRST@example.invalid')?->id === $first->id, 'The winning member must not change.');

echo "HTTP, member identity and registration integration checks passed.\n";