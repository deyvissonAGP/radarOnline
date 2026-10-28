<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vagas', function (Blueprint $table) {
            // Faixa salarial mensal, na moeda informada pela vaga.
            $table->unsignedInteger('salario_min')->nullable();
            $table->unsignedInteger('salario_max')->nullable();
            $table->string('salario_moeda', 3)->nullable();

            // Marcação pessoal: candidatei | descartada.
            $table->string('status', 20)->nullable()->index();
            $table->timestampTz('status_em')->nullable();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Tecnologias em jsonb com índice GIN: o filtro por tecnologia usa o índice.
        DB::statement('ALTER TABLE vagas ALTER COLUMN tecnologias TYPE jsonb USING tecnologias::jsonb');
        DB::statement('CREATE INDEX vagas_tecnologias_gin ON vagas USING gin (tecnologias)');

        // Busca em português que ignora acentos ("senior" acha "Sênior") e reduz
        // as palavras ao radical ("desenvolvedora" acha "desenvolvedor").
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement('DROP TEXT SEARCH CONFIGURATION IF EXISTS pt_unaccent');
        DB::statement('CREATE TEXT SEARCH CONFIGURATION pt_unaccent (COPY = portuguese)');
        DB::statement('ALTER TEXT SEARCH CONFIGURATION pt_unaccent ALTER MAPPING FOR hword, hword_part, word WITH unaccent, portuguese_stem');
        DB::statement(<<<'SQL'
            ALTER TABLE vagas ADD COLUMN busca tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('pt_unaccent'::regconfig, coalesce(titulo, '')), 'A') ||
                setweight(to_tsvector('pt_unaccent'::regconfig, coalesce(empresa, '')), 'B') ||
                setweight(to_tsvector('pt_unaccent'::regconfig, coalesce(descricao, '')), 'C')
            ) STORED
        SQL);
        DB::statement('CREATE INDEX vagas_busca_gin ON vagas USING gin (busca)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS vagas_busca_gin');
            DB::statement('ALTER TABLE vagas DROP COLUMN IF EXISTS busca');
            DB::statement('DROP TEXT SEARCH CONFIGURATION IF EXISTS pt_unaccent');
            DB::statement('DROP INDEX IF EXISTS vagas_tecnologias_gin');
            DB::statement('ALTER TABLE vagas ALTER COLUMN tecnologias TYPE json USING tecnologias::json');
        }

        Schema::table('vagas', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['salario_min', 'salario_max', 'salario_moeda', 'status', 'status_em']);
        });
    }
};
