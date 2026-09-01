<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'cartilla_configuracion';

    protected $fillable = [
        'clave',
        'valor',
    ];

    protected $casts = [
        'valor' => 'array',
    ];
}
