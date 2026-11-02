<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OfficialProgressTest extends TestCase
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
        Schema::connection('legacy')->create('quiz_question', function (Blueprint $table) {
            $table->integer('id_question')->primary();
            $table->integer('id_quiz');
        });
        Schema::connection('legacy')->create('quiz_progression', function (Blueprint $table) {
            $table->integer('id_prog')->primary();
            $table->integer('id_user');
            $table->integer('id_question');
        });
        DB::connection('legacy')->table('quiz')->insert(['id_quiz' => 1, 'id_quiz_user' => 0]);
        DB::connection('legacy')->table('quiz_question')->insert([
            ['id_question' => 10, 'id_quiz' => 1],
            ['id_question' => 11, 'id_quiz' => 1],
        ]);
        DB::connection('legacy')->table('quiz_progression')->insert(['id_prog' => 1, 'id_user' => 7, 'id_question' => 11]);
        DB::table('official_quizzes')->insert([
            'legacy_id' => 1, 'name' => 'Quiz officiel', 'description' => 'Bienvenue',
            'image_path' => 'quiz.png', 'image_alt' => 'Image',
        ]);
        DB::table('official_questions')->insert([
            ['legacy_id' => 10, 'quiz_id' => 1, 'number' => 1, 'text' => 'Question une'],
            ['legacy_id' => 11, 'quiz_id' => 1, 'number' => 2, 'text' => 'Question deux'],
        ]);
        DB::table('official_answers')->insert([
            ['legacy_id' => 100, 'question_id' => 10, 'text' => 'Bonne', 'correct' => 1],
            ['legacy_id' => 101, 'question_id' => 11, 'text' => 'Bonne finale', 'correct' => 1],
            ['legacy_id' => 102, 'question_id' => 11, 'text' => 'Mauvaise finale', 'correct' => 0],
        ]);
    }

    public function test_member_can_resume_after_reconnecting_and_wrong_answer_resets_progress(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/quiz/1/start')->assertRedirect('/quiz/1/question');
        $this->post('/quiz/1/answer', ['answer_id' => 100]);
        $this->post('/quiz/1/next');
        $this->assertDatabaseHas('official_quiz_progress', ['user_id' => $user->id, 'quiz_id' => 1, 'question_id' => 11]);
        $this->post('/deconnexion');
        $this->actingAs($user)->get('/quiz/1')->assertSee('Reprendre à la question 2');
        $this->post('/quiz/1/resume')->assertRedirect('/quiz/1/question');
        $this->get('/quiz/1/question')->assertSee('Question deux');
        $this->post('/quiz/1/answer', ['answer_id' => 102]);
        $this->assertDatabaseHas('official_quiz_progress', ['user_id' => $user->id, 'quiz_id' => 1, 'question_id' => 10]);
        $this->post('/quiz/1/resume');
        $this->get('/quiz/1/question')->assertSee('Question une');
    }

    public function test_completion_clears_member_progress_but_visitors_do_not_create_it(): void
    {
        $this->post('/quiz/1/start');
        $this->assertDatabaseCount('official_quiz_progress', 0);
        $this->post('/quiz/1/resume')->assertRedirect('/connexion');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/quiz/1/start');
        $this->post('/quiz/1/answer', ['answer_id' => 100]);
        $this->post('/quiz/1/next');
        $this->post('/quiz/1/answer', ['answer_id' => 101]);
        $this->assertDatabaseCount('official_quiz_progress', 0);
        $this->assertDatabaseHas('official_quiz_results', ['user_id' => $user->id, 'quiz_id' => 1]);
    }

    public function test_import_requires_parents_and_preserves_newer_progress(): void
    {
        $this->artisan('qetg:import-official-progress')->assertFailed();
        $this->assertDatabaseCount('official_quiz_progress', 0);
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['legacy_id' => 7]);
        $this->artisan('qetg:import-official-progress', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('official_quiz_progress', 0);
        $this->artisan('qetg:import-official-progress')->assertSuccessful();
        $this->assertDatabaseHas('official_quiz_progress', ['user_id' => $user->id, 'quiz_id' => 1, 'question_id' => 11, 'legacy_question_id' => 11]);
        DB::table('official_quiz_progress')->where('user_id', $user->id)->update(['question_id' => 10]);
        $this->artisan('qetg:import-official-progress')->assertSuccessful();
        $this->assertDatabaseHas('official_quiz_progress', ['user_id' => $user->id, 'question_id' => 10]);
        DB::table('official_questions')->where('legacy_id', 10)->delete();
        $this->assertDatabaseHas('official_quiz_progress', ['user_id' => $user->id, 'question_id' => null, 'legacy_question_id' => 11]);
    }

    public function test_duplicate_and_orphaned_legacy_progress_are_rejected(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['legacy_id' => 7]);
        DB::connection('legacy')->table('quiz_progression')->insert(['id_prog' => 2, 'id_user' => 7, 'id_question' => 10]);
        $this->artisan('qetg:import-official-progress')
            ->expectsOutput('The snapshot contains multiple progress records for one member and quiz. No progress was imported.')
            ->assertFailed();
        $this->assertDatabaseCount('official_quiz_progress', 0);
        DB::connection('legacy')->table('quiz_progression')->where('id_prog', 2)->update(['id_question' => 999]);
        $this->artisan('qetg:import-official-progress')
            ->expectsOutput('The snapshot contains progress referencing a missing question or quiz. No progress was imported.')
            ->assertFailed();
        $this->assertDatabaseCount('official_quiz_progress', 0);
    }
}
