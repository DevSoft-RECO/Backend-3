<?php

namespace App\Http\Controllers;

use App\Models\TipoApoyo;
use Illuminate\Http\Request;

class TipoApoyoController extends Controller
{
    public function index()
    {
        return response()->json(TipoApoyo::where('activo', true)->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|unique:tipos_apoyo,nombre',
            'activo' => 'boolean'
        ]);

        $tipo = TipoApoyo::create($data);

        return response()->json($tipo, 201);
    }

    public function update(Request $request, TipoApoyo $tipo)
    {
        // 'tipo' parameter comes from route {tipo} binding. Ensure proper naming in route.
        // Assuming route is /tipos-apoyo/{tipo}
        // Unique validation needs to ignore current ID
        $request->validate([
            'nombre' => 'required|string|unique:tipos_apoyo,nombre,'.$tipo->id,
            'activo' => 'boolean'
        ]);

        $tipo->update($request->only(['nombre', 'activo']));

        return response()->json($tipo);
    }

    public function destroy($id)
    {
        $tipo = TipoApoyo::findOrFail($id);

        // Verificar uso
        if ($tipo->solicitudes()->exists()) {
             return response()->json(['error' => 'No se puede eliminar: Hay solicitudes usando este tipo de apoyo.'], 409);
        }

        $tipo->delete();

        return response()->json(['message' => 'Tipo de apoyo eliminado']);
    }
}
