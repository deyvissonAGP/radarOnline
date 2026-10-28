@extends('layout')

@section('titulo', 'Regras do filtro · Radar de vagas tech')

@push('estilos')
<style>
    .bloco { padding: 18px 20px; margin-bottom: 14px; }
    .bloco h3 { margin: 0 0 6px; font-size: 15px; }
    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    th, td { text-align: left; padding: 8px 10px; border-top: 1px solid var(--line); vertical-align: top; }
    th { color: var(--muted); font-weight: 500; border-top: 0; }
    code { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 12.5px; word-break: break-word; }
    .tabela { overflow-x: auto; padding: 6px 10px; }
    .erro { color: var(--warn); }
</style>
@endpush

@section('conteudo')
    <h1>Como o filtro decide o que é vaga</h1>
    <p class="muted">
        Muita coisa aparece nas fontes sem ser vaga de tecnologia: cursos, listas de grupos, propaganda de currículo, vagas de outras áreas.
        Estas são as regras aplicadas, na ordem, sobre o texto em minúsculas e sem acentos.
        Só contam itens publicados nas últimas {{ $janela }} horas.
    </p>

    <div class="card bloco">
        <h3>1. Texto bloqueado</h3>
        <p class="muted">Se aparecer em qualquer parte do anúncio, o item é descartado.</p>
        @foreach ($regras['texto_bloqueado'] as $nome => $padrao)
            <div><b>{{ $nome }}</b>: <code>{{ $padrao }}</code></div>
        @endforeach
    </div>

    <div class="card bloco">
        <h3>2. Título bloqueado</h3>
        <p class="muted">Se aparecer no título, o item é descartado.</p>
        @foreach ($regras['titulo_bloqueado'] as $nome => $padrao)
            <div><b>{{ $nome }}</b>: <code>{{ $padrao }}</code></div>
        @endforeach
    </div>

    <div class="card bloco">
        <h3>3. Precisa ser um cargo de tecnologia</h3>
        <p class="muted">
            O título precisa bater com uma destas áreas, testadas nesta ordem; a primeira que bater é a área da vaga.
            @if ($areasAtivas)
                Só estão ligadas: <b>{{ implode(', ', $areasAtivas) }}</b> (<span class="mono">VAGAS_AREAS</span>).
            @endif
        </p>
        @foreach ($regras['areas'] as $chave => $area)
            <div @class(['muted' => $areasAtivas && ! in_array($chave, $areasAtivas, true)])><b>{{ $area['nome'] }}</b>: <code>{{ $area['titulo'] }}</code></div>
        @endforeach
    </div>

    <div class="card bloco">
        <h3>4. Ou um cargo genérico com a descrição cheia de tecnologia</h3>
        <p class="muted">
            Títulos como "Engineer" ou "Analista" passam (área "Outras de tecnologia") se a descrição citar
            pelo menos {{ $regras['minimo_tecnologias'] }} das tecnologias abaixo.
        </p>
        <code>{{ $regras['cargo_generico'] }}</code>
    </div>

    <div class="card bloco">
        <h3>Tecnologias reconhecidas</h3>
        <p class="muted">Não decidem se é vaga: servem para classificar e para os filtros da página e do Telegram.</p>
        @foreach ($regras['tecnologias'] as $nome => $padrao)
            <div><b>{{ $nome }}</b>: <code>{{ $padrao }}</code></div>
        @endforeach
    </div>

    <div class="card bloco">
        <h3>Duplicadas</h3>
        <p class="muted" style="margin:0">
            A mesma vaga publicada em mais de uma fonte (mesmo cargo e mesma empresa) conta uma vez só.
        </p>
    </div>

    <h2>Fontes</h2>
    <div class="card bloco">
        @foreach ($fontes as $fonte)
            <span @class(['pill', 'ok' => $fonte['ativa'], 'neutro' => ! $fonte['ativa']])>{{ $fonte['nome'] }}{{ $fonte['ativa'] ? '' : ' (desligada)' }}</span>
        @endforeach
    </div>

    <h2>Descartados recentemente</h2>
    <div class="card tabela">
        <table>
            <tr><th>Título</th><th>Fonte</th><th>Motivo</th></tr>
            @forelse ($rejeitadas as $r)
                <tr>
                    <td><a href="{{ $r->url }}" target="_blank" rel="noopener">{{ $r->titulo }}</a></td>
                    <td>{{ $r->fonteLabel() }}</td>
                    <td>{{ $r->motivo_rejeicao }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">Nada descartado na janela atual.</td></tr>
            @endforelse
        </table>
    </div>

    <h2>Últimas coletas</h2>
    <div class="card tabela">
        <table>
            <tr><th>Quando</th><th>Fonte</th><th>Encontradas</th><th>Novas</th><th>Status</th></tr>
            @forelse ($coletas as $c)
                <tr>
                    <td>{{ $c->executada_em->timezone(config('vagas.fuso'))->format('d/m H:i') }}</td>
                    <td>{{ $c->fonte }}</td>
                    <td>{{ $c->encontradas }}</td>
                    <td>{{ $c->novas }}</td>
                    <td>@if ($c->sucesso) ok @else <span class="erro">{{ $c->erro }}</span> @endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Nenhuma coleta ainda.</td></tr>
            @endforelse
        </table>
    </div>
@endsection
