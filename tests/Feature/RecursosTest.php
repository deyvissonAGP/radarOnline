<?php

namespace Tests\Feature;

use App\Models\Vaga;
use App\Radar\Services\ColetaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Marcação de vagas, filtros da página, filtros do Telegram, alerta de fonte e limpeza. */
class RecursosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-28 12:00:00'));
    }

    private function vaga(string $titulo, array $campos = []): Vaga
    {
        static $n = 0;
        $n++;

        return Vaga::create($campos + [
            'fonte' => 'gupy',
            'id_externo' => (string) $n,
            'titulo' => $titulo,
            'url' => "https://exemplo.com/{$n}",
            'aceita' => true,
            'impressao_digital' => sha1($titulo.$n),
            'publicada_em' => now()->subHours(2),
            'vista_primeira_vez_em' => now(),
            'vista_ultima_vez_em' => now(),
        ]);
    }

    public function test_marcar_candidatura_e_descarte(): void
    {
        $candidatei = $this->vaga('Dev Laravel Pleno');
        $descartada = $this->vaga('Dev WordPress Júnior');
        $this->vaga('Dev Symfony Sênior');

        $this->post(route('vagas.marcar', $candidatei), ['status' => 'candidatei'])->assertRedirect();
        $this->post(route('vagas.marcar', $descartada), ['status' => 'descartada'])->assertRedirect();
        $this->post(route('vagas.marcar', $descartada), ['status' => 'invalido'])->assertSessionHasErrors('status');

        $this->get('/')->assertOk()
            ->assertSee('Dev Laravel Pleno')
            ->assertSee('Dev Symfony Sênior')
            ->assertDontSee('Dev WordPress Júnior')
            ->assertSee('Candidatei (1)')
            ->assertSee('Descartadas (1)');

        $this->get('/?lista=descartada')->assertOk()->assertSee('Dev WordPress Júnior')->assertDontSee('Dev Symfony Sênior');

        // A candidatura continua na lista mesmo depois de sair da janela de 72 horas.
        $this->travel(10)->days();
        $this->get('/?lista=candidatei')->assertOk()->assertSee('Dev Laravel Pleno');

        // Desfazer volta a vaga ao normal.
        $this->post(route('vagas.marcar', $descartada), ['status' => ''])->assertRedirect();
        $this->assertNull($descartada->fresh()->status);
    }

    public function test_filtros_de_salario_tecnologia_e_busca_na_descricao(): void
    {
        $this->vaga('Dev PHP A', ['salario_min' => 5000, 'salario_max' => 7000, 'salario_moeda' => 'BRL', 'tecnologias' => ['laravel']]);
        $this->vaga('Dev PHP B', ['salario_min' => 3000, 'salario_max' => 3000, 'salario_moeda' => 'BRL', 'tecnologias' => ['symfony']]);
        $this->vaga('Dev PHP C', ['salario_min' => 9000, 'salario_max' => 9000, 'salario_moeda' => 'USD', 'descricao' => 'Stack com Kubernetes']);

        $this->get('/?salario=6000')->assertOk()->assertSee('Dev PHP A')->assertDontSee('Dev PHP B')->assertDontSee('Dev PHP C');
        $this->get('/?tec=laravel')->assertOk()->assertSee('Dev PHP A')->assertDontSee('Dev PHP B');
        $this->get('/?q=kubernetes')->assertOk()->assertSee('Dev PHP C')->assertDontSee('Dev PHP A');
        $this->get('/')->assertSee('R$ 5.000 – 7.000/mês')->assertSee('US$ 9.000/mês');
    }

    public function test_salario_extraido_na_coleta(): void
    {
        $job = new \App\Radar\JobData('gupy', 'x1', 'Desenvolvedor PHP Pleno', 'https://exemplo.com/x1', 'Acme',
            description: 'Laravel e filas. Faixa salarial: R$ 6.000 a R$ 8.000. VR de R$ 1.000.');

        $vaga = app(ColetaService::class)->gravar($job);

        $this->assertSame([6000, 8000, 'BRL'], [$vaga->salario_min, $vaga->salario_max, $vaga->salario_moeda]);
    }

    public function test_filtros_pessoais_do_telegram(): void
    {
        config(['vagas.telegram.modelos' => ['remoto'], 'vagas.telegram.niveis' => ['pleno', 'senior']]);

        $this->vaga('Remota pleno', ['modelo' => 'remoto', 'senioridade' => 'pleno']);
        $this->vaga('Remota sem nível', ['modelo' => 'remoto']);
        $this->vaga('Presencial pleno', ['modelo' => 'presencial', 'senioridade' => 'pleno']);
        $this->vaga('Remota júnior', ['modelo' => 'remoto', 'senioridade' => 'junior']);
        $this->vaga('Remota descartada', ['modelo' => 'remoto', 'senioridade' => 'pleno', 'status' => 'descartada']);

        $this->artisan('vagas:notificar --simular')
            ->expectsOutputToContain('Remota pleno')
            ->expectsOutputToContain('Remota sem nível')
            ->doesntExpectOutputToContain('Presencial pleno')
            ->doesntExpectOutputToContain('Remota júnior')
            ->doesntExpectOutputToContain('Remota descartada')
            ->expectsOutputToContain('2 vaga(s) para enviar.')
            ->assertSuccessful();
    }

    public function test_filtros_de_area_e_tecnologia_do_telegram(): void
    {
        config(['vagas.telegram.areas' => ['desenvolvimento'], 'vagas.telegram.tecnologias' => ['php', 'python']]);

        $this->vaga('Dev PHP', ['area' => 'desenvolvimento', 'tecnologias' => ['php', 'laravel']]);
        $this->vaga('Dev Python', ['area' => 'desenvolvimento', 'tecnologias' => ['python']]);
        $this->vaga('Dev Java', ['area' => 'desenvolvimento', 'tecnologias' => ['java']]);
        $this->vaga('Dev sem tecnologia', ['area' => 'desenvolvimento']);
        $this->vaga('Engenheiro de Dados Python', ['area' => 'dados', 'tecnologias' => ['python']]);

        $this->artisan('vagas:notificar --simular')
            ->expectsOutputToContain('Dev PHP')
            ->expectsOutputToContain('Dev Python')
            ->doesntExpectOutputToContain('Dev Java')
            ->doesntExpectOutputToContain('Dev sem tecnologia')
            ->doesntExpectOutputToContain('Engenheiro de Dados Python')
            ->expectsOutputToContain('2 vaga(s) para enviar.')
            ->assertSuccessful();
    }

    public function test_reavaliar_aplica_as_regras_atuais(): void
    {
        $antiga = $this->vaga('Desenvolvedor Python Pleno', ['aceita' => false, 'motivo_rejeicao' => 'não cita PHP nem framework PHP']);
        $errada = $this->vaga('Vendedor Interno', ['aceita' => true]);

        $this->artisan('vagas:reavaliar')->expectsOutputToContain('1 passaram a ser aceitas, 1 passaram a ser rejeitadas')->assertSuccessful();

        $this->assertTrue($antiga->fresh()->aceita);
        $this->assertSame('desenvolvimento', $antiga->fresh()->area);
        $this->assertFalse($errada->fresh()->aceita);
    }

    public function test_alerta_uma_vez_quando_a_fonte_falha_seguidas_vezes(): void
    {
        config([
            'vagas.termos' => ['php'],
            'vagas.fontes.github.ativa' => false,
            'vagas.fontes.himalayas.ativa' => false,
            'vagas.fontes.remotar.ativa' => false,
            'vagas.fontes.programathor.ativa' => false,
            'vagas.telegram.token' => null,
        ]);
        Http::fake(['portal.gupy.io/*' => Http::response('erro', 500)]);

        $this->artisan('vagas:coletar')->doesntExpectOutputToContain('falhou nas últimas')->assertSuccessful();
        $this->artisan('vagas:coletar')->expectsOutputToContain('A fonte Gupy falhou nas últimas 2 coletas')->assertSuccessful();
        $this->artisan('vagas:coletar')->doesntExpectOutputToContain('falhou nas últimas')->assertSuccessful();

        $this->get('/')->assertOk()->assertSee('falhando (3×)');
    }

    public function test_limpeza_mantem_candidaturas(): void
    {
        $this->vaga('Antiga candidatada', ['publicada_em' => now()->subDays(40), 'status' => 'candidatei']);
        $this->vaga('Antiga descartada', ['publicada_em' => now()->subDays(40), 'status' => 'descartada']);
        $this->vaga('Antiga sem marcação', ['publicada_em' => now()->subDays(40)]);

        $this->assertSame(2, app(ColetaService::class)->limpar());
        $this->assertSame(['Antiga candidatada'], Vaga::pluck('titulo')->all());
    }
}
