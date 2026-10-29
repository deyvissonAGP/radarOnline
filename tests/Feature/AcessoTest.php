<?php

namespace Tests\Feature;

use App\Models\Coleta;
use App\Radar\Services\Acesso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tela de login, "manter conectado" e rotas abertas para o monitor. */
class AcessoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['radar.acesso.usuario' => 'eu', 'radar.acesso.senha' => 'uma-senha-bem-longa']);
    }

    public function test_sem_login_vai_para_a_tela_de_entrar_e_depois_volta(): void
    {
        $this->get('/regras')->assertRedirect('/entrar');
        $this->get('/entrar')->assertOk()->assertSee('Entrar')->assertDontSee('Sair');

        $this->post('/entrar', ['usuario' => 'eu', 'senha' => 'uma-senha-bem-longa'])->assertRedirect('/regras');
        $this->get('/regras')->assertOk()->assertSee('Sair');
        $this->get('/entrar')->assertRedirect('/');
    }

    public function test_senha_errada_mostra_erro(): void
    {
        $this->from('/entrar')->post('/entrar', ['usuario' => 'eu', 'senha' => 'errada'])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors(['usuario' => 'Usuário ou senha incorretos.']);
        $this->get('/')->assertRedirect('/entrar');
    }

    public function test_bloqueia_depois_de_muitas_tentativas(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/entrar', ['usuario' => 'eu', 'senha' => 'errada']);
        }

        $this->post('/entrar', ['usuario' => 'eu', 'senha' => 'uma-senha-bem-longa'])
            ->assertSessionHasErrors('usuario');
        $this->get('/')->assertRedirect('/entrar');
    }

    public function test_sair_encerra_a_sessao_e_o_cookie(): void
    {
        $this->post('/entrar', ['usuario' => 'eu', 'senha' => 'uma-senha-bem-longa', 'lembrar' => '1'])
            ->assertCookie(Acesso::COOKIE);

        $this->post('/sair')->assertRedirect('/entrar')->assertCookieExpired(Acesso::COOKIE);
        $this->get('/')->assertRedirect('/entrar');
    }

    public function test_manter_conectado_entra_pelo_cookie_e_cai_se_a_senha_mudar(): void
    {
        $token = app(Acesso::class)->tokenLembrar();

        $this->withCookie(Acesso::COOKIE, $token)->get('/')->assertOk();

        $this->flushSession();
        config(['radar.acesso.senha' => 'senha-nova-e-longa']);
        $this->withCookie(Acesso::COOKIE, $token)->get('/')->assertRedirect('/entrar');
    }

    public function test_sem_marcar_manter_conectado_nao_cria_cookie(): void
    {
        $this->post('/entrar', ['usuario' => 'eu', 'senha' => 'uma-senha-bem-longa'])
            ->assertCookieMissing(Acesso::COOKIE);
    }

    public function test_producao_sem_senha_fica_bloqueada(): void
    {
        config(['radar.acesso.senha' => '']);
        $this->app['env'] = 'production';

        $this->get('/')->assertStatus(503);
    }

    public function test_saude_e_up_sem_login(): void
    {
        $this->get('/up')->assertOk();
        $this->get('/saude')->assertStatus(503)->assertJson(['ok' => false, 'ultima_coleta' => null]);

        Coleta::create(['fonte' => 'gupy', 'sucesso' => true, 'executada_em' => now()->subHours(2)]);
        $this->get('/saude')->assertOk()->assertJson(['ok' => true]);

        $this->travel(3)->hours();
        $this->get('/saude')->assertStatus(503);
    }
}
