<?php

namespace App\Enums;

enum EstadoSolicitud: string {
    case Solicitado = 'SOLICITADO';
    case EnGestion = 'EN_GESTION';
    case Aprobado = 'APROBADO';
    case Finalizado = 'FINALIZADO';
    case Rechazado = 'RECHAZADO';
}
