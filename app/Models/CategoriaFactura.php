<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaFactura extends Model
{
    use HasFactory;

    protected $table = 'categorias_facturas';

    protected $fillable = [
        'nombre',
    ];

    public function facturas()
    {
        return $this->hasMany(Factura::class, 'categoria_id');
    }
}
