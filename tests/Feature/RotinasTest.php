<?php

namespace Tests\Feature;

use App\Jobs\ExecutarRotina;
use App\Models\Execucao;
use App\Models\Vaga;
use App\Radar\Rotinas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Botões da página /rotinas: fila, bloqueio de repetição, execução e histórico. */
class RotinasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-28 12:00:00'));
    }

    public function test_pagina_lista_as_rotinas(): void
    {
        $this->get('/rotinas')->assertOk()
            ->assertSee('Coletar vagas')
            ->assertSee('Enviar vagas novas')
            ->assertSee('Testar o Telegram')
            ->assertSee('Nenhuma rotina executada ainda.');
    }

    public function test_botao_coloca_na_fila_e_nao_deixa_repetir(): void
    {
        Queue::fake();

        $this->post('/rotinas/coletar', ['fonte' => 'gupy'])
            ->assertRedirect('/rotinas')
            ->assertSessionHas('aviso');

        Queue::assertPushed(ExecutarRotina::class, 1);
        $execucao = Execucao::sole();
        $this->assertSame(['coletar', 'na_fila', 'web', ['--fonte' => ['gupy']]], [$execucao->rotina, $execucao->status, $execucao->origem, $execucao->parametros]);

        // Clicar de novo enquanto está na fila não duplica.
        $this->post('/rotinas/coletar')->assertSessionHas('erro');
        Queue::assertPushed(ExecutarRotina::class, 1);

        $this->get('/rotinas')->assertSee('Rodando…')->assertSee('em_andamento', false);
        $this->getJson('/rotinas/estado')->assertJson(['em_andamento' => 1]);
    }

    public function test_so_executa_rotinas_da_lista_e_fontes_validas(): void
    {
        Queue::fake();

        $this->post('/rotinas/migrate:fresh')->assertNotFound();
        $this->post('/rotinas/coletar', ['fonte' => 'inexistente'])->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_execucao_guarda_a_saida_e_o_resultado(): void
    {
        Vaga::create([
            'fonte' => 'gupy', 'id_externo' => '1', 'titulo' => 'Dev PHP', 'url' => 'https://exemplo.com',
            'aceita' => true, 'impressao_digital' => 'x', 'publicada_em' => now()->subHour(),
            'vista_primeira_vez_em' => now(), 'vista_ultima_vez_em' => now(),
        ]);

        $this->post('/rotinas/simular');   // fila "sync" nos testes: roda na hora

        $execucao = Execucao::sole();
        $this->assertSame('concluida', $execucao->status);
        $this->assertStringContainsString('Dev PHP', $execucao->saida);
        $this->assertStringContainsString('1 vaga(s) para enviar.', $execucao->saida);
        $this->assertNotNull($execucao->terminada_em);

        $this->get('/rotinas')->assertSee('Concluída')->assertSee('Ver saída');
    }

    public function test_falha_fica_registrada(): void
    {
        config(['vagas.telegram.token' => null, 'vagas.telegram.chat_id' => null]);

        $this->post('/rotinas/testar_telegram');

        $execucao = Execucao::sole();
        $this->assertSame('falhou', $execucao->status);
        $this->assertStringContainsString('Telegram não configurado', $execucao->saida);
        $this->get('/rotinas')->assertSee('Falhou');
    }

    public function test_execucao_presa_e_liberada(): void
    {
        Queue::fake();
        Rotinas::pedir('reavaliar');

        $this->travel(25)->minutes();
        $this->get('/rotinas')->assertOk();

        $this->assertSame('falhou', Execucao::sole()->status);
        $this->assertNotNull(Rotinas::pedir('reavaliar'), 'depois de liberada, dá para pedir de novo');
    }

    public function test_agendador_registra_no_historico_e_pula_se_ja_estiver_rodando(): void
    {
        $this->assertSame('concluida', Rotinas::executarAgora('limpar')->status);
        $this->assertSame('agendada', Execucao::sole()->origem);

        Execucao::create(['rotina' => 'limpar', 'origem' => 'web', 'status' => 'rodando', 'pedida_em' => now(), 'iniciada_em' => now()]);
        $this->assertNull(Rotinas::executarAgora('limpar'));
    }
}
