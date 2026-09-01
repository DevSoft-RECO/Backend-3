<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class MovimientoInventario extends Model
{
    protected $table = 'cartilla_movimientos_inventario';

    protected $fillable = [
        'codigo',
        'agencia_id',
        'usuario_id',
        'nombre_usuario',
        'recurso',
        'nombre_promocional',
        'tipo_movimiento',
        'cantidad',
        'alcance',
        'codigo_registro',
        'agencia_destino_id',
        'codigo_cliente',
        'detalle',
        'es_manual',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'es_manual' => 'boolean',
    ];

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }

    public function agenciaDestino()
    {
        return $this->belongsTo(Agencia::class, 'agencia_destino_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function registro()
    {
        return $this->belongsTo(Registro::class, 'codigo_registro', 'codigo');
    }
}
