<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficialQuizPlayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('official_quizzes')->insert([
            'legacy_id' => 1, 'name' => 'Quiz test', 'description' => 'Bienvenue',
            'image_path' => 'quiz.png', 'image_alt' => 'Image',
        ]);
        DB::table('official_questions')->insert([
            ['legacy_id' => 10, 'quiz_id' => 1, 'number' => 1, 'introduction' => 'Intro', 'text' => 'Première question', 'difficulty' => 'Facile'],
            ['legacy_id' => 11, 'quiz_id' => 1, 'number' => 2, 'introduction' => null, 'text' => 'Deuxième question', 'difficulty' => null],
        ]);
        DB::table('official_answers')->insert([
            ['legacy_id' => 100, 'question_id' => 10, 'number' => 1, 'text' => 'Mauvaise', 'correct' => 0, 'explanation' => 'Explication fausse'],
            ['legacy_id' => 101, 'question_id' => 10, 'number' => 2, 'text' => 'Bonne', 'correct' => 1, 'explanation' => 'Explication juste'],
            ['legacy_id' => 102, 'question_id' => 11, 'number' => 1, 'text' => 'Finale', 'correct' => 1, 'explanation' => 'Terminé'],
        ]);
    }

    public function test_visitor_can_play_and_must_restart_after_a_wrong_answer(): void
    {
        $this->get('/quiz/1')->assertOk()->assertSee('Quiz test');
        $this->get('/quiz/1/question')->assertStatus(409);
        $this->post('/quiz/1/start')->assertRedirect('/quiz/1/question');
        $this->get('/quiz/1/question')->assertOk()->assertSee('Première question')->assertDontSee('Explication juste');
        $this->post('/quiz/1/answer', ['answer_id' => 100])->assertRedirect('/quiz/1/result');
        $this->get('/quiz/1/result')->assertOk()->assertSee('Explication fausse')->assertSee('Recommencer');
        $this->post('/quiz/1/next')->assertStatus(409);
        $this->post('/quiz/1/start')->assertRedirect('/quiz/1/question');
        $this->post('/quiz/1/answer', ['answer_id' => 101])->assertRedirect('/quiz/1/result');
        $this->get('/quiz/1/result')->assertOk()->assertSee('Explication juste')->assertSee('Question suivante');
        $this->post('/quiz/1/next')->assertRedirect('/quiz/1/question');
        $this->get('/quiz/1/question')->assertOk()->assertSee('Deuxième question');
        $this->post('/quiz/1/answer', ['answer_id' => 102])->assertRedirect('/quiz/1/result');
        $this->get('/quiz/1/result')->assertOk()->assertSee('Ce quiz est terminé.');
        $this->post('/quiz/1/next')->assertStatus(409);
    }

    public function test_answer_must_belong_to_current_question_and_cannot_be_replayed(): void
    {
        $this->post('/quiz/1/start');
        $this->from('/quiz/1/question')->post('/quiz/1/answer', ['answer_id' => 102])
            ->assertRedirect('/quiz/1/question')->assertSessionHasErrors('answer_id');
        $this->post('/quiz/1/answer', ['answer_id' => 101])->assertRedirect('/quiz/1/result');
        $this->post('/quiz/1/answer', ['answer_id' => 101])->assertStatus(409);
    }

    public function test_incomplete_or_unknown_quiz_is_not_playable(): void
    {
        DB::table('official_answers')->where('question_id', 11)->delete();
        $this->from('/quiz/1')->post('/quiz/1/start')->assertRedirect('/quiz/1')->assertSessionHasErrors('quiz');
        $this->get('/quiz/999')->assertNotFound();
    }

    public function test_a_changed_import_requires_the_visitor_to_restart(): void
    {
        DB::table('official_quizzes')->where('legacy_id', 1)->update(['content_hash' => str_repeat('a', 64)]);
        $this->post('/quiz/1/start')->assertRedirect('/quiz/1/question');
        DB::table('official_quizzes')->where('legacy_id', 1)->update(['content_hash' => str_repeat('b', 64)]);
        $this->get('/quiz/1/question')->assertStatus(409);
        $this->post('/quiz/1/answer', ['answer_id' => 101])->assertStatus(409);
    }

    public function test_answer_rechecks_the_quiz_version_before_recording_a_result(): void
    {
        DB::table('official_quizzes')->where('legacy_id', 1)->update(['content_hash' => str_repeat('a', 64)]);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/quiz/1/start')->assertRedirect();
        $this->post('/quiz/1/answer', ['answer_id' => 101])->assertRedirect();
        $this->post('/quiz/1/next')->assertRedirect();

        $quizReads = 0;
        DB::listen(function (QueryExecuted $query) use (&$quizReads) {
            if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'official_quizzes') && ++$quizReads === 2) {
                DB::table('official_quizzes')->where('legacy_id', 1)->update(['content_hash' => str_repeat('b', 64)]);
            }
        });

        $this->post('/quiz/1/answer', ['answer_id' => 102])->assertStatus(409);
        $this->assertSame(3, $quizReads);
        $this->assertDatabaseMissing('official_quiz_results', ['user_id' => $user->id, 'quiz_id' => 1]);
        $this->assertDatabaseHas('official_quiz_progress', ['user_id' => $user->id, 'quiz_id' => 1, 'question_id' => 11]);
    }
}
