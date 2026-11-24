<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use DateTimeImmutable;
use QETG\Domain\Repository\PendingRegistrationRepositoryInterface;

final class PurgeExpiredRegistrations
{
    public function __construct(
        private PendingRegistrationRepositoryInterface $registrations,
        private int $validityDays = 1
    ) {
    }

    public function execute(?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable();
        $cutoff = $now->modify('-' . max(1, $this->validityDays) . ' days');
        return $this->registrations->deleteExpiredBefore($cutoff);
    }
}
