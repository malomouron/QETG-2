<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\UseCase\ChangeMemberPassword;
use QETG\Application\UseCase\ResetMemberPassword;
use QETG\Domain\Entity\Member;
use QETG\Domain\Entity\PasswordReset;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\PasswordResetRepositoryInterface;
use QETG\Domain\Service\PasswordHasher;

function assertPasswordUseCase(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$hasher = new PasswordHasher();
$memberRepository = new class($hasher) implements MemberRepositoryInterface {
    public ?Member $member;
    public function __construct(PasswordHasher $hasher)
    {
        $this->member = new Member(4, 'test-member', $hasher->hash('Current-password-1'), 'test@example.invalid', 'Nom', 'Prénom', false, 'default', true, '', '2026-01-01');
    }
    public function findById(int $id): ?Member { return $this->member?->id === $id ? $this->member : null; }
    public function findByLogin(string $login): ?Member { unset($login); return null; }
    public function findByEmail(string $email): ?Member { unset($email); return null; }
    public function searchOthers(int $excludedMemberId, string $loginTerm, int $limit): array { unset($excludedMemberId, $loginTerm, $limit); return array(); }
    public function save(Member $member): Member { $this->member = $member; return $member; }
    public function delete(int $id): void { unset($id); }
};
$resets = new class implements PasswordResetRepositoryInterface {
    public array $deletedMemberIds = array();
    public function findByTokenHash(string $tokenHash): ?PasswordReset { unset($tokenHash); return null; }
    public function deleteByMemberId(int $memberId): void { $this->deletedMemberIds[] = $memberId; }
    public function save(PasswordReset $passwordReset): PasswordReset { return $passwordReset; }
    public function delete(int $id): void { unset($id); }
};

$change = new ChangeMemberPassword($memberRepository, $hasher);
$updated = $change->execute(4, 'Current-password-1', 'New-password-123', 'New-password-123');
assertPasswordUseCase($hasher->verify('New-password-123', $updated->passwordHash), 'Change password use case should persist a modern hash.');
assertPasswordUseCase($updated->newsletterEnabled, 'Changing password should preserve the newsletter preference.');

$reset = new ResetMemberPassword($memberRepository, $resets, $hasher);
$reset->execute(4, 'Reset-password-456', 'Reset-password-456');
assertPasswordUseCase($hasher->verify('Reset-password-456', (string) $memberRepository->member?->passwordHash), 'Reset password use case should persist the new hash.');
assertPasswordUseCase($resets->deletedMemberIds === array(4), 'Password reset should invalidate outstanding reset tokens.');

fwrite(STDOUT, "Password use-case checks passed.\n");
