<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use DateTimeImmutable;
use QETG\Domain\Entity\MemberSubscription;
use QETG\Domain\Repository\MemberSubscriptionRepositoryInterface;

final class MySqlMemberSubscriptionRepository extends MySqlRepository implements MemberSubscriptionRepositoryInterface
{
    public function findByMembers(int $subscriberMemberId, int $followedMemberId): ?MemberSubscription
    {
        $row = $this->one(
            'SELECT * FROM quiz_subscription_membre WHERE idUser = ? AND idUserAbonnement = ? LIMIT 1',
            $subscriberMemberId,
            $followedMemberId
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function findFollowing(int $subscriberMemberId): array
    {
        $rows = $this->many('SELECT * FROM quiz_subscription_membre WHERE idUser = ? ORDER BY dateAjout DESC', $subscriberMemberId);
        return array_map(fn (array $row): MemberSubscription => $this->hydrate($row), $rows);
    }

    public function findFollowers(int $memberId): array
    {
        $rows = $this->many('SELECT * FROM quiz_subscription_membre WHERE idUserAbonnement = ? ORDER BY dateAjout DESC', $memberId);
        return array_map(fn (array $row): MemberSubscription => $this->hydrate($row), $rows);
    }

    public function save(MemberSubscription $subscription): MemberSubscription
    {
        if ($subscription->id === null) {
            if ($subscription->createdAt === null) {
                $this->execute(
                    'INSERT INTO quiz_subscription_membre (idUser, idUserAbonnement) VALUES (?, ?)',
                    $subscription->subscriberMemberId,
                    $subscription->followedMemberId
                );
            } else {
                $this->execute(
                    'INSERT INTO quiz_subscription_membre (idUser, idUserAbonnement, dateAjout) VALUES (?, ?, ?)',
                    $subscription->subscriberMemberId,
                    $subscription->followedMemberId,
                    $subscription->createdAt->format('Y-m-d H:i:s')
                );
            }
            return new MemberSubscription($this->lastInsertId(), $subscription->subscriberMemberId, $subscription->followedMemberId, $subscription->createdAt);
        }

        if ($subscription->createdAt === null) {
            $this->execute(
                'UPDATE quiz_subscription_membre SET idUser = ?, idUserAbonnement = ? WHERE idAbonnement = ?',
                $subscription->subscriberMemberId,
                $subscription->followedMemberId,
                $subscription->id
            );
        } else {
            $this->execute(
                'UPDATE quiz_subscription_membre SET idUser = ?, idUserAbonnement = ?, dateAjout = ? WHERE idAbonnement = ?',
                $subscription->subscriberMemberId,
                $subscription->followedMemberId,
                $subscription->createdAt->format('Y-m-d H:i:s'),
                $subscription->id
            );
        }
        return $subscription;
    }

    public function delete(int $subscriberMemberId, int $followedMemberId): void
    {
        $this->execute(
            'DELETE FROM quiz_subscription_membre WHERE idUser = ? AND idUserAbonnement = ?',
            $subscriberMemberId,
            $followedMemberId
        );
    }

    private function hydrate(array $row): MemberSubscription
    {
        return new MemberSubscription(
            (int) $row['idAbonnement'],
            (int) $row['idUser'],
            (int) $row['idUserAbonnement'],
            new DateTimeImmutable((string) $row['dateAjout'])
        );
    }
}