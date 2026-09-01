<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ColocacionImportacion extends Model
{
    protected $table = 'cartilla_colocaciones_importaciones';

    protected $fillable = [
        'usuario_id',
        'nombre_usuario',
        'nombre_archivo',
        'total_filas',
        'filas_elegibles',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function pagos()
    {
        return $this->hasMany(ColocacionPago::class, 'importacion_id');
    }
}
