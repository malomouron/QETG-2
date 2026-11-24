<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use QETG\Application\Service\NewsletterUnsubscribeToken;
use QETG\Domain\Entity\Member;
use QETG\Domain\Repository\MemberRepositoryInterface;

final class UnsubscribeFromNewsletter
{
    public function __construct(
        private MemberRepositoryInterface $members,
        private NewsletterUnsubscribeToken $tokens
    ) {
    }

    public function execute(string $token): bool
    {
        if (preg_match('/\A([1-9][0-9]*)\.[a-f0-9]{64}\z/', $token, $matches) !== 1) {
            return false;
        }
        $member = $this->members->findById((int) $matches[1]);
        if (!$member instanceof Member || !$this->tokens->verifies($token, $member)) {
            return false;
        }
        if (!$member->newsletterEnabled) {
            return true;
        }

        $this->members->save(new Member(
            $member->id,
            $member->login,
            $member->passwordHash,
            $member->email,
            $member->lastName,
            $member->firstName,
            $member->isAdministrator,
            $member->profileImage,
            false,
            $member->registrationIp,
            $member->registeredAt
        ));
        return true;
    }
}
