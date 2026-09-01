<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;

class InventarioStock extends Model
{
    protected $table = 'cartilla_inventario_stocks';

    protected $fillable = [
        'agencia_id',
        'recurso',
        'nombre_promocional',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }
}
