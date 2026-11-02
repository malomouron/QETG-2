<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MemberSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.legacy' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('legacy');
        Schema::connection('legacy')->create('quiz_subscription_membre', function (Blueprint $table) {
            $table->integer('idAbonnement')->primary();
            $table->integer('idUser');
            $table->integer('idUserAbonnement');
            $table->string('dateAjout');
        });
    }

    public function test_members_can_follow_unfollow_and_list_subscriptions(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $other = User::factory()->create(['name' => 'Bob']);
        $this->get('/abonnements')->assertRedirect('/connexion');
        $this->post('/membres/'.$other->id.'/abonnement', ['action' => 'follow'])->assertRedirect('/connexion');
        $this->actingAs($user)->get('/membres/'.$other->id)->assertSee("S'abonner");
        $this->post('/membres/'.$other->id.'/abonnement', ['action' => 'follow'])->assertRedirect();
        $this->get('/membres/'.$other->id)->assertSee('Se désabonner');
        $this->get('/abonnements')->assertSee('Bob');
        $this->post('/membres/'.$other->id.'/abonnement', ['action' => 'unfollow'])->assertRedirect();
        $this->get('/abonnements')->assertDontSee('Bob')->assertSee("Vous n'êtes abonné", false);
        $this->assertDatabaseHas('member_subscriptions', ['follower_id' => $user->id, 'followed_id' => $other->id, 'active' => false]);
        $this->from('/membres/'.$user->id)->post('/membres/'.$user->id.'/abonnement', ['action' => 'follow'])
            ->assertSessionHasErrors('action');
        $this->post('/membres/999999/abonnement', ['action' => 'follow'])->assertNotFound();
    }

    public function test_import_requires_members_and_preserves_a_later_unsubscribe(): void
    {
        DB::connection('legacy')->table('quiz_subscription_membre')->insert([
            'idAbonnement' => 1, 'idUser' => 7, 'idUserAbonnement' => 8, 'dateAjout' => '2020-01-01 12:00:00',
        ]);
        $this->artisan('qetg:import-member-subscriptions')->assertFailed();
        $user = User::factory()->create(['legacy_id' => 7]);
        $other = User::factory()->create(['legacy_id' => 8]);
        $this->artisan('qetg:import-member-subscriptions', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('member_subscriptions', 0);
        $this->artisan('qetg:import-member-subscriptions')->assertSuccessful();
        $this->assertDatabaseHas('member_subscriptions', [
            'follower_id' => $user->id, 'followed_id' => $other->id, 'active' => true, 'legacy_date' => '2020-01-01 12:00:00',
        ]);
        $this->actingAs($user)->post('/membres/'.$other->id.'/abonnement', ['action' => 'unfollow'])->assertRedirect();
        $this->artisan('qetg:import-member-subscriptions')->assertSuccessful();
        $this->assertDatabaseHas('member_subscriptions', ['follower_id' => $user->id, 'followed_id' => $other->id, 'active' => false]);
        DB::connection('legacy')->table('quiz_subscription_membre')->insert([
            'idAbonnement' => 2, 'idUser' => 7, 'idUserAbonnement' => 8, 'dateAjout' => '2020-01-02 12:00:00',
        ]);
        $this->artisan('qetg:import-member-subscriptions')->assertFailed();
        $this->assertDatabaseCount('member_subscriptions', 1);
    }
}
