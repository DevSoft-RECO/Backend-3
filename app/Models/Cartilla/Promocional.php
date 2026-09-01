<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;

class Promocional extends Model
{
    protected $table = 'cartilla_promocionales';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
