<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\Promocional;
use App\Models\Cartilla\InventarioStock;
use Illuminate\Http\Request;

class PromocionalController extends Controller
{
    public function index()
    {
        return response()->json(Promocional::orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255|unique:cartilla_promocionales,nombre',
        ]);

        $promo = Promocional::create($data);

        // Inicializar stock en cero para central y todas las agencias existentes
        InventarioStock::updateOrCreate([
            'agencia_id' => null,
            'recurso' => 'PROMOCIONAL',
            'nombre_promocional' => $promo->nombre,
        ], ['cantidad' => 0]);

        $agencias = \App\Models\Cartilla\Agencia::all();
        foreach ($agencias as $ag) {
            InventarioStock::updateOrCreate([
                'agencia_id' => $ag->id,
                'recurso' => 'PROMOCIONAL',
                'nombre_promocional' => $promo->nombre,
            ], ['cantidad' => 0]);
        }

        return response()->json(['msg' => 'Promocional creado con éxito', 'data' => $promo], 201);
    }

    public function update(Request $request, Promocional $promocional)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255|unique:cartilla_promocionales,nombre,' . $promocional->id,
            'activo' => 'required|boolean',
        ]);

        // Si se intenta desactivar, validar que el stock acumulado sea cero
        if ($promocional->activo && !$data['activo']) {
            $totalStock = InventarioStock::where('recurso', 'PROMOCIONAL')
                ->where('nombre_promocional', $promocional->nombre)
                ->sum('cantidad');

            if ($totalStock > 0) {
                return response()->json(['error' => 'No se puede desactivar un promocional que tiene stock vigente en alguna agencia o central.'], 409);
            }
        }

        $promocional->update($data);
        return response()->json(['msg' => 'Promocional actualizado', 'data' => $promocional]);
    }

    public function destroy($id)
    {
        $promocional = Promocional::findOrFail($id);

        // Validar si tiene stock físico > 0 por nombre_promocional
        $totalStock = InventarioStock::where('recurso', 'PROMOCIONAL')
            ->where('nombre_promocional', $promocional->nombre)
            ->sum('cantidad');

        if ($totalStock > 0) {
            return response()->json(['error' => 'No se puede eliminar porque tiene stock físico.'], 409);
        }

        // Si tiene movimientos históricos de inventario (excluyendo ajustes vacíos si los hubiere)
        $hasMovements = \App\Models\Cartilla\MovimientoInventario::where('recurso', 'PROMOCIONAL')
            ->where('nombre_promocional', $promocional->nombre)
            ->where('cantidad', '>', 0)
            ->exists();

        if ($hasMovements) {
            return response()->json(['error' => 'No se puede eliminar permanentemente porque tiene historial de movimientos de inventario. Considere desactivarlo.'], 409);
        }

        // Si ha sido entregado a un asociado en registros
        $hasRegistros = \App\Models\Cartilla\Registro::whereNotNull('promocional_entregado')
            ->where('promocional_entregado', '!=', '')
            ->where('promocional_entregado', $promocional->nombre)
            ->exists();
        if ($hasRegistros) {
            return response()->json(['error' => 'No se puede eliminar permanentemente porque ha sido entregado en registros de asociados. Considere desactivarlo.'], 409);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($promocional) {
            // Limpiar registros de stock en 0 asociados a este promocional
            InventarioStock::where('recurso', 'PROMOCIONAL')
                ->where('nombre_promocional', $promocional->nombre)
                ->delete();

            // Eliminar directamente de la base de datos por ID
            \App\Models\Cartilla\Promocional::where('id', $promocional->id)->delete();
        });

        return response()->json(['msg' => 'Promocional eliminado']);
    }
}
