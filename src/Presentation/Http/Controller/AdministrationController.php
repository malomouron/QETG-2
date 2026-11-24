<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use DomainException;
use QETG\Application\Port\AdministrationRepositoryInterface;
use QETG\Domain\Repository\DifficultyRepositoryInterface;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Presentation\Http\QuizQuestionInput;
use Throwable;

final class AdministrationController
{
    public function __construct(
        private AdministrationRepositoryInterface $administration,
        private MemberRepositoryInterface $members,
        private DifficultyRepositoryInterface $difficulties,
        private string $basePath,
        private array $healthConfiguration = array()
    ) {
    }

    public function index(): void
    {
        if ($this->authorize() === null) {
            return;
        }
        $basePath = $this->basePath;
        $quizPage = self::positiveInteger($_GET['quiz_page'] ?? null) ?? 1;
        $reportPage = self::positiveInteger($_GET['report_page'] ?? null) ?? 1;
        $csrfToken = $_SESSION['administration_csrf'] ??= bin2hex(random_bytes(32));
        $flash = $_SESSION['administration_flash'] ?? null;
        $draft = $_SESSION['administration_draft'] ?? array('xp' => '5');
        unset($_SESSION['administration_flash'], $_SESSION['administration_draft']);

        $data = null;
        $difficulties = array();
        try {
            $availableDifficulties = $this->difficulties->findAll();
            $data = $this->administration->dashboard($quizPage, $reportPage);
            $difficulties = $availableDifficulties;
        } catch (Throwable $exception) {
            http_response_code(503);
            error_log('QETG administration dashboard: ' . $exception->getMessage());
        }
        $siteHealth = $this->siteHealth($data);
        require dirname(__DIR__, 2) . '/View/administration.php';
    }

    public function publishQuiz(): void
    {
        $actorId = $this->authorizeWrite();
        if ($actorId === null) {
            return;
        }
        $title = self::text($_POST['title'] ?? null, 50);
        $description = self::text($_POST['description'] ?? null, 5000);
        $rawXp = $_POST['xp'] ?? null;
        $xp = filter_var($rawXp, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => 2147483647)));
        $questions = QuizQuestionInput::normalize($_POST['questions'] ?? null);
        // Keep only bounded, normalized data in the session for the failed-form redirect.
        $_SESSION['administration_draft'] = array(
            'title' => $title ?? '', 'description' => $description ?? '',
            'xp' => is_string($rawXp) && strlen($rawXp) <= 10 ? $rawXp : '',
            'questions' => $questions ?? array(),
        );
        $this->mutate(function () use ($actorId, $title, $description, $xp, $questions): void {
            if ($title === null || $description === null || $xp === false || $questions === null) {
                throw new DomainException('Vérifiez le titre, la description, le gain d’XP et les questions.');
            }
            $this->administration->publishQuiz($actorId, $title, $description, $xp, $questions);
            unset($_SESSION['administration_draft']);
        }, 'Le quiz officiel a été publié.', 'creer-quiz', 'quiz');
    }

    public function deleteQuiz(): void
    {
        $actorId = $this->authorizeWrite();
        if ($actorId !== null) {
            $this->mutate(function () use ($actorId): void {
                $quizId = self::positiveInteger($_POST['quiz_id'] ?? null);
                if ($quizId === null) {
                    throw new DomainException('Identifiant de quiz invalide.');
                }
                $this->administration->deleteQuiz($actorId, $quizId);
            }, 'Le quiz officiel a été supprimé.', 'quiz');
        }
    }

    public function deleteReportedMember(): void
    {
        $actorId = $this->authorizeWrite();
        if ($actorId !== null) {
            $this->mutate(function () use ($actorId): void {
                $memberId = self::positiveInteger($_POST['member_id'] ?? null);
                if ($memberId === null) {
                    throw new DomainException('Identifiant de compte invalide.');
                }
                $this->administration->deleteReportedMember($actorId, $memberId);
            }, 'Le compte signalé a été supprimé.', 'signalements');
        }
    }

    private function authorize(): ?int
    {
        header('Cache-Control: no-store');
        $memberId = self::positiveInteger($_SESSION['id'] ?? null);
        if ($memberId === null) {
            header('Location: ' . $this->basePath . '/connexion', true, 303);
            return null;
        }
        try {
            $member = $this->members->findById($memberId);
        } catch (Throwable $exception) {
            error_log('QETG administration authorization: ' . $exception->getMessage());
            $this->reject(503, 'Vérification des droits momentanément indisponible.');
            return null;
        }
        if ($member === null || !$member->isAdministrator) {
            $_SESSION['admin'] = 0;
            $this->reject(403, 'Cet espace est réservé aux administrateurs.');
            return null;
        }
        $_SESSION['admin'] = 1;
        return $memberId;
    }

    private function authorizeWrite(): ?int
    {
        $actorId = $this->authorize();
        if ($actorId === null) {
            return null;
        }
        $token = $_SESSION['administration_csrf'] ?? null;
        $submitted = $_POST['_csrf'] ?? null;
        if (!is_string($token) || $token === '' || !is_string($submitted) || !hash_equals($token, $submitted)) {
            $this->reject(403, 'Le formulaire a expiré. Rechargez le tableau de bord.');
            return null;
        }
        return $actorId;
    }

    private function mutate(callable $action, string $success, string $section, ?string $successSection = null): void
    {
        try {
            $action();
            $_SESSION['administration_flash'] = array('type' => 'success', 'message' => $success);
            $section = $successSection ?? $section;
        } catch (DomainException $exception) {
            $_SESSION['administration_flash'] = array('type' => 'error', 'message' => $exception->getMessage());
        } catch (Throwable $exception) {
            error_log('QETG administration write: ' . $exception->getMessage());
            $_SESSION['administration_flash'] = array('type' => 'error', 'message' => 'L’action n’a pas pu aboutir. Réessayez plus tard.');
        }
        header('Location: ' . $this->basePath . '/administration#' . $section, true, 303);
    }

    private function reject(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
    }

    /** @return list<array{label: string, state: string, level: string, detail: string}> */
    private function siteHealth(?array $data): array
    {
        $root = dirname(__DIR__, 4);
        $quizUploadsReady = is_dir($root . '/uploads/quizzes') && is_writable($root . '/uploads/quizzes');
        $avatarUploadsReady = is_dir($root . '/uploads/avatars') && is_writable($root . '/uploads/avatars');
        $captchaConfigured = !empty($this->healthConfiguration['captchaConfigured']);
        $captchaMisconfigured = !empty($this->healthConfiguration['captchaMisconfigured']);
        $trafficReady = $data !== null && empty($data['traffic']['migrationMissing']);

        return array(
            array(
                'label' => 'Base de données',
                'state' => $data === null ? 'Indisponible' : 'Opérationnelle',
                'level' => $data === null ? 'error' : 'ok',
                'detail' => $data === null ? 'Lecture des données impossible.' : 'Connexion et lecture des indicateurs réussies.',
            ),
            array(
                'label' => 'Stockage des images',
                'state' => $quizUploadsReady && $avatarUploadsReady ? 'Accessible' : 'À vérifier',
                'level' => $quizUploadsReady && $avatarUploadsReady ? 'ok' : 'warning',
                'detail' => 'Quiz : ' . ($quizUploadsReady ? 'accessible' : 'non accessible') . ' · Avatars : ' . ($avatarUploadsReady ? 'accessible' : 'non accessible') . '.',
            ),
            array(
                'label' => 'Exécution PHP',
                'state' => PHP_VERSION_ID >= 80200 && extension_loaded('mysqli') && extension_loaded('fileinfo') ? 'Prête' : 'À vérifier',
                'level' => PHP_VERSION_ID >= 80200 && extension_loaded('mysqli') && extension_loaded('fileinfo') ? 'ok' : 'warning',
                'detail' => 'PHP ' . PHP_VERSION . ' · mysqli ' . (extension_loaded('mysqli') ? 'actif' : 'absent') . ' · fileinfo ' . (extension_loaded('fileinfo') ? 'actif' : 'absent') . '.',
            ),
            array(
                'label' => 'Envoi des e-mails',
                'state' => !empty($this->healthConfiguration['mailConfigured']) ? 'Configuré' : 'Optionnel',
                'level' => !empty($this->healthConfiguration['mailConfigured']) ? 'ok' : 'warning',
                'detail' => !empty($this->healthConfiguration['mailConfigured']) ? 'Les paramètres SMTP et l’adresse d’envoi sont présents.' : 'SMTP non configuré : les notifications e-mail peuvent être indisponibles.',
            ),
            array(
                'label' => 'Protection reCAPTCHA',
                'state' => $captchaMisconfigured ? 'Configuration incomplète' : ($captchaConfigured ? 'Configurée' : 'Désactivée'),
                'level' => $captchaMisconfigured ? 'warning' : 'ok',
                'detail' => $captchaMisconfigured ? 'Renseignez les deux clés Google ; les commentaires sont suspendus en attendant.' : ($captchaConfigured ? 'Les deux clés sont renseignées ; le service externe n’est pas sondé.' : 'Protection facultative désactivée sur cette installation.'),
            ),
            array(
                'label' => 'Mesure du trafic',
                'state' => $trafficReady ? 'Active' : 'Migration requise',
                'level' => $trafficReady ? 'ok' : 'warning',
                'detail' => $trafficReady ? 'Comptage agrégé, sans adresse IP ni agent utilisateur.' : 'Appliquez la migration de trafic quotidien pour démarrer la collecte.',
            ),
        );
    }

    private static function positiveInteger(mixed $value): ?int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        return $parsed === false ? null : $parsed;
    }

    private static function text(mixed $value, int $maxLength): ?string
    {
        return is_string($value) && strlen($value) <= $maxLength ? $value : null;
    }
}