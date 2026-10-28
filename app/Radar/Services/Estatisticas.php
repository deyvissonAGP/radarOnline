<?php

namespace App\Radar\Services;

use App\Models\Coleta;
use App\Models\Vaga;
use Illuminate\Support\Collection;

final class Estatisticas
{
    /**
     * @return array{
     *   abertas: int, novas_24h: int, remotas: int, empresas: int, rejeitadas: int,
     *   por_tecnologia: array<string, int>, por_fonte: array<string, int>, por_area: array<string, int>,
     *   ultima_coleta: ?\Illuminate\Support\Carbon
     * }
     */
    public function gerar(): array
    {
        $abertas = Vaga::abertas()->get(['id', 'empresa', 'modelo', 'publicada_em', 'tecnologias', 'fonte', 'area']);

        return [
            'abertas' => $abertas->count(),
            'novas_24h' => $abertas->where('publicada_em', '>=', now()->subDay())->count(),
            'remotas' => $abertas->where('modelo', 'remoto')->count(),
            'empresas' => $abertas->pluck('empresa')->filter()->map(fn ($e) => mb_strtolower(trim($e)))->unique()->count(),
            'rejeitadas' => Vaga::where('aceita', false)
                ->where('publicada_em', '>=', now()->subHours((int) config('vagas.janela_horas', 72)))
                ->count(),
            'por_tecnologia' => $this->contarTecnologias($abertas),
            'por_fonte' => $abertas->countBy('fonte')->sortDesc()->all(),
            'por_area' => $abertas->whereNotNull('area')->countBy('area')->sortDesc()->all(),
            'ultima_coleta' => Coleta::latest('executada_em')->first()?->executada_em,
        ];
    }

    /** @return array<string, int> */
    private function contarTecnologias(Collection $vagas): array
    {
        return $vagas->flatMap(fn (Vaga $v) => $v->tecnologias ?: [])
            ->countBy()
            ->sortDesc()
            ->all();
    }
}
