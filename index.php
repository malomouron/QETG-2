<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/bootstrap/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use QETG\Infrastructure\Persistence\MySQL\MySqlCommentReplyRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlCommentRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;
use QETG\Infrastructure\Mail\PhpMailerEmailSender;
use QETG\Infrastructure\Security\GoogleRecaptchaVerifier;
use QETG\Infrastructure\Persistence\MySQL\MySqlAdministrationRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlAnswerRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlDifficultyRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlImageRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlMemberRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlMemberSubscriptionRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlPendingRegistrationRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlPasswordResetRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuestionRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizProgressRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlAchievementRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlMemberAchievementRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizReactionRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizStatisticRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlUserReportRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlTransactionManager;
use QETG\Application\UseCase\SubmitFeedback;
use QETG\Application\UseCase\SubmitCommentReply;
use QETG\Application\UseCase\SearchQuizzes;
use QETG\Application\UseCase\SubmitMemberQuiz;
use QETG\Application\UseCase\SaveMemberQuiz;
use QETG\Application\UseCase\UnsubscribeFromNewsletter;
use QETG\Application\UseCase\ReportMember;
use QETG\Application\UseCase\SetMemberSubscription;
use QETG\Application\UseCase\SetQuizFavorite;
use QETG\Application\UseCase\ToggleQuizReaction;
use QETG\Application\Service\NewsletterUnsubscribeToken;
use QETG\Presentation\Http\Controller\HomeController;
use QETG\Presentation\Http\Controller\AccountController;
use QETG\Presentation\Http\Controller\AdministrationController;
use QETG\Presentation\Http\Controller\InformationController;
use QETG\Presentation\Http\Controller\InteractionController;
use QETG\Presentation\Http\Controller\CommentReplyController;
use QETG\Presentation\Http\Controller\OfficialQuizController;
use QETG\Presentation\Http\Controller\SearchController;
use QETG\Presentation\Http\Controller\FavoriteController;
use QETG\Presentation\Http\Controller\MemberQuizController;
use QETG\Presentation\Http\Controller\MemberSubscriptionController;
use QETG\Presentation\Http\Controller\MemberQuizPlayController;
use QETG\Presentation\Http\Controller\MemberQuizEditorController;
use QETG\Presentation\Http\Controller\PublicProfileController;
use QETG\Presentation\Http\Controller\NewsletterController;
use QETG\Presentation\Http\Controller\UserReportController;
use QETG\Presentation\Http\Router;

$database = new MySqlConnection($dbhost, $dbuser, $dbpass, $dbname);
$quizRepository = new MySqlQuizRepository($database);
$imageRepository = new MySqlImageRepository($database);
$quizReactionRepository = new MySqlQuizReactionRepository($database);
$quizStatisticRepository = new MySqlQuizStatisticRepository($database);
$memberRepository = new MySqlMemberRepository($database);
$commentRepository = new MySqlCommentRepository($database);
$commentReplyRepository = new MySqlCommentReplyRepository($database);
$pendingRegistrationRepository = new MySqlPendingRegistrationRepository($database);
$passwordResetRepository = new MySqlPasswordResetRepository($database);
$achievementRepository = new MySqlAchievementRepository($database);
$memberAchievementRepository = new MySqlMemberAchievementRepository($database);
$answerRepository = new MySqlAnswerRepository($database);
$questionRepository = new MySqlQuestionRepository($database);
$difficultyRepository = new MySqlDifficultyRepository($database);
$quizProgressRepository = new MySqlQuizProgressRepository($database);
$memberSubscriptionRepository = new MySqlMemberSubscriptionRepository($database);
$userReportRepository = new MySqlUserReportRepository($database);
$transactionManager = new MySqlTransactionManager($database);
$basePath = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');
$perPage = isset($nombreDeQuizAfficher) ? (int) $nombreDeQuizAfficher : 5;
$emailSender = new PhpMailerEmailSender(
    (string) ($smtp_host ?? ''),
    (string) ($smtp_username ?? ''),
    (string) ($smtp_password ?? ''),
    (string) ($smtp_from ?? $expediteur ?? ''),
    (int) ($smtp_port ?? 465),
    (string) ($smtp_encryption ?? 'ssl'),
    isset($smtp_bcc) ? (string) $smtp_bcc : null
);

$feedbackCaptchaSiteKey = trim((string) ($site_key ?? ''));
$feedbackCaptchaSecretKey = trim((string) ($recaptcha_secret_key ?? ''));
$feedbackCaptchaMisconfigured = ($feedbackCaptchaSiteKey === '') !== ($feedbackCaptchaSecretKey === '');

$home = new HomeController(
    $quizRepository,
    $imageRepository,
    $quizReactionRepository,
    $quizStatisticRepository,
    $memberRepository,
    $commentRepository,
    $commentReplyRepository,
    $perPage,
    $basePath,
    $feedbackCaptchaSiteKey,
    $feedbackCaptchaMisconfigured
);

$accounts = new AccountController(
    $memberRepository,
    $pendingRegistrationRepository,
    $passwordResetRepository,
    $quizStatisticRepository,
    $quizRepository,
    $imageRepository,
    $achievementRepository,
    $memberAchievementRepository,
    $quizReactionRepository,
    $emailSender,
    $basePath
);

$information = new InformationController($basePath);

$newsletterUnsubscribeTokens = new NewsletterUnsubscribeToken((string) ($myprivatekey ?? ''));
$newsletter = new NewsletterController(
    new UnsubscribeFromNewsletter($memberRepository, $newsletterUnsubscribeTokens),
    $basePath
);

$interactions = new InteractionController(
    new SubmitFeedback($commentRepository, $emailSender),
    new ToggleQuizReaction($quizRepository, $quizReactionRepository),
    $basePath,
    !$feedbackCaptchaMisconfigured && $feedbackCaptchaSecretKey !== ''
        ? new GoogleRecaptchaVerifier($feedbackCaptchaSecretKey)
        : null,
    $feedbackCaptchaMisconfigured
);
$commentReplies = new CommentReplyController(
    new SubmitCommentReply(
        $commentRepository,
        $memberRepository,
        $commentReplyRepository
    ),
    $basePath
);

$officialQuizzes = new OfficialQuizController(
    $quizRepository,
    $questionRepository,
    $answerRepository,
    $imageRepository,
    $difficultyRepository,
    $quizProgressRepository,
    $quizStatisticRepository,
    $achievementRepository,
    $memberAchievementRepository,
    $basePath
);

$search = new SearchController(
    new SearchQuizzes($quizRepository),
    $imageRepository,
    $basePath
);

$favorites = new FavoriteController(
    new SetQuizFavorite($quizStatisticRepository),
    $basePath
);

$memberSubscriptions = new MemberSubscriptionController(
    new SetMemberSubscription($memberRepository, $memberSubscriptionRepository),
    $basePath
);
$memberQuizzes = new MemberQuizController(
    $quizRepository,
    $imageRepository,
    $memberRepository,
    $quizReactionRepository,
    $memberSubscriptionRepository,
    $basePath
);
$memberQuizPlay = new MemberQuizPlayController(
    $quizRepository,
    $questionRepository,
    $answerRepository,
    $imageRepository,
    $memberRepository,
    new SubmitMemberQuiz($questionRepository, $answerRepository, $quizStatisticRepository),
    $basePath
);
$memberQuizEditor = new MemberQuizEditorController(
    $quizRepository,
    $questionRepository,
    $answerRepository,
    $difficultyRepository,
    new SaveMemberQuiz(
        $quizRepository,
        $questionRepository,
        $answerRepository,
        $difficultyRepository,
        $transactionManager
    ),
    $basePath
);
$publicProfiles = new PublicProfileController(
    $memberRepository,
    $quizStatisticRepository,
    $quizRepository,
    $imageRepository,
    $achievementRepository,
    $memberAchievementRepository,
    $memberSubscriptionRepository,
    $basePath
);
$userReports = new UserReportController(new ReportMember($memberRepository, $userReportRepository), $basePath);
$administrationRepository = new MySqlAdministrationRepository($database);
$administration = new AdministrationController(
    $administrationRepository,
    $memberRepository,
    $difficultyRepository,
    $basePath,
    array(
        'mailConfigured' => trim((string) ($smtp_host ?? '')) !== ''
            && filter_var((string) ($smtp_from ?? $expediteur ?? ''), FILTER_VALIDATE_EMAIL) !== false,
        'captchaConfigured' => !$feedbackCaptchaMisconfigured && $feedbackCaptchaSecretKey !== '',
        'captchaMisconfigured' => $feedbackCaptchaMisconfigured,
    )
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
$router->post('/deconnexion', array($accounts, 'logout'));
$router->get('/administration', array($administration, 'index'));
$router->post('/administration/quiz/creer', array($administration, 'publishQuiz'));
$router->post('/administration/quiz/supprimer', array($administration, 'deleteQuiz'));
$router->post('/administration/signalements/supprimer-compte', array($administration, 'deleteReportedMember'));
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
$router->post('/compte/favori', array($favorites, 'update'));
$router->get('/quiz-membres', array($memberQuizzes, 'index'));
$router->get('/quiz-membres/jouer', array($memberQuizPlay, 'show'));
$router->post('/quiz-membres/reponse', array($memberQuizPlay, 'submit'));
$router->get('/quiz-membres/editeur', array($memberQuizEditor, 'form'));
$router->post('/quiz-membres/enregistrer', array($memberQuizEditor, 'save'));
$router->post('/communaute/abonnement', array($memberSubscriptions, 'update'));
$router->post('/communaute/signalement', array($userReports, 'submit'));
$router->get('/profil-public', array($publicProfiles, 'show'));
$router->get('/newsletter/desabonnement', array($newsletter, 'confirmUnsubscribe'));
$router->post('/newsletter/desabonnement', array($newsletter, 'unsubscribe'));
$router->get('/a-propos', array($information, 'about'));
$router->get('/aide', array($information, 'help'));
$router->get('/recherche', array($search, 'index'));
$router->get('/quiz-officiel', array($officialQuizzes, 'presentation'));
$router->get('/quiz/question', array($officialQuizzes, 'question'));
$router->post('/quiz/reponse', array($officialQuizzes, 'answer'));
$router->post('/interactions/commentaire', array($interactions, 'comment'));
$router->post('/interactions/reponse-commentaire', array($commentReplies, 'submit'));
$router->post('/interactions/reaction', array($interactions, 'reaction'));
$router->dispatch(
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    (string) ($_SERVER['REQUEST_URI'] ?? '/'),
    $basePath,
    static function (string $path) use ($administrationRepository): void {
        // Admin traffic is excluded; store only aggregate counts, never IPs or user agents.
        if ($path === '/administration') {
            return;
        }
        $today = date('Y-m-d');
        $newSessionToday = ($_SESSION['qetg_traffic_day'] ?? null) !== $today;
        try {
            $administrationRepository->recordPageView($newSessionToday);
            $_SESSION['qetg_traffic_day'] = $today;
        } catch (\Throwable) {
            // Older installations can keep serving pages until the optional traffic migration is applied.
        }
    }
);
