<?php

namespace App\Radar;

use App\Jobs\ExecutarRotina;
use App\Models\Execucao;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * As rotinas do Radar que podem ser disparadas pela página /rotinas ou pelo agendador.
 * Só o que está nesta lista pode ser executado: a página nunca roda um comando qualquer.
 */
final class Rotinas
{
    /** Execuções "rodando" há mais tempo que isso são dadas como perdidas (worker caiu). */
    private const MINUTOS_PERDIDA = 20;

    /**
     * @return array<string, array{nome: string, descricao: string, comando: string, parametros?: array<string, mixed>, grupo: string, confirmar?: string}>
     */
    public static function lista(): array
    {
        $max = (int) config('vagas.telegram.max_por_rodada', 15);

        return [
            'coletar' => [
                'grupo' => 'Coleta',
                'nome' => 'Coletar vagas',
                'descricao' => 'Busca vagas nas fontes ativas, grava as novas e apaga as antigas. Leva de 1 a 2 minutos.',
                'comando' => 'vagas:coletar',
            ],
            'reavaliar' => [
                'grupo' => 'Coleta',
                'nome' => 'Reaplicar as regras',
                'descricao' => 'Passa de novo o filtro e a classificação em todas as vagas gravadas. Use depois de mudar config/regras.php ou VAGAS_AREAS.',
                'comando' => 'vagas:reavaliar',
            ],
            'limpar' => [
                'grupo' => 'Coleta',
                'nome' => 'Apagar vagas antigas',
                'descricao' => 'Remove vagas com mais de '.config('vagas.manter_dias', 30).' dias (as marcadas como "Candidatei" ficam). A coleta já faz isso sozinha.',
                'comando' => 'vagas:limpar',
            ],
            'notificar' => [
                'grupo' => 'Telegram',
                'nome' => 'Enviar vagas novas',
                'descricao' => "Envia as vagas que ainda não foram avisadas, até {$max} por vez, respeitando os filtros do Telegram.",
                'comando' => 'vagas:notificar',
            ],
            'simular' => [
                'grupo' => 'Telegram',
                'nome' => 'Ver o que seria enviado',
                'descricao' => 'Mostra aqui as mensagens que o próximo envio mandaria, sem enviar nada.',
                'comando' => 'vagas:notificar',
                'parametros' => ['--simular' => true],
            ],
            'resumo' => [
                'grupo' => 'Telegram',
                'nome' => 'Enviar resumo do dia',
                'descricao' => 'Manda agora o resumo com os números do dia (o automático sai às 8h).',
                'comando' => 'vagas:resumo',
                'parametros' => ['--telegram' => true],
            ],
            'testar_telegram' => [
                'grupo' => 'Telegram',
                'nome' => 'Testar o Telegram',
                'descricao' => 'Envia uma mensagem de teste para conferir o token e o chat.',
                'comando' => 'vagas:testar-telegram',
            ],
            'marcar_enviadas' => [
                'grupo' => 'Telegram',
                'nome' => 'Marcar tudo como enviado',
                'descricao' => 'Dá todas as vagas atuais como avisadas, sem enviar. Daqui em diante só chegam as novas.',
                'comando' => 'vagas:notificar',
                'parametros' => ['--marcar-enviadas' => true],
                'confirmar' => 'Marcar todas as vagas atuais como enviadas? Elas não serão mais mandadas no Telegram.',
            ],
        ];
    }

    public static function existe(string $rotina): bool
    {
        return isset(self::lista()[$rotina]);
    }

    /**
     * Coloca a rotina na fila. Devolve null se ela já estiver na fila ou rodando.
     *
     * @param  array<string, mixed>  $extras  Parâmetros a mais (ex.: ['--fonte' => ['gupy']])
     */
    public static function pedir(string $rotina, array $extras = [], string $origem = 'web'): ?Execucao
    {
        self::marcarPerdidas();

        if (Execucao::where('rotina', $rotina)->emAndamento()->exists()) {
            return null;
        }

        $execucao = Execucao::create([
            'rotina' => $rotina,
            'parametros' => $extras ?: null,
            'origem' => $origem,
            'status' => 'na_fila',
            'pedida_em' => now(),
        ]);

        ExecutarRotina::dispatch($execucao->id);

        return $execucao;
    }

    /**
     * Roda na hora, no processo atual (usado pelo agendador), registrando no histórico.
     * Devolve null se a mesma rotina já estiver rodando (ex.: pedida pela página).
     */
    public static function executarAgora(string $rotina, array $extras = [], string $origem = 'agendada'): ?Execucao
    {
        self::marcarPerdidas();

        if (Execucao::where('rotina', $rotina)->emAndamento()->exists()) {
            return null;
        }

        $execucao = Execucao::create([
            'rotina' => $rotina,
            'parametros' => $extras ?: null,
            'origem' => $origem,
            'status' => 'na_fila',
            'pedida_em' => now(),
        ]);

        return self::executar($execucao);
    }

    public static function executar(Execucao $execucao): Execucao
    {
        $definicao = self::lista()[$execucao->rotina] ?? null;
        $execucao->update(['status' => 'rodando', 'iniciada_em' => now()]);

        if (! $definicao) {
            $execucao->update(['status' => 'falhou', 'saida' => "Rotina desconhecida: {$execucao->rotina}", 'terminada_em' => now()]);

            return $execucao;
        }

        $saida = new BufferedOutput;
        try {
            $codigo = Artisan::call($definicao['comando'], ($definicao['parametros'] ?? []) + ($execucao->parametros ?? []), $saida);
            $status = $codigo === 0 ? 'concluida' : 'falhou';
            $texto = $saida->fetch();
        } catch (Throwable $e) {
            $status = 'falhou';
            $texto = $saida->fetch()."\n".$e->getMessage();
        }

        $execucao->update([
            'status' => $status,
            'saida' => mb_substr(trim($texto), -20000),
            'terminada_em' => now(),
        ]);

        return $execucao;
    }

    /** Se o worker cair no meio, a execução ficaria "rodando" para sempre e bloquearia o botão. */
    public static function marcarPerdidas(): void
    {
        Execucao::emAndamento()
            ->where('pedida_em', '<', now()->subMinutes(self::MINUTOS_PERDIDA))
            ->update([
                'status' => 'falhou',
                'saida' => 'Interrompida: passou de '.self::MINUTOS_PERDIDA.' minutos sem terminar (o worker parou?).',
                'terminada_em' => now(),
            ]);
    }
}
