<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Registro extends Model
{
    protected $table = 'cartilla_registros';

    protected $fillable = [
        'codigo',
        'agencia_id',
        'usuario_id',
        'nombre_colaborador',
        'codigo_cliente',
        'accion',
        'tipo_operacion',
        'monto',
        'numero_cuenta',
        'stickers',
        'cartilla_nueva',
        'cartilla_completada',
        'sorteo',
        'promocional_entregado',
        'notas',
        'source_payment_date',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'stickers' => 'integer',
        'cartilla_nueva' => 'boolean',
        'cartilla_completada' => 'boolean',
        'sorteo' => 'boolean',
        'source_payment_date' => 'date',
    ];

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class, 'codigo_registro', 'codigo');
    }
}
