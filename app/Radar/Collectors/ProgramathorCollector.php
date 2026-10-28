<?php

namespace App\Radar\Collectors;

use App\Radar\JobData;
use App\Radar\Salario;
use App\Radar\Text;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Programathor (experimental): lê a listagem HTML geral de vagas, da mais nova para a mais antiga.
 * A listagem não mostra a data de publicação, então cada vaga nova é aberta
 * para ler o JobPosting (JSON-LD) da página de detalhe: data e descrição completa.
 * Vagas já gravadas não são abertas de novo. Se a página de detalhe falhar,
 * a vaga entra com a data em que o Radar a viu.
 */
final class ProgramathorCollector extends BaseCollector
{
    private const BASE = 'https://programathor.com.br';

    public function key(): string
    {
        return 'programathor';
    }

    public function label(): string
    {
        return 'Programathor';
    }

    public function collect(): array
    {
        $corte = new DateTimeImmutable('-'.(int) ($this->config['janela_horas'] ?? 72).' hours');
        $vagas = [];

        for ($pagina = 1; $pagina <= (int) ($this->config['paginas'] ?? 4); $pagina++) {
            $resposta = $this->client()->get(self::BASE.'/jobs'.($pagina > 1 ? "/page/{$pagina}" : ''));
            if (! $resposta->successful()) {
                if ($pagina === 1) {
                    throw new RuntimeException("Programathor respondeu HTTP {$resposta->status()}");
                }
                break;
            }

            $temNovaNaJanela = false;
            foreach ($this->parse($resposta->body()) as $vaga) {
                // Já gravada: não precisa abrir a página de detalhe de novo.
                if ($this->jaConhecida && ($this->jaConhecida)($vaga->externalId)) {
                    $vagas[] = $vaga;

                    continue;
                }

                $vaga = $this->detalhar($vaga);
                $vagas[] = $vaga;
                $temNovaNaJanela = $temNovaNaJanela || ! $vaga->publishedAt || $vaga->publishedAt >= $corte;
            }

            // A listagem vem da mais nova para a mais antiga: uma página só com vagas
            // conhecidas ou antigas quer dizer que o resto também é.
            if (! $temNovaNaJanela) {
                break;
            }
        }

        return $vagas;
    }

    private function detalhar(JobData $vaga): JobData
    {
        try {
            $detalhe = $this->client()->get($vaga->url);

            return $detalhe->successful() ? $this->comDetalhes($vaga, $detalhe->body()) : $vaga;
        } catch (Throwable) {
            return $vaga;
        }
    }

    /** @return JobData[] Vagas abertas da listagem (as marcadas como vencidas ficam de fora). */
    public function parse(string $html): array
    {
        $xp = Dom::xpath($html);
        $vagas = [];

        foreach ($xp->query('//a[starts-with(@href, "/jobs/")]') ?: [] as $link) {
            $href = $link->attributes?->getNamedItem('href')?->nodeValue ?? '';
            if (! preg_match('~^/jobs/(\d+)~', $href, $m) || isset($vagas[$m[1]])) {
                continue;
            }

            $h3 = $xp->query('.//h3', $link)?->item(0) ?? $xp->query('.//h2', $link)?->item(0);
            if (! $h3) {
                continue;
            }

            // Selos dentro do título: "NOVA", "Vencida", "📍 PRESENCIAL - SOMENTE PARA CANDIDATOS NO LOCAL".
            $selos = [];
            foreach (iterator_to_array($xp->query('.//span', $h3) ?: []) as $span) {
                $selos[] = Text::normalize($span->textContent);
                $span->parentNode?->removeChild($span);
            }
            $titulo = trim((string) preg_replace(['/\b(NOVA|Vencida|Expirada)\b/u', '/\s+/u'], ['', ' '], $h3->textContent));
            $selos = implode(' ', $selos).' '.Text::normalize($h3->textContent);

            if (! $titulo || preg_match('/\b(vencida|expirada)\b/u', $selos)) {
                continue;
            }

            $empresa = Dom::text($xp, './/i[contains(@class, "fa-briefcase")]/parent::*', $link);
            $local = Dom::text($xp, './/i[contains(@class, "fa-map-marker")]/parent::*', $link);
            $tags = array_values(array_filter(array_map(
                fn ($n) => self::str($n->textContent),
                iterator_to_array($xp->query('.//span[contains(@class, "tag-list")]', $link) ?: []),
            )));
            $texto = trim((string) preg_replace('/\s+/u', ' ', $link->textContent));
            $salario = Salario::extrair(Dom::text($xp, './/i[contains(@class, "fa-money")]/parent::*', $link));

            $modelo = match (true) {
                str_contains($selos, 'presencial') => 'presencial',
                str_contains(mb_strtolower((string) $local), 'remoto') => 'remoto',
                default => null,
            };

            $vagas[$m[1]] = new JobData(
                source: $this->key(),
                externalId: $m[1],
                title: $titulo,
                url: self::BASE.$href,
                company: $empresa,
                location: $local,
                workplaceType: $modelo,
                publishedAt: null,
                description: $texto,
                tags: $tags,
                salary: $salario,
            );
        }

        return array_values($vagas);
    }

    /** Completa a vaga com a data e a descrição do JobPosting da página de detalhe. */
    public function comDetalhes(JobData $vaga, string $html): JobData
    {
        $posting = null;
        if (preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $blocos)) {
            foreach ($blocos[1] as $bloco) {
                // O JSON do site às vezes traz quebras de linha cruas dentro das strings.
                $json = json_decode((string) preg_replace('/[\r\n\t]+/', ' ', $bloco), true);
                if (($json['@type'] ?? null) === 'JobPosting') {
                    $posting = $json;
                    break;
                }
            }
        }

        $data = self::str($posting['datePosted'] ?? null);
        if (! $data && preg_match('~"datePosted"\s*:\s*"([^"]+)"~', $html, $m)) {
            $data = $m[1];
        }

        $publicada = null;
        if ($data) {
            try {
                // "2026-10-27" sem hora: meia-noite no horário de Brasília.
                $publicada = new DateTimeImmutable($data, new DateTimeZone('America/Sao_Paulo'));
            } catch (Throwable) {
            }
        }

        $descricao = Text::stripHtml($posting['description'] ?? null);

        return new JobData(
            source: $vaga->source,
            externalId: $vaga->externalId,
            title: $vaga->title,
            url: $vaga->url,
            company: $vaga->company,
            location: $vaga->location,
            workplaceType: $vaga->workplaceType,
            publishedAt: $publicada,
            description: $descricao !== '' ? $descricao : $vaga->description,
            tags: $vaga->tags,
            salary: $vaga->salary,
        );
    }
}
