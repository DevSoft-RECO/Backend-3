<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\NotaRapida;
use Illuminate\Http\Request;

class NotaRapidaController extends Controller
{
    public function index()
    {
        return response()->json(NotaRapida::orderBy('orden')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'texto' => 'required|string|max:255',
        ]);

        $orden = NotaRapida::max('orden') + 1;
        $nota = NotaRapida::create([
            'texto' => $data['texto'],
            'orden' => $orden,
            'activo' => true,
        ]);

        return response()->json($nota, 201);
    }

    public function update(Request $request, NotaRapida $notas_rapida)
    {
        $data = $request->validate([
            'texto' => 'required|string|max:255',
            'activo' => 'required|boolean',
        ]);

        $notas_rapida->update($data);
        return response()->json($notas_rapida);
    }

    public function destroy(NotaRapida $notas_rapida)
    {
        $notas_rapida->delete();
        return response()->json(['msg' => 'Nota rápida eliminada']);
    }

    public function reordenar(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:cartilla_notas_rapidas,id',
        ]);

        foreach ($data['ids'] as $index => $id) {
            NotaRapida::where('id', $id)->update(['orden' => $index + 1]);
        }

        return response()->json(['msg' => 'Orden de notas rápidas actualizado']);
    }
}
