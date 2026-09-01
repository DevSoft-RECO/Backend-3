<?php

namespace App\Enums\Cartilla;

enum AccionParticipante: string {
    case CreditoNuevo = 'CREDITO_NUEVO';
    case PlazoFijo    = 'PLAZO_FIJO';
    case Motocicleta  = 'MOTOCICLETA';
    case PagoPuntual  = 'PAGO_PUNTUAL';
}
