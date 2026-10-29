<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Execucao extends Model
{
    protected $table = 'execucoes';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'parametros' => 'array',
            'pedida_em' => 'datetime',
            'iniciada_em' => 'datetime',
            'terminada_em' => 'datetime',
        ];
    }

    public function scopeEmAndamento(Builder $query): Builder
    {
        return $query->whereIn('status', ['na_fila', 'rodando']);
    }

    public function estaEmAndamento(): bool
    {
        return in_array($this->status, ['na_fila', 'rodando'], true);
    }

    /** Duração em segundos (até agora, se ainda estiver rodando). */
    public function duracao(): ?int
    {
        if (! $this->iniciada_em) {
            return null;
        }

        return (int) $this->iniciada_em->diffInSeconds($this->terminada_em ?? now());
    }
}
