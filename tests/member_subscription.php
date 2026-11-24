<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\UseCase\SetMemberSubscription;
use QETG\Domain\Entity\Member;
use QETG\Domain\Entity\MemberSubscription;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\MemberSubscriptionRepositoryInterface;

function assertMemberSubscription(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$memberRepository = new class implements MemberRepositoryInterface {
    public function findById(int $id): ?Member { return in_array($id, array(1, 2), true) ? new Member($id, 'member-' . $id, '', '', '', '', false, 'default', false, '', '') : null; }
    public function findByLogin(string $login): ?Member { unset($login); return null; }
    public function findByEmail(string $email): ?Member { unset($email); return null; }
    public function searchOthers(int $excludedMemberId, string $loginTerm, int $limit): array { unset($excludedMemberId, $loginTerm, $limit); return array(); }
    public function save(Member $member): Member { return $member; }
    public function delete(int $id): void { unset($id); }
};
$repository = new class implements MemberSubscriptionRepositoryInterface {
    public array $rows = array();
    public function findByMembers(int $subscriberMemberId, int $followedMemberId): ?MemberSubscription { return $this->rows[$subscriberMemberId . ':' . $followedMemberId] ?? null; }
    public function findFollowing(int $subscriberMemberId): array { unset($subscriberMemberId); return array_values($this->rows); }
    public function findFollowers(int $memberId): array { unset($memberId); return array_values($this->rows); }
    public function save(MemberSubscription $subscription): MemberSubscription { $this->rows[$subscription->subscriberMemberId . ':' . $subscription->followedMemberId] = $subscription; return $subscription; }
    public function delete(int $subscriberMemberId, int $followedMemberId): void { unset($this->rows[$subscriberMemberId . ':' . $followedMemberId]); }
};

$service = new SetMemberSubscription($memberRepository, $repository);
$service->execute(1, 2, true);
$service->execute(1, 2, true);
assertMemberSubscription(count($repository->rows) === 1, 'Following the same member twice must not duplicate subscriptions.');
$service->execute(1, 2, false);
assertMemberSubscription($repository->rows === array(), 'Unfollowing must delete the subscription.');

try {
    $service->execute(1, 1, true);
    throw new RuntimeException('A member must not follow themself.');
} catch (InvalidArgumentException $exception) {
    assertMemberSubscription(str_contains($exception->getMessage(), 'ne peut pas être suivi'), 'Self-follow should return a clear validation error.');
}

fwrite(STDOUT, "Member subscription checks passed.\n");
