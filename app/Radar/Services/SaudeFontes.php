<?php

namespace App\Radar\Services;

use App\Models\Coleta;
use Illuminate\Support\Carbon;

/**
 * Situação de cada fonte a partir do histórico de coletas.
 * Conta como falha a coleta que deu erro ou que não encontrou nada:
 * uma fonte que muda de formato costuma "funcionar" e voltar vazia.
 */
final class SaudeFontes
{
    public function __construct(private readonly CollectorRegistry $registry) {}

    /** @return array<string, array{nome: string, ok: ?bool, ultima: ?Carbon, ultimo_ok: ?Carbon, falhas_seguidas: int, erro: ?string}> */
    public function situacao(): array
    {
        $situacao = [];
        foreach ($this->registry->todos() as $chave => $coletor) {
            $situacao[$chave] = ['nome' => $coletor->label()] + $this->daFonte($chave);
        }

        return $situacao;
    }

    /** @return array{ok: ?bool, ultima: ?Carbon, ultimo_ok: ?Carbon, falhas_seguidas: int, erro: ?string} */
    public function daFonte(string $fonte): array
    {
        $coletas = Coleta::where('fonte', $fonte)->latest('executada_em')->latest('id')->limit(20)->get();

        $falhas = 0;
        foreach ($coletas as $coleta) {
            if (! $this->falhou($coleta)) {
                break;
            }
            $falhas++;
        }

        $ultima = $coletas->first();
        $ultimoOk = Coleta::where('fonte', $fonte)->where('sucesso', true)->where('encontradas', '>', 0)->max('executada_em');

        return [
            'ok' => $ultima ? ! $this->falhou($ultima) : null,
            'ultima' => $ultima?->executada_em,
            'ultimo_ok' => $ultimoOk ? Carbon::parse($ultimoOk) : null,
            'falhas_seguidas' => $falhas,
            'erro' => $ultima && $this->falhou($ultima) ? ($ultima->erro ?: 'nenhuma vaga encontrada') : null,
        ];
    }

    private function falhou(Coleta $coleta): bool
    {
        return ! $coleta->sucesso || $coleta->encontradas === 0;
    }
}
