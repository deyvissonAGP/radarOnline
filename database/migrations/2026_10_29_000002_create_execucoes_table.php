<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Histórico das rotinas (coletar, enviar no Telegram...), pedidas pela página ou pelo agendador.
        Schema::create('execucoes', function (Blueprint $table) {
            $table->id();
            $table->string('rotina', 40)->index();
            $table->json('parametros')->nullable();
            $table->string('origem', 20);                 // web | agendada
            $table->string('status', 20)->index();        // na_fila | rodando | concluida | falhou
            $table->text('saida')->nullable();
            $table->timestampTz('pedida_em');
            $table->timestampTz('iniciada_em')->nullable();
            $table->timestampTz('terminada_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execucoes');
    }
};
