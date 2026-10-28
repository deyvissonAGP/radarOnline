<?php

namespace Tests\Feature;

use App\Models\Coleta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Senha do site e rotas abertas para o monitor. */
class AcessoTest extends TestCase
{
    use RefreshDatabase;

    private function comSenha(): void
    {
        config(['radar.acesso.usuario' => 'eu', 'radar.acesso.senha' => 'uma-senha-bem-longa']);
    }

    public function test_pede_senha_quando_configurada(): void
    {
        $this->comSenha();

        $this->get('/')->assertStatus(401)->assertHeader('WWW-Authenticate');
        $this->withBasicAuth('eu', 'errada')->get('/')->assertStatus(401);
        $this->withBasicAuth('eu', 'uma-senha-bem-longa')->get('/')->assertOk();
    }

    public function test_bloqueia_depois_de_muitas_tentativas(): void
    {
        $this->comSenha();

        for ($i = 0; $i < 10; $i++) {
            $this->withBasicAuth('eu', 'errada')->get('/')->assertStatus(401);
        }
        $this->withBasicAuth('eu', 'uma-senha-bem-longa')->get('/')->assertStatus(429);
    }

    public function test_producao_sem_senha_fica_bloqueada(): void
    {
        $this->app['env'] = 'production';

        $this->get('/')->assertStatus(503);
    }

    public function test_saude_e_up_sem_senha(): void
    {
        $this->comSenha();

        $this->get('/up')->assertOk();
        $this->get('/saude')->assertStatus(503)->assertJson(['ok' => false, 'ultima_coleta' => null]);

        Coleta::create(['fonte' => 'gupy', 'sucesso' => true, 'executada_em' => now()->subHours(2)]);
        $this->get('/saude')->assertOk()->assertJson(['ok' => true]);

        $this->travel(3)->hours();
        $this->get('/saude')->assertStatus(503);
    }
}
