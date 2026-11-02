<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_homepage_explains_the_migration(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Quiz En Tout Genre')
            ->assertSee('site historique');
    }
}
