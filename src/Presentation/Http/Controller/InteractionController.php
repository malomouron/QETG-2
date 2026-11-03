<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use InvalidArgumentException;
use QETG\Application\Port\CaptchaVerifierInterface;
use QETG\Application\UseCase\SubmitFeedback;
use QETG\Application\UseCase\ToggleQuizReaction;
use Throwable;

final class InteractionController
{
    public function __construct(
        private SubmitFeedback $submitFeedback,
        private ToggleQuizReaction $toggleReaction,
        private string $basePath,
        private ?CaptchaVerifierInterface $captchaVerifier = null
    ) {
    }

    public function comment(): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->flash('error', 'Le formulaire a expiré. Rechargez la page et réessayez.');
            $this->redirect('/accueil#commentaires');
        }

        if ($this->captchaVerifier !== null && !$this->captchaVerifier->verify(
            trim((string) ($_POST['g-recaptcha-response'] ?? '')),
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        )) {
            $this->flash('error', 'Veuillez valider la vérification anti-robot.');
            $this->redirect('/accueil#commentaires');
        }

        try {
            $result = $this->submitFeedback->execute(
                (string) ($_POST['user_name'] ?? ''),
                (string) ($_POST['user_prenom'] ?? ''),
                (string) ($_POST['user_mail'] ?? ''),
                (string) ($_POST['user_message'] ?? ''),
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                isset($_SESSION['id'])
            );
        } catch (InvalidArgumentException $exception) {
            $this->flash('error', $exception->getMessage());
            $this->redirect('/accueil#commentaires');
        } catch (Throwable $exception) {
            error_log('QETG feedback submission failed: ' . $exception->getMessage());
            $this->flash('error', 'Le commentaire n’a pas pu être enregistré. Réessayez plus tard.');
            $this->redirect('/accueil#commentaires');
        }

        $this->flash(
            'success',
            $result->notificationSent
                ? 'Votre commentaire a été publié et l’équipe a été notifiée.'
                : 'Votre commentaire a été publié. La notification e-mail est indisponible sur cette installation.'
        );
        $this->redirect('/accueil#commentaires');
    }

    public function reaction(): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->flash('error', 'La session a expiré. Rechargez la page et réessayez.');
            $this->redirect($this->safeReturnPath($_POST['return_to'] ?? null));
        }

        $quizId = filter_var($_POST['quiz_id'] ?? null, FILTER_VALIDATE_INT);
        $value = filter_var($_POST['value'] ?? null, FILTER_VALIDATE_INT);
        if ($quizId === false || $quizId === null || $value === false || $value === null) {
            $this->flash('error', 'Réaction invalide.');
            $this->redirect($this->safeReturnPath($_POST['return_to'] ?? null));
        }

        try {
            $newValue = $this->toggleReaction->execute((int) $quizId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), (int) $value);
            $this->flash('success', $newValue === null ? 'Votre réaction a été retirée.' : 'Votre réaction a été enregistrée.');
        } catch (InvalidArgumentException $exception) {
            $this->flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('QETG quiz reaction failed: ' . $exception->getMessage());
            $this->flash('error', 'La réaction n’a pas pu être enregistrée. Réessayez.');
        }

        $this->redirect($this->safeReturnPath($_POST['return_to'] ?? null));
    }

    private function hasValidCsrfToken(): bool
    {
        $token = (string) ($_POST['_csrf'] ?? '');
        return $token !== ''
            && isset($_SESSION['interaction_csrf'])
            && hash_equals((string) $_SESSION['interaction_csrf'], $token);
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['interaction_flash'] = array('type' => $type, 'message' => $message);
    }

    private function safeReturnPath(mixed $candidate): string
    {
        if (!\is_string($candidate) || $candidate === '' || !\str_starts_with($candidate, '/') || \str_starts_with($candidate, '//')) {
            return '/accueil#catalogue';
        }
        $path = parse_url($candidate, PHP_URL_PATH);
        if (!\is_string($path) || $this->basePath !== '' && !\str_starts_with($path, $this->basePath . '/')) {
            return '/accueil#catalogue';
        }
        return \substr($candidate, \strlen($this->basePath));
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $this->basePath . $path, true, 303);
        exit;
    }
}
