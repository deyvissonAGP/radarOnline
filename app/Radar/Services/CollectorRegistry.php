<?php

namespace App\Radar\Services;

use App\Models\Vaga;
use App\Radar\Collectors\BaseCollector;
use App\Radar\Contracts\Collector;

/** Monta os coletores a partir de config/vagas.php. */
final class CollectorRegistry
{
    /** @return array<string, Collector> fonte => coletor */
    public function todos(bool $somenteAtivos = true): array
    {
        $coletores = [];

        foreach (config('vagas.fontes', []) as $chave => $cfg) {
            if ($somenteAtivos && ! filter_var($cfg['ativa'] ?? false, FILTER_VALIDATE_BOOL)) {
                continue;
            }

            $classe = $cfg['classe'];
            $coletores[$chave] = new $classe(
                config: $cfg + ['janela_horas' => config('vagas.janela_horas')],
                termos: config('vagas.termos', ['desenvolvedor']),
                http: config('vagas.http', []),
            );

            if ($coletores[$chave] instanceof BaseCollector) {
                $coletores[$chave]->usarVagasConhecidas(
                    fn (string $id) => Vaga::where('fonte', $chave)->where('id_externo', $id)->exists(),
                );
            }
        }

        return $coletores;
    }

    public function obter(string $fonte): ?Collector
    {
        return $this->todos(false)[$fonte] ?? null;
    }
}
