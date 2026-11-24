<?php

declare(strict_types=1);

namespace QETG\Application\Service;

use InvalidArgumentException;

final class RandomPasswordGenerator
{
    public function generate(int $length): string
    {
        if ($length < 1 || $length > 256) {
            throw new InvalidArgumentException('La longueur du mot de passe doit être comprise entre 1 et 256 caractères.');
        }

        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $password = '';
        $maxIndex = strlen($characters) - 1;
        for ($index = 0; $index < $length; $index++) {
            $password .= $characters[random_int(0, $maxIndex)];
        }
        return $password;
    }
}
