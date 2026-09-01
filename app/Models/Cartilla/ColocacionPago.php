<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ColocacionPago extends Model
{
    protected $table = 'cartilla_colocaciones_pagos';

    protected $fillable = [
        'importacion_id',
        'agencia_id',
        'codigo_cliente',
        'numero_cuenta',
        'monto',
        'fecha_pago',
        'fecha_sugerida_pago',
        'estado',
        'registro_id',
        'reclamado_por_usuario_id',
        'reclamado_en',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'fecha_sugerida_pago' => 'date',
        'reclamado_en' => 'datetime',
        'monto' => 'decimal:2',
    ];

    public function importacion()
    {
        return $this->belongsTo(ColocacionImportacion::class, 'importacion_id');
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }

    public function registro()
    {
        return $this->belongsTo(Registro::class, 'registro_id');
    }

    public function reclamadoPorUsuario()
    {
        return $this->belongsTo(User::class, 'reclamado_por_usuario_id');
    }
}
