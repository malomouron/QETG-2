<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\UseCase\PurgeExpiredRegistrations;
use QETG\Domain\Entity\PendingRegistration;
use QETG\Domain\Repository\PendingRegistrationRepositoryInterface;

$repository = new class implements PendingRegistrationRepositoryInterface {
    public ?\DateTimeImmutable $cutoff = null;
    public function findById(int $id): ?PendingRegistration { unset($id); return null; }
    public function findByConfirmationToken(string $token): ?PendingRegistration { unset($token); return null; }
    public function findByLogin(string $login): ?PendingRegistration { unset($login); return null; }
    public function findByEmail(string $email): ?PendingRegistration { unset($email); return null; }
    public function save(PendingRegistration $registration): PendingRegistration { return $registration; }
    public function delete(int $id): void { unset($id); }
    public function deleteExpiredBefore(\DateTimeImmutable $cutoff): int { $this->cutoff = $cutoff; return 3; }
};

$count = (new PurgeExpiredRegistrations($repository, 1))->execute(new \DateTimeImmutable('2026-11-10 12:00:00'));
if ($count !== 3 || $repository->cutoff?->format('Y-m-d H:i:s') !== '2026-11-09 12:00:00') {
    throw new RuntimeException('Cleanup should purge registrations older than the configured validity period.');
}

fwrite(STDOUT, "Expired registration cleanup checks passed.\n");
