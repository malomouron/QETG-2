<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficialImageTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private string $png;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('legacy-source');
        $this->root = Storage::disk('legacy-source')->path('');
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lX8AAAAASUVORK5CYII=', true);
        Storage::disk('legacy-source')->put('commun/quiz.png', $this->png);
        Storage::disk('legacy-source')->put('commun/question.png', $this->png);
        Storage::disk('legacy-source')->put('commun/answer.png', $this->png);
        DB::table('official_quizzes')->insert([
            'legacy_id' => 1, 'name' => 'Quiz', 'description' => 'Test',
            'image_path' => '/quiz/commun/quiz.png', 'image_alt' => 'Couverture',
            'content_hash' => str_repeat('a', 64),
        ]);
        DB::table('official_questions')->insert([
            'legacy_id' => 10, 'quiz_id' => 1, 'number' => 1, 'text' => 'Question',
            'image_path' => 'commun/question.png', 'image_alt' => 'Illustration',
        ]);
        DB::table('official_answers')->insert([
            'legacy_id' => 100, 'question_id' => 10, 'number' => 1, 'text' => 'Bonne',
            'correct' => true, 'explanation' => 'Explication',
            'image_path' => 'commun/answer.png', 'image_alt' => 'Réponse',
        ]);
    }

    public function test_import_copies_and_displays_all_official_images(): void
    {
        $this->artisan('qetg:import-official-images', ['root' => $this->root, '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseHas('official_quizzes', ['legacy_id' => 1, 'image_storage_key' => null]);
        $this->artisan('qetg:import-official-images', ['root' => $this->root])->assertSuccessful();
        $key = 'legacy-images/'.hash('sha256', $this->png);
        Storage::disk('local')->assertExists($key);
        $this->get('/catalogue')->assertSee('/images-officielles/quiz/1')->assertSee('Couverture');
        $this->get('/quiz/1')->assertSee('/images-officielles/quiz/1');
        $this->post('/quiz/1/start')->assertRedirect();
        $this->get('/quiz/1/question')->assertSee('/images-officielles/question/10');
        $this->post('/quiz/1/answer', ['answer_id' => 100])->assertRedirect();
        $this->get('/quiz/1/result')->assertSee('/images-officielles/answer/100');
        foreach (['quiz/1', 'question/10', 'answer/100'] as $image) {
            $response = $this->get('/images-officielles/'.$image)->assertOk()
                ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame($this->png, $response->streamedContent());
        }
        $this->get('/images-officielles/other/1')->assertNotFound();
        $this->get('/images-officielles/quiz/999')->assertNotFound();
    }

    public function test_changed_image_invalidates_sessions_but_identical_reimport_does_not(): void
    {
        $this->artisan('qetg:import-official-images', ['root' => $this->root])->assertSuccessful();
        $this->post('/quiz/1/start')->assertRedirect();
        $this->artisan('qetg:import-official-images', ['root' => $this->root])->assertSuccessful();
        $this->get('/quiz/1/question')->assertOk();
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);
        Storage::disk('legacy-source')->put('commun/question.png', $gif);
        $this->artisan('qetg:import-official-images', ['root' => $this->root])->assertSuccessful();
        $this->get('/quiz/1/question')->assertStatus(409);
        $this->post('/quiz/1/answer', ['answer_id' => 100])->assertStatus(409);
        $this->get('/images-officielles/question/10')->assertHeader('Content-Type', 'image/gif');
    }

    public function test_missing_invalid_or_unsafe_files_fail_without_changing_image_references(): void
    {
        $this->artisan('qetg:import-official-images', ['root' => $this->root])->assertSuccessful();
        $oldKey = DB::table('official_quizzes')->where('legacy_id', 1)->value('image_storage_key');
        foreach (['../outside.png', '%2e%2e%5coutside.png', 'https://example.com/image.png', 'commun/missing.png', 'commun/not-image.png'] as $path) {
            Storage::disk('legacy-source')->put('commun/not-image.png', 'not an image');
            DB::table('official_quizzes')->where('legacy_id', 1)->update(['image_path' => $path]);
            $this->artisan('qetg:import-official-images', ['root' => $this->root])->assertFailed();
            $this->assertSame($oldKey, DB::table('official_quizzes')->where('legacy_id', 1)->value('image_storage_key'));
            $this->get('/images-officielles/quiz/1')->assertNotFound();
            $this->get('/catalogue')->assertDontSee('/images-officielles/quiz/1');
        }
    }

    public function test_image_endpoint_cannot_read_arbitrary_storage_files(): void
    {
        DB::table('official_quizzes')->where('legacy_id', 1)->update([
            'image_storage_source' => '/quiz/commun/quiz.png',
            'image_storage_key' => '../secret',
            'image_mime' => 'image/png',
        ]);
        Storage::disk('local')->put('secret', 'private');
        $this->get('/images-officielles/quiz/1')->assertNotFound();
    }

    public function test_a_corrupted_existing_copy_is_not_overwritten_or_linked(): void
    {
        $key = 'legacy-images/'.hash('sha256', $this->png);
        Storage::disk('local')->put($key, 'corrupted');
        $this->artisan('qetg:import-official-images', ['root' => $this->root])->assertFailed();
        $this->assertSame('corrupted', Storage::disk('local')->get($key));
        $this->assertDatabaseHas('official_quizzes', ['legacy_id' => 1, 'image_storage_key' => null]);
    }
}
