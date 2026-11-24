<?php

declare(strict_types=1);

if (!function_exists('xdebug_get_code_coverage')) {
    return;
}
$directory = getenv('QETG_COVERAGE_DIR') ?: sys_get_temp_dir();
$name = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? 'inline')));
$file = rtrim($directory, '/\\') . '/' . $name . '-' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.ser';
@file_put_contents($file, serialize(xdebug_get_code_coverage()), LOCK_EX);
