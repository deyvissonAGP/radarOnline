<?php

namespace App\Http\Middleware;

use App\Radar\Services\Acesso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige login no site inteiro (usuário e senha de RADAR_USUARIO e RADAR_SENHA).
 *
 * - Sem senha definida: em produção bloqueia tudo; em desenvolvimento deixa passar.
 * - Quem marcou "manter conectado" volta logado pelo cookie, sem digitar a senha.
 */
class SenhaDeAcesso
{
    /** Abertas: a tela de login e as rotas do monitor de disponibilidade. */
    private const LIVRES = ['entrar', 'up', 'saude'];

    public function __construct(private readonly Acesso $acesso) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...self::LIVRES)) {
            return $next($request);
        }

        if (! $this->acesso->configurado()) {
            abort_if(app()->isProduction(), 503, 'Defina RADAR_USUARIO e RADAR_SENHA no .env.');

            return $next($request);
        }

        if ($request->session()->get(Acesso::SESSAO) === true) {
            return $next($request);
        }

        if ($this->acesso->lembrarValido($request->cookie(Acesso::COOKIE))) {
            $request->session()->regenerate();
            $request->session()->put(Acesso::SESSAO, true);

            return $next($request);
        }

        return redirect()->guest(route('entrar'));
    }
}
