<?php

declare(strict_types=1);

namespace QETG\Domain\Service;

use RuntimeException;

final class PasswordHasher
{
    public function verify(string $password, string $storedHash): bool
    {
        if (password_get_info($storedHash)['algoName'] === 'bcrypt' && strlen($password) > 72) {
            return false;
        }
        return password_verify($password, $storedHash);
    }

    public function hash(string $password): string
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        if ($algorithm === PASSWORD_DEFAULT && strlen($password) > 72) {
            throw new RuntimeException('Argon2id est requis pour hacher un mot de passe de plus de 72 octets.');
        }
        $hash = password_hash($password, $algorithm);
        if ($hash === false) {
            throw new RuntimeException('Impossible de hacher le mot de passe.');
        }
        return $hash;
    }

    public function needsRehash(string $hash): bool
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        return password_needs_rehash($hash, $algorithm);
    }
}
