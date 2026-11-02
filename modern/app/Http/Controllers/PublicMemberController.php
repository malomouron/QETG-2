<?php

namespace App\Http\Controllers;

use App\Support\LikePattern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicMemberController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate(['recherche' => ['nullable', 'string', 'max:200']]);
        $search = trim($validated['recherche'] ?? '');
        $query = DB::table('users')->select('id', 'name', 'legacy_login')->orderBy('name')->orderBy('id');
        if ($search !== '') {
            $query->whereRaw("name LIKE ? ESCAPE '!'", [LikePattern::contains($search)]);
        }

        return view('members', ['members' => $query->paginate(10)->withQueryString(), 'search' => $search]);
    }

    public function show(int $member): View
    {
        $user = DB::table('users')->where('id', $member)->first(['id', 'name', 'legacy_login']);
        abort_if($user === null, 404);
        $results = DB::table('official_quiz_results as result')
            ->join('official_quizzes as quiz', 'result.quiz_id', '=', 'quiz.legacy_id')
            ->where('result.user_id', $member)->orderByDesc('result.favorite')->orderBy('quiz.name')->orderBy('quiz.legacy_id')
            ->select('quiz.legacy_id', 'quiz.name', 'result.favorite', 'result.completed_at', 'result.legacy_date')
            ->paginate(10);

        $isFollowing = Auth::check() && DB::table('member_subscriptions')
            ->where('follower_id', Auth::id())->where('followed_id', $member)->where('active', true)->exists();

        return view('public-member', ['member' => $user, 'results' => $results, 'isFollowing' => $isFollowing]);
    }
}
