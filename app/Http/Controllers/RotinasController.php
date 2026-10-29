<?php

namespace App\Http\Controllers;

use App\Models\Execucao;
use App\Radar\Rotinas;
use App\Radar\Services\CollectorRegistry;
use App\Radar\Services\Telegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RotinasController extends Controller
{
    public function index(CollectorRegistry $registry, Telegram $telegram): View
    {
        Rotinas::marcarPerdidas();

        $execucoes = Execucao::latest('pedida_em')->latest('id')->limit(30)->get();

        return view('rotinas.index', [
            'rotinas' => collect(Rotinas::lista())->groupBy('grupo', preserveKeys: true),
            'execucoes' => $execucoes,
            'emAndamento' => $execucoes->filter->estaEmAndamento()->pluck('rotina')->unique()->values(),
            'fontes' => collect($registry->todos())->map->label(),
            'telegramConfigurado' => $telegram->configurado(),
            'proximaColeta' => $this->proximaColeta(),
        ]);
    }

    public function executar(Request $request, string $rotina, CollectorRegistry $registry): RedirectResponse
    {
        abort_unless(Rotinas::existe($rotina), 404);

        $extras = [];
        if ($rotina === 'coletar' && $request->filled('fonte')) {
            $fonte = (string) $request->input('fonte');
            abort_unless(array_key_exists($fonte, $registry->todos()), 422, 'Fonte desconhecida ou desligada.');
            $extras['--fonte'] = [$fonte];
        }

        $nome = Rotinas::lista()[$rotina]['nome'];
        $execucao = Rotinas::pedir($rotina, $extras);

        return redirect()->route('rotinas.index')->with(
            $execucao ? 'aviso' : 'erro',
            $execucao ? "“{$nome}” foi para a fila. O andamento aparece abaixo." : "“{$nome}” já está rodando. Espere terminar.",
        );
    }

    /** A página consulta isto enquanto houver rotina rodando, para se atualizar sozinha. */
    public function estado(): JsonResponse
    {
        Rotinas::marcarPerdidas();

        return response()->json([
            'em_andamento' => Execucao::emAndamento()->count(),
            'ultima_alteracao' => Execucao::max('terminada_em'),
        ]);
    }

    /** Próxima coleta automática: a cada 3 horas cheias (00h, 03h, 06h... no horário do servidor, UTC). */
    private function proximaColeta(): \Illuminate\Support\Carbon
    {
        $agora = now()->utc();

        return $agora->copy()->startOfHour()->addHours(3 - ($agora->hour % 3));
    }
}
