<?php

namespace App\Models\Ruleta;

use Illuminate\Database\Eloquent\Model;

class RuletaGiro extends Model
{
    protected $table = 'ruleta_giros';

    public $timestamps = false;

    protected $fillable = [
        'ruleta_id',
        'premio_id',
        'premio_nombre',
        'es_premio',
        'stock_descontado',
        'dispositivo',
        'agencia_nombre',
        'observacion_ganador',
        'created_at',
    ];

    protected $casts = [
        'es_premio' => 'boolean',
        'stock_descontado' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function ruleta()
    {
        return $this->belongsTo(Ruleta::class, 'ruleta_id');
    }

    public function premio()
    {
        return $this->belongsTo(RuletaPremio::class, 'premio_id');
    }
}
