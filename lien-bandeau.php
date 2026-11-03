<?php

$basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/lien-bandeau.php'))), '/');
if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}
$section = isset($_GET['section']) ? (string) $_GET['section'] : '';
$anchors = array('2' => '#2', '3' => '#3', '4' => '#4', '5' => '#5');
$anchor = $anchors[$section] ?? '';
header('Location: ' . $basePath . '/a-propos' . $anchor, true, 301);
exit;
