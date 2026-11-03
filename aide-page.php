<?php

$basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/aide-page.php'))), '/');
if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}
header('Location: ' . $basePath . '/aide', true, 301);
exit;
