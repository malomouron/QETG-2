<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use QETG\Application\UseCase\ToggleQuizReaction;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizReactionRepository;
use QETG\Infrastructure\Persistence\MySQL\MySqlQuizRepository;

$quizId = filter_var($_GET['q'] ?? null, FILTER_VALIDATE_INT);
$value = filter_var($_GET['l'] ?? null, FILTER_VALIDATE_INT);
if (!isset($_SESSION['auth']) || (int) $_SESSION['auth'] !== 1 || $quizId === false || $quizId === null || !in_array($value, array(0, 1), true)) {
    header('Location: quiz_utilisateur.php', true, 303);
    exit;
}

try {
    $database = new db($dbhost, $dbuser, $dbpass, $dbname);
    $quizRepository = new MySqlQuizRepository($database);
    $quiz = $quizRepository->findById((int) $quizId);
    if ($quiz !== null && $quiz->isMemberCreated) {
        $useCase = new ToggleQuizReaction($quizRepository, new MySqlQuizReactionRepository($database));
        $useCase->execute((int) $quizId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), (int) $value);
    }
} catch (Throwable $exception) {
    error_log('QETG legacy member-quiz reaction failed: ' . $exception->getMessage());
}

$_SESSION['auth'] = 0;
header('Location: quiz_utilisateur.php', true, 303);
exit;
