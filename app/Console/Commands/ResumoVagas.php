<?php

namespace App\Console\Commands;

use App\Radar\Services\Resumo;
use App\Radar\Services\Telegram;
use Illuminate\Console\Command;

class ResumoVagas extends Command
{
    protected $signature = 'vagas:resumo {--telegram : Também envia o resumo para o Telegram}';

    protected $description = 'Mostra o resumo do dia (números prontos para postar)';

    public function handle(Resumo $resumo, Telegram $telegram): int
    {
        $texto = $resumo->texto();
        $this->line($texto);

        if ($this->option('telegram')) {
            if (! $telegram->configurado()) {
                $this->warn('Telegram não configurado; resumo não enviado.');

                return self::SUCCESS;
            }
            $telegram->enviar(htmlspecialchars($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $this->info('Resumo enviado para o Telegram.');
        }

        return self::SUCCESS;
    }
}
