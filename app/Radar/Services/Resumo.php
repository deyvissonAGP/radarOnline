<?php

namespace App\Radar\Services;

/** Monta o texto do resumo diário, pronto para colar no LinkedIn ou mandar no Telegram. */
final class Resumo
{
    public function __construct(
        private readonly Estatisticas $estatisticas,
        private readonly CollectorRegistry $registry,
    ) {}

    public function texto(): string
    {
        $e = $this->estatisticas->gerar();
        $horas = (int) config('vagas.janela_horas', 72);
        $data = now()->timezone(config('vagas.fuso'))->format('d/m');

        $linhas = [
            "Radar de vagas tech 📡 — {$data}",
            '',
            "{$e['abertas']} vagas abertas (publicadas nas últimas {$horas}h)",
            "{$e['novas_24h']} novas nas últimas 24 horas",
            "{$e['remotas']} remotas",
            "{$e['empresas']} empresas contratando",
        ];

        if ($e['por_tecnologia']) {
            $top = array_slice($e['por_tecnologia'], 0, 5, true);
            $linhas[] = '';
            $linhas[] = 'Mais pedidos: '.implode(', ', array_map(
                fn ($tec, $n) => ucfirst($tec)." ({$n})",
                array_keys($top),
                $top,
            ));
        }

        $fontes = array_map(fn ($c) => $c->label(), $this->registry->todos());
        $linhas[] = '';
        $linhas[] = 'Fontes: '.implode(', ', $fontes).'.';
        $linhas[] = "{$e['rejeitadas']} itens descartados pelo filtro (cursos, grupos, propaganda). Regras públicas em ".url('/regras');
        $linhas[] = '';
        $linhas[] = 'Lista completa: '.url('/');

        return implode("\n", $linhas);
    }
}
