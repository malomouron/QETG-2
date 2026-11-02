<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class LegacyLoginController extends Controller
{
    public function show(): View
    {
        return view('login');
    }

    public function login(Request $request, LegacyPassword $passwords): RedirectResponse
    {
        $input = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $throttleKey = 'legacy-login:'.hash('sha256', Str::lower($input['login']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            throw ValidationException::withMessages(['login' => 'Trop de tentatives. Réessayez dans une minute.']);
        }

        $candidates = User::query()->where('legacy_login', $input['login'])
            ->orWhere('email', $input['login'])->get();
        $matching = $candidates->filter(fn (User $user) => $passwords->verify($input['password'], $user->password));
        if ($matching->count() !== 1) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['login' => 'Compte non reconnu ou mot de passe incorrect.']);
        }

        $user = $matching->first();
        $hash = null;
        if ($passwords->needsRehash($user->password)) {
            try {
                $hash = $passwords->hash($input['password']);
            } catch (RuntimeException $exception) {
                report($exception);
                throw ValidationException::withMessages(['login' => 'Le mot de passe ne peut pas être migré avec la configuration actuelle.']);
            }
        }
        $unchanged = DB::transaction(function () use ($user, $hash) {
            $stored = DB::table('users')->where('id', $user->id)->lockForUpdate()->first(['password']);
            if ($stored === null || ! hash_equals($user->password, $stored->password)) {
                return false;
            }
            if ($hash !== null) {
                DB::table('users')->where('id', $user->id)->update(['password' => $hash, 'updated_at' => now()]);
            }

            return true;
        });
        if (! $unchanged) {
            throw ValidationException::withMessages(['login' => 'Le compte a changé pendant la connexion. Réessayez.']);
        }
        if ($hash !== null) {
            $user->refresh();
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
