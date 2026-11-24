<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use InvalidArgumentException;
use QETG\Application\UseCase\SetMemberSubscription;

final class MemberSubscriptionController
{
    public function __construct(private SetMemberSubscription $setSubscription, private string $basePath)
    {
    }

    public function update(): void
    {
        if (!isset($_SESSION['id']) || (int) $_SESSION['id'] < 1) {
            $this->flash('error', 'Connectez-vous pour suivre un membre.');
            $this->redirect('/connexion');
        }

        $submittedToken = (string) ($_POST['_csrf'] ?? '');
        $sessionToken = $_SESSION['community_csrf'] ?? '';
        if ($submittedToken === '' || !is_string($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
            $this->flash('error', 'La session a expiré. Rechargez la page et réessayez.');
            $this->redirect($this->returnPath());
        }

        $memberId = filter_var($_POST['member_id'] ?? null, FILTER_VALIDATE_INT);
        $subscribe = ($_POST['subscribe'] ?? null) === '1';
        if ($memberId === false || $memberId === null || $memberId < 1 || !in_array($_POST['subscribe'] ?? null, array('0', '1'), true)) {
            $this->flash('error', 'Demande de suivi invalide.');
            $this->redirect($this->returnPath());
        }

        try {
            $this->setSubscription->execute((int) $_SESSION['id'], $memberId, $subscribe);
            $this->flash('success', $subscribe ? 'Vous suivez maintenant ce membre.' : 'Vous ne suivez plus ce membre.');
        } catch (InvalidArgumentException $exception) {
            $this->flash('error', $exception->getMessage());
        }

        $this->redirect($this->returnPath());
    }

    private function returnPath(): string
    {
        $returnTab = $_POST['return_tab'] ?? 'members';
        if ($returnTab === 'profile') {
            $profileId = filter_var($_POST['profile_id'] ?? null, FILTER_VALIDATE_INT);
            if ($profileId !== false && $profileId !== null && $profileId > 0) {
                return '/profil-public?id=' . $profileId;
            }
        }
        if (!is_string($returnTab) || !in_array($returnTab, array('following', 'members'), true)) {
            $returnTab = 'members';
        }
        return '/quiz-membres?tab=' . $returnTab;
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['community_flash'] = array('type' => $type, 'message' => $message);
    }

    private function redirect(string $path): never
    {
        $isPublicProfileReturn = preg_match('#\A/profil-public\?id=[1-9][0-9]*\z#', $path) === 1;
        if (!str_starts_with($path, '/quiz-membres') && $path !== '/connexion' && !$isPublicProfileReturn) {
            $path = '/quiz-membres';
        }
        header('Location: ' . $this->basePath . $path, true, 303);
        exit;
    }
}
