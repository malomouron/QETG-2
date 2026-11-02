<?php

namespace App\Http\Controllers;

use App\Support\LegacyPassword;
use App\Support\MemberIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class MemberProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile-edit', ['member' => Auth::user()]);
    }

    public function update(Request $request, LegacyPassword $passwords, MemberIdentity $identities): RedirectResponse
    {
        $input = $request->validate([
            'login' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'newsletter' => ['required', 'boolean'],
            'current_password' => ['required', 'string'],
        ]);
        DB::transaction(function () use ($request, $input, $passwords, $identities) {
            $identities->lock();
            $user = DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            if ($user === null || ! $passwords->verify($input['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Mot de passe incorrect.']);
            }
            foreach (['login' => $user->legacy_login, 'email' => $user->email] as $field => $previous) {
                if ($input[$field] !== $previous &&
                    ($identities->conflictsWithUsers($input[$field], $input[$field], false, $user->id) ||
                        $identities->conflictsWithPending($input[$field], $input[$field]))) {
                    throw ValidationException::withMessages([$field => 'Ce login ou cette adresse e-mail est déjà utilisé ou en attente de confirmation.']);
                }
            }
            $values = [
                'name' => $input['login'], 'legacy_login' => $input['login'],
                'legacy_first_name' => $input['first_name'], 'legacy_last_name' => $input['last_name'],
                'email' => $input['email'], 'legacy_newsletter' => $input['newsletter'], 'updated_at' => now(),
            ];
            if ($input['email'] !== $user->email) {
                $values['email_verified_at'] = null;
                DB::table('member_password_resets')->where('user_id', $user->id)->delete();
            }
            DB::table('users')->where('id', $user->id)->update($values);
        });

        return redirect()->route('profile.edit')->with('status', 'Vos informations ont été enregistrées.');
    }

    public function changePassword(Request $request, LegacyPassword $passwords): RedirectResponse
    {
        $input = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
        ]);
        try {
            $password = $passwords->hash($input['password']);
        } catch (RuntimeException $exception) {
            report($exception);
            throw ValidationException::withMessages(['password' => 'Ce mot de passe ne peut pas être enregistré avec la configuration actuelle.']);
        }
        DB::transaction(function () use ($request, $input, $password, $passwords) {
            $user = DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first(['id', 'password']);
            if ($user === null || ! $passwords->verify($input['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Mot de passe incorrect.']);
            }
            DB::table('users')->where('id', $user->id)->update(['password' => $password, 'remember_token' => null, 'updated_at' => now()]);
            DB::table('member_password_resets')->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');
    }

    public function destroy(Request $request, LegacyPassword $passwords, MemberIdentity $identities): RedirectResponse
    {
        $input = $request->validate([
            'current_password' => ['required', 'string'],
            'confirmation' => ['accepted'],
        ]);
        DB::transaction(function () use ($request, $input, $passwords, $identities) {
            $identities->lock();
            $user = DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first(['id', 'legacy_id', 'password']);
            if ($user === null || ! $passwords->verify($input['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Mot de passe incorrect.']);
            }
            if ($user->legacy_id !== null) {
                DB::table('deleted_legacy_members')->updateOrInsert(
                    ['legacy_id' => $user->legacy_id],
                    ['deleted_at' => now()],
                );
            }
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('users')->where('id', $user->id)->delete();
        });
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Votre compte sur cette version a été supprimé.');
    }
}
