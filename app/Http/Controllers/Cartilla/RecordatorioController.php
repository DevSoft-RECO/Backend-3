<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\Recordatorio;
use Illuminate\Http\Request;

class RecordatorioController extends Controller
{
    public function index()
    {
        return response()->json(Recordatorio::orderBy('orden')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'texto' => 'required|string|max:500',
        ]);

        $orden = Recordatorio::max('orden') + 1;
        $rec = Recordatorio::create([
            'texto' => $data['texto'],
            'orden' => $orden,
            'activo' => true,
        ]);

        return response()->json($rec, 201);
    }

    public function update(Request $request, Recordatorio $recordatorio)
    {
        $data = $request->validate([
            'texto' => 'required|string|max:500',
            'activo' => 'required|boolean',
        ]);

        $recordatorio->update($data);
        return response()->json($recordatorio);
    }

    public function destroy(Recordatorio $recordatorio)
    {
        $recordatorio->delete();
        return response()->json(['msg' => 'Recordatorio eliminado']);
    }

    public function reordenar(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:cartilla_recordatorios,id',
        ]);

        foreach ($data['ids'] as $index => $id) {
            Recordatorio::where('id', $id)->update(['orden' => $index + 1]);
        }

        return response()->json(['msg' => 'Orden de recordatorios actualizado']);
    }
}
