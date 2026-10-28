<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege o site inteiro com usuário e senha (HTTP Basic), definidos em
 * RADAR_USUARIO e RADAR_SENHA no .env. Use sempre atrás de HTTPS.
 *
 * - Sem senha definida: em produção bloqueia tudo; em desenvolvimento deixa passar.
 * - Depois de 10 tentativas erradas por minuto, o IP espera um minuto.
 */
class SenhaDeAcesso
{
    private const TENTATIVAS = 10;

    /** Rotas abertas para o monitor de disponibilidade. */
    private const LIVRES = ['up', 'saude'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...self::LIVRES)) {
            return $next($request);
        }

        $usuario = (string) config('radar.acesso.usuario');
        $senha = (string) config('radar.acesso.senha');

        if ($senha === '') {
            abort_if(app()->isProduction(), 503, 'Defina RADAR_USUARIO e RADAR_SENHA no .env.');

            return $next($request);
        }

        $chave = 'senha-de-acesso:'.$request->ip();
        if (RateLimiter::tooManyAttempts($chave, self::TENTATIVAS)) {
            abort(429, 'Muitas tentativas. Espere um minuto.');
        }

        if (hash_equals($usuario, (string) $request->getUser()) && hash_equals($senha, (string) $request->getPassword())) {
            RateLimiter::clear($chave);

            return $next($request);
        }

        // Só conta como erro quando o navegador mandou credenciais (a primeira visita vem sem).
        if ($request->getUser() !== null) {
            RateLimiter::hit($chave, 60);
        }

        return response('Acesso restrito.', 401, ['WWW-Authenticate' => 'Basic realm="Radar de vagas", charset="UTF-8"']);
    }
}
