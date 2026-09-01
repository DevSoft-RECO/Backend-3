<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;

class NotaRapida extends Model
{
    protected $table = 'cartilla_notas_rapidas';

    protected $fillable = [
        'texto',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
