<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MemberReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.legacy' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('legacy');
        Schema::connection('legacy')->create('quiz_signalement', function (Blueprint $table) {
            $table->integer('id_signalement')->primary();
            $table->integer('id_user_origine');
            $table->integer('id_user_cible');
        });
    }

    public function test_reporting_is_authenticated_and_only_explicit_administrators_can_moderate(): void
    {
        $reporter = User::factory()->create(['name' => 'Alice', 'legacy_admin' => 1]);
        $reported = User::factory()->create(['name' => 'Bob']);
        $this->post('/membres/'.$reported->id.'/signaler')->assertRedirect('/connexion');
        $this->actingAs($reporter)->get('/membres/'.$reported->id)->assertSee('Signaler ce membre');
        $this->post('/membres/'.$reported->id.'/signaler')->assertRedirect();
        $this->post('/membres/'.$reported->id.'/signaler')->assertRedirect();
        $this->assertDatabaseCount('member_reports', 1);
        $this->from('/membres/'.$reporter->id)->post('/membres/'.$reporter->id.'/signaler')->assertSessionHasErrors('report');
        $this->get('/administration/signalements')->assertForbidden();
        $report = DB::table('member_reports')->first();
        $this->post('/administration/signalements/'.$report->id.'/classer')->assertForbidden();
        $this->artisan('qetg:set-admin', ['user' => (string) $reporter->id, 'state' => 'grant', '--yes' => true])->assertSuccessful();
        $this->actingAs($reporter->fresh())->get('/administration/signalements')->assertOk()->assertSee('Alice')->assertSee('Bob');
        $this->post('/administration/signalements/'.$report->id.'/classer')->assertRedirect();
        $this->get('/administration/signalements')->assertSee('Aucun signalement en attente');
        $this->post('/membres/'.$reported->id.'/signaler')->assertRedirect();
        $this->assertNotNull(DB::table('member_reports')->where('id', $report->id)->value('resolved_at'));
        $this->artisan('qetg:set-admin', ['user' => (string) $reporter->id, 'state' => 'revoke', '--yes' => true])->assertSuccessful();
        $this->actingAs($reporter->fresh())->get('/administration/signalements')->assertForbidden();
    }

    public function test_import_rejects_missing_or_duplicate_members_and_preserves_resolved_reports(): void
    {
        DB::connection('legacy')->table('quiz_signalement')->insert(['id_signalement' => 1, 'id_user_origine' => 7, 'id_user_cible' => 8]);
        $this->artisan('qetg:import-member-reports')->assertFailed();
        $reporter = User::factory()->create(['legacy_id' => 7]);
        $reported = User::factory()->create(['legacy_id' => 8]);
        $this->artisan('qetg:import-member-reports', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('member_reports', 0);
        $this->artisan('qetg:import-member-reports')->assertSuccessful();
        DB::table('member_reports')->where('reporter_id', $reporter->id)->update(['resolved_at' => now()]);
        $this->artisan('qetg:import-member-reports')->assertSuccessful();
        $this->assertNotNull(DB::table('member_reports')->where('reported_id', $reported->id)->value('resolved_at'));
        DB::connection('legacy')->table('quiz_signalement')->insert(['id_signalement' => 2, 'id_user_origine' => 7, 'id_user_cible' => 8]);
        $this->artisan('qetg:import-member-reports')->assertFailed();
        $this->assertDatabaseCount('member_reports', 1);
    }
}
