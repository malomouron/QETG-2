<?php

namespace App\Http\Controllers;

use App\Support\LikePattern;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfficialQuizCatalogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate(['recherche' => ['nullable', 'string', 'max:200']]);
        $search = trim($validated['recherche'] ?? '');
        $query = DB::table('official_quizzes')->orderBy('legacy_id');
        if ($search !== '') {
            $query->whereRaw("name LIKE ? ESCAPE '!'", [LikePattern::contains($search)]);
        }

        return view('official-quizzes', [
            'quizzes' => $query->paginate(10)->withQueryString(),
            'search' => $search,
        ]);
    }
}
