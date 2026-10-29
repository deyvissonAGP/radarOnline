<?php

use App\Radar\Rotinas;
use Illuminate\Support\Facades\Schedule;

// As rotinas automáticas passam pelo mesmo registro dos botões da página /rotinas,
// então aparecem no histórico de lá. Se a mesma rotina já estiver rodando
// (pedida pela página), a rodada agendada é pulada.

// Coleta a cada 3 horas e avisa no Telegram logo em seguida.
Schedule::call(function () {
    $coleta = Rotinas::executarAgora('coletar');
    if ($coleta?->status === 'concluida') {
        Rotinas::executarAgora('notificar');
    }
})->name('coletar-e-notificar')->everyThreeHours()->withoutOverlapping(30);

// Resumo diário às 8h (horário de Brasília).
Schedule::call(fn () => Rotinas::executarAgora('resumo'))
    ->name('resumo-diario')
    ->dailyAt('08:00')
    ->timezone('America/Sao_Paulo');
