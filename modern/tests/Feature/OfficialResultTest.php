<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OfficialResultTest extends TestCase
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
        Schema::connection('legacy')->create('quiz', function (Blueprint $table) {
            $table->integer('id_quiz')->primary();
            $table->integer('id_quiz_user');
        });
        Schema::connection('legacy')->create('quiz_statistique', function (Blueprint $table) {
            $table->integer('id_user');
            $table->integer('id_quiz');
            $table->text('date');
            $table->integer('note');
            $table->integer('favoris');
            $table->integer('quiz_utilisateur');
        });
        DB::connection('legacy')->table('quiz')->insert([
            ['id_quiz' => 1, 'id_quiz_user' => 0],
            ['id_quiz' => 2, 'id_quiz_user' => 1],
        ]);
        DB::connection('legacy')->table('quiz_statistique')->insert([
            ['id_user' => 7, 'id_quiz' => 1, 'date' => 'Lundi-02-Novembre-2026', 'note' => 2, 'favoris' => 1, 'quiz_utilisateur' => 0],
            ['id_user' => 7, 'id_quiz' => 2, 'date' => 'Lundi-02-Novembre-2026', 'note' => 5, 'favoris' => 0, 'quiz_utilisateur' => 1],
        ]);
        DB::table('official_quizzes')->insert([
            'legacy_id' => 1, 'name' => 'Quiz officiel', 'description' => 'Bienvenue',
            'image_path' => 'quiz.png', 'image_alt' => 'Image',
        ]);
    }

    public function test_results_require_imported_parents_and_do_not_overwrite_newer_favorites(): void
    {
        $this->artisan('qetg:import-official-results')->assertFailed();
        $this->assertDatabaseCount('official_quiz_results', 0);
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['legacy_id' => 7]);
        $this->artisan('qetg:import-official-results', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('official_quiz_results', 0);
        $this->artisan('qetg:import-official-results')->assertSuccessful();
        $this->assertDatabaseCount('official_quiz_results', 1);
        $this->assertDatabaseHas('official_quiz_results', [
            'user_id' => $user->id, 'quiz_id' => 1, 'favorite' => 1,
            'legacy_score' => 2, 'legacy_favorite' => 1, 'legacy_date' => 'Lundi-02-Novembre-2026',
        ]);
        DB::table('official_quiz_results')->where('user_id', $user->id)->update(['favorite' => 0]);
        $this->artisan('qetg:import-official-results')->assertSuccessful();
        $this->assertDatabaseHas('official_quiz_results', ['user_id' => $user->id, 'favorite' => 0]);
    }

    public function test_orphaned_results_are_reported_instead_of_silently_skipped(): void
    {
        DB::connection('legacy')->table('quiz_statistique')->insert([
            'id_user' => 7, 'id_quiz' => 999, 'date' => '2026-01-01', 'note' => 0, 'favoris' => 0, 'quiz_utilisateur' => 0,
        ]);
        $this->artisan('qetg:import-official-results')
            ->expectsOutput('The snapshot contains results referencing a missing quiz. No results were imported.')
            ->assertFailed();
        $this->assertDatabaseCount('official_quiz_results', 0);
    }

    public function test_only_the_owner_can_view_and_update_favorites(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        DB::table('official_quiz_results')->insert(['user_id' => $owner->id, 'quiz_id' => 1, 'favorite' => 0]);
        $this->get('/profil')->assertRedirect('/connexion');
        $this->actingAs($other)->get('/profil')->assertOk()->assertDontSee('Quiz officiel');
        $this->actingAs($other)->post('/profil/favoris/1', ['favorite' => '1'])->assertNotFound();
        $this->actingAs($owner)->get('/profil')->assertOk()->assertSee('Quiz officiel')->assertSee('Ajouter aux favoris');
        $this->actingAs($owner)->from('/profil')->post('/profil/favoris/1', ['favorite' => '1'])->assertRedirect('/profil');
        $this->assertDatabaseHas('official_quiz_results', ['user_id' => $owner->id, 'quiz_id' => 1, 'favorite' => 1]);
        $this->actingAs($owner)->from('/profil')->post('/profil/favoris/1', ['favorite' => 'invalid'])->assertSessionHasErrors('favorite');
    }

    public function test_finishing_as_a_member_records_once_without_clearing_favorite(): void
    {
        $user = User::factory()->create();
        DB::table('official_questions')->insert(['legacy_id' => 10, 'quiz_id' => 1, 'number' => 1, 'text' => 'Question']);
        DB::table('official_answers')->insert(['legacy_id' => 100, 'question_id' => 10, 'text' => 'Bonne', 'correct' => 1]);
        $this->actingAs($user)->post('/quiz/1/start')->assertRedirect('/quiz/1/question');
        $this->post('/quiz/1/answer', ['answer_id' => 100])->assertRedirect('/quiz/1/result');
        $this->get('/quiz/1/result')->assertOk()->assertSee('Votre résultat est enregistré');
        $this->assertDatabaseCount('official_quiz_results', 1);
        $this->post('/profil/favoris/1', ['favorite' => '1']);
        $this->post('/quiz/1/start');
        $this->post('/quiz/1/answer', ['answer_id' => 100]);
        $this->assertDatabaseCount('official_quiz_results', 1);
        $this->assertDatabaseHas('official_quiz_results', ['user_id' => $user->id, 'quiz_id' => 1, 'favorite' => 1]);
    }
}
