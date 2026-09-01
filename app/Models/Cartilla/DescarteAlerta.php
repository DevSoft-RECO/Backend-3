<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class DescarteAlerta extends Model
{
    protected $table = 'cartilla_descartes_alertas';

    protected $fillable = [
        'usuario_id',
        'clave_alerta',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
