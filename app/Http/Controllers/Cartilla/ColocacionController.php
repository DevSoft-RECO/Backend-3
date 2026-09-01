<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\ColocacionImportacion;
use App\Models\Cartilla\ColocacionPago;
use App\Models\Cartilla\Agencia;
use App\Models\Cartilla\Registro;
use App\Models\Cartilla\InventarioStock;
use App\Models\Cartilla\MovimientoInventario;
use App\Models\Cartilla\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ColocacionController extends Controller
{
    /**
     * Cargar y procesar archivo CSV de colocaciones (Mercadeo)
     */
    public function importar(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'archivo_csv' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
        ]);

        $file = $request->file('archivo_csv');
        $content = file_get_contents($file->getRealPath());

        // Quitar BOM de UTF-8 si existe
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Detectar delimitador
        $delimiter = $this->detectarDelimitador($content);
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));

        if (count($lines) < 2) {
            return response()->json(['error' => 'El archivo CSV no contiene suficientes líneas.'], 422);
        }

        // Encabezados
        $headers = array_map(function ($h) {
            return strtoupper(trim(preg_replace('/\s+/', '', $h)));
        }, str_getcsv($lines[0], $delimiter));

        $required = ['AREA_FINANCIERA', 'CLIENTE', 'NUMERODOCUMENTO', 'FECHAULTIMOPAGO', 'FECHAULTIMOPAGOCAPITAL', 'CUOTACAPITAL'];
        $indexes = [];
        foreach ($required as $req) {
            $pos = array_search($req, $headers);
            if ($pos === false) {
                return response()->json(['error' => "Falta la columna requerida: {$req} en el archivo CSV."], 422);
            }
            $indexes[$req] = $pos;
        }

        // Mapeo de agencias por area_financiera
        $agenciasMap = Agencia::pluck('id', 'area_financiera')->toArray();

        // Obtener configuración de mecánica y rango de fechas de promoción
        $mecanica = Configuracion::where('clave', 'mecanica')->first()?->valor ?? [];
        $prefijo = $mecanica['prefijo_cuenta'] ?? '126';
        $digitos = $mecanica['digitos_cuenta'] ?? 15;
        $fechaInicioPromo = !empty($mecanica['fecha_inicio']) ? $mecanica['fecha_inicio'] : null;
        $fechaFinPromo = !empty($mecanica['fecha_fin']) ? $mecanica['fecha_fin'] : null;

        return DB::transaction(function () use ($file, $user, $lines, $delimiter, $indexes, $agenciasMap, $prefijo, $digitos, $fechaInicioPromo, $fechaFinPromo) {
            // Reemplazo de carga previa: Se limpian los pagos PENDIENTES anteriores no reclamados.
            // Los pagos ya RECLAMADOS se conservan intactos por auditoría y registros asociados.
            ColocacionPago::where('estado', 'PENDIENTE')->delete();

            $totalFilas = 0;
            $filasElegibles = 0;

            $importacion = ColocacionImportacion::create([
                'usuario_id'     => $user->id,
                'nombre_usuario' => $user->name ?? $user->username,
                'nombre_archivo' => $file->getClientOriginalName(),
                'total_filas'    => 0,
                'filas_elegibles'=> 0,
            ]);

            for ($i = 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;

                $totalFilas++;
                $row = str_getcsv($line, $delimiter);

                $areaFinanciera = trim($row[$indexes['AREA_FINANCIERA']] ?? '');
                $agenciaId = $agenciasMap[$areaFinanciera] ?? $agenciasMap[intval($areaFinanciera)] ?? null;
                if (!$agenciaId) continue;

                $codigoCliente = preg_replace('/\D/', '', $row[$indexes['CLIENTE']] ?? '');
                if (empty($codigoCliente)) continue;

                $numeroCuenta = preg_replace('/\D/', '', $row[$indexes['NUMERODOCUMENTO']] ?? '');
                if (empty($numeroCuenta)) continue;

                // Si hay prefijo configurado, validar que inicie con él si tiene la longitud esperada
                if (!empty($prefijo) && strpos($numeroCuenta, $prefijo) !== 0 && strlen($numeroCuenta) === intval($digitos)) {
                    continue;
                }

                $rawFechaPago = trim($row[$indexes['FECHAULTIMOPAGO']] ?? '');
                $rawFechaSugerida = trim($row[$indexes['FECHAULTIMOPAGOCAPITAL']] ?? '');

                if (empty($rawFechaPago) || empty($rawFechaSugerida)) continue;

                // Parsear fecha flexiblemente (ej: 20260210, 20260831, 2026-02-10)
                $parseFecha = function ($str) {
                    $digitsOnly = preg_replace('/\D/', '', $str);
                    if (strlen($digitsOnly) === 8) {
                        try {
                            return Carbon::createFromFormat('Ymd', $digitsOnly)->format('Y-m-d');
                        } catch (\Exception $e) {}
                    }
                    try {
                        return Carbon::parse(trim($str))->format('Y-m-d');
                    } catch (\Exception $e) {
                        return null;
                    }
                };

                $fechaPagoStrFormatted = $parseFecha($rawFechaPago);
                $fechaSugerida = $parseFecha($rawFechaSugerida);

                if (!$fechaPagoStrFormatted || !$fechaSugerida) continue;
                if ($fechaPagoStrFormatted !== $fechaSugerida) continue; // Solo pagos puntuales

                $monto = floatval(preg_replace('/[^0-9.]/', '', $row[$indexes['CUOTACAPITAL']] ?? '0'));
                if ($monto <= 0) continue;

                // Validar rango de fechas de la promoción comparando únicamente fechas (YYYY-MM-DD)
                if (!empty($fechaInicioPromo)) {
                    $fechaInicioDate = substr($fechaInicioPromo, 0, 10);
                    if ($fechaPagoStrFormatted < $fechaInicioDate) continue;
                }
                if (!empty($fechaFinPromo)) {
                    $fechaFinDate = substr($fechaFinPromo, 0, 10);
                    if ($fechaPagoStrFormatted > $fechaFinDate) continue;
                }

                $fechaPago = $fechaPagoStrFormatted;

                // Deduplicar contra registros ya creados en cartilla_registros
                $yaRegistradoEnCartilla = Registro::where('numero_cuenta', $numeroCuenta)
                    ->where(function ($q) use ($fechaPago) {
                        $q->whereDate('source_payment_date', $fechaPago)
                          ->orWhereDate('created_at', $fechaPago);
                    })
                    ->exists();

                if ($yaRegistradoEnCartilla) continue;

                // Deduplicar contra colocaciones_pagos previas
                $yaEnPagos = ColocacionPago::where('numero_cuenta', $numeroCuenta)
                    ->where('fecha_pago', $fechaPago)
                    ->exists();

                if ($yaEnPagos) continue;

                ColocacionPago::create([
                    'importacion_id'      => $importacion->id,
                    'agencia_id'          => $agenciaId,
                    'codigo_cliente'      => $codigoCliente,
                    'numero_cuenta'       => $numeroCuenta,
                    'monto'               => $monto,
                    'fecha_pago'          => $fechaPago,
                    'fecha_sugerida_pago' => $fechaSugerida,
                    'estado'              => 'PENDIENTE',
                ]);

                $filasElegibles++;
            }

            $importacion->update([
                'total_filas'     => $totalFilas,
                'filas_elegibles' => $filasElegibles,
            ]);

            return response()->json([
                'msg'             => 'Archivo CSV importado exitosamente',
                'data'            => $importacion,
                'total_filas'     => $totalFilas,
                'filas_elegibles' => $filasElegibles,
            ], 201);
        });
    }

    /**
     * Listar pagos automáticos pendientes de reclamo
     */
    public function pendientes(Request $request)
    {
        $user = $request->user();
        $query = ColocacionPago::with(['agencia'])
            ->where('estado', 'PENDIENTE');

        // Filtrar por rango de fechas de la promoción configurada
        $mecanica = Configuracion::where('clave', 'mecanica')->first()?->valor ?? [];
        if (!empty($mecanica['fecha_inicio'])) {
            $fechaInicioSimple = explode(' ', $mecanica['fecha_inicio'])[0];
            $query->whereDate('fecha_pago', '>=', $fechaInicioSimple);
        }
        if (!empty($mecanica['fecha_fin'])) {
            $fechaFinSimple = explode(' ', $mecanica['fecha_fin'])[0];
            $query->whereDate('fecha_pago', '<=', $fechaFinSimple);
        }

        // Aislamiento por agencia si no es admin de Mercadeo
        $isSuperAdmin = $user->hasRole('Super Admin');
        $hasAdminPermission = $user->hasPermissionTo('cartilla_mercadeo');

        if (!$isSuperAdmin && !$hasAdminPermission) {
            $agenciaCodigo = $user->agencia_id ?? $user->idagencia;
            $query->whereHas('agencia', function ($q) use ($agenciaCodigo) {
                $q->where('codigo', $agenciaCodigo);
            });
        }

        if ($request->filled('agencia_id')) {
            $query->where('agencia_id', $request->agencia_id);
        }

        if ($request->filled('codigo_cliente')) {
            $query->where('codigo_cliente', 'like', "%{$request->codigo_cliente}%");
        }

        if ($request->filled('numero_cuenta')) {
            $query->where('numero_cuenta', 'like', "%{$request->numero_cuenta}%");
        }

        if ($request->filled('fecha_pago')) {
            $query->whereDate('fecha_pago', $request->fecha_pago);
        }

        $query->orderBy('fecha_pago', 'desc')->orderBy('agencia_id', 'asc');
        $perPage = $request->input('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Reclamar un pago automático pendiente (Agencia Operativa)
     */
    public function reclamar(Request $request, ColocacionPago $pago)
    {
        $user = $request->user();

        if ($pago->estado !== 'PENDIENTE') {
            return response()->json(['error' => 'Este pago automático ya fue reclamado previamente.'], 409);
        }

        // Validar si la fecha del pago está dentro del rango válido de la promoción
        $mecanica = Configuracion::where('clave', 'mecanica')->first()?->valor ?? [];
        $fechaPagoStr = $pago->fecha_pago->format('Y-m-d') . ' 00:00:00';
        if (!empty($mecanica['fecha_inicio']) && $fechaPagoStr < $mecanica['fecha_inicio']) {
            return response()->json(['error' => 'La fecha de este pago es previa al inicio de la promoción actual.'], 422);
        }
        if (!empty($mecanica['fecha_fin']) && $fechaPagoStr > $mecanica['fecha_fin']) {
            return response()->json(['error' => 'La fecha de este pago es posterior a la finalización de la promoción actual.'], 422);
        }

        // Verificar que el usuario pertenezca a la agencia del pago (salvo admin)
        $isSuperAdmin = $user->hasRole('Super Admin');
        $hasAdminPermission = $user->hasPermissionTo('cartilla_mercadeo');

        if (!$isSuperAdmin && !$hasAdminPermission) {
            $userAgencia = $user->agencia_id ?? $user->idagencia;
            if ($pago->agencia->codigo !== $userAgencia) {
                return response()->json(['error' => 'No tienes permiso para reclamar pagos de otra agencia.'], 403);
            }
        }

        $data = $request->validate([
            'cartilla_nueva'        => 'boolean',
            'cartilla_completada'   => 'boolean',
            'promocional_entregado' => 'nullable|string|exists:cartilla_promocionales,nombre',
            'notas'                 => 'nullable|string|max:500',
        ]);

        $mecanica = Configuracion::where('clave', 'mecanica')->first()?->valor ?? [];
        $stickers = $mecanica['stickers_pago_puntual'] ?? 5;
        $agenciaId = $pago->agencia_id;

        return DB::transaction(function () use ($pago, $data, $user, $stickers, $agenciaId) {
            // Lock en el pago para evitar race conditions simultáneas
            $pagoLocked = ColocacionPago::where('id', $pago->id)->lockForUpdate()->first();
            if ($pagoLocked->estado !== 'PENDIENTE') {
                throw new \Exception("El pago ya fue reclamado por otro usuario.");
            }

            // Validar inventario de la agencia
            if ($stickers > 0) {
                $stockStickers = InventarioStock::where('agencia_id', $agenciaId)
                    ->where('recurso', 'STICKERS')
                    ->lockForUpdate()
                    ->first();
                if (!$stockStickers || $stockStickers->cantidad < $stickers) {
                    throw new \Exception("Inventario insuficiente de stickers en la agencia (Stock disponible: " . ($stockStickers->cantidad ?? 0) . ")");
                }
            }

            if (!empty($data['cartilla_nueva'])) {
                $stockCartillas = InventarioStock::where('agencia_id', $agenciaId)
                    ->where('recurso', 'CARTILLAS')
                    ->lockForUpdate()
                    ->first();
                if (!$stockCartillas || $stockCartillas->cantidad < 1) {
                    throw new \Exception("Inventario insuficiente de cartillas en la agencia.");
                }
            }

            if (!empty($data['promocional_entregado'])) {
                $stockPromo = InventarioStock::where('agencia_id', $agenciaId)
                    ->where('recurso', 'PROMOCIONAL')
                    ->where('nombre_promocional', $data['promocional_entregado'])
                    ->lockForUpdate()
                    ->first();
                if (!$stockPromo || $stockPromo->cantidad < 1) {
                    throw new \Exception("Inventario insuficiente del promocional '{$data['promocional_entregado']}' en la agencia.");
                }
            }

            // Generar código de registro
            $sigId = DB::table('cartilla_registros')->max('id') + 1;
            $codigoRegistro = 'REG-' . str_pad($sigId, 7, '0', STR_PAD_LEFT);

            // Armar nota con fecha histórica del pago
            $fechaHist = $pago->fecha_pago->format('d/m/Y');
            $notaAuto = "Crédito automático con fecha: {$fechaHist}";
            if (!empty($data['notas'])) {
                $notaAuto .= " - " . $data['notas'];
            }

            // Crear registro formal de participación
            $registro = Registro::create([
                'codigo'                => $codigoRegistro,
                'agencia_id'            => $agenciaId,
                'usuario_id'            => $user->id,
                'nombre_colaborador'    => $user->name ?? $user->username,
                'codigo_cliente'        => $pago->codigo_cliente,
                'accion'                => 'PAGO_PUNTUAL',
                'tipo_operacion'        => 'AUTOMÁTICO',
                'monto'                 => $pago->monto,
                'numero_cuenta'         => $pago->numero_cuenta,
                'stickers'              => $stickers,
                'cartilla_nueva'        => $data['cartilla_nueva'] ?? false,
                'cartilla_completada'   => $data['cartilla_completada'] ?? false,
                'sorteo'                => $data['cartilla_completada'] ?? false,
                'promocional_entregado' => $data['promocional_entregado'] ?? null,
                'notas'                 => $notaAuto,
                'source_payment_date'   => $pago->fecha_pago,
            ]);

            // Aplicar descuentos a stocks e insertar movimientos Kárdex
            if ($stickers > 0) {
                InventarioStock::where('agencia_id', $agenciaId)
                    ->where('recurso', 'STICKERS')
                    ->decrement('cantidad', $stickers);

                MovimientoInventario::create([
                    'codigo'             => 'MOV-S' . uniqid(),
                    'agencia_id'         => $agenciaId,
                    'usuario_id'         => $user->id,
                    'nombre_usuario'     => $user->name ?? $user->username,
                    'recurso'            => 'STICKERS',
                    'tipo_movimiento'    => 'EGRESO',
                    'cantidad'           => $stickers,
                    'alcance'            => 'consumo-registro',
                    'codigo_registro'    => $codigoRegistro,
                    'codigo_cliente'     => $pago->codigo_cliente,
                    'detalle'            => "Consumo automático por reclamo de pago {$codigoRegistro}",
                ]);
            }

            if (!empty($data['cartilla_nueva'])) {
                InventarioStock::where('agencia_id', $agenciaId)
                    ->where('recurso', 'CARTILLAS')
                    ->decrement('cantidad', 1);

                MovimientoInventario::create([
                    'codigo'             => 'MOV-C' . uniqid(),
                    'agencia_id'         => $agenciaId,
                    'usuario_id'         => $user->id,
                    'nombre_usuario'     => $user->name ?? $user->username,
                    'recurso'            => 'CARTILLAS',
                    'tipo_movimiento'    => 'EGRESO',
                    'cantidad'           => 1,
                    'alcance'            => 'consumo-registro',
                    'codigo_registro'    => $codigoRegistro,
                    'codigo_cliente'     => $pago->codigo_cliente,
                    'detalle'            => "Entrega de cartilla por reclamo de pago {$codigoRegistro}",
                ]);
            }

            if (!empty($data['promocional_entregado'])) {
                InventarioStock::where('agencia_id', $agenciaId)
                    ->where('recurso', 'PROMOCIONAL')
                    ->where('nombre_promocional', $data['promocional_entregado'])
                    ->decrement('cantidad', 1);

                MovimientoInventario::create([
                    'codigo'             => 'MOV-P' . uniqid(),
                    'agencia_id'         => $agenciaId,
                    'usuario_id'         => $user->id,
                    'nombre_usuario'     => $user->name ?? $user->username,
                    'recurso'            => 'PROMOCIONAL',
                    'nombre_promocional' => $data['promocional_entregado'],
                    'tipo_movimiento'    => 'EGRESO',
                    'cantidad'           => 1,
                    'alcance'            => 'consumo-registro',
                    'codigo_registro'    => $codigoRegistro,
                    'codigo_cliente'     => $pago->codigo_cliente,
                    'detalle'            => "Entrega de promocional '{$data['promocional_entregado']}' por reclamo de pago {$codigoRegistro}",
                ]);
            }

            // Actualizar estado del pago a RECLAMADO
            $pagoLocked->update([
                'estado'                   => 'RECLAMADO',
                'registro_id'              => $registro->id,
                'reclamado_por_usuario_id' => $user->id,
                'reclamado_en'             => now(),
            ]);

            return response()->json(['msg' => 'Pago automático reclamado y registrado con éxito', 'data' => $registro], 201);
        });
    }

    private function detectarDelimitador($content)
    {
        $firstLine = strtok($content, "\r\n");
        $candidates = [',', ';', '|', "\t"];
        $best = ',';
        $maxCount = -1;

        foreach ($candidates as $c) {
            $count = substr_count($firstLine, $c);
            if ($count > $maxCount) {
                $maxCount = $count;
                $best = $c;
            }
        }
        return $best;
    }
}
