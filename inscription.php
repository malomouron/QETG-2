<?php

declare(strict_types=1);

$scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/inscription.php'));
$basePath = rtrim(dirname($scriptPath), '/');
if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    session_start();
    $_POST['first_name'] = (string) ($_POST['prenom'] ?? $_POST['first_name'] ?? '');
    $_POST['last_name'] = (string) ($_POST['nom'] ?? $_POST['last_name'] ?? '');
    $_POST['password'] = (string) ($_POST['pass'] ?? $_POST['password'] ?? '');
    $_POST['password_confirmation'] = (string) ($_POST['pass_confirm'] ?? $_POST['password_confirmation'] ?? '');
    if (isset($_POST['newletter']) && !isset($_POST['newsletter'])) {
        $_POST['newsletter'] = $_POST['newletter'];
    }
    $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    $_POST['_csrf'] = $_SESSION['csrf_token'];
    $_SERVER['SCRIPT_NAME'] = $basePath . '/index.php';
    $_SERVER['REQUEST_URI'] = $basePath . '/inscription';
    require __DIR__ . '/index.php';
    exit;
}

header('Location: ' . $basePath . '/inscription', true, 302);
exit;
