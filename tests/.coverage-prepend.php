<?php

declare(strict_types=1);

if (!function_exists('xdebug_start_code_coverage')) {
    return;
}
xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
