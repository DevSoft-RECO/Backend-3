<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\EstadoSolicitud;

class SolicitudApoyo extends Model
{
    protected $table = 'solicitudes_apoyo';

    protected $fillable = [
        'estado', 'motivo_rechazo', 'fecha_rechazo', 'usuario_rechazo_id', 'nombre_usuario_rechazo',
        'fecha_solicitud', 'fecha_evento_inicio', 'fecha_evento_fin', 'nombre_solicitante', 'telefono',
        'nombre_contacto', 'comunidad_id', 'comentario_solicitud', 'path_documento_adjunto',
        'usuario_creacion', 'agencia_id',
        'comentario_gestion', 'usuario_gestion_id', 'nombre_usuario_gestion', 'fecha_inicio_gestion',
        'responsable_asignado', 'path_documento_firmado', 'monto', 'tipo_apoyo_id', 'usuario_aprobacion_id', 'nombre_usuario_aprobacion', 'fecha_aprobacion', 'comentario_aprobacion',
        'path_documento_evidencia'
    ];

    protected $casts = [
        'estado' => EstadoSolicitud::class, // Casteo automático al Enum
        'fecha_solicitud' => 'date',
        'fecha_evento_inicio' => 'date',
        'fecha_evento_fin' => 'date',
        'fecha_inicio_gestion' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_rechazo' => 'datetime',
    ];

    // Relaciones
    public function comunidad() {
        return $this->belongsTo(Comunidad::class);
    }

    public function tipoApoyo() {
        return $this->belongsTo(TipoApoyo::class);
    }
}
