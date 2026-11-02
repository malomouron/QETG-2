<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OfficialProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile', [
            'results' => DB::table('official_quiz_results as result')
                ->join('official_quizzes as quiz', 'result.quiz_id', '=', 'quiz.legacy_id')
                ->where('result.user_id', $request->user()->id)
                ->orderByDesc('result.favorite')->orderBy('quiz.name')->orderBy('quiz.legacy_id')
                ->select('quiz.legacy_id', 'quiz.name', 'result.favorite', 'result.completed_at', 'result.legacy_date')
                ->paginate(10),
        ]);
    }

    public function favorite(Request $request, int $quiz): RedirectResponse
    {
        $validated = $request->validate(['favorite' => ['required', 'boolean']]);
        $result = DB::table('official_quiz_results')->where('user_id', $request->user()->id)
            ->where('quiz_id', $quiz)->first();
        abort_if($result === null, 404);
        DB::table('official_quiz_results')->where('id', $result->id)
            ->update(['favorite' => $validated['favorite']]);

        return back();
    }
}
