<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\MemberSubscription;

interface MemberSubscriptionRepositoryInterface
{
    public function findByMembers(int $subscriberMemberId, int $followedMemberId): ?MemberSubscription;

    /** @return list<MemberSubscription> Members followed by this member. */
    public function findFollowing(int $subscriberMemberId): array;

    /** @return list<MemberSubscription> Members who follow this member. */
    public function findFollowers(int $memberId): array;

    public function save(MemberSubscription $subscription): MemberSubscription;

    public function delete(int $subscriberMemberId, int $followedMemberId): void;
}