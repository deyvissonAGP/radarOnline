<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vagas', function (Blueprint $table) {
            $table->id();
            $table->string('fonte', 40);
            $table->string('id_externo', 191);
            $table->string('titulo', 500);
            $table->string('empresa')->nullable();
            $table->text('url');
            $table->string('localizacao')->nullable();
            $table->string('modelo', 20)->nullable();       // remoto | hibrido | presencial
            $table->string('senioridade', 20)->nullable();
            $table->string('contrato', 20)->nullable();
            $table->json('tecnologias')->nullable();
            $table->text('descricao')->nullable();

            $table->boolean('aceita')->default(false);
            $table->string('motivo_rejeicao')->nullable();
            $table->string('impressao_digital', 40)->index();
            $table->foreignId('duplicada_de')->nullable()->constrained('vagas')->nullOnDelete();

            $table->timestampTz('publicada_em')->index();
            $table->timestampTz('vista_primeira_vez_em');
            $table->timestampTz('vista_ultima_vez_em');
            $table->timestampTz('notificada_em')->nullable();
            $table->timestamps();

            $table->unique(['fonte', 'id_externo']);
        });

        Schema::create('coletas', function (Blueprint $table) {
            $table->id();
            $table->string('fonte', 40);
            $table->boolean('sucesso');
            $table->unsignedInteger('encontradas')->default(0);
            $table->unsignedInteger('novas')->default(0);
            $table->unsignedInteger('aceitas')->default(0);
            $table->text('erro')->nullable();
            $table->timestampTz('executada_em')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coletas');
        Schema::dropIfExists('vagas');
    }
};
