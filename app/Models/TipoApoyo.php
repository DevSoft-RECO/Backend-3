<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoApoyo extends Model
{
    protected $table = 'tipos_apoyo';
    protected $fillable = ['nombre', 'activo'];

    public function solicitudes()
    {
        return $this->hasMany(SolicitudApoyo::class, 'tipo_apoyo_id');
    }
}
