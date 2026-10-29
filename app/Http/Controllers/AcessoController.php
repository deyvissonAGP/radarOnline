<?php

namespace App\Http\Controllers;

use App\Radar\Services\Acesso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcessoController extends Controller
{
    /** Tentativas erradas por IP antes de esperar um minuto. */
    private const TENTATIVAS = 5;

    public function __construct(private readonly Acesso $acesso) {}

    public function mostrar(Request $request): View|RedirectResponse
    {
        if (! $this->acesso->configurado() || $request->session()->get(Acesso::SESSAO) === true) {
            return redirect()->route('vagas.index');
        }

        return view('acesso.entrar');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'usuario' => ['required', 'string', 'max:100'],
            'senha' => ['required', 'string', 'max:200'],
        ], [], ['usuario' => 'usuário']);

        $chave = 'login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($chave, self::TENTATIVAS)) {
            throw ValidationException::withMessages([
                'usuario' => 'Muitas tentativas. Tente de novo em '.RateLimiter::availableIn($chave).' segundos.',
            ]);
        }

        if (! $this->acesso->confere($dados['usuario'], $dados['senha'])) {
            RateLimiter::hit($chave, 60);

            throw ValidationException::withMessages(['usuario' => 'Usuário ou senha incorretos.']);
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate();
        $request->session()->put(Acesso::SESSAO, true);

        if ($request->boolean('lembrar')) {
            Cookie::queue(Acesso::COOKIE, $this->acesso->tokenLembrar(), Acesso::DIAS_LEMBRAR * 24 * 60);
        }

        return redirect()->intended(route('vagas.index'));
    }

    public function sair(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(Acesso::COOKIE));

        return redirect()->route('entrar');
    }
}
