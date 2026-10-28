<?php

namespace App\Console\Commands;

use App\Models\Vaga;
use App\Radar\Services\Telegram;
use Illuminate\Console\Command;
use Throwable;

class NotificarVagas extends Command
{
    protected $signature = 'vagas:notificar
        {--simular : Mostra o que seria enviado, sem enviar}
        {--marcar-enviadas : Marca todas as vagas atuais como avisadas, sem enviar (use antes do primeiro envio)}';

    protected $description = 'Envia para o Telegram as vagas novas que ainda não foram avisadas';

    public function handle(Telegram $telegram): int
    {
        if ($this->option('marcar-enviadas')) {
            $total = Vaga::whereNull('notificada_em')->update(['notificada_em' => now()]);
            $this->info("{$total} vaga(s) marcadas como avisadas. A partir de agora só chegam as novas.");

            return self::SUCCESS;
        }

        if (! $this->option('simular') && ! $telegram->configurado()) {
            $this->warn('Telegram não configurado (TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID). Nada enviado.');

            return self::SUCCESS;
        }

        $modelos = config('vagas.telegram.modelos', []);
        $niveis = config('vagas.telegram.niveis', []);
        $areas = config('vagas.telegram.areas', []);
        $tecnologias = config('vagas.telegram.tecnologias', []);

        $vagas = Vaga::abertas()
            ->whereNull('notificada_em')
            ->whereNull('status')
            // Filtros pessoais: quem não tem a informação passa, para não perder vaga boa.
            ->when($modelos, fn ($q) => $q->where(fn ($w) => $w->whereIn('modelo', $modelos)->orWhereNull('modelo')))
            ->when($niveis, fn ($q) => $q->where(fn ($w) => $w->whereIn('senioridade', $niveis)->orWhereNull('senioridade')))
            // Área e tecnologia são estritas: quem pede "php" quer só vagas que citam PHP.
            ->when($areas, fn ($q) => $q->whereIn('area', $areas))
            ->when($tecnologias, fn ($q) => $q->where(function ($w) use ($tecnologias) {
                foreach ($tecnologias as $tec) {
                    $w->orWhereJsonContains('tecnologias', $tec);
                }
            }))
            ->oldest('publicada_em')
            ->limit((int) config('vagas.telegram.max_por_rodada', 15))
            ->get();

        foreach ($vagas as $vaga) {
            $mensagem = $telegram->formatarVaga($vaga);

            if ($this->option('simular')) {
                $this->line($mensagem."\n");

                continue;
            }

            try {
                $telegram->enviar($mensagem);
                $vaga->update(['notificada_em' => now()]);
                usleep(1_100_000); // limite do Telegram: ~1 mensagem por segundo no mesmo chat
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info($vagas->count().' vaga(s) '.($this->option('simular') ? 'para enviar.' : 'enviada(s).'));

        return self::SUCCESS;
    }
}
