<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;

class Recordatorio extends Model
{
    protected $table = 'cartilla_recordatorios';

    protected $fillable = [
        'texto',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
