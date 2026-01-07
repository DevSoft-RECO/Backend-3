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
}
