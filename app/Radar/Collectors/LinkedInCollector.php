<?php

namespace App\Radar\Collectors;

use App\Radar\JobData;
use RuntimeException;

/**
 * LinkedIn (experimental, DESLIGADO por padrão).
 *
 * Lê a listagem pública de vagas que o LinkedIn mostra para quem não está logado.
 * Os Termos de Uso do LinkedIn proíbem coleta automatizada, e o site costuma
 * bloquear IPs que fazem muitas requisições. Use por sua conta e risco, com
 * intervalos longos, e nunca com a sua conta logada.
 */
final class LinkedInCollector extends BaseCollector
{
    private const ENDPOINT = 'https://www.linkedin.com/jobs-guest/jobs/api/seeMoreJobPostings/search';

    public function key(): string
    {
        return 'linkedin';
    }

    public function label(): string
    {
        return 'LinkedIn';
    }

    public function collect(): array
    {
        $horas = (int) ($this->config['janela_horas'] ?? 72);
        $vagas = [];

        foreach ($this->termos as $termo) {
            $resposta = $this->client()->get(self::ENDPOINT, [
                'keywords' => $termo,
                'location' => $this->config['localizacao'] ?? 'Brasil',
                'f_TPR' => 'r'.($horas * 3600),
                'start' => 0,
            ]);

            if ($resposta->status() === 429) {
                throw new RuntimeException('LinkedIn limitou as requisições (HTTP 429). Aumente o intervalo.');
            }
            if (! $resposta->successful()) {
                continue;
            }

            foreach ($this->parse($resposta->body()) as $vaga) {
                $vagas[$vaga->externalId] = $vaga;
            }
            usleep(1_500_000);
        }

        return array_values($vagas);
    }

    /** @return JobData[] */
    public function parse(string $html): array
    {
        $xp = Dom::xpath($html);
        $vagas = [];

        foreach ($xp->query('//div[@data-entity-urn] | //li[.//*[contains(@class, "base-search-card__title")]]') ?: [] as $card) {
            $urn = $card->attributes?->getNamedItem('data-entity-urn')?->nodeValue
                ?? Dom::attr($xp, './/*[@data-entity-urn]', 'data-entity-urn', $card);
            $titulo = Dom::text($xp, './/*['.Dom::cls('base-search-card__title').']', $card);
            $url = Dom::attr($xp, './/a['.Dom::cls('base-card__full-link').']', 'href', $card);
            if (! $urn || ! $titulo || ! $url || ! preg_match('/(\d+)$/', $urn, $m) || isset($vagas[$m[1]])) {
                continue;
            }

            $local = Dom::text($xp, './/*['.Dom::cls('job-search-card__location').']', $card);

            $vagas[$m[1]] = new JobData(
                source: $this->key(),
                externalId: $m[1],
                title: $titulo,
                url: strtok($url, '?') ?: $url,
                company: Dom::text($xp, './/*['.Dom::cls('base-search-card__subtitle').']', $card),
                location: $local,
                workplaceType: null,
                publishedAt: self::date(Dom::attr($xp, './/time', 'datetime', $card)),
                description: null,
            );
        }

        return array_values($vagas);
    }
}
