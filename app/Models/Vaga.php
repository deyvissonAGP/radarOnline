<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Vaga extends Model
{
    protected $table = 'vagas';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tecnologias' => 'array',
            'aceita' => 'boolean',
            'publicada_em' => 'datetime',
            'vista_primeira_vez_em' => 'datetime',
            'vista_ultima_vez_em' => 'datetime',
            'notificada_em' => 'datetime',
            'status_em' => 'datetime',
            'salario_min' => 'integer',
            'salario_max' => 'integer',
        ];
    }

    /** Vagas que contam: aceitas pelo filtro, não duplicadas e dentro da janela. */
    public function scopeAbertas(Builder $query, ?int $horas = null): Builder
    {
        $horas ??= (int) config('vagas.janela_horas', 72);

        return $query->where('aceita', true)
            ->whereNull('duplicada_de')
            ->where('publicada_em', '>=', now()->subHours($horas));
    }

    public function scopeRemotas(Builder $query): Builder
    {
        return $query->where('modelo', 'remoto');
    }

    /** "R$ 5.000 – 7.000/mês", "US$ 4.000/mês" ou null. */
    public function salarioTexto(): ?string
    {
        if (! $this->salario_min && ! $this->salario_max) {
            return null;
        }

        $simbolo = ['BRL' => 'R$', 'USD' => 'US$', 'EUR' => '€', 'GBP' => '£'][$this->salario_moeda] ?? $this->salario_moeda;
        $n = fn (?int $v) => number_format((int) $v, 0, ',', '.');
        $faixa = $this->salario_min && $this->salario_max && $this->salario_min !== $this->salario_max
            ? $n($this->salario_min).' – '.$n($this->salario_max)
            : $n($this->salario_max ?: $this->salario_min);

        return "{$simbolo} {$faixa}/mês";
    }

    public function areaLabel(): ?string
    {
        if (! $this->area) {
            return null;
        }

        return config("regras.areas.{$this->area}.nome") ?? ($this->area === 'outras' ? 'Outras de tecnologia' : ucfirst($this->area));
    }

    public function fonteLabel(): string
    {
        $classe = config("vagas.fontes.{$this->fonte}.classe");

        return $classe ? app($classe)->label() : ucfirst($this->fonte);
    }
}
