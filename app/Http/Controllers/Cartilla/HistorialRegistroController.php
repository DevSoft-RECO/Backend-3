<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\HistorialRegistro;
use Illuminate\Http\Request;

class HistorialRegistroController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = HistorialRegistro::with(['registro.agencia']);

        $isSuperAdmin = $user->hasRole('Super Admin');
        $hasAdminPermission = $user->hasPermissionTo('admin_promocion');

        if (!$isSuperAdmin && !$hasAdminPermission) {
            $agenciaCodigo = $user->agencia_id ?? $user->idagencia;
            $agenciaObj = \App\Models\Cartilla\Agencia::where('codigo', $agenciaCodigo)->first() ?? \App\Models\Cartilla\Agencia::find($agenciaCodigo);
            $agenciaId = $agenciaObj ? $agenciaObj->id : null;
            
            if ($agenciaId) {
                $query->where(function($q) use ($agenciaId) {
                    $q->whereHas('registro', function($subQ) use ($agenciaId) {
                        $subQ->where('agencia_id', $agenciaId);
                    })->orWhere('snapshot->agencia_id', $agenciaId);
                });
            } else {
                $query->where('id', '<', 0); // Forzar 0 resultados si no tiene agencia asignada
            }
        }

        if ($request->filled('estado_cambio')) {
            $query->where('estado_cambio', $request->estado_cambio);
        }

        if ($request->filled('registro_codigo')) {
            $codigo = $request->registro_codigo;
            $query->where(function($q) use ($codigo) {
                $q->whereHas('registro', function($subQ) use ($codigo) {
                    $subQ->where('codigo', 'like', "%{$codigo}%");
                })->orWhere('snapshot->codigo', 'like', "%{$codigo}%");
            });
        }

        $query->orderBy('ejecutado_en', 'desc');
        $perPage = $request->input('per_page', 15);

        return response()->json($query->paginate($perPage));
    }
}
