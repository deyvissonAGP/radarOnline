<?php

namespace Tests\Unit;

use App\Radar\Collectors\GithubIssuesCollector;
use App\Radar\Collectors\GupyCollector;
use App\Radar\Collectors\HimalayasCollector;
use App\Radar\Collectors\LinkedInCollector;
use App\Radar\Collectors\ProgramathorCollector;
use App\Radar\Collectors\RemotarCollector;
use PHPUnit\Framework\TestCase;

class ColetoresTest extends TestCase
{
    private function fixture(string $arquivo): string
    {
        return file_get_contents(__DIR__.'/../fixtures/'.$arquivo);
    }

    public function test_gupy(): void
    {
        $vagas = (new GupyCollector)->parse(json_decode($this->fixture('gupy.json'), true));

        $this->assertCount(4, $vagas, 'item sem id deve ser ignorado');
        $this->assertSame('Desenvolvedor PHP Pleno (Laravel)', $vagas[0]->title);
        $this->assertSame('Acme Tecnologia', $vagas[0]->company);
        $this->assertSame('remote', $vagas[0]->workplaceType);
        $this->assertSame('2026-10-27', $vagas[0]->publishedAt?->format('Y-m-d'));
        $this->assertSame('São Luís - Maranhão', $vagas[1]->location);
        $this->assertStringNotContainsString('<p>', $vagas[0]->description);
    }

    public function test_gupy_le_a_lista_da_pagina(): void
    {
        $json = json_decode($this->fixture('gupy.json'), true);
        $html = '<html><script id="__NEXT_DATA__" type="application/json">'
            .json_encode(['props' => ['pageProps' => ['initialJobList' => $json]]])
            .'</script></html>';

        $coletor = new GupyCollector;
        $this->assertSame($json, $coletor->extrairLista($html));
        $this->assertNull($coletor->extrairLista('<html>sem dados</html>'));
    }

    /** Página real do portal.gupy.io (reduzida a 3 vagas). */
    public function test_gupy_pagina_real(): void
    {
        $coletor = new GupyCollector;
        $vagas = $coletor->parse($coletor->extrairLista($this->fixture('gupy-pagina.html')));

        $this->assertCount(3, $vagas);
        $this->assertSame('Analista Sistemas Pleno PHP', $vagas[0]->title);
        $this->assertSame('Bun/Buntech', $vagas[0]->company);
        $this->assertSame('on-site', $vagas[0]->workplaceType);
        $this->assertSame('Campina Grande - Paraíba', $vagas[0]->location);
        $this->assertSame('2026-10-26', $vagas[0]->publishedAt?->format('Y-m-d'));
        $this->assertStringStartsWith('https://buntech.gupy.io/job/', $vagas[0]->url);
        $this->assertSame('hybrid', $vagas[2]->workplaceType);
    }

    /** Resposta real da API da Himalayas (reduzida), com salários de teste. */
    public function test_himalayas(): void
    {
        $vagas = (new HimalayasCollector)->parse(json_decode($this->fixture('himalayas.json'), true));

        $this->assertCount(2, $vagas, 'item sem guid deve ser ignorado');
        $this->assertSame('Senior PHP Engineer', $vagas[0]->title);
        $this->assertSame('Lumiere Systems', $vagas[0]->company);
        $this->assertSame('remoto', $vagas[0]->workplaceType);
        $this->assertSame('Brazil', $vagas[0]->location);
        $this->assertSame(1793134105, $vagas[0]->publishedAt?->getTimestamp());
        $this->assertSame(['min' => 5000, 'max' => 7000, 'moeda' => 'USD'], $vagas[0]->salary, 'anual vira mensal');
        $this->assertNull($vagas[1]->salary, 'valor por hora fica de fora');
        $this->assertStringStartsWith('https://himalayas.app/', $vagas[0]->url);
    }

    public function test_github(): void
    {
        $vagas = (new GithubIssuesCollector)->parse(json_decode($this->fixture('github.json'), true), 'backend-br/vagas');

        $this->assertCount(3, $vagas, 'pull requests devem ser ignorados');
        $this->assertSame('backend-br/vagas#12001', $vagas[0]->externalId);
        $this->assertSame('Delta Software', $vagas[0]->company);
        $this->assertStringContainsString('Remoto', $vagas[0]->title);
        $this->assertContains('PHP', $vagas[0]->tags);
        $this->assertSame('São Paulo', $vagas[1]->location);
    }

    public function test_programathor(): void
    {
        $vagas = (new ProgramathorCollector)->parse($this->fixture('programathor.html'));

        $this->assertCount(3, $vagas, 'links repetidos contam uma vez e vencidas ficam de fora');
        $this->assertSame('Desenvolvedor PHP Júnior', $vagas[0]->title, 'selo NOVA sai do título');
        $this->assertSame('Zeta Sistemas', $vagas[0]->company);
        $this->assertSame('remoto', $vagas[0]->workplaceType);
        $this->assertSame('https://programathor.com.br/jobs/30123-desenvolvedor-php-junior', $vagas[0]->url);
        $this->assertNull($vagas[0]->publishedAt);

        $this->assertSame('Dev PHP Pleno', $vagas[2]->title, 'selo de presencial sai do título');
        $this->assertSame('presencial', $vagas[2]->workplaceType);
        $this->assertSame(['PHP', 'Symfony'], $vagas[2]->tags);
        $this->assertSame(['min' => 4000, 'max' => 4000, 'moeda' => 'BRL'], $vagas[0]->salary, 'salário do ícone de dinheiro');
        $this->assertNotContains('30100', array_map(fn ($v) => $v->externalId, $vagas));
    }

    public function test_programathor_detalhe(): void
    {
        $coletor = new ProgramathorCollector;
        $vaga = $coletor->comDetalhes($coletor->parse($this->fixture('programathor.html'))[0], $this->fixture('programathor-detalhe.html'));

        $this->assertSame('2026-10-27T00:00:00-03:00', $vaga->publishedAt?->format(DATE_ATOM));
        $this->assertStringContainsString('Laravel', $vaga->description);
        $this->assertStringNotContainsString('<p>', $vaga->description);
        $this->assertSame('Desenvolvedor PHP Júnior', $vaga->title);

        $semJsonLd = $coletor->comDetalhes($vaga, '<html></html>');
        $this->assertNull($semJsonLd->publishedAt, 'sem JobPosting a data fica em branco');
    }

    public function test_linkedin(): void
    {
        $vagas = (new LinkedInCollector)->parse($this->fixture('linkedin.html'));

        $this->assertCount(2, $vagas);
        $this->assertSame('4100000001', $vagas[0]->externalId);
        $this->assertSame('Theta Bank', $vagas[0]->company);
        $this->assertSame('https://br.linkedin.com/jobs/view/desenvolvedor-php-4100000001', $vagas[0]->url);
    }

    public function test_remotar(): void
    {
        $vagas = (new RemotarCollector)->parse(json_decode($this->fixture('remotar.json'), true));

        $this->assertCount(1, $vagas);
        $this->assertSame('Kappa Digital', $vagas[0]->company);
        $this->assertSame(['PHP', 'Laravel'], $vagas[0]->tags);
        $this->assertSame('https://remotar.com.br/job/77001/pessoa-desenvolvedora-full-stack-php', $vagas[0]->url);
    }
}
