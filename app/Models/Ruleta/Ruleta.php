<?php

namespace App\Models\Ruleta;

use App\Models\Cartilla\Agencia;
use Illuminate\Database\Eloquent\Model;

class Ruleta extends Model
{
    protected $table = 'ruletas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'agencia_o_evento',
        'agencia_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'agencia_id');
    }

    public function premios()
    {
        return $this->hasMany(RuletaPremio::class, 'ruleta_id')->orderBy('orden')->orderBy('id');
    }

    public function giros()
    {
        return $this->hasMany(RuletaGiro::class, 'ruleta_id')->latest('created_at');
    }
}
