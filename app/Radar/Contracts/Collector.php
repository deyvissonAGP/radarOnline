<?php

namespace App\Radar\Contracts;

use App\Radar\JobData;

interface Collector
{
    /** Identificador curto da fonte, gravado no banco (ex.: "gupy"). */
    public function key(): string;

    /** Nome exibido na página (ex.: "Gupy"). */
    public function label(): string;

    /**
     * Busca as vagas na fonte.
     *
     * @return JobData[]
     */
    public function collect(): array;
}
