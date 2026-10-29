<?php

namespace App\Console\Commands;

use App\Radar\Services\ColetaService;
use Illuminate\Console\Command;

class LimparVagas extends Command
{
    protected $signature = 'vagas:limpar';

    protected $description = 'Apaga vagas e registros de coleta mais antigos que VAGAS_MANTER_DIAS';

    public function handle(ColetaService $coleta): int
    {
        $apagadas = $coleta->limpar();
        $this->info("{$apagadas} vaga(s) antiga(s) removida(s).");

        return self::SUCCESS;
    }
}
