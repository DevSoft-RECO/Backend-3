<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class HistorialRegistro extends Model
{
    protected $table = 'cartilla_historial_registros';

    protected $fillable = [
        'registro_id',
        'usuario_id',
        'nombre_usuario',
        'estado_cambio',
        'snapshot',
        'ejecutado_en',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'ejecutado_en' => 'datetime',
    ];

    public function registro()
    {
        return $this->belongsTo(Registro::class, 'registro_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
