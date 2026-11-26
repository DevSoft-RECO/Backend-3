<?php

namespace App\Models\Ruleta;

use Illuminate\Database\Eloquent\Model;

class RuletaPremio extends Model
{
    protected $table = 'ruleta_premios';

    protected $fillable = [
        'ruleta_id',
        'nombre',
        'imagen_path',
        'modo_visualizacion',
        'probabilidad',
        'stock_inicial',
        'stock_actual',
        'stock_ilimitado',
        'es_premio',
        'orden',
        'activo',
    ];

    protected $casts = [
        'probabilidad' => 'float',
        'stock_inicial' => 'integer',
        'stock_actual' => 'integer',
        'stock_ilimitado' => 'boolean',
        'es_premio' => 'boolean',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    protected $appends = ['disponible', 'imagen_url'];

    public function getDisponibleAttribute(): bool
    {
        return $this->activo && ($this->stock_ilimitado || $this->stock_actual > 0);
    }

    public function getImagenUrlAttribute(): ?string
    {
        if (!$this->imagen_path) {
            return null;
        }
        return url($this->imagen_path);
    }

    public function ruleta()
    {
        return $this->belongsTo(Ruleta::class, 'ruleta_id');
    }

    public function giros()
    {
        return $this->hasMany(RuletaGiro::class, 'premio_id');
    }
}
