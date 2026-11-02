<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class MemberPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_unique_member_can_reset_a_password_once_and_revoke_database_sessions(): void
    {
        config(['app.url' => 'https://qetg.example']);
        $user = User::factory()->create(['email' => 'member@example.com', 'legacy_login' => 'member']);
        DB::table('users')->where('id', $user->id)->update(['password' => md5('old-password')]);
        DB::table('sessions')->insert([
            'id' => 'existing-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
        ]);
        $this->withServerVariables(['HTTP_HOST' => 'attacker.example'])->post('/mot-de-passe-oublie', ['login' => 'member'])
            ->assertRedirect()->assertSessionHas('status');
        $sent = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage()->getTextBody();
        $this->assertMatchesRegularExpression('~https://qetg\.example/mot-de-passe/'.$user->id.'/[A-Za-z0-9]{64}~', $sent);
        $this->assertStringNotContainsString('attacker.example', $sent);
        preg_match('~https://qetg\.example(/mot-de-passe/'.$user->id.'/([A-Za-z0-9]{64}))~', $sent, $matches);
        $path = $matches[1];
        $this->assertDatabaseHas('member_password_resets', ['user_id' => $user->id, 'token_hash' => hash('sha256', $matches[2])]);
        $this->get($path)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private');
        $this->post($path, ['password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertRedirect('/connexion')->assertSessionHas('status');
        $this->assertTrue(app(LegacyPassword::class)->verify('new-password', $user->fresh()->password));
        $this->assertDatabaseMissing('member_password_resets', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'existing-session']);
        $this->get('/connexion')->assertSee('Votre mot de passe a été modifié');
        $this->post('/connexion', ['login' => 'member', 'password' => 'new-password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->from($path)->post($path, ['password' => 'another-password', 'password_confirmation' => 'another-password'])
            ->assertSessionHasErrors('password');
        $this->assertTrue(app(LegacyPassword::class)->verify('new-password', $user->fresh()->password));
    }

    public function test_unknown_and_ambiguous_identifiers_get_the_same_response_without_mail(): void
    {
        User::factory()->create(['email' => 'same@example.com', 'legacy_login' => 'first']);
        User::factory()->create(['email' => 'same@example.com', 'legacy_login' => 'second']);
        foreach (['missing@example.com', 'same@example.com'] as $identifier) {
            $this->post('/mot-de-passe-oublie', ['login' => $identifier])
                ->assertRedirect()->assertSessionHas('status', 'Si un compte unique correspond, un message de réinitialisation lui a été envoyé.');
        }
        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
        $this->assertDatabaseCount('member_password_resets', 0);
    }

    public function test_expired_or_invalid_token_does_not_change_password(): void
    {
        $user = User::factory()->create();
        $before = $user->password;
        $token = str_repeat('a', 64);
        DB::table('member_password_resets')->insert([
            'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->subSecond(),
        ]);
        foreach ([$token, str_repeat('b', 64)] as $attempt) {
            $path = '/mot-de-passe/'.$user->id.'/'.$attempt;
            $this->from($path)->post($path, ['password' => 'new-password', 'password_confirmation' => 'new-password'])
                ->assertSessionHasErrors('password');
        }
        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_mail_failure_preserves_the_previous_reset_token(): void
    {
        $user = User::factory()->create(['legacy_login' => 'member']);
        DB::table('member_password_resets')->insert([
            'user_id' => $user->id, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addHour(),
        ]);
        Mail::shouldReceive('raw')->once()->andThrow(new TransportException('Delivery failed'));
        $this->from('/mot-de-passe-oublie')->post('/mot-de-passe-oublie', ['login' => 'member'])
            ->assertSessionHasErrors('login');
        $this->assertDatabaseHas('member_password_resets', ['user_id' => $user->id, 'token_hash' => str_repeat('a', 64)]);
    }

    public function test_reset_logs_out_the_member_using_the_current_session(): void
    {
        $user = User::factory()->create();
        $token = str_repeat('c', 64);
        DB::table('member_password_resets')->insert([
            'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour(),
        ]);
        $this->actingAs($user)->post('/mot-de-passe/'.$user->id.'/'.$token, [
            'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertRedirect('/connexion')->assertSessionHas('status');
        $this->assertGuest();
    }
}
