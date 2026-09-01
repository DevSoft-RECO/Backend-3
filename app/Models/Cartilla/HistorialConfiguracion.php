<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class HistorialConfiguracion extends Model
{
    protected $table = 'cartilla_historial_configuracion';

    protected $fillable = [
        'usuario_id',
        'nombre_usuario',
        'seccion',
        'resumen',
        'valor_anterior',
        'valor_nuevo',
    ];

    protected $casts = [
        'valor_anterior' => 'array',
        'valor_nuevo' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
