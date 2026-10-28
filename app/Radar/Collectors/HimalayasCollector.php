<?php

namespace App\Radar\Collectors;

use App\Radar\JobData;
use App\Radar\Text;
use DateTimeImmutable;
use RuntimeException;

/**
 * Himalayas: API pública e gratuita de vagas remotas (himalayas.app/api).
 * Busca só vagas que aceitam quem mora no Brasil (country=Brazil), da mais nova
 * para a mais antiga (sort=recent), até uma página inteira ficar fora da janela.
 * Sem termo de busca: o filtro do Radar separa o que é vaga de tecnologia.
 * Termos da API: link para a vaga original (applicationLink) e crédito à Himalayas.
 */
final class HimalayasCollector extends BaseCollector
{
    private const ENDPOINT = 'https://himalayas.app/jobs/api/search';

    private const POR_PAGINA = 20;

    public function key(): string
    {
        return 'himalayas';
    }

    public function label(): string
    {
        return 'Himalayas';
    }

    public function collect(): array
    {
        $corte = new DateTimeImmutable('-'.(int) ($this->config['janela_horas'] ?? 72).' hours');
        $vagas = [];

        for ($pagina = 1; $pagina <= (int) ($this->config['paginas'] ?? 25); $pagina++) {
            $resposta = $this->client()->acceptJson()->get(self::ENDPOINT, [
                'country' => $this->config['pais'] ?? 'Brazil',
                'sort' => 'recent',
                'page' => $pagina,
            ]);

            if (! $resposta->successful()) {
                if ($pagina === 1) {
                    throw new RuntimeException("Himalayas respondeu HTTP {$resposta->status()}");
                }
                break;
            }

            $json = $resposta->json() ?? [];
            $daPagina = $this->parse($json);
            foreach ($daPagina as $vaga) {
                $vagas[$vaga->externalId] = $vaga;
            }

            // As primeiras posições às vezes trazem vagas em destaque, mais antigas;
            // só para quando a página inteira estiver fora da janela.
            $naJanela = array_filter($daPagina, fn (JobData $v) => ! $v->publishedAt || $v->publishedAt >= $corte);
            if (count($json['jobs'] ?? []) < self::POR_PAGINA || ! $naJanela) {
                break;
            }
        }

        return array_values($vagas);
    }

    /** @return JobData[] */
    public function parse(array $json): array
    {
        $vagas = [];

        foreach ($json['jobs'] ?? [] as $item) {
            $id = self::str($item['guid'] ?? null);
            $titulo = self::str($item['title'] ?? null);
            $url = self::str($item['applicationLink'] ?? null) ?? $id;
            if (! $id || ! $titulo || ! $url) {
                continue;
            }

            $paises = array_values(array_filter(array_map(fn ($p) => self::str($p), (array) ($item['locationRestrictions'] ?? []))));
            $publicada = is_numeric($item['pubDate'] ?? null)
                ? (new DateTimeImmutable)->setTimestamp((int) $item['pubDate'])
                : self::date($item['pubDate'] ?? null);

            $vagas[] = new JobData(
                source: $this->key(),
                externalId: $id,
                title: $titulo,
                url: $url,
                company: self::str($item['companyName'] ?? null),
                location: $paises ? implode(', ', array_slice($paises, 0, 5)).(count($paises) > 5 ? '…' : '') : 'Qualquer país',
                workplaceType: 'remoto',
                publishedAt: $publicada,
                description: Text::stripHtml($item['description'] ?? $item['excerpt'] ?? null),
                tags: array_values(array_filter(array_map(fn ($s) => self::str($s), (array) ($item['seniority'] ?? [])))),
                salary: $this->salario($item),
            );
        }

        return $vagas;
    }

    /** Converte a faixa da API para valor mensal. Valores por hora ficam de fora. */
    private function salario(array $item): ?array
    {
        $min = is_numeric($item['minSalary'] ?? null) ? (float) $item['minSalary'] : null;
        $max = is_numeric($item['maxSalary'] ?? null) ? (float) $item['maxSalary'] : null;
        $moeda = self::str($item['currency'] ?? null);
        if (($min === null && $max === null) || ! $moeda) {
            return null;
        }

        $divisor = match (mb_strtolower((string) ($item['salaryPeriod'] ?? 'annual'))) {
            'annual', 'yearly', 'year' => 12,
            'monthly', 'month' => 1,
            default => null,
        };
        if ($divisor === null) {
            return null;
        }

        return [
            'min' => (int) round(($min ?? $max) / $divisor),
            'max' => (int) round(($max ?? $min) / $divisor),
            'moeda' => mb_strtoupper(mb_substr($moeda, 0, 3)),
        ];
    }
}
