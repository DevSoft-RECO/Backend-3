<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\EstadoSolicitud;

class SolicitudApoyo extends Model
{
    protected $table = 'solicitudes_apoyo';

    protected $fillable = [
        'estado', 'motivo_rechazo',
        'fecha_solicitud', 'fecha_evento', 'nombre_solicitante', 'telefono',
        'nombre_contacto', 'comunidad_id', 'comentario_solicitud', 'path_documento_adjunto',
        'usuario_creacion_id', 'agencia_id',
        'comentario_gestion', 'usuario_gestion_id',
        'responsable_asignado', 'path_documento_firmado', 'monto', 'tipo_apoyo_id', 'usuario_aprobacion_id',
        'path_foto_entrega', 'path_foto_conocimiento'
    ];

    protected $casts = [
        'estado' => EstadoSolicitud::class, // Casteo automático al Enum
        'fecha_solicitud' => 'date',
        'fecha_evento' => 'date',
    ];

    // Relaciones
    public function comunidad() {
        return $this->belongsTo(Comunidad::class);
    }

    public function tipoApoyo() {
        return $this->belongsTo(TipoApoyo::class);
    }
}
