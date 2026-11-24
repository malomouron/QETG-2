<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use InvalidArgumentException;
use QETG\Application\Port\EmailSenderInterface;
use QETG\Application\Port\OptionalEmailSenderInterface;
use QETG\Application\Service\NewsletterUnsubscribeToken;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\NewsletterRecipientRepositoryInterface;
use RuntimeException;
use Throwable;

final class SendNewsletterCampaign
{
    public function __construct(
        private NewsletterRecipientRepositoryInterface $recipients,
        private MemberRepositoryInterface $members,
        private EmailSenderInterface $emailSender,
        private NewsletterUnsubscribeToken $unsubscribeTokens,
        private string $siteBaseUrl
    ) {
    }

    /** @return array{sent: int, failed: int, skipped: int} */
    public function execute(string $subject, string $htmlBody, string $textBody): array
    {
        $subject = trim($subject);
        if ($subject === '' || strlen($subject) > 200 || trim($htmlBody) === '') {
            throw new InvalidArgumentException('Le sujet et le contenu HTML de la campagne sont obligatoires.');
        }
        if ($this->siteBaseUrl === '' || filter_var($this->siteBaseUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('APP_URL doit contenir l’URL publique du site.');
        }
        if ($this->emailSender instanceof OptionalEmailSenderInterface && !$this->emailSender->isConfigured()) {
            throw new RuntimeException('Le service SMTP n’est pas configuré; aucun e-mail n’a été envoyé.');
        }
        if (!$this->unsubscribeTokens->isConfigured()) {
            throw new RuntimeException('APP_PRIVATE_KEY doit contenir au moins 32 caractères avant d’envoyer une newsletter.');
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        foreach ($this->recipients->findSubscribedRecipients() as $recipient) {
            $member = $this->members->findById($recipient->memberId);
            if ($member === null || !$member->newsletterEnabled || $member->email !== $recipient->email) {
                $skipped++;
                continue;
            }
            $unsubscribeUrl = rtrim($this->siteBaseUrl, '/') . '/newsletter/desabonnement?token=' . rawurlencode($this->unsubscribeTokens->create($member));
            $html = rtrim($htmlBody) . '<hr><p>Pour ne plus recevoir cette newsletter, <a href="'
                . htmlspecialchars($unsubscribeUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '">désabonnez-vous ici</a>.</p>';
            $text = rtrim($textBody) . "\n\nDésabonnement : " . $unsubscribeUrl;
            try {
                $this->emailSender->send($member->email, $subject, $html, $text);
                $sent++;
            } catch (Throwable $exception) {
                $failed++;
                error_log('QETG newsletter delivery failed for member ' . $recipient->memberId . ': ' . $exception->getMessage());
            }
        }

        return array('sent' => $sent, 'failed' => $failed, 'skipped' => $skipped);
    }
}
