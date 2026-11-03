<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use QETG\Infrastructure\Persistence\MySQL\MySqlCommentReplyRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlCommentRepository;
use QETG\Infrastructure\Mail\PhpMailerEmailSender;
use QETG\Infrastructure\Security\GoogleRecaptchaVerifier;
use QETG\Infrastructure\Persistence\MySQL\MySqlAnswerRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlDifficultyRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlImageRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlMemberRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlPendingRegistrationRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlPasswordResetRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuestionRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizProgressRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlAchievementRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlMemberAchievementRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizReactionRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizStatisticRepository;
use QETG\Application\UseCase\SubmitFeedback;
use QETG\Application\UseCase\ToggleQuizReaction;
use QETG\Presentation\Http\Controller\HomeController;
use QETG\Presentation\Http\Controller\AccountController;
use QETG\Presentation\Http\Controller\InformationController;
use QETG\Presentation\Http\Controller\InteractionController;
use QETG\Presentation\Http\Controller\OfficialQuizController;
use QETG\Presentation\Http\Router;

$database = new db($dbhost, $dbuser, $dbpass, $dbname);
$basePath = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');
$perPage = isset($nombreDeQuizAfficher) ? (int) $nombreDeQuizAfficher : 5;

$home = new HomeController(
    new MySqlQuizRepository($database),
    new MySqlImageRepository($database),
    new MySqlQuizReactionRepository($database),
    new MySqlQuizStatisticRepository($database),
    new MySqlMemberRepository($database),
    new MySqlCommentRepository($database),
    new MySqlCommentReplyRepository($database),
    $perPage,
    $basePath
);

$accounts = new AccountController(
    new MySqlMemberRepository($database),
    new MySqlPendingRegistrationRepository($database),
    new MySqlPasswordResetRepository($database),
    new MySqlQuizStatisticRepository($database),
    new MySqlQuizRepository($database),
    new MySqlImageRepository($database),
    new MySqlAchievementRepository($database),
    new MySqlMemberAchievementRepository($database),
    new MySqlQuizReactionRepository($database),
    new PhpMailerEmailSender(
        (string) ($smtp_host ?? ''),
        (string) ($smtp_username ?? ''),
        (string) ($smtp_password ?? ''),
        (string) ($smtp_from ?? $expediteur ?? ''),
        (int) ($smtp_port ?? 465),
        (string) ($smtp_encryption ?? 'ssl'),
        isset($smtp_bcc) ? (string) $smtp_bcc : null
    ),
    $basePath
);

$information = new InformationController($basePath);

$emailSender = new PhpMailerEmailSender(
    (string) ($smtp_host ?? ''),
    (string) ($smtp_username ?? ''),
    (string) ($smtp_password ?? ''),
    (string) ($smtp_from ?? $expediteur ?? ''),
    (int) ($smtp_port ?? 465),
    (string) ($smtp_encryption ?? 'ssl'),
    isset($smtp_bcc) ? (string) $smtp_bcc : null
);

$interactions = new InteractionController(
    new SubmitFeedback(new MySqlCommentRepository($database), $emailSender),
    new ToggleQuizReaction(new MySqlQuizRepository($database), new MySqlQuizReactionRepository($database)),
    $basePath,
    (isset($site_key, $myprivatekey) && $site_key !== '' && $myprivatekey !== '')
        ? new GoogleRecaptchaVerifier((string) $myprivatekey)
        : null
);

$officialQuizzes = new OfficialQuizController(
    new MySqlQuizRepository($database),
    new MySqlQuestionRepository($database),
    new MySqlAnswerRepository($database),
    new MySqlImageRepository($database),
    new MySqlDifficultyRepository($database),
    new MySqlQuizProgressRepository($database),
    new MySqlQuizStatisticRepository($database),
    new MySqlAchievementRepository($database),
    new MySqlMemberAchievementRepository($database),
    $basePath
);

$router = new Router();
$router->get('/', array($home, 'index'));
$router->get('/index.php', array($home, 'index'));
$router->get('/accueil', array($home, 'index'));
$router->get('/connexion', array($accounts, 'loginForm'));
$router->post('/connexion', array($accounts, 'login'));
$router->get('/inscription', array($accounts, 'registerForm'));
$router->post('/inscription', array($accounts, 'register'));
$router->get('/confirmation-email', array($accounts, 'confirmRegistration'));
$router->get('/deconnexion', array($accounts, 'logout'));
$router->get('/mot-de-passe-oublie', array($accounts, 'forgotPasswordForm'));
$router->post('/mot-de-passe-oublie', array($accounts, 'requestPasswordReset'));
$router->get('/reinitialiser-mot-de-passe', array($accounts, 'resetPasswordForm'));
$router->post('/reinitialiser-mot-de-passe', array($accounts, 'resetPassword'));
$router->get('/compte/mot-de-passe', array($accounts, 'passwordForm'));
$router->post('/compte/mot-de-passe', array($accounts, 'changePassword'));
$router->get('/compte/profil', array($accounts, 'profileForm'));
$router->post('/compte/profil', array($accounts, 'updateProfile'));
$router->post('/compte/avatar', array($accounts, 'updateAvatar'));
$router->post('/compte/supprimer', array($accounts, 'deleteAccount'));
$router->post('/compte/quiz/supprimer', array($accounts, 'deleteCreatedQuiz'));
$router->get('/a-propos', array($information, 'about'));
$router->get('/aide', array($information, 'help'));
$router->get('/quiz-officiel', array($officialQuizzes, 'presentation'));
$router->get('/quiz/question', array($officialQuizzes, 'question'));
$router->post('/quiz/reponse', array($officialQuizzes, 'answer'));
$router->post('/interactions/commentaire', array($interactions, 'comment'));
$router->post('/interactions/reaction', array($interactions, 'reaction'));
$router->dispatch(
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    (string) ($_SERVER['REQUEST_URI'] ?? '/'),
    $basePath
);
