<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        return view('subscriptions', [
            'members' => DB::table('member_subscriptions as subscription')
                ->join('users as member', 'subscription.followed_id', '=', 'member.id')
                ->where('subscription.follower_id', $request->user()->id)->where('subscription.active', true)
                ->orderBy('member.name')->orderBy('member.id')
                ->select('member.id', 'member.name', 'member.legacy_login')->paginate(10),
        ]);
    }

    public function update(Request $request, int $member): RedirectResponse
    {
        $input = $request->validate(['action' => ['required', 'in:follow,unfollow']]);
        abort_unless(DB::table('users')->where('id', $member)->exists(), 404);
        if ((int) $request->user()->id === $member && $input['action'] === 'follow') {
            throw ValidationException::withMessages(['action' => 'Vous ne pouvez pas vous abonner à vous-même.']);
        }
        DB::table('member_subscriptions')->upsert([[
            'follower_id' => $request->user()->id,
            'followed_id' => $member,
            'active' => $input['action'] === 'follow',
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['follower_id', 'followed_id'], ['active', 'updated_at']);

        return back()->with('status', $input['action'] === 'follow' ? 'Abonnement enregistré.' : 'Abonnement retiré.');
    }
}
