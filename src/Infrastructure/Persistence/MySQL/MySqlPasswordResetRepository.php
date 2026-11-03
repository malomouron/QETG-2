<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use DateTimeImmutable;
use QETG\Domain\Entity\PasswordReset;
use QETG\Domain\Repository\PasswordResetRepositoryInterface;

final class MySqlPasswordResetRepository extends MySqlRepository implements PasswordResetRepositoryInterface
{
    public function findByTokenHash(string $tokenHash): ?PasswordReset
    {
        $row = $this->one('SELECT * FROM quiz_password_reset WHERE token_hash = ? LIMIT 1', $tokenHash);
        if ($row === null) {
            return null;
        }

        return new PasswordReset(
            (int) $row['id'],
            (int) $row['member_id'],
            (string) $row['token_hash'],
            new DateTimeImmutable((string) $row['expires_at']),
            new DateTimeImmutable((string) $row['created_at'])
        );
    }

    public function deleteByMemberId(int $memberId): void
    {
        $this->execute('DELETE FROM quiz_password_reset WHERE member_id = ?', $memberId);
    }

    public function save(PasswordReset $passwordReset): PasswordReset
    {
        $this->execute(
            'INSERT INTO quiz_password_reset (member_id, token_hash, expires_at) VALUES (?, ?, ?)',
            $passwordReset->memberId,
            $passwordReset->tokenHash,
            $passwordReset->expiresAt->format('Y-m-d H:i:s')
        );

        return new PasswordReset(
            $this->lastInsertId(),
            $passwordReset->memberId,
            $passwordReset->tokenHash,
            $passwordReset->expiresAt,
            $passwordReset->createdAt
        );
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_password_reset WHERE id = ?', $id);
    }
}
