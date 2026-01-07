<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    protected $table = 'municipios';
    protected $fillable = ['departamento_id', 'nombre'];

    public function departamento() {
        return $this->belongsTo(Departamento::class);
    }

    public function comunidades() {
        return $this->hasMany(Comunidad::class);
    }
}
