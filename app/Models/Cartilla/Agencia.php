<?php

namespace App\Models\Cartilla;

use Illuminate\Database\Eloquent\Model;

class Agencia extends Model
{
    protected $table = 'cartilla_agencias';

    protected $fillable = [
        'codigo',
        'nombre',
        'area_financiera',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function registros()
    {
        return $this->hasMany(Registro::class, 'agencia_id');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class, 'agencia_id');
    }

    public function stocks()
    {
        return $this->hasMany(InventarioStock::class, 'agencia_id');
    }
}
