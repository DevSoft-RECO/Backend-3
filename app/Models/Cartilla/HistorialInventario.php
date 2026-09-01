<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class HistorialInventario extends Model
{
    protected $table = 'cartilla_historial_inventario';

    protected $fillable = [
        'movimiento_id',
        'usuario_id',
        'nombre_usuario',
        'estado_cambio',
        'snapshot',
        'restaurado',
        'ejecutado_en',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'restaurado' => 'boolean',
        'ejecutado_en' => 'datetime',
    ];

    public function movimiento()
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
