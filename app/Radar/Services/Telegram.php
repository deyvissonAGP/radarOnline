<?php

namespace App\Radar\Services;

use App\Models\Vaga;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class Telegram
{
    public function configurado(): bool
    {
        return filled(config('vagas.telegram.token')) && filled(config('vagas.telegram.chat_id'));
    }

    public function enviar(string $html): void
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Defina TELEGRAM_BOT_TOKEN e TELEGRAM_CHAT_ID no .env');
        }

        $resposta = Http::timeout(15)->post(
            'https://api.telegram.org/bot'.config('vagas.telegram.token').'/sendMessage',
            [
                'chat_id' => config('vagas.telegram.chat_id'),
                'text' => $html,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ],
        );

        if (! $resposta->successful()) {
            throw new RuntimeException('Telegram recusou a mensagem: '.$resposta->body());
        }
    }

    public function formatarVaga(Vaga $vaga): string
    {
        $e = fn (?string $t) => htmlspecialchars((string) $t, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $detalhes = array_filter([
            $vaga->empresa ? '🏢 '.$e($vaga->empresa) : null,
            $vaga->modelo ? '📍 '.$e(ucfirst($vaga->modelo).($vaga->localizacao && $vaga->modelo !== 'remoto' ? " · {$vaga->localizacao}" : '')) : ($vaga->localizacao ? '📍 '.$e($vaga->localizacao) : null),
            $vaga->senioridade ? '🎯 '.$e(ucfirst($vaga->senioridade)) : null,
            $vaga->contrato ? '📄 '.$e(strtoupper($vaga->contrato)) : null,
            $vaga->salarioTexto() ? '💰 '.$e($vaga->salarioTexto()) : null,
            $vaga->areaLabel() ? '🗂 '.$e($vaga->areaLabel()) : null,
            $vaga->tecnologias ? '🧩 '.$e(implode(', ', array_map('ucfirst', $vaga->tecnologias))) : null,
        ]);

        return '💼 <b><a href="'.$e($vaga->url).'">'.$e($vaga->titulo).'</a></b>'."\n"
            .implode("\n", $detalhes)."\n"
            .'<i>via '.$e($vaga->fonteLabel()).'</i>';
    }
}
