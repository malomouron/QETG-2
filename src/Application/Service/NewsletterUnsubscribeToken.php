<?php

declare(strict_types=1);

namespace QETG\Application\Service;

use InvalidArgumentException;
use QETG\Domain\Entity\Member;

final class NewsletterUnsubscribeToken
{
    public function __construct(private string $secret)
    {
    }

    public function isConfigured(): bool
    {
        return strlen($this->secret) >= 32;
    }

    public function create(Member $member): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('APP_PRIVATE_KEY doit contenir au moins 32 caractères pour signer les liens de désabonnement.');
        }
        if ($member->id === null) {
            throw new InvalidArgumentException('Impossible de créer un lien pour un compte invalide.');
        }
        $memberId = (string) $member->id;
        $signature = hash_hmac('sha256', $memberId . "\n" . strtolower($member->email), $this->secret);
        return $memberId . '.' . $signature;
    }

    public function verifies(string $token, Member $member): bool
    {
        if (!$this->isConfigured() || $member->id === null || preg_match('/\A([1-9][0-9]*)\.([a-f0-9]{64})\z/', $token, $matches) !== 1) {
            return false;
        }
        if ((int) $matches[1] !== $member->id) {
            return false;
        }
        $expected = hash_hmac('sha256', $matches[1] . "\n" . strtolower($member->email), $this->secret);
        return hash_equals($expected, $matches[2]);
    }
}
