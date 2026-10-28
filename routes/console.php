<?php

use Illuminate\Support\Facades\Schedule;

// Coleta a cada 3 horas e avisa no Telegram logo em seguida.
Schedule::command('vagas:coletar')
    ->everyThreeHours()
    ->withoutOverlapping()
    ->onSuccess(fn () => Illuminate\Support\Facades\Artisan::call('vagas:notificar'));

// Resumo diário às 8h (horário de Brasília).
Schedule::command('vagas:resumo --telegram')
    ->dailyAt('08:00')
    ->timezone('America/Sao_Paulo');
