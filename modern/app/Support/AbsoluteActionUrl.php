<?php

namespace App\Support;

use Illuminate\Http\Request;
use RuntimeException;

class AbsoluteActionUrl
{
    public function make(Request $request, string $route, array $parameters): string
    {
        $appUrl = (string) config('app.url');
        $parts = parse_url($appUrl);
        if ($parts === false || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) ||
            empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) ||
            isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('APP_URL must be an absolute HTTP(S) URL without credentials, query, or fragment.');
        }
        $path = route($route, $parameters, false);
        $basePath = $request->getBaseUrl();
        if ($basePath !== '' && str_starts_with($path, $basePath.'/')) {
            $path = substr($path, strlen($basePath));
        }

        return rtrim($appUrl, '/').$path;
    }
}
