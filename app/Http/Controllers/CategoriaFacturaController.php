<?php

namespace App\Http\Controllers;

use App\Models\CategoriaFactura;
use Illuminate\Http\Request;

class CategoriaFacturaController extends Controller
{
    public function index()
    {
        return CategoriaFactura::orderBy('nombre')->get();
    }

    public function store(Request $request)
    {
        // Validación básica
        $request->validate(['nombre' => 'required|string|max:255']);

        $cat = CategoriaFactura::create($request->all());
        return response()->json(['msg' => 'Categoría creada', 'data' => $cat], 201);
    }

    public function update(Request $request, CategoriaFactura $categoria)
    {
        $request->validate(['nombre' => 'required|string|max:255']);

        $categoria->update($request->all());
        return response()->json(['msg' => 'Categoría actualizada', 'data' => $categoria]);
    }

    public function destroy(CategoriaFactura $categoria)
    {
        // Opcional: Validar si tiene facturas asociadas antes de borrar
        if ($categoria->facturas()->exists()) {
            return response()->json(['error' => 'No se puede eliminar porque tiene facturas asociadas.'], 409);
        }

        $categoria->delete();
        return response()->json(['msg' => 'Categoría eliminada']);
    }
}
