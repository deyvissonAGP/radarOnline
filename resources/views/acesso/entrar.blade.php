@extends('layout')

@section('titulo', 'Entrar · Radar de vagas tech')
@section('sem_navegacao', true)

@push('estilos')
<style>
    main { display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 64px); padding: 24px 0 48px; }
    .entrar { width: 100%; max-width: 380px; padding: 32px 28px 26px; box-shadow: 0 12px 32px -12px rgb(0 0 0 / 0.18); }
    .entrar .icone {
        display: grid; place-items: center; width: 44px; height: 44px; margin-bottom: 18px;
        border-radius: 12px; background: var(--accent-soft); font-size: 22px;
    }
    .entrar h1 { font-size: 22px; margin: 0 0 4px; }
    .entrar > p { margin: 0 0 22px; font-size: 14px; }
    .campo { margin-bottom: 14px; }
    .campo label { display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px; }
    .campo input {
        width: 100%; font: inherit; color: var(--text); background: var(--bg);
        border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px;
    }
    .campo input:focus { outline: 2px solid var(--accent-soft); border-color: var(--accent); }
    .lembrar { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--muted); margin: 4px 0 20px; cursor: pointer; }
    .lembrar input { accent-color: var(--accent); width: 16px; height: 16px; margin: 0; }
    .entrar button {
        width: 100%; font: inherit; font-weight: 600; border: 0; border-radius: 8px;
        padding: 11px 16px; background: var(--accent); color: var(--surface); cursor: pointer;
    }
    .entrar button:hover { filter: brightness(1.08); }
    .erro { font-size: 13.5px; color: var(--warn); background: transparent; border: 1px solid var(--warn); border-radius: 8px; padding: 9px 12px; margin-bottom: 16px; }
    footer { display: none; }
</style>
@endpush

@section('conteudo')
    <form class="card entrar" method="post" action="{{ route('entrar') }}">
        @csrf
        <div class="icone" aria-hidden="true">📡</div>
        <h1>Entrar</h1>
        <p class="muted">Acesso restrito ao painel de vagas.</p>

        @if ($errors->any())
            <div class="erro" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="campo">
            <label for="usuario">Usuário</label>
            <input id="usuario" name="usuario" type="text" value="{{ old('usuario') }}"
                   autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
        </div>

        <div class="campo">
            <label for="senha">Senha</label>
            <input id="senha" name="senha" type="password" autocomplete="current-password" required>
        </div>

        <label class="lembrar">
            <input type="checkbox" name="lembrar" value="1" @checked(old('lembrar', true))>
            Manter conectado por {{ \App\Radar\Services\Acesso::DIAS_LEMBRAR }} dias
        </label>

        <button type="submit">Entrar</button>
    </form>
@endsection
