<?php

namespace Tests\Feature;

use Tests\TestCase;

class InformationPageTest extends TestCase
{
    public function test_help_is_searchable_and_escapes_the_query(): void
    {
        $this->get('/aide')->assertOk()->assertSee('Reprendre un quiz')->assertSee('Mot de passe oublié');
        $this->get('/aide?recherche=progression')->assertOk()->assertSee('Reprendre un quiz')->assertDontSee('Mot de passe oublié');
        $this->get('/aide?recherche=%3Cscript%3E')->assertOk()->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>', false)->assertSee('Aucune réponse');
    }

    public function test_cinema_loads_only_the_explicitly_requested_external_player(): void
    {
        $this->get('/sortie-cine')->assertOk()->assertSee('Un tour chez ma fille')->assertSee('Un homme en colère')
            ->assertDontSee('<iframe', false);
        $this->get('/sortie-cine?video=fille')->assertOk()->assertSee('https://player.allocine.fr/19587387.html')
            ->assertDontSee('https://player.allocine.fr/19589615.html');
        $this->get('/sortie-cine?video=colere')->assertOk()->assertSee('https://player.allocine.fr/19589615.html')
            ->assertDontSee('https://player.allocine.fr/19587387.html');
    }
}
