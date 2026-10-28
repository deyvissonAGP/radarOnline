<?php

namespace App\Radar\Filter;

final class Avaliacao
{
    private function __construct(
        public readonly bool $aceita,
        public readonly ?string $motivo,
        public readonly ?string $area = null,
    ) {}

    public static function aceita(string $area): self
    {
        return new self(true, null, $area);
    }

    public static function rejeitada(string $motivo, ?string $area = null): self
    {
        return new self(false, $motivo, $area);
    }
}
