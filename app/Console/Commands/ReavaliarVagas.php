<?php

namespace App\Console\Commands;

use App\Radar\Services\ColetaService;
use Illuminate\Console\Command;

class ReavaliarVagas extends Command
{
    protected $signature = 'vagas:reavaliar';

    protected $description = 'Aplica de novo o filtro e a classificação às vagas já gravadas (use depois de mudar config/regras.php)';

    public function handle(ColetaService $coleta): int
    {
        $r = $coleta->reavaliar();

        $this->info("{$r['avaliadas']} vagas reavaliadas: {$r['aceitas_agora']} passaram a ser aceitas, {$r['rejeitadas_agora']} passaram a ser rejeitadas.");

        return self::SUCCESS;
    }
}
