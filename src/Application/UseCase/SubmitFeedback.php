<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use QETG\Application\DTO\FeedbackSubmissionResult;
use QETG\Application\Port\EmailSenderInterface;
use QETG\Application\Port\OptionalEmailSenderInterface;
use QETG\Domain\Entity\Comment;
use QETG\Domain\Repository\CommentRepositoryInterface;
use InvalidArgumentException;
use Throwable;

final class SubmitFeedback
{
    public function __construct(
        private CommentRepositoryInterface $comments,
        private EmailSenderInterface $emailSender
    ) {
    }

    public function execute(
        string $lastName,
        string $firstName,
        string $email,
        string $content,
        string $ipAddress,
        bool $verifiedMember
    ): FeedbackSubmissionResult {
        $lastName = trim($lastName);
        $firstName = trim($firstName);
        $email = trim($email);
        $content = trim($content);

        if ($lastName === '' || \strlen($lastName) > 50) {
            throw new InvalidArgumentException('Le nom est obligatoire et limité à 50 caractères.');
        }
        if ($firstName === '' || \strlen($firstName) > 50) {
            throw new InvalidArgumentException('Le prénom est obligatoire et limité à 50 caractères.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || \strlen($email) > 50) {
            throw new InvalidArgumentException('Veuillez saisir une adresse e-mail valide de 50 caractères maximum.');
        }
        if ($content === '' || \strlen($content) > 1000) {
            throw new InvalidArgumentException('Le commentaire est obligatoire et limité à 1000 caractères.');
        }

        if (!$verifiedMember) {
            $lastName .= ' (Personne non vérifiée)';
        }

        $savedComment = $this->comments->save(new Comment(
            null,
            $lastName,
            $firstName,
            $email,
            $content,
            date('d/m/Y'),
            $ipAddress
        ));

        $notificationSent = false;
        if ($this->emailSender instanceof OptionalEmailSenderInterface && !$this->emailSender->isConfigured()) {
            return new FeedbackSubmissionResult((int) $savedComment->id, false);
        }

        try {
            $safeName = htmlspecialchars($firstName . ' ' . $lastName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeContent = nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $html = '<!doctype html><html lang="fr"><body><h1>Nouveau commentaire QETG</h1><p>De : ' . $safeName . ' (' . $safeEmail . ')</p><div>' . $safeContent . '</div></body></html>';
            $this->emailSender->send(
                'admin@malomouron.fr',
                'Quiz en tout genre - Nouveau commentaire',
                $html,
                'Nouveau commentaire de ' . $firstName . ' ' . $lastName . " ($email):\n\n" . $content
            );
            $notificationSent = true;
        } catch (Throwable $exception) {
            // Feedback remains saved when local SMTP is intentionally unconfigured.
            error_log('QETG feedback notification unavailable: ' . $exception->getMessage());
        }

        return new FeedbackSubmissionResult((int) $savedComment->id, $notificationSent);
    }
}
