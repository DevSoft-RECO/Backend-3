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
}
