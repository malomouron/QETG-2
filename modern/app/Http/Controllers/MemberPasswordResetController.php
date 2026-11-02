<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AbsoluteActionUrl;
use App\Support\LegacyPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MemberPasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('password-request');
    }

    public function send(Request $request, AbsoluteActionUrl $urls): RedirectResponse
    {
        $input = $request->validate(['login' => ['required', 'string', 'max:255']]);
        $candidates = User::query()->where('legacy_login', $input['login'])->orWhere('email', $input['login'])->get();
        if ($candidates->count() === 1 && filter_var($candidates->first()->email, FILTER_VALIDATE_EMAIL)) {
            $user = $candidates->first();
            $token = Str::random(64);
            $url = $urls->make($request, 'password.reset', ['user' => $user->id, 'token' => $token]);
            try {
                DB::transaction(function () use ($user, $token, $url) {
                    $current = DB::table('users')->where('id', $user->id)->lockForUpdate()->first(['email']);
                    if ($current === null || $current->email !== $user->email) {
                        throw ValidationException::withMessages(['login' => 'Le compte a changé. Réessayez.']);
                    }
                    DB::table('member_password_resets')->updateOrInsert(
                        ['user_id' => $user->id],
                        ['token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour()],
                    );
                    Mail::raw("Pour choisir un nouveau mot de passe, ouvrez ce lien valable une heure :\n\n{$url}\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.", function ($message) use ($user) {
                        $message->to($user->email)->subject('Réinitialisation de votre mot de passe QETG');
                    });
                });
            } catch (TransportExceptionInterface $exception) {
                Log::error('Password reset email delivery failed.', ['exception' => get_class($exception)]);
                throw ValidationException::withMessages(['login' => 'Impossible d’envoyer le message actuellement. Réessayez plus tard.']);
            }
        }

        return back()->with('status', 'Si un compte unique correspond, un message de réinitialisation lui a été envoyé.');
    }

    public function resetForm(int $user, string $token): Response
    {
        return response()->view('password-reset', ['user' => $user, 'token' => $token])
            ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function reset(Request $request, int $user, string $token, LegacyPassword $passwords): RedirectResponse
    {
        $input = $request->validate(['password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed']]);
        try {
            $password = $passwords->hash($input['password']);
        } catch (RuntimeException $exception) {
            report($exception);
            throw ValidationException::withMessages(['password' => 'Ce mot de passe ne peut pas être enregistré avec la configuration actuelle.']);
        }

        DB::transaction(function () use ($user, $token, $password) {
            $account = DB::table('users')->where('id', $user)->lockForUpdate()->first(['id']);
            $reset = DB::table('member_password_resets')->where('user_id', $user)->lockForUpdate()->first();
            if ($account === null || $reset === null || now()->greaterThanOrEqualTo($reset->expires_at) ||
                ! hash_equals($reset->token_hash, hash('sha256', $token))) {
                throw ValidationException::withMessages(['password' => 'Ce lien est invalide ou a expiré. Demandez un nouveau message.']);
            }
            DB::table('users')->where('id', $user)->update(['password' => $password, 'remember_token' => null, 'updated_at' => now()]);
            DB::table('member_password_resets')->where('user_id', $user)->delete();
            DB::table('sessions')->where('user_id', $user)->delete();
        });
        if (Auth::check() && (int) Auth::id() === $user) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('status', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');
    }
}
