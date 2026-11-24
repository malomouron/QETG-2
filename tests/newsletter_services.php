<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\Port\EmailSenderInterface;
use QETG\Application\Service\NewsletterUnsubscribeToken;
use QETG\Application\UseCase\SendNewsletterCampaign;
use QETG\Application\UseCase\UnsubscribeFromNewsletter;
use QETG\Domain\Entity\Member;
use QETG\Domain\Entity\NewsletterRecipient;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\NewsletterRecipientRepositoryInterface;

function assertNewsletter(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$member = new Member(11, 'newsletter-user', 'hash', 'subscriber@example.invalid', 'Nom', 'Prénom', false, 'default', true, '', '2026-01-01');
$memberRepository = new class($member) implements MemberRepositoryInterface {
    public ?Member $member;
    public function __construct(Member $member) { $this->member = $member; }
    public function findById(int $id): ?Member { return $this->member?->id === $id ? $this->member : null; }
    public function findByLogin(string $login): ?Member { unset($login); return null; }
    public function findByEmail(string $email): ?Member { return $this->member?->email === $email ? $this->member : null; }
    public function searchOthers(int $excludedMemberId, string $loginTerm, int $limit): array { unset($excludedMemberId, $loginTerm, $limit); return array(); }
    public function save(Member $member): Member { $this->member = $member; return $member; }
    public function delete(int $id): void { unset($id); }
};
$recipientRepository = new class implements NewsletterRecipientRepositoryInterface {
    public function findSubscribedRecipients(): array
    {
        return array(new NewsletterRecipient(11, 'newsletter-user', 'subscriber@example.invalid'));
    }
};
$emailSender = new class implements EmailSenderInterface {
    public array $messages = array();
    public function send(string $recipient, string $subject, string $htmlBody, string $textBody): void
    {
        $this->messages[] = compact('recipient', 'subject', 'htmlBody', 'textBody');
    }
};

$tokenService = new NewsletterUnsubscribeToken(str_repeat('s', 40));
$campaign = new SendNewsletterCampaign($recipientRepository, $memberRepository, $emailSender, $tokenService, 'https://example.invalid');
$summary = $campaign->execute('Actualités QETG', '<h1>Bonjour</h1>', 'Bonjour');
assertNewsletter($summary === array('sent' => 1, 'failed' => 0, 'skipped' => 0), 'Campaign summary should count successful deliveries.');
assertNewsletter(count($emailSender->messages) === 1, 'One opted-in member should receive one email.');
assertNewsletter(str_contains($emailSender->messages[0]['htmlBody'], '/newsletter/desabonnement?token=11.'), 'Each campaign email should have a signed unsubscribe URL.');

preg_match('/token=([^"&]+)/', $emailSender->messages[0]['htmlBody'], $matches);
$token = rawurldecode($matches[1] ?? '');
$unsubscribe = new UnsubscribeFromNewsletter($memberRepository, $tokenService);
assertNewsletter($unsubscribe->execute($token), 'A valid unsubscribe token should be accepted.');
assertNewsletter($memberRepository->member?->newsletterEnabled === false, 'Unsubscribe should disable the newsletter preference.');
assertNewsletter($unsubscribe->execute($token), 'Repeating a valid unsubscribe should remain idempotent.');
assertNewsletter(!$unsubscribe->execute('11.' . str_repeat('0', 64)), 'An invalid signature should be rejected.');

fwrite(STDOUT, "Newsletter service checks passed.\n");
