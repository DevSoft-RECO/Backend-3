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
        $hasAdminPermission = $user->hasPermissionTo('cartilla_mercadeo');

        if (!$isSuperAdmin && !$hasAdminPermission) {
            $agenciaCodigo = $user->agencia_id ?? $user->idagencia;
            $agenciaId = \App\Models\Cartilla\Agencia::where('codigo', $agenciaCodigo)->value('id');
            $query->where(function($q) use ($agenciaCodigo, $agenciaId) {
                $q->whereHas('registro.agencia', function($subQ) use ($agenciaCodigo) {
                    $subQ->where('codigo', $agenciaCodigo);
                });
                if ($agenciaId) {
                    $q->orWhere('snapshot->agencia_id', $agenciaId);
                }
            });
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
