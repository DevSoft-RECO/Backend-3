<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_factura',
        'numero_serie',
        'categoria_id',
        'fecha_factura',
        'monto',
        'descripcion',
        'nombre_emisor',
        'nit_emisor',
    ];

    protected $casts = [
        'fecha_factura' => 'date',
        'monto' => 'decimal:2',
    ];

    public function categoria()
    {
        return $this->belongsTo(CategoriaFactura::class, 'categoria_id');
    }
}
