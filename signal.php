<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/bootstrap/legacy.php';
$basePath = \QETG\Presentation\View\Shared\ApplicationLayout::basePath();

$targetId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!isset($_SESSION['id'])) {
    header('Location: ' . $basePath . '/connexion', true, 303);
    exit;
}

if ($targetId === false || $targetId === null || $targetId < 1) {
    http_response_code(404);
    echo 'Profil introuvable.';
    exit;
}

// GET is only a compatibility redirect. Actual reporting is now a CSRF-protected POST.
header('Location: ' . $basePath . '/profil-public?id=' . $targetId . '#signaler', true, 303);
exit;