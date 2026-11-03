<?php

declare(strict_types=1);

$scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/deconnexion.php'));
$basePath = rtrim(dirname($scriptPath), '/');
if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}
header('Location: ' . $basePath . '/deconnexion', true, 302);
exit;
