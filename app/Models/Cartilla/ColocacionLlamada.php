<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ColocacionLlamada extends Model
{
    protected $table = 'cartilla_colocacion_llamadas';
    
    protected $fillable = [
        'pago_id',
        'agencia_id',
        'usuario_id',
        'estado',
        'notas'
    ];

    public function pago()
    {
        return $this->belongsTo(ColocacionPago::class, 'pago_id');
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}