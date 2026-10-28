<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Atrás do proxy reverso do servidor (nginx/Caddy), que termina o HTTPS.
        // O container só escuta em 127.0.0.1, então só o proxy chega até aqui.
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [App\Http\Middleware\SenhaDeAcesso::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
