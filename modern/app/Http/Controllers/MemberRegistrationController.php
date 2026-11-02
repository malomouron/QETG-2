<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AbsoluteActionUrl;
use App\Support\LegacyPassword;
use App\Support\MemberIdentity;
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

class MemberRegistrationController extends Controller
{
    public function show(): View
    {
        return view('register');
    }

    public function register(Request $request, LegacyPassword $passwords, MemberIdentity $identities, AbsoluteActionUrl $urls): RedirectResponse
    {
        $input = $request->validate([
            'login' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
            'newsletter' => ['sometimes', 'boolean'],
        ]);
        try {
            $password = $passwords->hash($input['password']);
        } catch (RuntimeException $exception) {
            report($exception);
            throw ValidationException::withMessages(['password' => 'Ce mot de passe ne peut pas être enregistré avec la configuration actuelle.']);
        }
        $token = Str::random(64);
        $url = $urls->make($request, 'register.confirmation', ['token' => $token]);
        try {
            DB::transaction(function () use ($input, $password, $token, $url, $identities) {
                $identities->lock();
                DB::table('pending_member_registrations')->where('expires_at', '<=', now())->delete();
                if ($identities->conflictsWithUsers($input['login'], $input['email']) ||
                    $identities->conflictsWithPending($input['login'], $input['email'])) {
                    throw ValidationException::withMessages(['login' => 'Ce login ou cette adresse e-mail est déjà utilisé ou en attente de confirmation.']);
                }
                DB::table('pending_member_registrations')->insert([
                    'login' => $input['login'], 'email' => $input['email'],
                    'first_name' => $input['first_name'], 'last_name' => $input['last_name'],
                    'password' => $password, 'newsletter' => $input['newsletter'] ?? false,
                    'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay(),
                ]);
                Mail::raw("Pour confirmer ou refuser votre inscription QETG, ouvrez ce lien valable 24 heures :\n\n{$url}\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.", function ($message) use ($input) {
                    $message->to($input['email'])->subject('Confirmation de votre inscription QETG');
                });
            });
        } catch (TransportExceptionInterface $exception) {
            Log::error('Registration email delivery failed.', ['exception' => get_class($exception)]);
            throw ValidationException::withMessages(['email' => 'Impossible d’envoyer le message actuellement. Réessayez plus tard.']);
        }

        return redirect()->route('register')->with('status', 'Consultez votre messagerie pour confirmer ou refuser votre inscription.');
    }

    public function confirmation(string $token): Response
    {
        return response()->view('register-confirmation', ['token' => $token])
            ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, string $token, MemberIdentity $identities): RedirectResponse
    {
        $input = $request->validate(['decision' => ['required', 'in:confirm,decline']]);
        $userId = DB::transaction(function () use ($token, $input, $identities) {
            $identities->lock();
            $pending = DB::table('pending_member_registrations')->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()->first();
            if ($pending === null || now()->greaterThanOrEqualTo($pending->expires_at)) {
                throw ValidationException::withMessages(['decision' => 'Ce lien est invalide ou a expiré. Recommencez votre inscription.']);
            }
            if ($input['decision'] === 'decline') {
                DB::table('pending_member_registrations')->where('id', $pending->id)->delete();

                return null;
            }
            if ($identities->conflictsWithUsers($pending->login, $pending->email) ||
                $identities->conflictsWithPending($pending->login, $pending->email, $pending->id)) {
                throw ValidationException::withMessages(['decision' => 'Ce login ou cette adresse e-mail est déjà utilisé.']);
            }
            $id = DB::table('users')->insertGetId([
                'name' => $pending->login,
                'legacy_login' => $pending->login,
                'email' => $pending->email,
                'legacy_first_name' => $pending->first_name,
                'legacy_last_name' => $pending->last_name,
                'legacy_newsletter' => $pending->newsletter,
                'password' => $pending->password,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('pending_member_registrations')->where('id', $pending->id)->delete();

            return $id;
        });
        if ($userId === null) {
            return redirect()->route('register')->with('status', 'La demande d’inscription a été annulée.');
        }
        Auth::login(User::query()->findOrFail($userId));
        $request->session()->regenerate();

        return redirect()->route('profile');
    }
}
