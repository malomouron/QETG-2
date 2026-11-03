<?php

declare(strict_types=1);

// Compatibility adapter: route legacy POSTs through the application controller.
$scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/envoyer-mail.php'));
$basePath = rtrim(dirname($scriptPath), '/');
if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}
$_SERVER['SCRIPT_NAME'] = $basePath . '/index.php';
$_SERVER['REQUEST_URI'] = $basePath . '/interactions/commentaire';
require __DIR__ . '/index.php';
