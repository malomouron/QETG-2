<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OfficialQuizCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_search_treats_wildcards_as_literals_and_keeps_query_on_pagination(): void
    {
        for ($id = 1; $id <= 11; $id++) {
            DB::table('official_quizzes')->insert([
                'legacy_id' => $id,
                'name' => "Quiz 100% sujet $id",
                'description' => 'Description',
                'image_path' => 'image.png',
                'image_alt' => 'Image',
            ]);
        }
        DB::table('official_quizzes')->insert([
            'legacy_id' => 12, 'name' => 'Quiz 100X autre', 'description' => 'Description',
            'image_path' => 'image.png', 'image_alt' => 'Image',
        ]);
        $this->get('/catalogue?recherche=100%25')->assertOk()->assertSee('Quiz 100% sujet 10')
            ->assertDontSee('Quiz 100X autre')->assertSee('recherche=100%25', false);
        $this->get('/catalogue?recherche=100%25&page=2')->assertOk()->assertSee('Quiz 100% sujet 11')
            ->assertDontSee('Quiz 100X autre');
        $this->get('/catalogue?recherche=100_')->assertOk()->assertSee('Aucun quiz officiel ne correspond');
        $this->get('/catalogue?recherche=%3Cscript%3E')->assertOk()->assertDontSee('<script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    private function prepareLegacySnapshot(): void
    {
        config(['database.connections.legacy' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('legacy');
        Schema::connection('legacy')->create('quiz_image', function (Blueprint $table) {
            $table->integer('id_image')->primary();
            $table->string('src');
            $table->string('alt');
        });
        Schema::connection('legacy')->create('quiz', function (Blueprint $table) {
            $table->integer('id_quiz')->primary();
            $table->integer('id_image_quiz')->nullable();
            $table->integer('id_quiz_user');
            $table->string('nom_quiz')->nullable();
            $table->text('bienvenue');
        });
        Schema::connection('legacy')->create('quiz_difficulte', function (Blueprint $table) {
            $table->integer('id_difficulte')->primary();
            $table->string('libelle');
        });
        Schema::connection('legacy')->create('quiz_question', function (Blueprint $table) {
            $table->integer('id_question')->primary();
            $table->integer('id_quiz');
            $table->integer('id_image_question')->nullable();
            $table->integer('id_difficulte');
            $table->integer('numero_question');
            $table->text('texte_bienvenue')->nullable();
            $table->text('question_texte');
        });
        Schema::connection('legacy')->create('quiz_reponse', function (Blueprint $table) {
            $table->integer('id_reponse')->primary();
            $table->integer('id_question');
            $table->integer('id_image_reponse')->nullable();
            $table->integer('num_reponse')->nullable();
            $table->string('choix_possible_quiz');
            $table->string('reponseOK');
            $table->text('texte_reponse_explicatif')->nullable();
        });
        DB::connection('legacy')->table('quiz_image')->insert(['id_image' => 1, 'src' => 'image.png', 'alt' => 'Image']);
        DB::connection('legacy')->table('quiz_difficulte')->insert(['id_difficulte' => 1, 'libelle' => 'Simple']);
        DB::connection('legacy')->table('quiz')->insert([
            ['id_quiz' => 1, 'id_image_quiz' => 1, 'id_quiz_user' => 0, 'nom_quiz' => 'Quiz &amp; histoire', 'bienvenue' => 'Bienvenue'],
            ['id_quiz' => 2, 'id_image_quiz' => 1, 'id_quiz_user' => 1, 'nom_quiz' => 'Quiz membre prive', 'bienvenue' => 'Prive'],
            ['id_quiz' => 3, 'id_image_quiz' => null, 'id_quiz_user' => 0, 'nom_quiz' => 'Sans image', 'bienvenue' => 'Absent'],
        ]);
        DB::connection('legacy')->table('quiz_question')->insert([
            ['id_question' => 10, 'id_quiz' => 1, 'id_image_question' => 1, 'id_difficulte' => 1, 'numero_question' => 1, 'texte_bienvenue' => 'Introduction', 'question_texte' => 'Question officielle'],
            ['id_question' => 20, 'id_quiz' => 2, 'id_image_question' => null, 'id_difficulte' => 1, 'numero_question' => 1, 'texte_bienvenue' => null, 'question_texte' => 'Question membre'],
        ]);
        DB::connection('legacy')->table('quiz_reponse')->insert([
            ['id_reponse' => 100, 'id_question' => 10, 'id_image_reponse' => 1, 'num_reponse' => 1, 'choix_possible_quiz' => 'Oui &lt;oui&gt;', 'reponseOK' => '1', 'texte_reponse_explicatif' => 'Bravo'],
            ['id_reponse' => 200, 'id_question' => 20, 'id_image_reponse' => null, 'num_reponse' => 1, 'choix_possible_quiz' => 'Secret', 'reponseOK' => '1', 'texte_reponse_explicatif' => null],
        ]);
    }

    public function test_import_requires_an_explicit_source(): void
    {
        config(['database.connections.legacy.database' => null]);

        $this->artisan('qetg:import-official-quizzes')
            ->assertFailed();
        $this->assertDatabaseCount('official_quizzes', 0);
    }

    public function test_import_rejects_an_identical_mysql_source_and_destination(): void
    {
        $default = config('database.default');
        $mysql = config('database.connections.mysql');
        $legacy = config('database.connections.legacy');
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => ['driver' => 'mysql', 'host' => 'db', 'port' => '3306', 'database' => 'same'],
            'database.connections.legacy' => ['driver' => 'mysql', 'host' => 'db', 'port' => '3306', 'database' => 'same', 'username' => 'reader'],
        ]);

        try {
            $this->artisan('qetg:import-official-quizzes')
                ->expectsOutput('The legacy snapshot and the destination must be separate databases.')
                ->assertFailed();
        } finally {
            config([
                'database.default' => $default,
                'database.connections.mysql' => $mysql,
                'database.connections.legacy' => $legacy,
            ]);
        }
    }

    public function test_import_is_read_only_on_source_and_repeatable(): void
    {
        $this->prepareLegacySnapshot();
        $this->artisan('qetg:import-official-quizzes', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('official_quizzes', 0);

        $this->artisan('qetg:import-official-quizzes')->assertSuccessful();
        $this->assertDatabaseCount('official_quizzes', 1);
        $this->assertDatabaseCount('official_questions', 1);
        $this->assertDatabaseCount('official_answers', 1);
        $this->assertDatabaseHas('official_quizzes', ['legacy_id' => 1, 'name' => 'Quiz & histoire']);
        $this->assertDatabaseHas('official_answers', ['legacy_id' => 100, 'question_id' => 10, 'correct' => true]);
        $this->assertDatabaseHas('official_answers', ['legacy_id' => 100, 'text' => 'Oui <oui>']);
        $this->get('/catalogue')->assertOk()->assertSee('Quiz &amp; histoire', false)->assertDontSee('Quiz &amp;amp; histoire', false);
        $this->assertSame(3, DB::connection('legacy')->table('quiz')->count());

        $originalHash = DB::table('official_quizzes')->where('legacy_id', 1)->value('content_hash');
        $this->artisan('qetg:import-official-quizzes')->assertSuccessful();
        $this->assertSame($originalHash, DB::table('official_quizzes')->where('legacy_id', 1)->value('content_hash'));
        DB::connection('legacy')->table('quiz')->where('id_quiz', 1)->update(['nom_quiz' => 'Quiz modifie']);
        $this->artisan('qetg:import-official-quizzes')->assertSuccessful();
        $this->assertDatabaseCount('official_quizzes', 1);
        $this->assertDatabaseHas('official_quizzes', ['legacy_id' => 1, 'name' => 'Quiz modifie']);
        $this->assertNotSame($originalHash, DB::table('official_quizzes')->where('legacy_id', 1)->value('content_hash'));
        DB::connection('legacy')->table('quiz_reponse')->where('id_reponse', 100)->delete();
        $this->artisan('qetg:import-official-quizzes')->assertSuccessful();
        $this->assertDatabaseCount('official_answers', 0);
        DB::connection('legacy')->table('quiz_question')->where('id_question', 10)->delete();
        $this->artisan('qetg:import-official-quizzes')->assertSuccessful();
        $this->assertDatabaseCount('official_questions', 0);
    }

    public function test_invalid_legacy_answer_aborts_without_writing(): void
    {
        $this->prepareLegacySnapshot();
        DB::connection('legacy')->table('quiz_reponse')->where('id_reponse', 100)->update(['reponseOK' => 'invalid']);

        $this->artisan('qetg:import-official-quizzes')
            ->expectsOutput('Invalid correctness value for legacy answer 100.')
            ->assertFailed();
        $this->assertDatabaseCount('official_quizzes', 0);
    }

    public function test_catalogue_is_paginated_and_escapes_legacy_content(): void
    {
        for ($id = 1; $id <= 11; $id++) {
            DB::table('official_quizzes')->insert([
                'legacy_id' => $id,
                'name' => $id === 1 ? '<script>alert(1)</script>' : "Quiz $id",
                'description' => 'Description',
                'image_path' => 'image.png',
                'image_alt' => 'Image',
            ]);
        }

        $this->get('/catalogue')->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Quiz 10')
            ->assertDontSee('Quiz 11');
        $this->get('/catalogue?page=2')->assertOk()->assertSee('Quiz 11');
    }

    public function test_import_rolls_back_if_a_record_cannot_be_saved(): void
    {
        $this->prepareLegacySnapshot();
        DB::connection('legacy')->table('quiz')->insert([
            'id_quiz' => 4,
            'id_image_quiz' => 1,
            'id_quiz_user' => 0,
            'nom_quiz' => null,
            'bienvenue' => 'Invalid fixture',
        ]);

        try {
            $this->artisan('qetg:import-official-quizzes');
            $this->fail('Invalid data must abort the import.');
        } catch (QueryException $exception) {
            $this->assertDatabaseCount('official_quizzes', 0);
            $this->assertDatabaseCount('official_questions', 0);
            $this->assertDatabaseCount('official_answers', 0);
        }
    }
}
