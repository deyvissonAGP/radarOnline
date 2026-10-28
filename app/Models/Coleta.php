<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coleta extends Model
{
    protected $table = 'coletas';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sucesso' => 'boolean',
            'executada_em' => 'datetime',
        ];
    }
}
