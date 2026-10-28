<?php

namespace App\Radar\Services;

use App\Models\Coleta;
use App\Models\Vaga;
use App\Radar\Classifier;
use App\Radar\Contracts\Collector;
use App\Radar\Filter\JobFilter;
use App\Radar\JobData;
use App\Radar\Salario;
use App\Radar\Text;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ColetaService
{
    private JobFilter $filtro;

    private Classifier $classificador;

    public function __construct()
    {
        $regras = config('regras');
        $this->filtro = new JobFilter($regras, config('vagas.areas', []));
        $this->classificador = new Classifier($regras['tecnologias'] ?? []);
    }

    /**
     * Roda um coletor e grava o resultado.
     *
     * @return array{fonte: string, sucesso: bool, encontradas: int, novas: int, aceitas: int, erro: ?string}
     */
    public function executar(string $chave, Collector $coletor): array
    {
        $resumo = ['fonte' => $chave, 'sucesso' => true, 'encontradas' => 0, 'novas' => 0, 'aceitas' => 0, 'erro' => null];

        try {
            $vagas = $coletor->collect();
            $resumo['encontradas'] = count($vagas);

            foreach ($vagas as $vaga) {
                $gravada = $this->gravar($vaga);
                if ($gravada === null) {
                    continue;
                }
                if ($gravada->wasRecentlyCreated) {
                    $resumo['novas']++;
                    if ($gravada->aceita) {
                        $resumo['aceitas']++;
                    }
                }
            }
        } catch (Throwable $e) {
            $resumo['sucesso'] = false;
            $resumo['erro'] = mb_substr($e->getMessage(), 0, 500);
            Log::warning("Coleta {$chave} falhou", ['erro' => $e->getMessage()]);
        }

        Coleta::create($resumo + ['executada_em' => now()]);

        return $resumo;
    }

    /** Grava ou atualiza a vaga. Retorna null se ela estiver fora da janela. */
    public function gravar(JobData $job): ?Vaga
    {
        $agora = now();
        $existente = Vaga::where('fonte', $job->source)->where('id_externo', $job->externalId)->first();

        if ($existente) {
            $existente->update(['vista_ultima_vez_em' => $agora]);

            return $existente;
        }

        // Fonte sem data (ex.: Programathor) → conta a partir de quando vimos a vaga.
        $publicada = $job->publishedAt ? Carbon::instance($job->publishedAt) : $agora;
        if ($publicada->lt($agora->copy()->subHours((int) config('vagas.janela_horas', 72)))) {
            return null;
        }
        if ($publicada->gt($agora)) {
            $publicada = $agora;
        }

        $avaliacao = $this->filtro->avaliar($job);
        $classe = $this->classificador->classificar($job);
        $digital = Text::fingerprint($job->title, $job->company);
        $salario = $job->salary ?? Salario::extrair($job->title."\n".$job->description);

        return DB::transaction(function () use ($job, $avaliacao, $classe, $digital, $salario, $publicada, $agora) {
            $original = $avaliacao->aceita ? $this->original($digital) : null;

            return Vaga::create([
                'fonte' => $job->source,
                'id_externo' => mb_substr($job->externalId, 0, 191),
                'titulo' => mb_substr($job->title, 0, 500),
                'empresa' => $job->company ? mb_substr($job->company, 0, 255) : null,
                'url' => $job->url,
                'localizacao' => $job->location ? mb_substr($job->location, 0, 255) : null,
                'modelo' => $classe['modelo'],
                'senioridade' => $classe['senioridade'],
                'contrato' => $classe['contrato'],
                'tecnologias' => $classe['tecnologias'],
                'salario_min' => $salario['min'] ?? null,
                'salario_max' => $salario['max'] ?? null,
                'salario_moeda' => $salario['moeda'] ?? null,
                'descricao' => $job->description ? mb_substr($job->description, 0, 8000) : null,
                'aceita' => $avaliacao->aceita,
                'motivo_rejeicao' => $avaliacao->motivo,
                'area' => $avaliacao->area,
                'impressao_digital' => $digital,
                'duplicada_de' => $original?->id,
                'publicada_em' => $publicada,
                'vista_primeira_vez_em' => $agora,
                'vista_ultima_vez_em' => $agora,
            ]);
        });
    }

    /**
     * Aplica de novo o filtro e a classificação às vagas já gravadas (depois de mudar as regras).
     *
     * @return array{avaliadas: int, aceitas_agora: int, rejeitadas_agora: int}
     */
    public function reavaliar(): array
    {
        $resultado = ['avaliadas' => 0, 'aceitas_agora' => 0, 'rejeitadas_agora' => 0];

        Vaga::orderBy('id')->chunkById(200, function ($vagas) use (&$resultado) {
            foreach ($vagas as $vaga) {
                $job = new JobData(
                    source: $vaga->fonte,
                    externalId: $vaga->id_externo,
                    title: $vaga->titulo,
                    url: $vaga->url,
                    company: $vaga->empresa,
                    location: $vaga->localizacao,
                    workplaceType: $vaga->modelo,
                    description: $vaga->descricao,
                );
                $avaliacao = $this->filtro->avaliar($job);
                $classe = $this->classificador->classificar($job);

                $estava = $vaga->aceita;
                $vaga->fill([
                    'aceita' => $avaliacao->aceita,
                    'motivo_rejeicao' => $avaliacao->motivo,
                    'area' => $avaliacao->area,
                    'tecnologias' => $classe['tecnologias'],
                    'senioridade' => $classe['senioridade'],
                    'contrato' => $classe['contrato'],
                ]);
                if ($avaliacao->aceita && ! $estava) {
                    $vaga->duplicada_de = $this->original($vaga->impressao_digital, $vaga->id)?->id;
                    $resultado['aceitas_agora']++;
                } elseif (! $avaliacao->aceita && $estava) {
                    $vaga->duplicada_de = null;
                    $resultado['rejeitadas_agora']++;
                }
                $vaga->save();
                $resultado['avaliadas']++;
            }
        });

        return $resultado;
    }

    /** Vaga aceita mais antiga com a mesma impressão digital (mesmo cargo e empresa) nos últimos 15 dias. */
    private function original(string $digital, ?int $exceto = null): ?Vaga
    {
        return Vaga::where('impressao_digital', $digital)
            ->where('aceita', true)
            ->whereNull('duplicada_de')
            ->where('publicada_em', '>=', now()->subDays(15))
            ->when($exceto, fn ($q) => $q->where('id', '<', $exceto))
            ->oldest('id')
            ->first();
    }

    /** Apaga vagas e registros de coleta antigos. Vagas marcadas como "candidatei" ficam. */
    public function limpar(): int
    {
        $limite = now()->subDays((int) config('vagas.manter_dias', 30));
        Coleta::where('executada_em', '<', $limite)->delete();
        Vaga::whereNotNull('duplicada_de')->where('publicada_em', '<', $limite)->delete();

        return Vaga::where('publicada_em', '<', $limite)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'candidatei'))
            ->delete();
    }
}
