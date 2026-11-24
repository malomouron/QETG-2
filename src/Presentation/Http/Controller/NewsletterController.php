<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use QETG\Application\UseCase\UnsubscribeFromNewsletter;

final class NewsletterController
{
    public function __construct(private UnsubscribeFromNewsletter $unsubscribe, private string $basePath = '')
    {
    }

    public function confirmUnsubscribe(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        if (preg_match('/\A[1-9][0-9]*\.[a-f0-9]{64}\z/', $token) !== 1) {
            http_response_code(400);
            $this->renderMessage('Lien de désabonnement invalide.', false);
            return;
        }
        if (!isset($_SESSION['newsletter_csrf']) || !is_string($_SESSION['newsletter_csrf'])) {
            $_SESSION['newsletter_csrf'] = bin2hex(random_bytes(32));
        }
        header('Content-Type: text/html; charset=UTF-8');
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="fr"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Désabonnement newsletter</title><body style="font-family:system-ui,sans-serif;max-width:640px;margin:12vh auto;padding:24px;color:#202630"><main><h1>Désabonnement à la newsletter</h1><p>Confirmez que vous ne souhaitez plus recevoir les newsletters de Quiz en tout genre.</p><form method="post" action="' . $escape($this->basePath . '/newsletter/desabonnement') . '"><input type="hidden" name="_csrf" value="' . $escape($_SESSION['newsletter_csrf']) . '"><input type="hidden" name="token" value="' . $escape($token) . '"><button type="submit">Confirmer le désabonnement</button></form><p><a href="' . $escape($this->basePath . '/accueil') . '">Annuler et retourner au site</a></p></main></body></html>';
    }

    public function unsubscribe(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        $submitted = (string) ($_POST['_csrf'] ?? '');
        $sessionToken = $_SESSION['newsletter_csrf'] ?? '';
        if ($submitted === '' || !is_string($sessionToken) || !hash_equals($sessionToken, $submitted)) {
            http_response_code(400);
            $this->renderMessage('La confirmation a expiré. Rouvrez le lien reçu par e-mail.', false);
            return;
        }
        $success = $this->unsubscribe->execute($token);
        $this->renderMessage(
            $success ? 'Votre adresse e-mail a été désabonnée de la newsletter.' : 'Ce lien de désabonnement est invalide ou expiré.',
            $success
        );
    }

    private function renderMessage(string $message, bool $success): void
    {
        http_response_code($success ? 200 : (http_response_code() >= 400 ? http_response_code() : 400));
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="fr"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Désabonnement newsletter</title><body style="font-family:system-ui,sans-serif;max-width:640px;margin:12vh auto;padding:24px;color:#202630"><main><h1>Désabonnement à la newsletter</h1><p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p><a href="' . htmlspecialchars($this->basePath . '/accueil', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Retour au site</a></main></body></html>';
    }
}
