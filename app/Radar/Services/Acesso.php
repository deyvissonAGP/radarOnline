<?php

namespace App\Radar\Services;

/**
 * Login de um usuário só, com usuário e senha definidos em RADAR_USUARIO e RADAR_SENHA.
 * Não há tabela de usuários: o site é pessoal.
 */
final class Acesso
{
    public const SESSAO = 'radar.autenticado';

    public const COOKIE = 'radar_lembrar';

    public const DIAS_LEMBRAR = 30;

    public function configurado(): bool
    {
        return (string) config('radar.acesso.senha') !== '';
    }

    public function confere(string $usuario, string $senha): bool
    {
        return $this->configurado()
            && hash_equals((string) config('radar.acesso.usuario'), $usuario)
            && hash_equals((string) config('radar.acesso.senha'), $senha);
    }

    /**
     * Valor do cookie "manter conectado". Depende do usuário, da senha e da APP_KEY:
     * trocar a senha desconecta todos os navegadores.
     */
    public function tokenLembrar(): string
    {
        return hash_hmac('sha256', config('radar.acesso.usuario').'|'.config('radar.acesso.senha'), (string) config('app.key'));
    }

    public function lembrarValido(?string $token): bool
    {
        return $this->configurado() && is_string($token) && hash_equals($this->tokenLembrar(), $token);
    }
}
