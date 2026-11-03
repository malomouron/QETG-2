<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\PasswordReset;

interface PasswordResetRepositoryInterface
{
    public function findByTokenHash(string $tokenHash): ?PasswordReset;

    public function deleteByMemberId(int $memberId): void;

    public function save(PasswordReset $passwordReset): PasswordReset;

    public function delete(int $id): void;
}
