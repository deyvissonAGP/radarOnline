@extends('layout')

@section('titulo', 'Resumo do dia · Radar de vagas tech')

@push('estilos')
<style>
    .resumo { padding: 20px; white-space: pre-wrap; font-size: 15px; margin: 16px 0; }
    .copiar { font: inherit; font-weight: 600; border: 0; border-radius: 8px; padding: 9px 18px; background: var(--accent); color: #fff; cursor: pointer; }
</style>
@endpush

@section('conteudo')
    <h1>Resumo do dia</h1>
    <p class="muted">Texto pronto para postar no LinkedIn ou mandar em grupos.</p>

    <div class="card resumo" id="texto">{{ $texto }}</div>

    <button class="copiar" type="button" id="copiar">Copiar texto</button>

    <script>
        document.getElementById('copiar').addEventListener('click', async (e) => {
            try {
                await navigator.clipboard.writeText(document.getElementById('texto').innerText);
                e.target.textContent = 'Copiado!';
            } catch {
                e.target.textContent = 'Selecione o texto e copie manualmente';
            }
            setTimeout(() => e.target.textContent = 'Copiar texto', 2500);
        });
    </script>
@endsection
