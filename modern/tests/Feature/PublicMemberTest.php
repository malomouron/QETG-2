<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_can_find_members_and_view_public_results_without_private_fields(): void
    {
        $user = User::factory()->create(['name' => 'Alice 100%', 'legacy_login' => 'Alice 100%', 'email' => 'private@example.com']);
        User::factory()->create(['name' => 'Alice 100X', 'legacy_login' => 'Alice 100X']);
        DB::table('official_quizzes')->insert([
            'legacy_id' => 1, 'name' => 'Quiz public', 'description' => 'Description',
            'image_path' => 'image.png', 'image_alt' => 'Image',
        ]);
        DB::table('official_quiz_results')->insert(['user_id' => $user->id, 'quiz_id' => 1, 'favorite' => true]);
        $this->get('/membres?recherche=100%25')->assertOk()->assertSee('Alice 100%')->assertDontSee('Alice 100X')
            ->assertDontSee('private@example.com');
        $this->get('/membres/'.$user->id)->assertOk()->assertSee('Alice 100%')->assertSee('Quiz public')
            ->assertSee('Favori')->assertDontSee('private@example.com')->assertDontSee('Retirer des favoris');
        $this->get('/membres/999999')->assertNotFound();
    }
}
