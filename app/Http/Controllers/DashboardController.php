<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SolicitudApoyo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Estadísticas por Estado
        // Agrupamos por estado y contamos, también queremos los IDs para el modal
        $statsRaw = SolicitudApoyo::select('estado', DB::raw('count(*) as total'), DB::raw('GROUP_CONCAT(id) as ids'))
            ->groupBy('estado')
            ->get();

        // Formateamos para el frontend
        $stats = [
            'SOLICITADO' => ['count' => 0, 'ids' => []],
            'EN_GESTION' => ['count' => 0, 'ids' => []],
            'APROBADO' => ['count' => 0, 'ids' => []],
            'FINALIZADO' => ['count' => 0, 'ids' => []],
            'RECHAZADO' => ['count' => 0, 'ids' => []],
        ];

        foreach ($statsRaw as $row) {
            $key = $row->estado instanceof \BackedEnum ? $row->estado->value : $row->estado;
            if (isset($stats[$key])) {
                $stats[$key]['count'] = $row->total;
                $stats[$key]['ids'] = $row->ids ? explode(',', $row->ids) : [];
            }
        }

        // 2. Próximos Eventos
        // Eventos a partir de hoy, ordenados por fecha ascendente
        $upcomingEvents = SolicitudApoyo::whereDate('fecha_evento', '>=', Carbon::today())
            ->whereIn('estado', ['SOLICITADO', 'EN_GESTION', 'APROBADO']) // Solo activos
            ->orderBy('fecha_evento', 'asc')
            ->take(10) // Limitamos a 10
            ->with(['comunidad.municipio']) // Eager Load
            ->get() // Obtenemos todas las columnas o especificar las existentes
            ->map(function ($event) {
                // Determinar el Lugar (Comunidad, Municipio)
                $lugar = 'N/A';
                if ($event->comunidad) {
                    $lugar = $event->comunidad->nombre;
                    if ($event->comunidad->municipio) {
                        $lugar .= ', ' . $event->comunidad->municipio->nombre;
                    }
                }

                return [
                    'id' => $event->id,
                    'fecha_evento' => $event->fecha_evento, // Es objeto Carbon gracias al cast
                    'nombre_solicitante' => $event->nombre_solicitante,
                    'estado' => $event->estado,
                    'lugar' => $lugar
                ];
            });

        // 3. Próximos Eventos (Mi Agencia)
        // Obtenemos el usuario autenticado para saber su agencia
        $user = request()->user();
        $agenciaId = $user->idagencia ?? null;

        $myUpcomingEvents = [];

        if ($agenciaId) {
            $myUpcomingEvents = SolicitudApoyo::whereDate('fecha_evento', '>=', Carbon::today())
                ->whereIn('estado', ['SOLICITADO', 'EN_GESTION', 'APROBADO'])
                ->where('agencia_id', $agenciaId) // Filtro exclusivo por agencia
                ->orderBy('fecha_evento', 'asc')
                ->take(10)
                ->with(['comunidad.municipio'])
                ->get()
                ->map(function ($event) {
                    // Reutilizamos la lógica de formateo de lugar
                    $lugar = 'N/A';
                    if ($event->comunidad) {
                        $lugar = $event->comunidad->nombre;
                        if ($event->comunidad->municipio) {
                            $lugar .= ', ' . $event->comunidad->municipio->nombre;
                        }
                    }

                    return [
                        'id' => $event->id,
                        'fecha_evento' => $event->fecha_evento,
                        'nombre_solicitante' => $event->nombre_solicitante,
                        'estado' => $event->estado,
                        'lugar' => $lugar
                    ];
                });
        }

        return response()->json([
            'stats' => $stats,
            'upcoming_events' => $upcomingEvents,
            'my_upcoming_events' => $myUpcomingEvents
        ]);
    }
}
