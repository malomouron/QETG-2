<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use InvalidArgumentException;
use QETG\Application\UseCase\SubmitCommentReply;
use Throwable;

final class CommentReplyController
{
    public function __construct(private SubmitCommentReply $submitReply, private string $basePath)
    {
    }

    public function submit(): void
    {
        if (!isset($_SESSION['id']) || (int) $_SESSION['id'] < 1) {
            $this->flash('error', 'Connectez-vous pour répondre à un commentaire.');
            $this->redirect('/connexion');
        }

        $token = (string) ($_POST['_csrf'] ?? '');
        if ($token === '' || !isset($_SESSION['interaction_csrf']) || !is_string($_SESSION['interaction_csrf'])
            || !hash_equals($_SESSION['interaction_csrf'], $token)
        ) {
            $this->flash('error', 'La session du formulaire a expiré. Rechargez la page et réessayez.');
            $this->redirect('/accueil#commentaires');
        }

        $commentId = filter_var($_POST['comment_id'] ?? $_POST['id_comm'] ?? null, FILTER_VALIDATE_INT);
        if ($commentId === false || $commentId === null || $commentId < 1) {
            $this->flash('error', 'Le commentaire sélectionné est invalide.');
            $this->redirect('/accueil#commentaires');
        }

        try {
            $this->submitReply->execute(
                $commentId,
                (int) $_SESSION['id'],
                (string) ($_POST['reply'] ?? $_POST['reponse_comm'] ?? ''),
                (string) ($_SERVER['REMOTE_ADDR'] ?? '')
            );
            $this->flash('success', 'Votre réponse a été publiée.');
        } catch (InvalidArgumentException $exception) {
            $this->flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('QETG comment reply failed: ' . $exception->getMessage());
            $this->flash('error', 'La réponse n’a pas pu être publiée. Réessayez.');
        }

        $this->redirect('/accueil#commentaires');
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['interaction_flash'] = array('type' => $type, 'message' => $message);
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $this->basePath . $path, true, 303);
        exit;
    }
}
