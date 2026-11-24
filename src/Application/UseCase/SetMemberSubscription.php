<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use InvalidArgumentException;
use QETG\Domain\Entity\MemberSubscription;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\MemberSubscriptionRepositoryInterface;

final class SetMemberSubscription
{
    public function __construct(
        private MemberRepositoryInterface $members,
        private MemberSubscriptionRepositoryInterface $subscriptions
    ) {
    }

    public function execute(int $subscriberId, int $followedId, bool $subscribe): void
    {
        if ($subscriberId < 1 || $followedId < 1 || $subscriberId === $followedId) {
            throw new InvalidArgumentException('Ce compte ne peut pas être suivi.');
        }
        if ($this->members->findById($followedId) === null) {
            throw new InvalidArgumentException('Ce membre n’existe pas.');
        }

        $existing = $this->subscriptions->findByMembers($subscriberId, $followedId);
        if ($subscribe && $existing === null) {
            $this->subscriptions->save(new MemberSubscription(null, $subscriberId, $followedId, null));
        } elseif (!$subscribe && $existing !== null) {
            $this->subscriptions->delete($subscriberId, $followedId);
        }
    }
}
