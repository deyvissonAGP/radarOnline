<?php

namespace App\Console\Commands;

use App\Radar\Services\Telegram;
use Illuminate\Console\Command;
use Throwable;

class TestarTelegram extends Command
{
    protected $signature = 'vagas:testar-telegram';

    protected $description = 'Envia uma mensagem de teste para o Telegram configurado';

    public function handle(Telegram $telegram): int
    {
        if (! $telegram->configurado()) {
            $this->error('Telegram não configurado: defina TELEGRAM_BOT_TOKEN e TELEGRAM_CHAT_ID no .env.');

            return self::FAILURE;
        }

        try {
            $telegram->enviar('✅ Teste do Radar de vagas: o Telegram está funcionando.');
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Mensagem de teste enviada.');

        return self::SUCCESS;
    }
}
