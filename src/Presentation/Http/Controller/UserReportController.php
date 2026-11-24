<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use InvalidArgumentException;
use QETG\Application\UseCase\ReportMember;
use Throwable;

final class UserReportController
{
    public function __construct(private ReportMember $reportMember, private string $basePath)
    {
    }

    public function submit(): void
    {
        $targetId = filter_var($_POST['member_id'] ?? null, FILTER_VALIDATE_INT);
        $redirectPath = $targetId !== false && $targetId !== null && $targetId > 0
            ? '/profil-public?id=' . $targetId . '#signaler'
            : '/quiz-membres';

        if (!isset($_SESSION['id']) || (int) $_SESSION['id'] < 1) {
            $this->flash('error', 'Connectez-vous pour signaler un profil.');
            $this->redirect('/connexion');
        }

        $submittedToken = (string) ($_POST['_csrf'] ?? '');
        $sessionToken = $_SESSION['user_report_csrf'] ?? '';
        if ($submittedToken === '' || !is_string($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
            $this->flash('error', 'La session a expiré. Rechargez la page et réessayez.');
            $this->redirect($redirectPath);
        }
        if ($targetId === false || $targetId === null || $targetId < 1) {
            $this->flash('error', 'Profil à signaler invalide.');
            $this->redirect('/quiz-membres');
        }

        try {
            $this->reportMember->execute((int) $_SESSION['id'], (int) $targetId);
            $this->flash('success', 'Le profil a été signalé à l’équipe de modération.');
        } catch (InvalidArgumentException $exception) {
            $this->flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('QETG user report failed: ' . $exception->getMessage());
            $this->flash('error', 'Le signalement n’a pas pu être enregistré. Réessayez plus tard.');
        }

        $this->redirect($redirectPath);
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['community_flash'] = array('type' => $type, 'message' => $message);
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $this->basePath . $path, true, 303);
        exit;
    }
}
