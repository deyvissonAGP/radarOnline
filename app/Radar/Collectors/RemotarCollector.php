<?php

namespace App\Radar\Collectors;

use App\Radar\JobData;
use App\Radar\Text;
use DateTimeImmutable;
use RuntimeException;

/**
 * Remotar (experimental): vagas remotas brasileiras.
 * O formato da resposta não é documentado publicamente; a leitura abaixo é
 * tolerante a variações. Se nada vier, rode `php artisan vagas:coletar --fonte=remotar -v`
 * e ajuste o endpoint em config/vagas.php.
 */
final class RemotarCollector extends BaseCollector
{
    public function key(): string
    {
        return 'remotar';
    }

    public function label(): string
    {
        return 'Remotar';
    }

    public function collect(): array
    {
        $endpoint = $this->config['endpoint'] ?? 'https://api.remotar.com.br/jobs';
        $corte = new DateTimeImmutable('-'.(int) ($this->config['janela_horas'] ?? 72).' hours');
        $vagas = [];

        // O feed vem da mais nova para a mais antiga (com algumas fixadas no topo):
        // lê até uma página inteira ficar fora da janela.
        for ($pagina = 1; $pagina <= (int) ($this->config['paginas'] ?? 12); $pagina++) {
            $resposta = $this->client()->acceptJson()->get($endpoint, ['page' => $pagina]);
            if (! $resposta->successful()) {
                if ($pagina === 1) {
                    throw new RuntimeException("Remotar respondeu HTTP {$resposta->status()} em {$endpoint}");
                }
                break;
            }

            $daPagina = $this->parse($resposta->json() ?? []);
            foreach ($daPagina as $vaga) {
                $vagas[$vaga->externalId] = $vaga;
            }

            $naJanela = array_filter($daPagina, fn (JobData $v) => ! $v->publishedAt || $v->publishedAt >= $corte);
            if (! $naJanela) {
                break;
            }
        }

        return array_values($vagas);
    }

    /** @return JobData[] */
    public function parse(array $json): array
    {
        $itens = $json['data'] ?? $json['jobs'] ?? $json['results'] ?? (array_is_list($json) ? $json : []);
        $vagas = [];

        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = self::str($item['id'] ?? $item['_id'] ?? null);
            $titulo = self::str($item['title'] ?? $item['name'] ?? null);
            if (! $id || ! $titulo) {
                continue;
            }

            $empresa = $item['company'] ?? null;
            $empresa = is_array($empresa) ? self::str($empresa['name'] ?? null) : self::str($empresa ?? $item['companyName'] ?? null);
            $slug = self::str($item['slug'] ?? null);

            $vagas[] = new JobData(
                source: $this->key(),
                externalId: $id,
                title: $titulo,
                url: self::str($item['url'] ?? null)
                    ?? 'https://remotar.com.br/job/'.$id.($slug ? '/'.$slug : ''),
                company: $empresa,
                location: 'Remoto',
                workplaceType: 'remote',
                publishedAt: self::date($item['createdAt'] ?? $item['publishedAt'] ?? $item['created_at'] ?? null),
                description: Text::stripHtml($item['description'] ?? $item['subtitle'] ?? null),
                tags: array_values(array_filter(array_map(
                    fn ($t) => is_array($t) ? self::str($t['name'] ?? null) : self::str($t),
                    (array) ($item['tags'] ?? $item['jobTags'] ?? []),
                ))),
            );
        }

        return $vagas;
    }
}
