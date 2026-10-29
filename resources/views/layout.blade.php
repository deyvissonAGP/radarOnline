<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Radar de vagas tech')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f6f6f9; --surface: #ffffff; --text: #1b1d2a; --muted: #636780; --line: #e3e4ec;
            --accent: #5b5fc7; --accent-soft: #ecedfb; --ok: #0f8a6b; --ok-soft: #e3f6f0; --warn: #b4540e;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1018; --surface: #171925; --text: #e8e9f2; --muted: #9a9db5; --line: #272a3b;
                --accent: #9a9df0; --accent-soft: #23254a; --ok: #4fd1a8; --ok-soft: #12302a; --warn: #f0a35e;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.55 Inter, system-ui, sans-serif; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 0 16px; }
        header.top { border-bottom: 1px solid var(--line); background: var(--surface); }
        header.top .wrap { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-top: 14px; padding-bottom: 14px; flex-wrap: wrap; }
        .brand { font-weight: 700; font-size: 18px; color: var(--text); }
        .brand span { color: var(--accent); }
        nav a { margin-left: 18px; color: var(--muted); font-weight: 500; }
        nav a.ativo { color: var(--text); }
        nav form { display: inline; margin: 0 0 0 18px; }
        nav .sair { font: inherit; font-weight: 500; color: var(--muted); background: none; border: 0; padding: 0; cursor: pointer; }
        nav .sair:hover { color: var(--text); }
        main { padding: 28px 0 60px; }
        .mono { font-family: "JetBrains Mono", ui-monospace, monospace; }
        .muted { color: var(--muted); }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; }
        .pill { display: inline-block; padding: 2px 9px; border-radius: 99px; font-size: 12.5px; font-weight: 500; background: var(--accent-soft); color: var(--accent); }
        .pill.ok { background: var(--ok-soft); color: var(--ok); }
        .pill.neutro { background: transparent; border: 1px solid var(--line); color: var(--muted); }
        h1 { font-size: 26px; margin: 0 0 6px; letter-spacing: -0.02em; }
        h2 { font-size: 18px; margin: 32px 0 12px; }
        footer { color: var(--muted); font-size: 13px; padding: 24px 0 40px; border-top: 1px solid var(--line); }
    </style>
    @stack('estilos')
</head>
<body>
<header class="top">
    <div class="wrap">
        <a class="brand" href="{{ route('vagas.index') }}">📡 Radar de vagas <span>tech</span></a>
        @unless (View::hasSection('sem_navegacao'))
            <nav>
                <a href="{{ route('vagas.index') }}" @class(['ativo' => request()->routeIs('vagas.index')])>Vagas</a>
                <a href="{{ route('vagas.resumo') }}" @class(['ativo' => request()->routeIs('vagas.resumo')])>Resumo</a>
                <a href="{{ route('vagas.regras') }}" @class(['ativo' => request()->routeIs('vagas.regras')])>Regras</a>
                @if (session(\App\Radar\Services\Acesso::SESSAO))
                    <form method="post" action="{{ route('sair') }}">
                        @csrf
                        <button type="submit" class="sair">Sair</button>
                    </form>
                @endif
            </nav>
        @endunless
    </div>
</header>
<main class="wrap">
    @yield('conteudo')
</main>
<footer class="wrap">
    Vagas coletadas automaticamente. Sempre confira os detalhes no site original antes de se candidatar.
</footer>
</body>
</html>
