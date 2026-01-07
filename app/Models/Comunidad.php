<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comunidad extends Model
{
    protected $table = 'comunidades';
    protected $fillable = ['municipio_id', 'nombre'];

    public function municipio() {
        return $this->belongsTo(Municipio::class);
    }
}
