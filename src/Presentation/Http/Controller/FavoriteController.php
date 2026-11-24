<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use InvalidArgumentException;
use QETG\Application\UseCase\SetQuizFavorite;

final class FavoriteController
{
    public function __construct(
        private SetQuizFavorite $setQuizFavorite,
        private string $basePath
    ) {
    }

    public function update(): void
    {
        if (!isset($_SESSION['id']) || (int) $_SESSION['id'] < 1) {
            $this->flash('error', 'Connectez-vous pour gérer vos favoris.');
            $this->redirect('/connexion');
        }

        $submittedToken = (string) ($_POST['_csrf'] ?? '');
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if ($submittedToken === '' || !is_string($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
            $this->flash('error', 'La session du formulaire a expiré. Réessayez.');
            $this->redirect('/compte/profil');
        }

        $quizId = filter_var($_POST['quiz_id'] ?? null, FILTER_VALIDATE_INT);
        $favoriteValue = $_POST['favorite'] ?? null;
        if ($quizId === false || $quizId === null || $quizId < 1 || !in_array($favoriteValue, array('0', '1', 0, 1), true)) {
            $this->flash('error', 'La demande de favori est invalide.');
            $this->redirect('/compte/profil');
        }

        try {
            $statistic = $this->setQuizFavorite->execute(
                (int) $_SESSION['id'],
                $quizId,
                (string) $favoriteValue === '1'
            );
            $this->flash('success', $statistic->isFavorite ? 'Quiz ajouté aux favoris.' : 'Quiz retiré des favoris.');
        } catch (InvalidArgumentException $exception) {
            $this->flash('error', $exception->getMessage());
        }

        $this->redirect('/compte/profil');
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['account_flash'] = array('type' => $type, 'message' => $message);
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $this->basePath . $path, true, 303);
        exit;
    }
}
