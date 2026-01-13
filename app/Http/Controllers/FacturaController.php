<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FacturaController extends Controller
{
    // Listar con filtros y paginación
    public function index(Request $request)
    {
        $query = Factura::with('categoria:id,nombre');

        // Búsqueda general por numero o serie
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero_factura', 'like', "%{$search}%")
                  ->orWhere('numero_serie', 'like', "%{$search}%");
            });
        }
        // Filtros específicos (opcional, si se usaran por separado)
        elseif ($request->filled('numero')) {
             $query->where('numero_factura', 'like', "%{$request->numero}%");
        }

        /*
        // Eliminamos el filtro estricto de serie si usamos search,
        // o lo dejamos como 'AND' solo si no hay 'search'
        if ($request->filled('serie') && !$request->filled('search')) {
            $query->where('numero_serie', 'like', "%{$request->serie}%");
        }
        */

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_factura', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_factura', '<=', $request->fecha_fin);
        }

        // Paginación: 10 por defecto para rapidez, o el valor solicitado
        $perPage = $request->input('per_page', 10);
        return $query->latest()->paginate($perPage);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero_factura' => 'required|string',
            'numero_serie' => 'required|string',
            'categoria_id' => 'required|exists:categorias_facturas,id',
            'fecha_factura' => 'required|date',
            'monto' => 'required|numeric',
            'descripcion' => 'nullable|string',
            'nombre_emisor' => 'nullable|string',
            'nit_emisor' => 'nullable|string',
        ]);

        $factura = Factura::create($validated);
        return response()->json(['msg' => 'Factura creada', 'data' => $factura], 201);
    }

    public function update(Request $request, Factura $factura)
    {
        $validated = $request->validate([
            'numero_factura' => 'required|string',
            'numero_serie' => 'required|string',
            'categoria_id' => 'required|exists:categorias_facturas,id',
            'fecha_factura' => 'required|date',
            'monto' => 'required|numeric',
            'descripcion' => 'nullable|string',
            'nombre_emisor' => 'nullable|string',
            'nit_emisor' => 'nullable|string',
        ]);

        $factura->update($validated);
        return response()->json(['msg' => 'Factura actualizada', 'data' => $factura]);
    }

    public function destroy(Factura $factura)
    {
        $factura->delete();
        return response()->json(['msg' => 'Factura eliminada']);
    }

    // Exportar CSV


public function exportCsv(Request $request)
{
    $query = Factura::with('categoria');

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('numero_factura', 'like', "%{$search}%")
              ->orWhere('numero_serie', 'like', "%{$search}%");
        });
    }

    if ($request->filled('fecha_inicio')) {
        $query->whereDate('fecha_factura', '>=', $request->fecha_inicio);
    }

    if ($request->filled('fecha_fin')) {
        $query->whereDate('fecha_factura', '<=', $request->fecha_fin);
    }

    $filename = 'facturas_' . now()->format('Ymd_His') . '.csv';

    $headers = [
        'Content-Type'        => 'text/csv; charset=Windows-1252',
        'Content-Disposition' => "attachment; filename=\"$filename\"",
        'Cache-Control'       => 'no-store, no-cache',
    ];

    $callback = function () use ($query) {
        $file = fopen('php://output', 'w');

        // Encabezados
        fputcsv($file, $this->encodeRow([
            'ID',
            'No. Factura',
            'Serie',
            'Categoría',
            'Fecha',
            'Monto',
            'Emisor',
            'NIT',
            'Descripción'
        ]));

        $query->chunk(500, function ($facturas) use ($file) {
            foreach ($facturas as $f) {
                fputcsv($file, $this->encodeRow([
                    $f->id,
                    $f->numero_factura,
                    $f->numero_serie,
                    $f->categoria->nombre ?? '-',
                    $f->fecha_factura->format('d/m/Y'),
                    $f->monto,
                    $f->nombre_emisor,
                    $f->nit_emisor,
                    $f->descripcion,
                ]));
            }
        });

        fclose($file);
    };

    return new StreamedResponse($callback, 200, $headers);
}

/**
 * Convierte cada valor a Windows-1252 para Excel
 */
private function encodeRow(array $row): array
{
    return array_map(function ($value) {
        return is_string($value)
            ? mb_convert_encoding($value, 'Windows-1252', 'UTF-8')
            : $value;
    }, $row);
}

}
