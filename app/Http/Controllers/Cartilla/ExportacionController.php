<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\Registro;
use App\Models\Cartilla\MovimientoInventario;
use App\Models\Cartilla\HistorialRegistro;
use App\Models\Cartilla\HistorialInventario;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportacionController extends Controller
{
    public function exportarRegistros(Request $request)
    {
        $query = Registro::with(['agencia']);

        $filename = 'cartilla_registros_' . now()->format('Ymd_His') . '.csv';
        return $this->streamCsv($filename, [
            'ID', 'Codigo', 'Agencia', 'Colaborador', 'Codigo Cliente', 'Accion', 'Operacion', 'Monto', 'Cuenta', 'Stickers', 'Nueva Cartilla', 'Completada', 'Sorteo', 'Promocional', 'Notas', 'Fecha'
        ], function($file) use ($query) {
            $query->chunk(500, function($registros) use ($file) {
                foreach ($registros as $r) {
                    fputcsv($file, $this->encodeRow([
                        $r->id,
                        $r->codigo,
                        $r->agencia->nombre ?? '-',
                        $r->nombre_colaborador,
                        $r->codigo_cliente,
                        $r->accion,
                        $r->tipo_operacion ?? '-',
                        $r->monto,
                        $r->numero_cuenta ?? '-',
                        $r->stickers,
                        $r->cartilla_nueva ? 'SI' : 'NO',
                        $r->cartilla_completada ? 'SI' : 'NO',
                        $r->sorteo ? 'SI' : 'NO',
                        $r->promocional_entregado ?? '-',
                        $r->notas ?? '-',
                        $r->created_at->format('d/m/Y H:i:s'),
                    ]));
                }
            });
        });
    }

    public function exportarHistorialRegistros(Request $request)
    {
        $query = HistorialRegistro::with(['registro']);

        $filename = 'cartilla_historial_registros_' . now()->format('Ymd_His') . '.csv';
        return $this->streamCsv($filename, [
            'ID', 'Registro ID', 'Codigo Registro', 'Usuario Cambio', 'Estado Cambio', 'Fecha Ejecucion', 'Snapshot JSON'
        ], function($file) use ($query) {
            $query->chunk(500, function($historial) use ($file) {
                foreach ($historial as $h) {
                    fputcsv($file, $this->encodeRow([
                        $h->id,
                        $h->registro_id,
                        $h->registro->codigo ?? '-',
                        $h->nombre_usuario,
                        $h->estado_cambio,
                        $h->ejecutado_en->format('d/m/Y H:i:s'),
                        json_encode($h->snapshot),
                    ]));
                }
            });
        });
    }

    public function exportarMovimientos(Request $request)
    {
        $query = MovimientoInventario::with(['agencia', 'agenciaDestino']);

        $filename = 'cartilla_inventario_movimientos_' . now()->format('Ymd_His') . '.csv';
        return $this->streamCsv($filename, [
            'ID', 'Codigo', 'Agencia Origen', 'Usuario', 'Recurso', 'Promocional', 'Tipo Movimiento', 'Cantidad', 'Alcance', 'Registro', 'Agencia Destino', 'Cliente Reposicion', 'Detalle', 'Manual', 'Fecha'
        ], function($file) use ($query) {
            $query->chunk(500, function($movs) use ($file) {
                foreach ($movs as $m) {
                    fputcsv($file, $this->encodeRow([
                        $m->id,
                        $m->codigo,
                        $m->agencia->nombre ?? 'Central',
                        $m->nombre_usuario,
                        $m->recurso,
                        $m->nombre_promocional ?? '-',
                        $m->tipo_movimiento,
                        $m->cantidad,
                        $m->alcance,
                        $m->codigo_registro ?? '-',
                        $m->agenciaDestino->nombre ?? '-',
                        $m->codigo_cliente ?? '-',
                        $m->detalle ?? '-',
                        $m->es_manual ? 'SI' : 'NO',
                        $m->created_at->format('d/m/Y H:i:s'),
                    ]));
                }
            });
        });
    }

    public function exportarHistorialInventario(Request $request)
    {
        $query = HistorialInventario::with(['movimiento']);

        $filename = 'cartilla_historial_inventario_' . now()->format('Ymd_His') . '.csv';
        return $this->streamCsv($filename, [
            'ID', 'Movimiento ID', 'Codigo Movimiento', 'Usuario', 'Estado Cambio', 'Restaurado', 'Fecha Ejecucion', 'Snapshot JSON'
        ], function($file) use ($query) {
            $query->chunk(500, function($historial) use ($file) {
                foreach ($historial as $h) {
                    fputcsv($file, $this->encodeRow([
                        $h->id,
                        $h->movimiento_id,
                        $h->movimiento->codigo ?? '-',
                        $h->nombre_usuario,
                        $h->estado_cambio,
                        $h->restaurado ? 'SI' : 'NO',
                        $h->ejecutado_en->format('d/m/Y H:i:s'),
                        json_encode($h->snapshot),
                    ]));
                }
            });
        });
    }

    private function streamCsv($filename, array $headersRow, callable $callback)
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=Windows-1252',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Cache-Control'       => 'no-store, no-cache',
        ];

        return new StreamedResponse(function() use ($headersRow, $callback) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $this->encodeRow($headersRow));
            $callback($file);
            fclose($file);
        }, 200, $headers);
    }

    private function encodeRow(array $row): array
    {
        return array_map(function ($value) {
            return is_string($value)
                ? mb_convert_encoding($value, 'Windows-1252', 'UTF-8')
                : $value;
        }, $row);
    }
}
