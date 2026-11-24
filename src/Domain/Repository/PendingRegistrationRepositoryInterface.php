<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\PendingRegistration;
use DateTimeImmutable;

interface PendingRegistrationRepositoryInterface
{
    public function findById(int $id): ?PendingRegistration;

    public function findByConfirmationToken(string $token): ?PendingRegistration;

    public function findByLogin(string $login): ?PendingRegistration;

    public function findByEmail(string $email): ?PendingRegistration;

    public function save(PendingRegistration $registration): PendingRegistration;

    public function delete(int $id): void;

    public function deleteExpiredBefore(DateTimeImmutable $cutoff): int;
}