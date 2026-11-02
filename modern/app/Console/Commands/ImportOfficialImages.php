<?php

namespace App\Console\Commands;

use App\Support\LegacyImageException;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportOfficialImages extends Command
{
    protected $signature = 'qetg:import-official-images {root : Read-only copy of the legacy web root} {--strip-prefix=quiz : Legacy URL prefix to remove} {--dry-run : Validate without writing}';

    protected $description = 'Copy referenced official quiz images from an isolated legacy file snapshot';

    public function handle(): int
    {
        try {
            $root = realpath($this->argument('root'));
            if ($root === false || ! is_dir($root) || ! is_readable($root)) {
                throw new LegacyImageException('The image snapshot directory is not readable.');
            }
            $prefix = trim((string) $this->option('strip-prefix'), '/');
            if ($prefix !== '' && ! preg_match('/\A[a-zA-Z0-9_-]+\z/', $prefix)) {
                throw new LegacyImageException('The URL prefix must be a single path segment.');
            }

            $references = $this->references();
            $plan = [];
            foreach ($references as $reference) {
                $source = $reference->image_path;
                $item = ['reference' => $reference, 'key' => null, 'mime' => null, 'path' => null];
                if ($source !== null && $source !== '') {
                    $path = $this->resolvePath($root, $source, $prefix);
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
                    if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                        throw new LegacyImageException("Unsupported image type for {$reference->kind} {$reference->id}.");
                    }
                    $digest = hash_file('sha256', $path);
                    if ($digest === false) {
                        throw new LegacyImageException("Cannot hash image for {$reference->kind} {$reference->id}.");
                    }
                    $item['path'] = $path;
                    $item['key'] = 'legacy-images/'.$digest;
                    $item['mime'] = $mime;
                }
                $plan[] = $item;
            }

            if (! $this->option('dry-run')) {
                foreach ($plan as $item) {
                    if ($item['key'] !== null) {
                        $this->copyImage($item['path'], $item['key'], $item['mime']);
                    }
                }
                DB::transaction(function () use ($references, $plan) {
                    $quizIds = $references->pluck('quiz_id')->unique()->sort()->values();
                    DB::table('official_quizzes')->whereIn('legacy_id', $quizIds)->orderBy('legacy_id')->lockForUpdate()->get();
                    if ($references->toJson() !== $this->references()->toJson()) {
                        throw new LegacyImageException('The quiz data changed during import; retry the image import.');
                    }
                    $mediaByQuiz = [];
                    foreach ($plan as $item) {
                        $ref = $item['reference'];
                        DB::table($ref->table_name)->where('legacy_id', $ref->id)->update([
                            'image_storage_key' => $item['key'],
                            'image_storage_source' => $item['key'] === null ? null : $ref->image_path,
                            'image_mime' => $item['mime'],
                        ]);
                        $mediaByQuiz[$ref->quiz_id][] = [$ref->kind, $ref->id, $ref->image_path, $item['key'], $item['mime']];
                    }
                    foreach ($mediaByQuiz as $quizId => $media) {
                        DB::table('official_quizzes')->where('legacy_id', $quizId)
                            ->update(['media_hash' => hash('sha256', json_encode($media, JSON_THROW_ON_ERROR))]);
                    }
                });
            }
        } catch (LegacyImageException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $count = count(array_filter($plan, fn ($item) => $item['key'] !== null));
        $this->info(($this->option('dry-run') ? 'Images validated: ' : 'Images imported: ').$count.'.');

        return self::SUCCESS;
    }

    private function references(): Collection
    {
        $quizzes = DB::table('official_quizzes')->orderBy('legacy_id')->get(['legacy_id as id', 'legacy_id as quiz_id', 'image_path'])
            ->map(fn ($row) => (object) ['kind' => 'quiz', 'table_name' => 'official_quizzes', ...((array) $row)]);
        $questions = DB::table('official_questions')->orderBy('legacy_id')->get(['legacy_id as id', 'quiz_id', 'image_path'])
            ->map(fn ($row) => (object) ['kind' => 'question', 'table_name' => 'official_questions', ...((array) $row)]);
        $answers = DB::table('official_answers as a')->join('official_questions as q', 'a.question_id', '=', 'q.legacy_id')
            ->orderBy('a.legacy_id')->get(['a.legacy_id as id', 'q.quiz_id', 'a.image_path'])
            ->map(fn ($row) => (object) ['kind' => 'answer', 'table_name' => 'official_answers', ...((array) $row)]);

        return $quizzes->concat($questions)->concat($answers)->values();
    }

    private function resolvePath(string $root, string $source, string $prefix): string
    {
        $parts = parse_url(str_replace('\\', '/', $source));
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || ! isset($parts['path'])) {
            throw new LegacyImageException('Only local image references are supported: '.$source);
        }
        $path = str_replace('\\', '/', rawurldecode($parts['path']));
        $segments = explode('/', trim($path, '/'));
        if ($prefix !== '' && ($segments[0] ?? null) === $prefix) {
            array_shift($segments);
        }
        if (in_array('..', $segments, true) || str_contains($path, "\0")) {
            throw new LegacyImageException('Unsafe image reference: '.$source);
        }
        $candidate = realpath($root.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments));
        $boundary = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if ($candidate === false || ! str_starts_with($candidate, $boundary) || ! is_file($candidate) || ! is_readable($candidate)) {
            throw new LegacyImageException('Image missing or outside the snapshot: '.$source);
        }

        return $candidate;
    }

    private function copyImage(string $source, string $key, string $mime): void
    {
        $disk = Storage::disk('local');
        $digest = substr($key, strlen('legacy-images/'));
        if ($disk->exists($key)) {
            if (hash_file('sha256', $disk->path($key)) !== $digest ||
                (new \finfo(FILEINFO_MIME_TYPE))->file($disk->path($key)) !== $mime) {
                throw new LegacyImageException('An existing stored image has an invalid checksum: '.$key);
            }

            return;
        }

        $temporary = 'legacy-images/staging/'.bin2hex(random_bytes(16));
        $stream = fopen($source, 'rb');
        if ($stream === false) {
            throw new LegacyImageException('Cannot open image in the snapshot.');
        }
        try {
            if (! $disk->put($temporary, $stream) || hash_file('sha256', $disk->path($temporary)) !== $digest ||
                (new \finfo(FILEINFO_MIME_TYPE))->file($disk->path($temporary)) !== $mime) {
                throw new LegacyImageException('Image changed or could not be copied; retry the import.');
            }
            if (! $disk->move($temporary, $key)) {
                throw new LegacyImageException('Could not store imported image.');
            }
        } finally {
            fclose($stream);
            if ($disk->exists($temporary) && ! $disk->delete($temporary)) {
                throw new LegacyImageException('Could not remove a temporary image file.');
            }
        }
    }
}
