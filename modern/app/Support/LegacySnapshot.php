<?php

namespace App\Support;

class LegacySnapshot
{
    public function configurationError(): ?string
    {
        $source = config('database.connections.legacy');
        if (! is_array($source) || empty($source['database']) ||
            (($source['driver'] ?? null) === 'mysql' && (empty($source['host']) || empty($source['username'])))) {
            return 'Configure LEGACY_DB_HOST, LEGACY_DB_DATABASE and LEGACY_DB_USERNAME for a read-only snapshot.';
        }

        $destination = config('database.connections.'.config('database.default'));
        if (($source['driver'] ?? null) === 'mysql' && is_array($destination) &&
            ($destination['driver'] ?? null) === 'mysql' &&
            $source['host'] === ($destination['host'] ?? null) &&
            (string) ($source['port'] ?? '3306') === (string) ($destination['port'] ?? '3306') &&
            $source['database'] === ($destination['database'] ?? null)) {
            return 'The legacy snapshot and the destination must be separate databases.';
        }

        return null;
    }

    public static function text(?string $value): ?string
    {
        return $value === null ? null : html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
