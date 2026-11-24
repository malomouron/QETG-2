<?php

declare(strict_types=1);

namespace QETG\Presentation\View\Shared;

final class ViewHelpers
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function assetUrl(?string $path, ?string $basePath = null): string
    {
        if ($path === null || $path === '') {
            return '';
        }
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $path) === 1) {
            return $path;
        }

        $basePath ??= ApplicationLayout::basePath();
        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }
}
