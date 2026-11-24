<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use InvalidArgumentException;
use QETG\Domain\Entity\Member;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\PasswordResetRepositoryInterface;
use QETG\Domain\Service\PasswordHasher;

final class ResetMemberPassword
{
    public function __construct(
        private MemberRepositoryInterface $members,
        private PasswordResetRepositoryInterface $passwordResets,
        private PasswordHasher $passwordHasher
    ) {
    }

    public function execute(int $memberId, string $newPassword, string $confirmation): void
    {
        $member = $this->members->findById($memberId);
        if ($member === null) {
            throw new InvalidArgumentException('Le compte associé à ce lien n’existe plus.');
        }
        if (strlen($newPassword) < 10 || !hash_equals($newPassword, $confirmation)) {
            throw new InvalidArgumentException('Le nouveau mot de passe doit contenir au moins 10 caractères et les deux saisies doivent correspondre.');
        }

        $this->members->save(new Member(
            $member->id,
            $member->login,
            $this->passwordHasher->hash($newPassword),
            $member->email,
            $member->lastName,
            $member->firstName,
            $member->isAdministrator,
            $member->profileImage,
            $member->newsletterEnabled,
            $member->registrationIp,
            $member->registeredAt
        ));
        $this->passwordResets->deleteByMemberId($member->id ?? 0);
    }
}
