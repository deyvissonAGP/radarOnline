<?php

namespace Tests\Feature;

use App\Models\Vaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Fluxo completo: coleta (com HTTP simulado) → banco → página e resumo. */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-28 12:00:00'));

        config([
            'vagas.termos' => ['php'],
            'vagas.fontes.remotar.ativa' => false,
            'vagas.fontes.programathor.ativa' => false,
            'vagas.fontes.himalayas.ativa' => false,
            'vagas.fontes.github.repositorios' => ['backend-br/vagas'],
        ]);

        Http::fake([
            'portal.gupy.io/*' => Http::response('<script id="__NEXT_DATA__" type="application/json">'
                .json_encode(['props' => ['pageProps' => ['initialJobList' => json_decode(file_get_contents(base_path('tests/fixtures/gupy.json')), true)]]])
                .'</script>'),
            'api.github.com/*' => Http::response(file_get_contents(base_path('tests/fixtures/github.json'))),
        ]);
    }

    public function test_coleta_filtra_e_grava(): void
    {
        $this->artisan('vagas:coletar')->assertSuccessful();

        // Gupy: 2 vagas; GitHub: 2 vagas (PHP e Node.js). Curso, grupo e vendedor ficam como rejeitados.
        $this->assertSame(4, Vaga::abertas()->count());
        $this->assertSame(3, Vaga::where('aceita', false)->count());
        $this->assertSame(2, Vaga::abertas()->remotas()->count());

        // Rodar de novo não duplica nada.
        $this->artisan('vagas:coletar')->assertSuccessful();
        $this->assertSame(7, Vaga::count());
    }

    public function test_paginas_respondem(): void
    {
        $this->artisan('vagas:coletar');

        $this->get('/')->assertOk()->assertSee('Desenvolvedor PHP Pleno (Laravel)')->assertDontSee('Curso de Formação');
        $this->get('/?modelo=hibrido')->assertOk()->assertSee('Beta Comunicação')->assertDontSee('Delta Software');
        $this->get('/?tec=symfony')->assertOk()->assertSee('Delta Software');
        $this->get('/?area=desenvolvimento')->assertOk()->assertSee('Epsilon')->assertSee('Desenvolvimento');
        $this->get('/resumo')->assertOk()->assertSee('4 vagas abertas');
        $this->get('/regras')->assertOk()->assertSee('link de grupo');
    }

    public function test_resumo_e_simulacao_do_telegram(): void
    {
        $this->artisan('vagas:coletar');

        $this->artisan('vagas:resumo')->expectsOutputToContain('2 remotas')->assertSuccessful();
        $this->artisan('vagas:notificar --simular')->expectsOutputToContain('4 vaga(s) para enviar.')->assertSuccessful();
    }
}
