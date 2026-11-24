<?php

declare(strict_types=1);

use QETG\Presentation\View\Shared\ApplicationLayout;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET', true, 405);
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

header('Location: ' . ApplicationLayout::basePath() . '/administration', true, 303);
exit;