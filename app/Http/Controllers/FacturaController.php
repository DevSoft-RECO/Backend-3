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

        if ($request->filled('numero')) {
            $query->where('numero_factura', 'like', "%{$request->numero}%");
        }

        if ($request->filled('serie')) {
            $query->where('numero_serie', 'like', "%{$request->serie}%");
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_factura', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_factura', '<=', $request->fecha_fin);
        }

        return $query->latest()->paginate(20);
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
        // Replicamos los filtros para exportar lo que se ve (o todo)
        $query = Factura::with('categoria');

        if ($request->filled('numero')) {
            $query->where('numero_factura', 'like', "%{$request->numero}%");
        }
        // ... otros filtros si se desean ...

        $facturas = $query->get(); // Obtener todo para CSV (cuidadado con memoria si son millones, chunks es mejor)

        $filename = "facturas_" . date('Ymd_His') . ".csv";

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function() use ($facturas) {
            $file = fopen('php://output', 'w');

            // Encabezados CSV
            fputcsv($file, [
                'ID', 'No. Factura', 'Serie', 'Categoría', 'Fecha', 'Monto', 'Emisor', 'NIT', 'Descripción'
            ]);

            foreach ($facturas as $f) {
                fputcsv($file, [
                    $f->id,
                    $f->numero_factura,
                    $f->numero_serie,
                    $f->categoria->nombre ?? '-',
                    $f->fecha_factura->format('d/m/Y'),
                    $f->monto,
                    $f->nombre_emisor,
                    $f->nit_emisor,
                    $f->descripcion
                ]);
            }
            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
