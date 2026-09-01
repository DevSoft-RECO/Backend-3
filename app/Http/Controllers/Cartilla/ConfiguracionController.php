<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\Configuracion;
use App\Models\Cartilla\HistorialConfiguracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $configs = Configuracion::all()->pluck('valor', 'clave');
        return response()->json($configs);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'clave' => 'required|in:mecanica,alertas_agencia,alertas_central,info_evento',
            'valor' => 'required|array',
        ]);

        return DB::transaction(function () use ($data, $user) {
            $config = Configuracion::where('clave', $data['clave'])->first();
            $valorAnterior = $config ? $config->valor : null;

            Configuracion::updateOrCreate(
                ['clave' => $data['clave']],
                ['valor' => $data['valor']]
            );

            HistorialConfiguracion::create([
                'usuario_id'      => $user->id,
                'nombre_usuario'  => $user->name ?? $user->username,
                'seccion'         => $data['clave'],
                'resumen'         => "Actualización de configuración en sección '{$data['clave']}'",
                'valor_anterior'  => $valorAnterior,
                'valor_nuevo'     => $data['valor'],
            ]);

            return response()->json(['msg' => 'Configuración actualizada correctamente']);
        });
    }

    public function historial(Request $request)
    {
        $query = HistorialConfiguracion::query();

        if ($request->filled('seccion')) {
            $query->where('seccion', $request->seccion);
        }

        $query->orderBy('created_at', 'desc');
        $perPage = $request->input('per_page', 15);

        return response()->json($query->paginate($perPage));
    }
}
