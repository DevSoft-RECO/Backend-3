<?php

namespace App\Http\Controllers;

use App\Models\SolicitudApoyo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    /**
     * List all solicitudes with general filters for audit.
     */
    public function index(Request $request)
    {
        $query = SolicitudApoyo::with(['comunidad.municipio', 'tipoApoyo']);

        // Filter by ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
            return response()->json($query->paginate(20));
        }

        // Filter by Agency
        if ($request->filled('agencia_id')) {
            $query->where('agencia_id', $request->agencia_id);
        }

        // Filter by Status
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filter by Date Range (Event Start)
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_evento_inicio', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_evento_inicio', '<=', $request->fecha_fin);
        }

        // Ordering: defaults to newest first for audit overview
        $query->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 15);
        return response()->json($query->paginate($perPage));
    }

    /**
     * Get a catalog of unique agency IDs currently in the solicitudes table.
     */
    public function agenciasCatalog()
    {
        $agencias = SolicitudApoyo::select('agencia_id')
            ->distinct()
            ->orderBy('agencia_id', 'asc')
            ->get();

        return response()->json($agencias);
    }
}
