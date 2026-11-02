<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class MemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(): array
    {
        return [
            'login' => 'new-member', 'first_name' => 'Ada', 'last_name' => 'Example',
            'email' => 'new@example.com', 'password' => 'new-password',
            'password_confirmation' => 'new-password', 'newsletter' => '1',
        ];
    }

    private function registerAndGetPath(): string
    {
        $this->post('/inscription', $this->registration())->assertRedirect('/inscription')->assertSessionHas('status');
        $sent = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage()->getTextBody();
        $this->assertMatchesRegularExpression('~https://qetg\.example/inscription/confirmation/[A-Za-z0-9]{64}~', $sent);
        preg_match('~https://qetg\.example(/inscription/confirmation/[A-Za-z0-9]{64})~', $sent, $match);

        return $match[1];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://qetg.example']);
    }

    public function test_registration_requires_confirmation_then_logs_in_the_member(): void
    {
        $path = $this->registerAndGetPath();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('pending_member_registrations', 1);
        $pending = DB::table('pending_member_registrations')->first();
        $this->assertNotSame('new-password', $pending->password);
        $this->get($path)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertDatabaseCount('users', 0);
        $this->post($path, ['decision' => 'confirm'])->assertRedirect('/profil');
        $this->assertAuthenticated();
        $user = User::where('legacy_login', 'new-member')->firstOrFail();
        $this->assertSame('Ada', $user->legacy_first_name);
        $this->assertSame(1, (int) $user->legacy_newsletter);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(app(LegacyPassword::class)->verify('new-password', $user->password));
        $this->assertDatabaseCount('pending_member_registrations', 0);
        $this->post('/deconnexion');
        $this->from($path)->post($path, ['decision' => 'confirm'])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_declining_or_expiring_an_invitation_does_not_create_an_account(): void
    {
        $path = $this->registerAndGetPath();
        $this->post($path, ['decision' => 'decline'])->assertRedirect('/inscription');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('pending_member_registrations', 0);
        $path = $this->registerAndGetPath();
        DB::table('pending_member_registrations')->update(['expires_at' => now()->subSecond()]);
        $this->from($path)->post($path, ['decision' => 'confirm'])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_or_pending_identifiers_are_not_reused(): void
    {
        User::factory()->create(['email' => 'registered@example.com', 'legacy_login' => 'registered']);
        foreach ([
            ['login' => 'registered'],
            ['email' => 'registered@example.com'],
            ['login' => 'registered@example.com'],
        ] as $changes) {
            $this->from('/inscription')->post('/inscription', array_replace($this->registration(), $changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('pending_member_registrations', 0);
        $this->registerAndGetPath();
        $this->from('/inscription')->post('/inscription', $this->registration())->assertSessionHasErrors('login');
        $this->assertDatabaseCount('pending_member_registrations', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_mail_failure_does_not_reserve_or_create_an_account(): void
    {
        Mail::shouldReceive('raw')->once()->andThrow(new TransportException('Delivery failed'));
        $this->from('/inscription')->post('/inscription', $this->registration())->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('pending_member_registrations', 0);
    }
}
