<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use QETG\Application\Port\EmailSenderInterface;
use QETG\Domain\Entity\Member;
use QETG\Domain\Entity\MemberAchievement;
use QETG\Domain\Entity\PendingRegistration;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\PendingRegistrationRepositoryInterface;
use QETG\Domain\Entity\PasswordReset;
use QETG\Domain\Repository\PasswordResetRepositoryInterface;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;
use QETG\Domain\Repository\ImageRepositoryInterface;
use QETG\Domain\Repository\AchievementRepositoryInterface;
use QETG\Domain\Repository\MemberAchievementRepositoryInterface;
use QETG\Domain\Repository\QuizReactionRepositoryInterface;
use DateTimeImmutable;

final class AccountController
{
    public function __construct(
        private MemberRepositoryInterface $members,
        private PendingRegistrationRepositoryInterface $pendingRegistrations,
        private PasswordResetRepositoryInterface $passwordResets,
        private QuizStatisticRepositoryInterface $statistics,
        private QuizRepositoryInterface $quizzes,
        private ImageRepositoryInterface $images,
        private AchievementRepositoryInterface $achievements,
        private MemberAchievementRepositoryInterface $memberAchievements,
        private QuizReactionRepositoryInterface $reactions,
        private EmailSenderInterface $emailSender,
        private string $basePath
    ) {
    }

    public function loginForm(): void
    {
        $this->render('login');
    }

    public function registerForm(): void
    {
        $this->render('register');
    }

    public function login(): void
    {
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du formulaire a expiré. Réessayez.');
            $this->redirect('/connexion');
        }

        $identity = trim((string) ($_POST['identity'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($identity === '' || $password === '') {
            $this->flash('error', 'Veuillez saisir votre identifiant et votre mot de passe.');
            $this->redirect('/connexion');
        }

        $matches = array();
        foreach (array($this->members->findByLogin($identity), $this->members->findByEmail($identity)) as $member) {
            if ($member !== null && !isset($matches[$member->id])) {
                $matches[$member->id] = $member;
            }
        }
        $valid = array_values(array_filter($matches, static fn (Member $member): bool => verifierMotDePasse($password, $member->passwordHash)));

        if (\count($valid) !== 1) {
            $_SESSION['account_login_value'] = $identity;
            $this->flash('error', \count($valid) > 1
                ? 'Plusieurs comptes correspondent à ces informations. Contactez l’assistance.'
                : 'Identifiant ou mot de passe incorrect.');
            $this->redirect('/connexion');
        }

        $member = $valid[0];
        if (motDePasseDoitEtreRehache($member->passwordHash)) {
            $member = new Member(
                $member->id,
                $member->login,
                hacherMotDePasse($password),
                $member->email,
                $member->lastName,
                $member->firstName,
                $member->isAdministrator,
                $member->profileImage,
                $member->newsletterEnabled,
                $member->registrationIp,
                $member->registeredAt
            );
            $this->members->save($member);
        }

        session_regenerate_id(true);
        $_SESSION['id'] = $member->id;
        $_SESSION['login'] = $member->login;
        $_SESSION['admin'] = $member->isAdministrator ? 1 : 0;
        $this->flash('success', 'Vous êtes connecté.');
        $this->redirect('/accueil');
    }

    public function register(): void
    {
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du formulaire a expiré. Réessayez.');
            $this->redirect('/inscription');
        }

        $login = trim((string) ($_POST['login'] ?? ''));
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
        $newsletterEnabled = isset($_POST['newsletter']);
        $_SESSION['account_register_values'] = array(
            'login' => $login,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'newsletter' => $newsletterEnabled,
        );

        if ($login === '' || $firstName === '' || $lastName === '' || $email === '' || $password === '') {
            $this->flash('error', 'Tous les champs obligatoires doivent être renseignés.');
            $this->redirect('/inscription');
        }
        if (!preg_match('/\A[a-zA-Z0-9_.-]{3,30}\z/', $login)) {
            $this->flash('error', 'Le nom d’utilisateur doit contenir 3 à 30 lettres, chiffres, points, tirets ou soulignés.');
            $this->redirect('/inscription');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Veuillez saisir une adresse e-mail valide.');
            $this->redirect('/inscription');
        }
        if (\strlen($password) < 10) {
            $this->flash('error', 'Le mot de passe doit contenir au moins 10 caractères.');
            $this->redirect('/inscription');
        }
        if (!hash_equals($password, $passwordConfirmation)) {
            $this->flash('error', 'Les deux mots de passe ne correspondent pas.');
            $this->redirect('/inscription');
        }
        if ($this->members->findByLogin($login) !== null || $this->pendingRegistrations->findByLogin($login) !== null) {
            $this->flash('error', 'Ce nom d’utilisateur est déjà utilisé.');
            $this->redirect('/inscription');
        }
        if ($this->members->findByEmail($email) !== null || $this->pendingRegistrations->findByEmail($email) !== null) {
            $this->flash('error', 'Cette adresse e-mail est déjà associée à un compte.');
            $this->redirect('/inscription');
        }

        $token = bin2hex(random_bytes(32));
        $registration = new PendingRegistration(
            null,
            $token,
            $login,
            hacherMotDePasse($password),
            $email,
            $lastName,
            $firstName,
            $newsletterEnabled,
            date('Y-m-d')
        );
        $registration = $this->pendingRegistrations->save($registration);

        $confirmationUrl = $this->absoluteUrl('/confirmation-email?token=' . rawurlencode($token));
        $safeLogin = htmlspecialchars($login, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($confirmationUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="fr"><body style="font-family:Arial,sans-serif;background:#f3f7fb;padding:32px;color:#202630"><main style="max-width:560px;margin:auto;background:#fff;border-radius:16px;padding:32px"><h1>Confirmez votre adresse e-mail</h1><p>Bonjour ' . $safeLogin . ', confirmez la création de votre compte Quiz en tout genre.</p><p><a href="' . $safeUrl . '" style="display:inline-block;background:#3cb2ff;color:#10202d;padding:13px 20px;border-radius:10px;text-decoration:none;font-weight:bold">Confirmer mon compte</a></p><p>Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.</p></main></body></html>';

        try {
            $this->emailSender->send($email, 'Confirmez votre inscription - Quiz en tout genre', $html, 'Confirmez votre compte : ' . $confirmationUrl);
        } catch (\Throwable $exception) {
            error_log('QETG registration email failed: ' . $exception->getMessage());
            if ($registration->id !== null) {
                $this->pendingRegistrations->delete($registration->id);
            }
            $this->flash('error', 'L’e-mail de confirmation n’a pas pu être envoyé. Vous pouvez réessayer.');
            $this->redirect('/inscription');
        }

        $this->flash('success', 'Un lien de confirmation vient d’être envoyé à votre adresse e-mail.');
        unset($_SESSION['account_register_values']);
        $this->redirect('/connexion');
    }

    public function confirmRegistration(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            $this->render('confirmation', 'Lien de confirmation invalide ou expiré.');
            return;
        }

        $registration = $this->pendingRegistrations->findByConfirmationToken($token);
        if ($registration === null) {
            $this->render('confirmation', 'Ce lien a déjà été utilisé ou n’est plus valide.');
            return;
        }

        $createdAt = DateTimeImmutable::createFromFormat('!Y-m-d', $registration->createdOn);
        if ($createdAt === false || $createdAt < new DateTimeImmutable('-1 day')) {
            if ($registration->id !== null) {
                $this->pendingRegistrations->delete($registration->id);
            }
            $this->render('confirmation', 'Ce lien d’inscription a expiré. Veuillez recommencer votre inscription.');
            return;
        }

        if ($this->members->findByLogin($registration->login) !== null || $this->members->findByEmail($registration->email) !== null) {
            $this->pendingRegistrations->delete((int) $registration->id);
            $this->render('confirmation', 'Un compte utilise déjà ces informations. Contactez l’assistance.');
            return;
        }

        $member = $this->members->save(new Member(
            null,
            $registration->login,
            $registration->passwordHash,
            $registration->email,
            $registration->lastName,
            $registration->firstName,
            false,
            'default',
            $registration->newsletterEnabled,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            date('d/m/Y')
        ));
        $this->pendingRegistrations->delete((int) $registration->id);
        session_regenerate_id(true);
        $_SESSION['id'] = $member->id;
        $_SESSION['login'] = $member->login;
        $_SESSION['admin'] = 0;
        $this->render('confirmation', 'Votre adresse est confirmée. Votre compte est prêt.');
    }

    public function logout(): void
    {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
        }
        session_destroy();
        header('Location: ' . $this->basePath . '/accueil', true, 303);
        exit;
    }

    public function forgotPasswordForm(): void
    {
        $this->render('forgot-password');
    }

    public function requestPasswordReset(): void
    {
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du formulaire a expiré. Réessayez.');
            $this->redirect('/mot-de-passe-oublie');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Veuillez saisir une adresse e-mail valide.');
            $this->redirect('/mot-de-passe-oublie');
        }

        // Always show the same response to avoid revealing whether an address has an account.
        $member = $this->members->findByEmail($email);
        if ($member !== null && $member->id !== null) {
            $this->passwordResets->deleteByMemberId($member->id);
            $token = bin2hex(random_bytes(32));
            $this->passwordResets->save(new PasswordReset(
                null,
                $member->id,
                hash('sha256', $token),
                new DateTimeImmutable('+1 hour')
            ));

            $resetUrl = $this->absoluteUrl('/reinitialiser-mot-de-passe?token=' . rawurlencode($token));
            $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeLogin = htmlspecialchars($member->login, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = '<!doctype html><html lang="fr"><body style="font-family:Arial,sans-serif;background:#f3f7fb;padding:32px;color:#202630"><main style="max-width:560px;margin:auto;background:#fff;border-radius:16px;padding:32px"><h1>Réinitialiser votre mot de passe</h1><p>Bonjour ' . $safeLogin . ', utilisez le lien ci-dessous dans l’heure qui suit.</p><p><a href="' . $safeUrl . '" style="display:inline-block;background:#3cb2ff;color:#10202d;padding:13px 20px;border-radius:10px;text-decoration:none;font-weight:bold">Choisir un nouveau mot de passe</a></p><p>Si vous n’avez pas demandé cette réinitialisation, ignorez ce message.</p></main></body></html>';
            try {
                $this->emailSender->send($member->email, 'Réinitialisation de votre mot de passe', $html, 'Réinitialisez votre mot de passe : ' . $resetUrl);
            } catch (\Throwable $exception) {
                error_log('QETG password reset email failed: ' . $exception->getMessage());
                $this->passwordResets->deleteByMemberId($member->id);
            }
        }

        $this->flash('success', 'Si un compte correspond à cette adresse, un lien de réinitialisation a été envoyé.');
        $this->redirect('/mot-de-passe-oublie');
    }

    public function resetPasswordForm(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $reset = $this->findValidPasswordReset($token);
        if ($reset === null) {
            $this->render('reset-password', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return;
        }

        $this->render('reset-password', null, true);
    }

    public function resetPassword(): void
    {
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du formulaire a expiré. Réessayez.');
            $this->redirect('/mot-de-passe-oublie');
        }

        $token = (string) ($_POST['token'] ?? '');
        $reset = $this->findValidPasswordReset($token);
        if ($reset === null) {
            $this->render('reset-password', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return;
        }

        $member = $this->members->findById($reset->memberId);
        $password = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if ($member === null) {
            $this->passwordResets->delete($reset->id ?? 0);
            $this->render('reset-password', 'Le compte associé à ce lien n’existe plus.');
            return;
        }
        if (\strlen($password) < 10 || !hash_equals($password, $confirmation)) {
            $this->flash('error', 'Le nouveau mot de passe doit contenir au moins 10 caractères et les deux saisies doivent correspondre.');
            $this->redirect('/reinitialiser-mot-de-passe?token=' . rawurlencode($token));
        }

        $this->members->save(new Member(
            $member->id,
            $member->login,
            hacherMotDePasse($password),
            $member->email,
            $member->lastName,
            $member->firstName,
            $member->isAdministrator,
            $member->profileImage,
            $member->newsletterEnabled,
            $member->registrationIp,
            $member->registeredAt
        ));
        $this->passwordResets->deleteByMemberId($member->id ?? 0);
        $this->flash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
        $this->redirect('/connexion');
    }

    public function passwordForm(): void
    {
        if (!$this->requireMember()) {
            return;
        }
        $this->render('password');
    }

    public function profileForm(): void
    {
        if (!$this->requireMember()) {
            return;
        }
        $member = $this->members->findById((int) $_SESSION['id']);
        if ($member === null) {
            $this->logout();
        }
        $statistics = $this->statisticsForMember((int) $member->id);
        $playedQuizzes = array();
        $experience = 0;
        foreach ($statistics as $statistic) {
            $quiz = $this->quizzes->findById($statistic->quizId);
            if ($quiz === null) {
                continue;
            }
            $experience += $quiz->experiencePoints;
            $playedQuizzes[] = array(
                'statistic' => $statistic,
                'quiz' => $quiz,
                'image' => $quiz->imageId === null ? null : $this->images->findById($quiz->imageId),
            );
        }
        $earnedAchievements = array();
        foreach ($this->memberAchievements->findByMemberId((int) $member->id) as $memberAchievement) {
            $achievement = $this->achievements->findById($memberAchievement->achievementId);
            if ($achievement !== null) {
                $experience += $achievement->experienceReward;
                $earnedAchievements[] = array('grant' => $memberAchievement, 'achievement' => $achievement);
            }
        }
        $level = intdiv($experience, 15) + 1;
        if ($level >= 10 && !$this->memberAchievements->hasAchievement((int) $member->id, 5)) {
            $levelAchievement = $this->achievements->findById(5);
            if ($levelAchievement !== null) {
                $grant = $this->memberAchievements->save(new MemberAchievement(
                    null,
                    $levelAchievement->id ?? 5,
                    (int) $member->id,
                    date('d/m/Y')
                ));
                $earnedAchievements[] = array('grant' => $grant, 'achievement' => $levelAchievement);
                $experience += $levelAchievement->experienceReward;
                $level = intdiv($experience, 15) + 1;
            }
        }
        $createdQuizCards = array();
        foreach ($this->quizzes->findByCreator((int) $member->id) as $createdQuiz) {
            $likes = 0;
            $dislikes = 0;
            foreach ($this->reactionsForQuiz((int) $createdQuiz->id) as $reaction) {
                if ($reaction->value === 1) {
                    $likes++;
                } else {
                    $dislikes++;
                }
            }
            $createdQuizCards[] = array('quiz' => $createdQuiz, 'likes' => $likes, 'dislikes' => $dislikes);
        }
        $profileData = array(
            'playedQuizzes' => $playedQuizzes,
            'achievements' => $earnedAchievements,
            'createdQuizzes' => $createdQuizCards,
            'experience' => $experience,
            'level' => $level,
            'experienceInLevel' => $experience - ($level - 1) * 15,
            'avatarChoices' => $this->availableAvatars(),
        );
        $profileStats = array(
            'played' => \count($statistics),
            'favorites' => \count(array_filter($statistics, static fn ($statistic): bool => $statistic->isFavorite)),
            'memberQuizzes' => \count(array_filter($statistics, static fn ($statistic): bool => $statistic->isMemberQuiz)),
        );
        $this->render('profile', null, false, $member, $profileStats, $profileData);
    }

    public function updateProfile(): void
    {
        if (!$this->requireMember()) {
            return;
        }
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du formulaire a expiré. Réessayez.');
            $this->redirect('/compte/profil');
        }

        $member = $this->members->findById((int) $_SESSION['id']);
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        if ($member === null) {
            $this->logout();
        }
        if ($firstName === '' || $lastName === '' || \strlen($firstName) > 50 || \strlen($lastName) > 50) {
            $this->flash('error', 'Le prénom et le nom sont obligatoires et ne peuvent pas dépasser 50 caractères.');
            $this->redirect('/compte/profil');
        }

        $updated = new Member(
            $member->id,
            $member->login,
            $member->passwordHash,
            $member->email,
            $lastName,
            $firstName,
            $member->isAdministrator,
            $member->profileImage,
            isset($_POST['newsletter']),
            $member->registrationIp,
            $member->registeredAt
        );
        $this->members->save($updated);
        $this->flash('success', 'Vos informations ont été enregistrées.');
        $this->redirect('/compte/profil');
    }

    public function updateAvatar(): void
    {
        if (!$this->requireMember()) {
            return;
        }
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du formulaire a expiré. Réessayez.');
            $this->redirect('/compte/profil');
        }

        $member = $this->members->findById((int) $_SESSION['id']);
        if ($member === null) {
            $this->logout();
        }

        $preset = basename((string) ($_POST['avatar_preset'] ?? ''));
        $upload = $_FILES['avatar'] ?? null;
        $hasUpload = \is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        if (!$hasUpload && $preset !== '') {
            $allowed = $this->availableAvatars();
            if (!\in_array($preset, $allowed, true)) {
                $this->flash('error', 'Cet avatar n’est pas disponible.');
                $this->redirect('/compte/profil');
            }
            $profileImage = 'assets/images/avatars/presets/' . $preset;
        } else {
            if (!\is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($upload['size'] ?? 0) > 2 * 1024 * 1024) {
                $this->flash('error', 'Choisissez un fichier image valide de 2 Mo maximum.');
                $this->redirect('/compte/profil');
            }
            $temporaryPath = (string) ($upload['tmp_name'] ?? '');
            $imageInfo = @getimagesize($temporaryPath);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
            $extensions = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp');
            if ($imageInfo === false || !isset($extensions[$mime])) {
                $this->flash('error', 'Formats autorisés : JPEG, PNG, GIF ou WebP.');
                $this->redirect('/compte/profil');
            }
            $uploadDirectory = dirname(__DIR__, 4) . '/uploads/avatars';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                $this->flash('error', 'Le stockage des avatars est temporairement indisponible.');
                $this->redirect('/compte/profil');
            }
            $filename = 'member-' . (int) $member->id . '-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
            if (!move_uploaded_file($temporaryPath, $uploadDirectory . '/' . $filename)) {
                $this->flash('error', 'Impossible d’enregistrer cet avatar. Réessayez.');
                $this->redirect('/compte/profil');
            }
            $profileImage = 'uploads/avatars/' . $filename;
        }

        $this->members->save(new Member(
            $member->id,
            $member->login,
            $member->passwordHash,
            $member->email,
            $member->lastName,
            $member->firstName,
            $member->isAdministrator,
            $profileImage,
            $member->newsletterEnabled,
            $member->registrationIp,
            $member->registeredAt
        ));
        $this->flash('success', 'Votre avatar a été mis à jour.');
        $this->redirect('/compte/profil');
    }

    public function deleteAccount(): void
    {
        if (!$this->requireMember()) {
            return;
        }
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du formulaire a expiré. Réessayez.');
            $this->redirect('/compte/profil');
        }

        $member = $this->members->findById((int) $_SESSION['id']);
        $password = (string) ($_POST['current_password'] ?? '');
        if ($member === null || !verifierMotDePasse($password, $member->passwordHash)) {
            $this->flash('error', 'Le mot de passe est incorrect. Le compte n’a pas été supprimé.');
            $this->redirect('/compte/profil');
        }

        try {
            $this->members->delete((int) $member->id);
        } catch (\Throwable $exception) {
            error_log('QETG account deletion failed: ' . $exception->getMessage());
            $this->flash('error', 'Le compte n’a pas pu être supprimé. Il peut être lié à du contenu qui doit être conservé.');
            $this->redirect('/compte/profil');
        }

        $_SESSION = array();
        session_regenerate_id(true);
        $this->render('deleted', 'Votre compte et vos quiz personnels ont été supprimés.');
    }

    public function deleteCreatedQuiz(): void
    {
        if (!$this->requireMember()) {
            return;
        }
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du formulaire a expiré. Réessayez.');
            $this->redirect('/compte/profil');
        }

        $quizId = filter_var($_POST['quiz_id'] ?? null, FILTER_VALIDATE_INT);
        $quiz = $quizId === false || $quizId === null ? null : $this->quizzes->findById((int) $quizId);
        if ($quiz === null || !$quiz->isMemberCreated || $quiz->creatorMemberId !== (int) $_SESSION['id']) {
            $this->flash('error', 'Ce quiz n’existe pas ou ne vous appartient pas.');
            $this->redirect('/compte/profil');
        }

        $this->quizzes->delete((int) $quiz->id);
        $this->flash('success', 'Votre quiz et ses questions ont été supprimés.');
        $this->redirect('/compte/profil');
    }

    public function changePassword(): void
    {
        if (!$this->requireMember()) {
            return;
        }
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du formulaire a expiré. Réessayez.');
            $this->redirect('/compte/mot-de-passe');
        }

        $member = $this->members->findById((int) $_SESSION['id']);
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if ($member === null || !verifierMotDePasse($currentPassword, $member->passwordHash)) {
            $this->flash('error', 'Le mot de passe actuel est incorrect.');
            $this->redirect('/compte/mot-de-passe');
        }
        if (\strlen($newPassword) < 10 || !hash_equals($newPassword, $confirmation)) {
            $this->flash('error', 'Le nouveau mot de passe doit contenir au moins 10 caractères et les deux saisies doivent correspondre.');
            $this->redirect('/compte/mot-de-passe');
        }

        $this->members->save(new Member(
            $member->id,
            $member->login,
            hacherMotDePasse($newPassword),
            $member->email,
            $member->lastName,
            $member->firstName,
            $member->isAdministrator,
            $member->profileImage,
            $member->newsletterEnabled,
            $member->registrationIp,
            $member->registeredAt
        ));
        $this->flash('success', 'Votre mot de passe a été modifié.');
        $this->redirect('/compte/mot-de-passe');
    }

    private function render(string $page, ?string $message = null, bool $resetAllowed = false, ?Member $accountMember = null, array $profileStats = array(), array $profileData = array()): void
    {
        $flash = $_SESSION['account_flash'] ?? null;
        unset($_SESSION['account_flash']);
        $csrfToken = $this->csrfToken();
        $basePath = $this->basePath;
        global $domaine;
        $shareBaseUrl = rtrim((string) $domaine, '/') . $basePath;
        $loginValue = (string) ($_SESSION['account_login_value'] ?? '');
        unset($_SESSION['account_login_value']);
        $registerValues = (array) ($_SESSION['account_register_values'] ?? array());
        if ($page === 'register') {
            unset($_SESSION['account_register_values']);
        }
        $resetToken = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
        require dirname(__DIR__, 2) . '/View/account.php';
    }

    /** @return list<\QETG\Domain\Entity\QuizStatistic> */
    private function statisticsForMember(int $memberId): array
    {
        return $this->statistics->findByMemberId($memberId);
    }

    private function reactionsForQuiz(int $quizId): array
    {
        return $this->reactions->findByQuizId($quizId);
    }

    /** @return list<string> */
    private function availableAvatars(): array
    {
        $files = glob(dirname(__DIR__, 4) . '/assets/images/avatars/presets/*.png') ?: array();
        return array_values(array_map('basename', $files));
    }

    private function findValidPasswordReset(string $token): ?PasswordReset
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return null;
        }
        $reset = $this->passwordResets->findByTokenHash(hash('sha256', $token));
        if ($reset === null) {
            return null;
        }
        if ($reset->expiresAt <= new DateTimeImmutable()) {
            if ($reset->id !== null) {
                $this->passwordResets->delete($reset->id);
            }
            return null;
        }
        return $reset;
    }

    private function requireMember(): bool
    {
        if (!isset($_SESSION['id'])) {
            $this->flash('error', 'Connectez-vous pour accéder à cette page.');
            $this->redirect('/connexion');
            return false;
        }
        return true;
    }

    private function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !\is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function isValidCsrfToken(): bool
    {
        $submitted = (string) ($_POST['_csrf'] ?? '');
        return $submitted !== '' && isset($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $submitted);
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

    private function absoluteUrl(string $path): string
    {
        global $domaine;
        return rtrim((string) $domaine, '/') . $this->basePath . $path;
    }
}
