<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyMemberLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.legacy' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('legacy');
        Schema::connection('legacy')->create('quiz_membre', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->text('login');
            $table->text('pass_md5');
            $table->string('email');
            $table->string('nom');
            $table->string('prenom');
            $table->integer('admin');
            $table->string('image_profil');
            $table->integer('newletter');
            $table->text('ip');
            $table->string('date');
        });
        DB::connection('legacy')->table('quiz_membre')->insert([
            ['id' => 1, 'login' => 'alice', 'pass_md5' => md5('old-password'), 'email' => 'shared@example.invalid', 'nom' => 'Test', 'prenom' => 'Alice', 'admin' => 1, 'image_profil' => 'default', 'newletter' => 1, 'ip' => '127.0.0.1', 'date' => '2020-01-01'],
            ['id' => 2, 'login' => 'bob', 'pass_md5' => password_hash('modern-password', PASSWORD_BCRYPT), 'email' => 'shared@example.invalid', 'nom' => 'Test', 'prenom' => 'Bob', 'admin' => 0, 'image_profil' => 'default', 'newletter' => 0, 'ip' => '127.0.0.2', 'date' => '2020-01-02'],
        ]);
    }

    public function test_import_is_repeatable_and_never_overwrites_an_existing_account(): void
    {
        $this->artisan('qetg:import-members', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $this->artisan('qetg:import-members')->assertSuccessful();
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', ['legacy_id' => 1, 'legacy_login' => 'alice', 'legacy_admin' => 1, 'legacy_newsletter' => 1]);
        $this->assertSame(2, DB::connection('legacy')->table('quiz_membre')->count());

        DB::table('users')->where('legacy_id', 1)->update(['password' => password_hash('changed-password', PASSWORD_BCRYPT)]);
        $this->artisan('qetg:import-members')->assertSuccessful();
        $hash = DB::table('users')->where('legacy_id', 1)->value('password');
        $this->assertTrue(password_verify('changed-password', $hash));
        $this->assertDatabaseCount('users', 2);
    }

    public function test_invalid_hash_aborts_the_whole_import(): void
    {
        DB::connection('legacy')->table('quiz_membre')->where('id', 2)->update(['pass_md5' => 'unsupported']);
        $this->artisan('qetg:import-members')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_import_rejects_conflicts_with_new_and_pending_accounts(): void
    {
        $local = User::factory()->create(['email' => 'local@example.invalid', 'legacy_login' => 'bob']);
        $this->artisan('qetg:import-members', ['--dry-run' => true])->assertFailed();
        $this->artisan('qetg:import-members')->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $local->delete();

        DB::table('pending_member_registrations')->insert([
            'login' => 'alice', 'email' => 'pending@example.invalid', 'first_name' => 'Test',
            'last_name' => 'Test', 'password' => 'hash', 'newsletter' => false,
            'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addHour(),
        ]);
        $this->artisan('qetg:import-members')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_deleted_members_are_not_reimported(): void
    {
        $this->artisan('qetg:import-members')->assertSuccessful();
        $this->post('/connexion', ['login' => 'alice', 'password' => 'old-password'])->assertRedirect('/');
        $this->post('/profil/suppression', ['current_password' => 'old-password', 'confirmation' => '1'])->assertRedirect('/connexion');
        $this->assertDatabaseHas('deleted_legacy_members', ['legacy_id' => 1]);
        $this->artisan('qetg:import-members')->assertSuccessful();
        $this->assertDatabaseMissing('users', ['legacy_id' => 1]);
        $this->assertDatabaseHas('users', ['legacy_id' => 2]);
    }

    public function test_login_upgrades_legacy_password_and_logout_ends_the_session(): void
    {
        $this->artisan('qetg:import-members')->assertSuccessful();
        $this->from('/connexion')->post('/connexion', ['login' => 'alice', 'password' => 'wrong'])
            ->assertRedirect('/connexion')->assertSessionHasErrors('login');
        $this->assertSame(md5('old-password'), DB::table('users')->where('legacy_id', 1)->value('password'));
        $this->post('/connexion', ['login' => 'alice', 'password' => 'old-password'])->assertRedirect('/');
        $user = User::where('legacy_id', 1)->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(password_verify('old-password', $user->password));
        $this->assertArrayNotHasKey('legacy_ip', $user->toArray());
        $this->post('/deconnexion')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_shared_email_can_authenticate_only_one_matching_account(): void
    {
        $this->artisan('qetg:import-members')->assertSuccessful();
        $this->post('/connexion', ['login' => 'shared@example.invalid', 'password' => 'modern-password'])->assertRedirect('/');
        $this->assertAuthenticatedAs(User::where('legacy_id', 2)->firstOrFail());
        $this->post('/deconnexion');
        DB::table('users')->where('legacy_id', 2)->update(['password' => md5('old-password')]);
        $this->from('/connexion')->post('/connexion', ['login' => 'shared@example.invalid', 'password' => 'old-password'])
            ->assertRedirect('/connexion')->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_repeated_failures_are_rate_limited(): void
    {
        $this->artisan('qetg:import-members')->assertSuccessful();
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post('/connexion', ['login' => 'alice', 'password' => 'incorrect'])->assertSessionHasErrors('login');
        }
        $this->post('/connexion', ['login' => 'alice', 'password' => 'old-password'])
            ->assertSessionHasErrors(['login' => 'Trop de tentatives. Réessayez dans une minute.']);
        $this->assertGuest();
    }

    public function test_bcrypt_does_not_accept_a_longer_password_with_the_same_prefix(): void
    {
        DB::connection('legacy')->table('quiz_membre')->where('id', 1)
            ->update(['pass_md5' => password_hash(str_repeat('b', 72), PASSWORD_BCRYPT)]);
        $this->artisan('qetg:import-members')->assertSuccessful();
        $this->from('/connexion')->post('/connexion', ['login' => 'alice', 'password' => str_repeat('b', 72).'suffix'])
            ->assertRedirect('/connexion')->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->post('/connexion', ['login' => 'alice', 'password' => str_repeat('b', 72)])->assertRedirect('/');
        $this->assertAuthenticatedAs(User::where('legacy_id', 1)->firstOrFail());
    }

    public function test_long_legacy_md5_password_is_migrated_without_truncation(): void
    {
        if (! defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id is required for long passwords.');
        }
        $prefix = str_repeat('a', 72);
        DB::connection('legacy')->table('quiz_membre')->where('id', 1)
            ->update(['pass_md5' => md5($prefix.'first')]);
        $this->artisan('qetg:import-members')->assertSuccessful();
        $this->from('/connexion')->post('/connexion', ['login' => 'alice', 'password' => $prefix.'other'])
            ->assertRedirect('/connexion')->assertSessionHasErrors('login');
        $this->post('/connexion', ['login' => 'alice', 'password' => $prefix.'first'])->assertRedirect('/');
        $user = User::where('legacy_id', 1)->firstOrFail();
        $this->assertTrue(password_verify($prefix.'first', $user->password));
        $this->assertFalse(password_verify($prefix.'other', $user->password));
    }
}
