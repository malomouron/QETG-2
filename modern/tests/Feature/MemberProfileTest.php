<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    private function profile(): array
    {
        return [
            'login' => 'new-login', 'first_name' => 'Alice', 'last_name' => 'Example',
            'email' => 'new@example.com', 'newsletter' => '1', 'current_password' => 'old-password',
        ];
    }

    private function member(): User
    {
        $user = User::factory()->create(['email' => 'old@example.com', 'legacy_login' => 'old-login']);
        DB::table('users')->where('id', $user->id)->update(['password' => md5('old-password')]);

        return $user->fresh();
    }

    public function test_member_can_update_profile_and_clear_old_email_reset_tokens(): void
    {
        $user = $this->member();
        DB::table('member_password_resets')->insert([
            'user_id' => $user->id, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addHour(),
        ]);
        $this->actingAs($user)->get('/profil/modifier')->assertOk()->assertSee('old-login');
        $this->post('/profil/modifier', $this->profile())->assertRedirect('/profil/modifier');
        $this->assertDatabaseHas('users', [
            'id' => $user->id, 'legacy_login' => 'new-login', 'email' => 'new@example.com',
            'legacy_first_name' => 'Alice', 'legacy_last_name' => 'Example', 'legacy_newsletter' => 1,
        ]);
        $this->assertDatabaseMissing('member_password_resets', ['user_id' => $user->id]);
    }

    public function test_profile_requires_correct_password_and_rejects_conflicting_identifiers(): void
    {
        $user = $this->member();
        User::factory()->create(['email' => 'other@example.com', 'legacy_login' => 'other']);
        $this->actingAs($user)->from('/profil/modifier')->post('/profil/modifier', array_replace($this->profile(), ['current_password' => 'wrong']))
            ->assertSessionHasErrors('current_password');
        $this->from('/profil/modifier')->post('/profil/modifier', array_replace($this->profile(), ['login' => 'other@example.com']))
            ->assertSessionHasErrors('login');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'legacy_login' => 'old-login', 'email' => 'old@example.com']);
    }

    public function test_member_can_change_password_and_is_logged_out(): void
    {
        $user = $this->member();
        DB::table('member_password_resets')->insert([
            'user_id' => $user->id, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addHour(),
        ]);
        $this->actingAs($user)->from('/profil/modifier')->post('/profil/mot-de-passe', [
            'current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('current_password');
        $this->post('/profil/mot-de-passe', [
            'current_password' => 'old-password', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertRedirect('/connexion');
        $this->assertGuest();
        $this->assertTrue(app(LegacyPassword::class)->verify('new-password', $user->fresh()->password));
        $this->assertDatabaseMissing('member_password_resets', ['user_id' => $user->id]);
    }

    public function test_existing_duplicate_email_does_not_prevent_other_profile_edits(): void
    {
        $user = $this->member();
        User::factory()->create(['email' => 'old@example.com', 'legacy_login' => 'other']);
        $this->actingAs($user)->post('/profil/modifier', array_replace($this->profile(), ['email' => 'old@example.com']))
            ->assertRedirect('/profil/modifier')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'legacy_login' => 'new-login', 'email' => 'old@example.com']);
    }

    public function test_guests_cannot_change_a_profile(): void
    {
        $this->get('/profil/modifier')->assertRedirect('/connexion');
        $this->post('/profil/modifier', $this->profile())->assertRedirect('/connexion');
    }

    public function test_account_deletion_requires_password_and_confirmation_and_leaves_a_tombstone(): void
    {
        $user = $this->member();
        DB::table('users')->where('id', $user->id)->update(['legacy_id' => 42]);
        $this->actingAs($user)->from('/profil/modifier')->post('/profil/suppression', [
            'current_password' => 'wrong', 'confirmation' => '1',
        ])->assertSessionHasErrors('current_password');
        $this->from('/profil/modifier')->post('/profil/suppression', [
            'current_password' => 'old-password',
        ])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->post('/profil/suppression', [
            'current_password' => 'old-password', 'confirmation' => '1',
        ])->assertRedirect('/connexion')->assertSessionHas('status');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('deleted_legacy_members', ['legacy_id' => 42]);
    }
}
