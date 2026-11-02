<?php

namespace App\Support;

use RuntimeException;

class LegacyPassword
{
    public function supported(string $hash): bool
    {
        return $this->isMd5($hash) || (password_get_info($hash)['algo'] ?? null) !== null;
    }

    public function verify(string $password, string $hash): bool
    {
        if (password_get_info($hash)['algoName'] === 'bcrypt' && strlen($password) > 72) {
            return false;
        }

        return $this->isMd5($hash)
            ? hash_equals(strtolower($hash), md5($password))
            : password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return $this->isMd5($hash) || password_needs_rehash($hash, $this->algorithm());
    }

    public function hash(string $password): string
    {
        $algorithm = $this->algorithm();
        if ($algorithm === PASSWORD_DEFAULT && strlen($password) > 72) {
            throw new RuntimeException('Argon2id est requis pour hacher un mot de passe de plus de 72 octets.');
        }

        $hash = password_hash($password, $algorithm);
        if ($hash === false) {
            throw new RuntimeException('Impossible de hacher le mot de passe.');
        }

        return $hash;
    }

    private function isMd5(string $hash): bool
    {
        return preg_match('/\A[0-9a-f]{32}\z/i', $hash) === 1;
    }

    private function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }
}
