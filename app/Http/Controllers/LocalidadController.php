<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Municipio;
use App\Models\Comunidad;
use Illuminate\Http\Request;

class LocalidadController extends Controller
{
    // --- DEPARTAMENTOS ---
    public function indexDepartamentos()
    {
        return response()->json(Departamento::all());
    }

    public function storeDepartamento(Request $request)
    {
        $data = $request->validate(['nombre' => 'required|string|unique:departamentos,nombre']);
        $depto = Departamento::create($data);
        return response()->json($depto, 201);
    }

    public function updateDepartamento(Request $request, Departamento $departamento)
    {
        $request->validate(['nombre' => 'required|string|unique:departamentos,nombre,'.$departamento->id]);
        $departamento->update(['nombre' => $request->nombre]);
        return response()->json($departamento);
    }

    public function destroyDepartamento(Departamento $departamento)
    {
        if ($departamento->municipios()->exists()) {
             return response()->json(['error' => 'No se puede eliminar: Tiene municipios asociados.'], 409);
        }
        $departamento->delete();
        return response()->json(['message' => 'Departamento eliminado']);
    }

    // --- MUNICIPIOS ---
    public function indexMunicipios(Request $request)
    {
        $query = Municipio::query();
        if ($request->has('departamento_id')) {
            $query->where('departamento_id', $request->departamento_id);
        }
        return response()->json($query->get()); // O get() si no son muchos
    }

    public function storeMunicipio(Request $request)
    {
        $data = $request->validate([
            'departamento_id' => 'required|exists:departamentos,id',
            'nombre' => 'required|string'
        ]);
        $muni = Municipio::create($data);
        return response()->json($muni, 201);
    }

    public function updateMunicipio(Request $request, Municipio $municipio)
    {
        $request->validate(['nombre' => 'required|string']);
        $municipio->update(['nombre' => $request->nombre]);
        return response()->json($municipio);
    }

    public function destroyMunicipio(Municipio $municipio)
    {
        if ($municipio->comunidades()->exists()) {
             return response()->json(['error' => 'No se puede eliminar: Tiene comunidades asociadas.'], 409);
        }
        $municipio->delete();
        return response()->json(['message' => 'Municipio eliminado']);
    }

    // --- COMUNIDADES ---
    public function indexComunidades(Request $request)
    {
        $query = Comunidad::query();
        if ($request->has('municipio_id')) {
            $query->where('municipio_id', $request->municipio_id);
        }
        return response()->json($query->get());
    }

    public function storeComunidad(Request $request)
    {
        $data = $request->validate([
            'municipio_id' => 'required|exists:municipios,id',
            'nombre' => 'required|string'
        ]);
        $comu = Comunidad::create($data);
        return response()->json($comu, 201);
    }

    public function updateComunidad(Request $request, Comunidad $comunidad)
    {
        $request->validate(['nombre' => 'required|string']);
        $comunidad->update(['nombre' => $request->nombre]);
        return response()->json($comunidad);
    }

    public function destroyComunidad(Comunidad $comunidad)
    {
        // Verificar uso en solicitudes (si existe la relación)
        if ($comunidad->solicitudes()->exists()) {
             return response()->json(['error' => 'No se puede eliminar: Tiene solicitudes asociadas.'], 409);
        }
        $comunidad->delete();
        return response()->json(['message' => 'Comunidad eliminada']);
    }
}
