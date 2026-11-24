<?php

declare(strict_types=1);

namespace QETG\Presentation\View\Shared;

final class ApplicationLayout
{
    public static function renderDocumentStart(
        string $title,
        array $stylesheets,
        string $bodyClass,
        ?string $description = null,
        string $charset = 'UTF-8'
    ): void {
        $escape = array(ViewHelpers::class, 'escape');

        echo "<!doctype html>\n<html lang=\"fr\">\n<head>\n";
        self::renderHead($title, $stylesheets, $charset);
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . PHP_EOL;
        if ($description !== null && $description !== '') {
            echo '<meta name="description" content="' . $escape($description) . '">' . PHP_EOL;
        }
        $siteScriptPath = self::basePath() . '/assets/js/site.js';
        echo '<script src="' . $escape($siteScriptPath) . '" defer></script>' . PHP_EOL;
        echo '</head>' . PHP_EOL . '<body class="' . $escape($bodyClass) . '">' . PHP_EOL;
    }

    public static function renderDocumentEnd(): void
    {
        echo "</body>\n</html>\n";
    }

    public static function renderScript(string $source): void
    {
        $escape = array(ViewHelpers::class, 'escape');
        echo '<script src="' . $escape($source) . '" defer></script>' . PHP_EOL;
    }

    /** @param list<string> $stylesheets */
    public static function renderHead(string $title, array $stylesheets, string $charset = 'UTF-8'): void
    {
        $escape = array(ViewHelpers::class, 'escape');
        $basePath = self::basePath();
        $faviconPath = $basePath . '/assets/images/community/icon.png';
        $cookieScriptPath = $basePath . '/node_modules/tarteaucitronjs/tarteaucitron.js';
        $cookieConfigScriptPath = $basePath . '/assets/js/cookie-consent.js';

        echo '<title>' . $escape($title) . '</title>' . PHP_EOL;
        echo '<meta charset="' . $escape($charset) . '">' . PHP_EOL;
        echo '<link rel="icon" type="image/png" href="' . $escape($faviconPath) . '">' . PHP_EOL;
        foreach ($stylesheets as $stylesheet) {
            echo '<link rel="stylesheet" href="' . $escape($stylesheet) . '">' . PHP_EOL;
        }
            echo '<script src="' . $escape($cookieScriptPath) . '" defer></script>' . PHP_EOL;
            echo '<script src="' . $escape($cookieConfigScriptPath) . '" data-icon-src="' . $escape($faviconPath) . '" defer></script>' . PHP_EOL;
    }

    public static function basePath(): string
    {
        $applicationRoot = realpath(dirname(__DIR__, 4));
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($applicationRoot === false || $documentRoot === false) {
            return '';
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $normalizedApplication = str_replace('\\', '/', $applicationRoot);
        if (strtolower($normalizedApplication) === strtolower($normalizedRoot)) {
            return '';
        }
        if (stripos($normalizedApplication, $normalizedRoot . '/') === 0) {
            return rtrim(substr($normalizedApplication, strlen($normalizedRoot)), '/');
        }
        return '';
    }
}
