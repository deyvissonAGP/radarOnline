<?php

namespace App\Jobs;

use App\Models\Execucao;
use App\Radar\Rotinas;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Roda, no worker da fila, uma rotina pedida pela página /rotinas. */
class ExecutarRotina implements ShouldQueue
{
    use Queueable;

    /** Uma tentativa só: repetir uma coleta ou um envio no Telegram por conta própria não é seguro. */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $execucaoId) {}

    public function handle(): void
    {
        $execucao = Execucao::find($this->execucaoId);
        if ($execucao && $execucao->status === 'na_fila') {
            Rotinas::executar($execucao);
        }
    }

    public function failed(?\Throwable $erro): void
    {
        Execucao::whereKey($this->execucaoId)->update([
            'status' => 'falhou',
            'saida' => 'O worker interrompeu a rotina: '.($erro?->getMessage() ?? 'erro desconhecido'),
            'terminada_em' => now(),
        ]);
    }
}
