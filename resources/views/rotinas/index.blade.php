@extends('layout')

@section('titulo', 'Rotinas · Radar de vagas tech')

@push('estilos')
<style>
    .situacao { display: flex; flex-wrap: wrap; gap: 8px 20px; margin: 6px 0 22px; font-size: 14px; color: var(--muted); }
    .situacao b { color: var(--text); font-weight: 600; }
    .alerta { padding: 11px 14px; border-radius: 10px; margin-bottom: 18px; font-size: 14px; border: 1px solid var(--line); background: var(--surface); }
    .alerta.ok { border-color: var(--ok); color: var(--ok); }
    .alerta.erro { border-color: var(--warn); color: var(--warn); }
    .grupo h2 { margin: 26px 0 10px; }
    .rotinas { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
    .rotina { padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; }
    .rotina h3 { margin: 0; font-size: 15px; }
    .rotina p { margin: 0; font-size: 13.5px; color: var(--muted); flex: 1; }
    .rotina form { display: flex; gap: 8px; margin: 0; }
    .rotina select { font: inherit; font-size: 13.5px; color: var(--text); background: var(--bg); border: 1px solid var(--line); border-radius: 8px; padding: 7px 8px; min-width: 0; flex: 1; }
    .botao { font: inherit; font-size: 14px; font-weight: 600; border: 0; border-radius: 8px; padding: 8px 14px; background: var(--accent); color: var(--surface); cursor: pointer; white-space: nowrap; }
    .botao:hover { filter: brightness(1.08); }
    .botao.perigo { background: transparent; color: var(--warn); border: 1px solid var(--warn); }
    .botao[disabled] { opacity: .55; cursor: wait; filter: none; }
    .rodando-agora { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; color: var(--accent); font-weight: 500; }
    .giro { width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--accent-soft); border-top-color: var(--accent); animation: giro .8s linear infinite; }
    @keyframes giro { to { transform: rotate(360deg); } }
    .historico { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    th, td { text-align: left; padding: 10px 12px; border-top: 1px solid var(--line); vertical-align: top; }
    th { color: var(--muted); font-weight: 500; border-top: 0; white-space: nowrap; }
    td.quando { white-space: nowrap; color: var(--muted); }
    .pill.falhou { background: transparent; border: 1px solid var(--warn); color: var(--warn); }
    details summary { cursor: pointer; color: var(--accent); font-size: 13px; }
    pre { margin: 8px 0 0; padding: 10px 12px; background: var(--bg); border: 1px solid var(--line); border-radius: 8px; font: 12px/1.5 "JetBrains Mono", ui-monospace, monospace; white-space: pre; max-height: 360px; overflow: auto; }
    @media (prefers-reduced-motion: reduce) { .giro { animation: none; } }
</style>
@endpush

@section('conteudo')
    <h1>Rotinas</h1>
    <div class="situacao">
        <span>Coleta automática a cada 3 horas · próxima <b>{{ $proximaColeta->timezone(config('vagas.fuso'))->format('H:i') }}</b></span>
        <span>Resumo automático às <b>08:00</b></span>
        <span>Telegram: <b>{{ $telegramConfigurado ? 'configurado' : 'não configurado' }}</b></span>
    </div>

    @if (session('aviso'))
        <div class="alerta ok" role="status">{{ session('aviso') }}</div>
    @endif
    @if (session('erro'))
        <div class="alerta erro" role="alert">{{ session('erro') }}</div>
    @endif
    @if (! $telegramConfigurado)
        <div class="alerta">As rotinas de Telegram precisam de <span class="mono">TELEGRAM_BOT_TOKEN</span> e <span class="mono">TELEGRAM_CHAT_ID</span> no <span class="mono">.env</span>.</div>
    @endif

    @foreach ($rotinas as $grupo => $itens)
        <section class="grupo">
            <h2>{{ $grupo }}</h2>
            <div class="rotinas">
                @foreach ($itens as $chave => $rotina)
                    @php($rodando = $emAndamento->contains($chave))
                    <div class="card rotina">
                        <h3>{{ $rotina['nome'] }}</h3>
                        <p>{{ $rotina['descricao'] }}</p>
                        <form method="post" action="{{ route('rotinas.executar', $chave) }}"
                              @isset($rotina['confirmar']) onsubmit="return confirm(@js($rotina['confirmar']))" @endisset>
                            @csrf
                            @if ($chave === 'coletar')
                                <select name="fonte" aria-label="Fonte">
                                    <option value="">Todas as fontes</option>
                                    @foreach ($fontes as $fonte => $nome)
                                        <option value="{{ $fonte }}">Só {{ $nome }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @if ($rodando)
                                <span class="rodando-agora"><span class="giro"></span> Rodando…</span>
                            @else
                                <button type="submit" @class(['botao', 'perigo' => isset($rotina['confirmar'])])>Executar</button>
                            @endif
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    <h2>Histórico</h2>
    <div class="card historico">
        <table>
            <tr><th>Quando</th><th>Rotina</th><th>Origem</th><th>Situação</th><th>Duração</th><th>Saída</th></tr>
            @forelse ($execucoes as $e)
                <tr>
                    <td class="quando">{{ $e->pedida_em->timezone(config('vagas.fuso'))->format('d/m H:i') }}</td>
                    <td>
                        {{ \App\Radar\Rotinas::lista()[$e->rotina]['nome'] ?? $e->rotina }}
                        @if ($e->parametros['--fonte'] ?? null)
                            <span class="muted">({{ implode(', ', (array) $e->parametros['--fonte']) }})</span>
                        @endif
                    </td>
                    <td class="muted">{{ $e->origem === 'agendada' ? 'Automática' : 'Manual' }}</td>
                    <td>
                        @switch($e->status)
                            @case('na_fila') <span class="rodando-agora"><span class="giro"></span> Na fila</span> @break
                            @case('rodando') <span class="rodando-agora"><span class="giro"></span> Rodando</span> @break
                            @case('concluida') <span class="pill ok">Concluída</span> @break
                            @default <span class="pill falhou">Falhou</span>
                        @endswitch
                    </td>
                    <td class="muted">{{ $e->duracao() !== null ? ($e->duracao() >= 60 ? intdiv($e->duracao(), 60).'min '.($e->duracao() % 60).'s' : $e->duracao().'s') : '–' }}</td>
                    <td>
                        @if ($e->saida)
                            <details @if ($loop->first && ! $e->estaEmAndamento()) open @endif>
                                <summary>Ver saída</summary>
                                <pre>{{ $e->saida }}</pre>
                            </details>
                        @else
                            <span class="muted">–</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Nenhuma rotina executada ainda.</td></tr>
            @endforelse
        </table>
    </div>

    @if ($emAndamento->isNotEmpty())
        <script>
            // Enquanto houver rotina na fila ou rodando, confere a cada 3 s e recarrega quando terminar.
            (function () {
                const url = @js(route('rotinas.estado'));
                const timer = setInterval(async () => {
                    try {
                        const r = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (r.ok && (await r.json()).em_andamento === 0) {
                            clearInterval(timer);
                            location.reload();
                        }
                    } catch (e) { /* tenta de novo no próximo ciclo */ }
                }, 3000);
            })();
        </script>
    @endif
@endsection
