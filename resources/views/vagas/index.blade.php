@extends('layout')

@push('estilos')
<style>
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 20px 0 24px; }
    .stat { padding: 16px 18px; }
    .stat b { display: block; font-size: 30px; line-height: 1.1; letter-spacing: -0.03em; }
    .stat span { color: var(--muted); font-size: 13.5px; }
    .filtros { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px; margin-bottom: 16px; }
    .filtros input, .filtros select {
        font: inherit; color: var(--text); background: var(--bg); border: 1px solid var(--line);
        border-radius: 8px; padding: 8px 10px; min-width: 0;
    }
    .filtros input[type=search] { flex: 1 1 220px; }
    .filtros button { font: inherit; font-weight: 600; border: 0; border-radius: 8px; padding: 8px 16px; background: var(--accent); color: #fff; cursor: pointer; }
    .vaga { padding: 16px 18px; display: flex; gap: 14px; justify-content: space-between; align-items: flex-start; }
    .vaga + .vaga { border-top: 1px solid var(--line); }
    .vaga h3 { margin: 0 0 4px; font-size: 16px; font-weight: 600; }
    .vaga .meta { color: var(--muted); font-size: 13.5px; }
    .vaga .tags { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; }
    .vaga .quando { white-space: nowrap; font-size: 13px; color: var(--muted); text-align: right; }
    .vazio { padding: 40px; text-align: center; color: var(--muted); }
    .paginacao { display: flex; justify-content: space-between; margin-top: 16px; }
    .saude { display: flex; flex-wrap: wrap; gap: 6px 14px; margin: -8px 0 20px; font-size: 13px; color: var(--muted); }
    .saude .ponto { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; background: var(--ok); }
    .saude .falha .ponto { background: var(--warn); }
    .saude .falha { color: var(--warn); }
    .saude .nunca .ponto { background: var(--line); }
    .abas { display: flex; gap: 18px; margin: 0 0 12px; font-size: 14px; }
    .abas a { color: var(--muted); font-weight: 500; }
    .abas a.ativo { color: var(--text); }
    .filtros input[type=number] { width: 150px; }
    .pill.salario { background: var(--ok-soft); color: var(--ok); }
    .acoes { display: flex; gap: 6px; justify-content: flex-end; margin-top: 8px; }
    .acoes form { margin: 0; }
    .acoes button { font: inherit; font-size: 12.5px; color: var(--muted); background: transparent; border: 1px solid var(--line); border-radius: 99px; padding: 2px 10px; cursor: pointer; }
    .acoes button:hover { color: var(--text); border-color: var(--muted); }
    .vaga.candidatei h3::after { content: " ✓ candidatei"; font-size: 12.5px; font-weight: 500; color: var(--ok); }
    @media (max-width: 640px) {
        .stats { grid-template-columns: repeat(2, 1fr); }
        .vaga { flex-direction: column; }
        .vaga .quando { text-align: left; }
        .acoes { justify-content: flex-start; }
    }
</style>
@endpush

@section('conteudo')
    <h1>Vagas de tecnologia publicadas nas últimas {{ config('vagas.janela_horas') }} horas</h1>
    <p class="muted">
        Desenvolvimento, dados, infra, QA, segurança, design, produto e suporte, de {{ $fontes->values()->join(', ', ' e ') }}.
        @if ($stats['ultima_coleta'])
            Última coleta {{ $stats['ultima_coleta']->timezone(config('vagas.fuso'))->diffForHumans() }}.
        @endif
    </p>

    <div class="saude" aria-label="Situação das fontes">
        @foreach ($saude as $fonte)
            <span @class(['falha' => $fonte['ok'] === false, 'nunca' => $fonte['ok'] === null])
                  title="{{ $fonte['erro'] ?? '' }}">
                <span class="ponto"></span>{{ $fonte['nome'] }}:
                @if ($fonte['ok'] === null)
                    ainda não coletada
                @elseif ($fonte['ok'])
                    ok
                @else
                    falhando{{ $fonte['falhas_seguidas'] > 1 ? " ({$fonte['falhas_seguidas']}×)" : '' }},
                    último sucesso {{ $fonte['ultimo_ok'] ? $fonte['ultimo_ok']->timezone(config('vagas.fuso'))->diffForHumans() : 'nunca' }}
                @endif
            </span>
        @endforeach
    </div>

    <section class="stats">
        <div class="card stat"><b>{{ $stats['abertas'] }}</b><span>vagas abertas</span></div>
        <div class="card stat"><b>{{ $stats['novas_24h'] }}</b><span>novas em 24h</span></div>
        <div class="card stat"><b>{{ $stats['remotas'] }}</b><span>remotas</span></div>
        <div class="card stat"><b>{{ $stats['empresas'] }}</b><span>empresas</span></div>
    </section>

    <nav class="abas">
        @php($lista = $filtros['lista'] ?? null)
        <a href="{{ route('vagas.index') }}" @class(['ativo' => ! $lista])>Vagas abertas</a>
        <a href="{{ route('vagas.index', ['lista' => 'candidatei']) }}" @class(['ativo' => $lista === 'candidatei'])>Candidatei ({{ $contagem['candidatei'] ?? 0 }})</a>
        <a href="{{ route('vagas.index', ['lista' => 'descartada']) }}" @class(['ativo' => $lista === 'descartada'])>Descartadas ({{ $contagem['descartada'] ?? 0 }})</a>
    </nav>

    <form class="card filtros" method="get">
        @if ($lista)<input type="hidden" name="lista" value="{{ $lista }}">@endif
        <input type="search" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Buscar no cargo, empresa ou descrição">
        <select name="modelo">
            <option value="">Qualquer modelo</option>
            @foreach (['remoto' => 'Remoto', 'hibrido' => 'Híbrido', 'presencial' => 'Presencial'] as $v => $l)
                <option value="{{ $v }}" @selected(($filtros['modelo'] ?? '') === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <select name="area">
            <option value="">Qualquer área</option>
            @foreach ($areas as $chave => $nome)
                @if (isset($stats['por_area'][$chave]) || ($filtros['area'] ?? '') === $chave)
                    <option value="{{ $chave }}" @selected(($filtros['area'] ?? '') === $chave)>{{ $nome }} ({{ $stats['por_area'][$chave] ?? 0 }})</option>
                @endif
            @endforeach
        </select>
        <select name="tec">
            <option value="">Qualquer tecnologia</option>
            @foreach ($stats['por_tecnologia'] as $tec => $n)
                <option value="{{ $tec }}" @selected(($filtros['tec'] ?? '') === $tec)>{{ ucfirst($tec) }} ({{ $n }})</option>
            @endforeach
        </select>
        <select name="nivel">
            <option value="">Qualquer nível</option>
            @foreach (['estagio' => 'Estágio', 'junior' => 'Júnior', 'pleno' => 'Pleno', 'senior' => 'Sênior', 'especialista' => 'Especialista'] as $v => $l)
                <option value="{{ $v }}" @selected(($filtros['nivel'] ?? '') === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <select name="fonte">
            <option value="">Todas as fontes</option>
            @foreach ($fontes as $chave => $nome)
                <option value="{{ $chave }}" @selected(($filtros['fonte'] ?? '') === $chave)>{{ $nome }}</option>
            @endforeach
        </select>
        <input type="number" name="salario" value="{{ $filtros['salario'] ?? '' }}" min="0" step="500" placeholder="Salário mín. (R$)" title="Mostra só vagas com salário informado em reais, a partir deste valor">
        <button type="submit">Filtrar</button>
    </form>

    <section class="card">
        @forelse ($vagas as $vaga)
            <article @class(['vaga', 'candidatei' => $vaga->status === 'candidatei'])>
                <div>
                    <h3><a href="{{ $vaga->url }}" target="_blank" rel="noopener">{{ $vaga->titulo }}</a></h3>
                    <div class="meta">
                        {{ $vaga->empresa ?? 'Empresa não informada' }}
                        @if ($vaga->localizacao && $vaga->modelo !== 'remoto') · {{ $vaga->localizacao }} @endif
                    </div>
                    <div class="tags">
                        @if ($vaga->areaLabel())
                            <span class="pill neutro">{{ $vaga->areaLabel() }}</span>
                        @endif
                        @if ($vaga->modelo)
                            <span @class(['pill', 'ok' => $vaga->modelo === 'remoto', 'neutro' => $vaga->modelo !== 'remoto'])>{{ ['remoto' => 'Remoto', 'hibrido' => 'Híbrido', 'presencial' => 'Presencial'][$vaga->modelo] }}</span>
                        @endif
                        @if ($vaga->senioridade)
                            <span class="pill neutro">{{ ucfirst($vaga->senioridade) }}</span>
                        @endif
                        @if ($vaga->contrato)
                            <span class="pill neutro">{{ strtoupper($vaga->contrato) }}</span>
                        @endif
                        @if ($vaga->salarioTexto())
                            <span class="pill salario">{{ $vaga->salarioTexto() }}</span>
                        @endif
                        @foreach ($vaga->tecnologias ?? [] as $tec)
                            <span class="pill">{{ ucfirst($tec) }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="quando">
                    {{ $vaga->publicada_em->timezone(config('vagas.fuso'))->diffForHumans() }}<br>
                    <span class="mono">{{ $vaga->fonteLabel() }}</span>
                    <div class="acoes">
                        @foreach ($vaga->status ? ['' => 'Desfazer'] : ['candidatei' => 'Candidatei', 'descartada' => 'Descartar'] as $status => $rotulo)
                            <form method="post" action="{{ route('vagas.marcar', $vaga) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $status }}">
                                <button type="submit">{{ $rotulo }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </article>
        @empty
            <div class="vazio">
                Nenhuma vaga por aqui ainda.<br>
                Rode <span class="mono">php artisan vagas:coletar</span> ou ajuste os filtros.
            </div>
        @endforelse
    </section>

    @if ($vagas->hasPages())
        <div class="paginacao">
            <span>@if ($vagas->previousPageUrl())<a href="{{ $vagas->previousPageUrl() }}">← Anteriores</a>@endif</span>
            <span class="muted">Página {{ $vagas->currentPage() }} de {{ $vagas->lastPage() }}</span>
            <span>@if ($vagas->nextPageUrl())<a href="{{ $vagas->nextPageUrl() }}">Próximas →</a>@endif</span>
        </div>
    @endif
@endsection
