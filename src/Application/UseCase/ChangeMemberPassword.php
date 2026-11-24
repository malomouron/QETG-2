<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use InvalidArgumentException;
use QETG\Domain\Entity\Member;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Service\PasswordHasher;

final class ChangeMemberPassword
{
    public function __construct(
        private MemberRepositoryInterface $members,
        private PasswordHasher $passwordHasher
    ) {
    }

    public function execute(int $memberId, string $currentPassword, string $newPassword, string $confirmation): Member
    {
        $member = $this->members->findById($memberId);
        if ($member === null || !$this->passwordHasher->verify($currentPassword, $member->passwordHash)) {
            throw new InvalidArgumentException('Le mot de passe actuel est incorrect.');
        }
        if (strlen($newPassword) < 10 || !hash_equals($newPassword, $confirmation)) {
            throw new InvalidArgumentException('Le nouveau mot de passe doit contenir au moins 10 caractères et les deux saisies doivent correspondre.');
        }

        return $this->members->save(new Member(
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
    }
}
