<?php

namespace App\Console\Commands;

use App\Radar\Services\CollectorRegistry;
use App\Radar\Services\ColetaService;
use App\Radar\Services\SaudeFontes;
use App\Radar\Services\Telegram;
use Illuminate\Console\Command;
use Throwable;

class ColetarVagas extends Command
{
    protected $signature = 'vagas:coletar
        {--fonte=* : Coletar só destas fontes (ex.: --fonte=gupy --fonte=github)}';

    protected $description = 'Busca vagas de tecnologia em todas as fontes ativas e grava no banco';

    public function handle(CollectorRegistry $registry, ColetaService $coleta, SaudeFontes $saude, Telegram $telegram): int
    {
        $filtro = $this->option('fonte');
        $coletores = $filtro
            ? array_filter(array_map(fn ($f) => $registry->obter($f), array_combine($filtro, $filtro)))
            : $registry->todos();

        if (! $coletores) {
            $this->error('Nenhuma fonte ativa. Confira config/vagas.php e o .env.');

            return self::FAILURE;
        }

        $linhas = [];
        foreach ($coletores as $chave => $coletor) {
            $this->line("→ {$coletor->label()}...");
            $r = $coleta->executar($chave, $coletor);
            $linhas[] = [
                $coletor->label(),
                $r['sucesso'] ? '<info>ok</info>' : '<error>falhou</error>',
                $r['encontradas'],
                $r['novas'],
                $r['aceitas'],
                $r['erro'] ? mb_strimwidth($r['erro'], 0, 70, '…') : '',
            ];
        }

        $this->table(['Fonte', 'Status', 'Encontradas', 'Novas', 'Novas aceitas', 'Erro'], $linhas);

        $this->alertarFalhas($coletores, $saude, $telegram);

        $apagadas = $coleta->limpar();
        if ($apagadas) {
            $this->line("{$apagadas} vagas antigas removidas.");
        }

        return self::SUCCESS;
    }

    /**
     * Avisa uma única vez, quando a fonte atinge o limite de falhas seguidas
     * (erro ou zero vagas). Se continuar falhando, não repete a cada rodada.
     *
     * @param  array<string, \App\Radar\Contracts\Collector>  $coletores
     */
    private function alertarFalhas(array $coletores, SaudeFontes $saude, Telegram $telegram): void
    {
        $limite = max(1, (int) config('vagas.alerta_falhas_seguidas', 2));

        foreach ($coletores as $chave => $coletor) {
            $situacao = $saude->daFonte($chave);
            if ($situacao['falhas_seguidas'] !== $limite) {
                continue;
            }

            $mensagem = "⚠️ A fonte {$coletor->label()} falhou nas últimas {$limite} coletas: {$situacao['erro']}";
            $this->warn($mensagem);

            if ($telegram->configurado()) {
                try {
                    $telegram->enviar(htmlspecialchars($mensagem, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                } catch (Throwable $e) {
                    $this->error('Não consegui avisar no Telegram: '.$e->getMessage());
                }
            }
        }
    }
}
