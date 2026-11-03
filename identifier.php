<?php

declare(strict_types=1);

$scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/identifier.php'));
$basePath = rtrim(dirname($scriptPath), '/');
if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    session_start();
    $_POST['identity'] = trim((string) ($_POST['login'] ?? ''));
    $_POST['password'] = (string) ($_POST['pass'] ?? '');
    $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    $_POST['_csrf'] = $_SESSION['csrf_token'];
    $_SERVER['SCRIPT_NAME'] = $basePath . '/index.php';
    $_SERVER['REQUEST_URI'] = $basePath . '/connexion';
    require __DIR__ . '/index.php';
    exit;
}

header('Location: ' . $basePath . '/connexion', true, 302);
exit;
