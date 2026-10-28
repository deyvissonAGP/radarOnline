<?php

namespace App\Radar\Collectors;

use App\Radar\Contracts\Collector;
use DateTimeImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

abstract class BaseCollector implements Collector
{
    /**
     * @param  array<string, mixed>  $config  Configuração desta fonte (config/vagas.php)
     * @param  string[]  $termos  Termos de busca (desenvolvedor, dados...)
     * @param  array<string, mixed>  $http  Timeout e user agent
     */
    public function __construct(
        protected readonly array $config = [],
        protected readonly array $termos = ['desenvolvedor'],
        protected readonly array $http = [],
    ) {}

    /** @var (\Closure(string): bool)|null Diz se a vaga (pelo id externo) já está no banco. */
    protected ?\Closure $jaConhecida = null;

    /** Permite ao coletor pular trabalho extra (ex.: abrir a página de detalhe) para vagas já gravadas. */
    public function usarVagasConhecidas(\Closure $jaConhecida): static
    {
        $this->jaConhecida = $jaConhecida;

        return $this;
    }

    protected function client(): PendingRequest
    {
        return Http::timeout((int) ($this->http['timeout'] ?? 20))
            ->retry(2, 1500, throw: false)
            ->withHeaders([
                'User-Agent' => $this->http['user_agent'] ?? 'RadarVagas/1.0',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
            ]);
    }

    protected static function date(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected static function str(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
