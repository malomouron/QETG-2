<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialImageController extends Controller
{
    public function __invoke(string $kind, int $id): StreamedResponse
    {
        $table = match ($kind) {
            'quiz' => 'official_quizzes',
            'question' => 'official_questions',
            'answer' => 'official_answers',
            default => abort(404),
        };
        $image = DB::table($table)->where('legacy_id', $id)->first();
        abort_if($image === null || $image->image_path === null || $image->image_path !== $image->image_storage_source, 404);
        abort_if(! is_string($image->image_storage_key) || ! preg_match('/\Alegacy-images\/[a-f0-9]{64}\z/', $image->image_storage_key), 404);
        abort_if(! in_array($image->image_mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($image->image_storage_key), 404);

        return $disk->response($image->image_storage_key, null, [
            'Content-Type' => $image->image_mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
