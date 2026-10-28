<?php

namespace App\Radar\Collectors;

use App\Radar\JobData;
use App\Radar\Text;
use DateTimeImmutable;
use RuntimeException;

/**
 * Gupy: lê a página de busca do portal.gupy.io. A antiga API pública
 * (portal.api.gupy.io) saiu do ar; a página traz as vagas no JSON do Next.js
 * (__NEXT_DATA__), no mesmo formato que a API devolvia, 12 por página e da mais nova para a mais antiga.
 */
final class GupyCollector extends BaseCollector
{
    private const BASE = 'https://portal.gupy.io/job-search/term=';

    private const POR_PAGINA = 12;

    public function key(): string
    {
        return 'gupy';
    }

    public function label(): string
    {
        return 'Gupy';
    }

    public function collect(): array
    {
        $vagas = [];
        $erros = 0;
        $maxPaginas = (int) ($this->config['paginas'] ?? 5);
        $corte = new DateTimeImmutable('-'.(int) ($this->config['janela_horas'] ?? 72).' hours');

        foreach ($this->termos as $termo) {
            for ($pagina = 1; $pagina <= $maxPaginas; $pagina++) {
                $url = self::BASE.rawurlencode($termo).($pagina > 1 ? "&page={$pagina}" : '');
                $resposta = $this->client()->get($url);
                $lista = $resposta->successful() ? $this->extrairLista($resposta->body()) : null;

                if ($lista === null) {
                    // Só conta como erro se nem a primeira página do termo veio.
                    $erros += $pagina === 1 ? 1 : 0;
                    break;
                }

                $daPagina = $this->parse($lista);
                foreach ($daPagina as $vaga) {
                    $vagas[$vaga->externalId] = $vaga;
                }

                // A lista vem da mais nova para a mais antiga: para quando a página
                // acaba ou quando já passou da janela de horas.
                $maisAntiga = end($daPagina) ?: null;
                if (count($lista['data'] ?? []) < self::POR_PAGINA || ($maisAntiga?->publishedAt && $maisAntiga->publishedAt < $corte)) {
                    break;
                }
            }
        }

        if ($erros === count($this->termos)) {
            throw new RuntimeException('Gupy não respondeu para nenhum termo de busca.');
        }

        return array_values($vagas);
    }

    /** Tira a lista de vagas ({data, pagination}) do JSON embutido na página. */
    public function extrairLista(string $html): ?array
    {
        if (! preg_match('~<script id="__NEXT_DATA__"[^>]*>(.*?)</script>~s', $html, $m)) {
            return null;
        }
        $lista = json_decode($m[1], true)['props']['pageProps']['initialJobList'] ?? null;

        return is_array($lista) ? $lista : null;
    }

    /** @return JobData[] */
    public function parse(array $json): array
    {
        $vagas = [];

        foreach ($json['data'] ?? [] as $item) {
            $id = self::str($item['id'] ?? null);
            $titulo = self::str($item['name'] ?? null);
            $url = self::str($item['jobUrl'] ?? null);
            if (! $id || ! $titulo || ! $url) {
                continue;
            }

            $local = implode(' - ', array_filter([self::str($item['city'] ?? null), self::str($item['state'] ?? null)]));
            $modelo = self::str($item['workplaceType'] ?? null);
            if (! $modelo && ($item['isRemoteWork'] ?? false) === true) {
                $modelo = 'remote';
            }

            $vagas[] = new JobData(
                source: $this->key(),
                externalId: $id,
                title: $titulo,
                url: $url,
                company: self::str($item['careerPageName'] ?? $item['careerPageDisplayName'] ?? null),
                location: $local !== '' ? $local : null,
                workplaceType: $modelo,
                publishedAt: self::date($item['publishedDate'] ?? $item['publishedAt'] ?? null),
                description: Text::stripHtml($item['description'] ?? null),
                tags: array_values(array_filter(array_map(
                    fn ($s) => is_array($s) ? self::str($s['name'] ?? null) : self::str($s),
                    (array) ($item['skills'] ?? []),
                ))),
            );
        }

        return $vagas;
    }
}
