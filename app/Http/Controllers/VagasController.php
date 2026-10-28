<?php

namespace App\Http\Controllers;

use App\Models\Coleta;
use App\Models\Vaga;
use App\Radar\Services\CollectorRegistry;
use App\Radar\Services\Estatisticas;
use App\Radar\Services\Resumo;
use App\Radar\Services\SaudeFontes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VagasController extends Controller
{
    public function index(Request $request, Estatisticas $estatisticas, CollectorRegistry $registry, SaudeFontes $saude): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'in:remoto,hibrido,presencial'],
            'tec' => ['nullable', 'string', 'max:30'],
            'nivel' => ['nullable', 'in:estagio,junior,pleno,senior,especialista'],
            'area' => ['nullable', 'string', 'max:30'],
            'fonte' => ['nullable', 'string', 'max:30'],
            'salario' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'lista' => ['nullable', 'in:candidatei,descartada'],
        ]);

        // Candidaturas e descartadas aparecem mesmo depois de sair da janela de horas.
        $lista = $filtros['lista'] ?? null;
        $base = $lista
            ? Vaga::where('status', $lista)
            : Vaga::abertas()->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'descartada'));

        $vagas = $base
            ->when($filtros['q'] ?? null, fn ($q, $busca) => $this->buscar($q, $busca))
            ->when($filtros['modelo'] ?? null, fn ($q, $m) => $q->where('modelo', $m))
            ->when($filtros['nivel'] ?? null, fn ($q, $n) => $q->where('senioridade', $n))
            ->when($filtros['area'] ?? null, fn ($q, $a) => $q->where('area', $a))
            ->when($filtros['fonte'] ?? null, fn ($q, $f) => $q->where('fonte', $f))
            ->when($filtros['tec'] ?? null, fn ($q, $t) => $q->whereJsonContains('tecnologias', $t))
            // Salário só compara valores em reais: a vaga entra se o teto da faixa chega ao mínimo pedido.
            ->when($filtros['salario'] ?? null, fn ($q, $s) => $q->where('salario_moeda', 'BRL')->whereRaw('coalesce(salario_max, salario_min) >= ?', [(int) $s]))
            ->latest($lista ? 'status_em' : 'publicada_em')
            ->paginate(30)
            ->withQueryString();

        return view('vagas.index', [
            'vagas' => $vagas,
            'stats' => $estatisticas->gerar(),
            'filtros' => $filtros,
            'fontes' => collect($registry->todos())->map->label(),
            'saude' => $saude->situacao(),
            'areas' => collect(config('regras.areas'))->map(fn ($a) => $a['nome'])->put('outras', 'Outras de tecnologia'),
            'contagem' => Vaga::whereNotNull('status')->groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status'),
        ]);
    }

    /**
     * Para monitor externo: 200 se a última coleta é recente, 503 se o agendador parou.
     * Não exige senha e não mostra nada além do horário da última coleta.
     */
    public function saude(): JsonResponse
    {
        $ultima = Coleta::max('executada_em');
        $ok = $ultima && Carbon::parse($ultima)->gt(now()->subHours((int) config('radar.saude_max_horas', 4)));

        return response()->json([
            'ok' => (bool) $ok,
            'ultima_coleta' => $ultima ? Carbon::parse($ultima)->toIso8601String() : null,
        ], $ok ? 200 : 503);
    }

    public function marcar(Request $request, Vaga $vaga): RedirectResponse
    {
        $dados = $request->validate(['status' => ['nullable', 'in:candidatei,descartada']]);
        $status = $dados['status'] ?? null;

        $vaga->update(['status' => $status, 'status_em' => $status ? now() : null]);

        return back();
    }

    /** No Postgres usa a busca textual em português (título, empresa e descrição); nos testes (SQLite), LIKE. */
    private function buscar(Builder $query, string $busca): Builder
    {
        if (DB::getDriverName() === 'pgsql') {
            return $query->whereRaw("busca @@ websearch_to_tsquery('pt_unaccent', ?)", [$busca]);
        }

        $termo = '%'.mb_strtolower($busca).'%';

        return $query->where(fn ($w) => $w->whereRaw('lower(titulo) like ?', [$termo])
            ->orWhereRaw('lower(empresa) like ?', [$termo])
            ->orWhereRaw('lower(descricao) like ?', [$termo]));
    }

    public function resumo(Resumo $resumo): View
    {
        return view('vagas.resumo', ['texto' => $resumo->texto()]);
    }

    public function regras(CollectorRegistry $registry): View
    {
        $janela = now()->subHours((int) config('vagas.janela_horas', 72));

        return view('vagas.regras', [
            'regras' => config('regras'),
            'areasAtivas' => config('vagas.areas', []),
            'janela' => config('vagas.janela_horas'),
            'fontes' => collect($registry->todos(false))->map(fn ($c, $k) => [
                'nome' => $c->label(),
                'ativa' => filter_var(config("vagas.fontes.{$k}.ativa"), FILTER_VALIDATE_BOOL),
            ]),
            'rejeitadas' => Vaga::where('aceita', false)->where('publicada_em', '>=', $janela)
                ->latest('publicada_em')->limit(50)->get(['titulo', 'empresa', 'fonte', 'motivo_rejeicao', 'url']),
            'coletas' => Coleta::latest('executada_em')->limit(20)->get(),
        ]);
    }
}
