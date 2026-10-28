<?php

namespace App\Radar\Collectors;

use App\Radar\JobData;
use DateTimeImmutable;
use RuntimeException;

/**
 * Repositórios de vagas no GitHub, onde cada vaga é uma issue (ex.: backend-br/vagas).
 */
final class GithubIssuesCollector extends BaseCollector
{
    public function key(): string
    {
        return 'github';
    }

    public function label(): string
    {
        return 'GitHub';
    }

    public function collect(): array
    {
        $horas = (int) ($this->config['janela_horas'] ?? 72);
        $desde = (new DateTimeImmutable("-{$horas} hours"))->format('Y-m-d\TH:i:s\Z');
        $vagas = [];
        $falhas = [];

        foreach ((array) ($this->config['repositorios'] ?? []) as $repo) {
            $cliente = $this->client()->withHeaders(['Accept' => 'application/vnd.github+json']);
            if (! empty($this->config['token'])) {
                $cliente = $cliente->withToken($this->config['token']);
            }

            $resposta = $cliente->get("https://api.github.com/repos/{$repo}/issues", [
                'state' => 'open',
                'since' => $desde,
                'per_page' => 100,
                'sort' => 'created',
                'direction' => 'desc',
            ]);

            if (! $resposta->successful()) {
                $falhas[] = "{$repo} (HTTP {$resposta->status()})";

                continue;
            }

            array_push($vagas, ...$this->parse($resposta->json() ?? [], $repo));
        }

        if ($falhas && ! $vagas) {
            throw new RuntimeException('Falha ao ler: '.implode(', ', $falhas));
        }

        return $vagas;
    }

    /** @return JobData[] */
    public function parse(array $issues, string $repo): array
    {
        $vagas = [];

        foreach ($issues as $issue) {
            if (isset($issue['pull_request'])) {
                continue;
            }
            $titulo = self::str($issue['title'] ?? null);
            $url = self::str($issue['html_url'] ?? null);
            if (! $titulo || ! $url) {
                continue;
            }

            $labels = array_values(array_filter(array_map(
                fn ($l) => is_array($l) ? self::str($l['name'] ?? null) : self::str($l),
                (array) ($issue['labels'] ?? []),
            )));

            $vagas[] = new JobData(
                source: $this->key(),
                externalId: $repo.'#'.($issue['number'] ?? $issue['id'] ?? md5($url)),
                title: self::tituloLimpo($titulo),
                url: $url,
                company: self::empresa($titulo),
                location: self::local($titulo),
                workplaceType: null,
                publishedAt: self::date($issue['created_at'] ?? null),
                description: self::str($issue['body'] ?? null),
                tags: $labels,
            );
        }

        return $vagas;
    }

    /** "[Remoto] Dev PHP @ Empresa" → "Dev PHP" (as tags entre colchetes continuam no texto pelas labels). */
    private static function tituloLimpo(string $titulo): string
    {
        $limpo = preg_replace('/\s+(@|na|no|at)\s+[^@\[\]]+$/iu', '', $titulo) ?? $titulo;
        $limpo = trim((string) preg_replace('/\[[^\]]*\]/u', '', $limpo));

        return $limpo !== '' ? $limpo.self::marcadores($titulo) : $titulo;
    }

    /** Mantém [Remoto], [Híbrido] etc. no fim do título para o classificador enxergar. */
    private static function marcadores(string $titulo): string
    {
        preg_match_all('/\[([^\]]+)\]/u', $titulo, $m);

        return $m[1] ? ' ('.implode(', ', $m[1]).')' : '';
    }

    private static function empresa(string $titulo): ?string
    {
        if (preg_match('/\s@\s*([^@\[\]]+?)\s*(\[|$)/u', $titulo, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\s(?:na|no|at)\s+([A-Z][^@\[\]]+?)\s*$/u', $titulo, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private static function local(string $titulo): ?string
    {
        preg_match_all('/\[([^\]]+)\]/u', $titulo, $m);
        foreach ($m[1] as $marcador) {
            if (! preg_match('/^(remoto|remote|h[íi]brido|hybrid|presencial|clt|pj|junior|j[úu]nior|pleno|s[êe]nior|est[áa]gio)$/iu', trim($marcador))) {
                return trim($marcador);
            }
        }

        return null;
    }
}
