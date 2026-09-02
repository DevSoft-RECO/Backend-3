<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\Agencia;
use App\Models\Cartilla\Registro;
use App\Models\Cartilla\InventarioStock;
use App\Models\Cartilla\MovimientoInventario;
use App\Models\Cartilla\Promocional;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function agencia(Request $request)
    {
        $user = $request->user();
        $agenciaCodigo = $user->agencia_id ?? $user->idagencia;

        $isSuperAdmin = $user->hasRole('Super Admin');
        $hasAdminPermission = $user->hasPermissionTo('admin_promocion');

        if ($request->filled('agencia_id') && ($isSuperAdmin || $hasAdminPermission)) {
            $agencia = Agencia::find($request->agencia_id);
        } else {
            $agencia = Agencia::where('codigo', $agenciaCodigo)->first()
                      ?? Agencia::find($agenciaCodigo);
        }

        if (!$agencia) {
            $agencia = Agencia::first();
        }

        if (!$agencia) {
            return response()->json(['error' => 'No existe agencia configurada en el sistema.'], 404);
        }

        $agenciaId = $agencia->id;

        // Rango de fechas de la promoción
        $mecanica = \App\Models\Cartilla\Configuracion::where('clave', 'mecanica')->first()?->valor ?? [];
        $fechaInicioPromo = !empty($mecanica['fecha_inicio']) ? $mecanica['fecha_inicio'] : null;
        $fechaFinPromo = !empty($mecanica['fecha_fin']) ? $mecanica['fecha_fin'] : null;

        $aplicarRangoFecha = function ($q) use ($fechaInicioPromo, $fechaFinPromo) {
            if ($fechaInicioPromo) {
                $q->where('created_at', '>=', $fechaInicioPromo);
            }
            if ($fechaFinPromo) {
                $q->where('created_at', '<=', $fechaFinPromo);
            }
            return $q;
        };

        // 1. INVENTARIO: CARTILLAS
        $cartillasRecibidas = (int) MovimientoInventario::where('agencia_destino_id', $agenciaId)
            ->where('recurso', 'CARTILLAS')
            ->where('tipo_movimiento', 'EGRESO')
            ->sum('cantidad');

        $cartillasEntregadas = (int) $aplicarRangoFecha(Registro::where('agencia_id', $agenciaId)->where('cartilla_nueva', true))->count();

        $cartillasReposicion = (int) MovimientoInventario::where('agencia_id', $agenciaId)
            ->where('recurso', 'CARTILLAS')
            ->where('tipo_movimiento', 'EGRESO')
            ->where('detalle', 'LIKE', '%REPOSICIÓN%')
            ->sum('cantidad');

        $totalCartillasSalida = $cartillasEntregadas + $cartillasReposicion;

        $cartillasDisponible = (int) InventarioStock::where('agencia_id', $agenciaId)
            ->where('recurso', 'CARTILLAS')
            ->value('cantidad') ?? 0;

        // 2. INVENTARIO: STICKERS
        $stickersRecibidos = (int) MovimientoInventario::where('agencia_destino_id', $agenciaId)
            ->where('recurso', 'STICKERS')
            ->where('tipo_movimiento', 'EGRESO')
            ->sum('cantidad');

        $stickersEntregados = (int) $aplicarRangoFecha(Registro::where('agencia_id', $agenciaId))->sum('stickers');

        $stickersDisponible = (int) InventarioStock::where('agencia_id', $agenciaId)
            ->where('recurso', 'STICKERS')
            ->value('cantidad') ?? 0;

        // 3. INVENTARIO: PROMOCIONALES
        $promocionalesRecibidos = (int) MovimientoInventario::where('agencia_destino_id', $agenciaId)
            ->where('recurso', 'PROMOCIONAL')
            ->where('tipo_movimiento', 'EGRESO')
            ->sum('cantidad');

        $promocionalesEntregados = (int) $aplicarRangoFecha(Registro::where('agencia_id', $agenciaId)
            ->whereNotNull('promocional_entregado')
            ->where('promocional_entregado', '!=', ''))->count();

        $promocionalesDisponible = (int) InventarioStock::where('agencia_id', $agenciaId)
            ->where('recurso', 'PROMOCIONAL')
            ->sum('cantidad');

        // Desglose por tipo de promocional
        $catalogoPromos = Promocional::where('activo', true)->get();
        $promocionalesDesglose = [];

        foreach ($catalogoPromos as $p) {
            $rec = (int) MovimientoInventario::where('agencia_destino_id', $agenciaId)
                ->where('recurso', 'PROMOCIONAL')
                ->where('nombre_promocional', $p->nombre)
                ->where('tipo_movimiento', 'EGRESO')
                ->sum('cantidad');

            $ent = (int) $aplicarRangoFecha(Registro::where('agencia_id', $agenciaId)
                ->where('promocional_entregado', $p->nombre))->count();

            $disp = (int) InventarioStock::where('agencia_id', $agenciaId)
                ->where('recurso', 'PROMOCIONAL')
                ->where('nombre_promocional', $p->nombre)
                ->value('cantidad') ?? 0;

            $promocionalesDesglose[] = [
                'id'          => $p->id,
                'nombre'      => $p->nombre,
                'recibidos'   => $rec,
                'entregados'  => $ent,
                'disponible'  => $disp
            ];
        }

        // 4. PARTICIPACIONES Y SORTEOS
        $registrosQuery = $aplicarRangoFecha(Registro::where('agencia_id', $agenciaId));
        $totalRegistros = (int) $registrosQuery->count();
        $totalMonto = (float) $registrosQuery->sum('monto');
        $totalStickersEntregados = (int) $registrosQuery->sum('stickers');
        
        // Las oportunidades para el gran sorteo corresponden a las cartillas completadas / llenas
        $totalCartillasLlenas = (int) $aplicarRangoFecha(Registro::where('agencia_id', $agenciaId)
            ->where('cartilla_completada', true))->count();

        // Desglose por Acción
        $acciones = ['CREDITO_NUEVO', 'PLAZO_FIJO', 'MOTOCICLETA', 'PAGO_PUNTUAL'];
        $desgloseAcciones = [];

        foreach ($acciones as $acc) {
            $q = $aplicarRangoFecha(Registro::where('agencia_id', $agenciaId)->where('accion', $acc));
            $desgloseAcciones[$acc] = [
                'cantidad' => (int) $q->count(),
                'monto'    => (float) $q->sum('monto'),
                'stickers' => (int) $q->sum('stickers'),
            ];
        }

        // Detalle de cartillas completadas
        $cartillasLlenasDetalle = $aplicarRangoFecha(Registro::where('agencia_id', $agenciaId)
            ->where('cartilla_completada', true))
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return response()->json([
            'agencia' => [
                'id'     => $agencia->id,
                'nombre' => $agencia->nombre,
                'codigo' => $agencia->codigo,
            ],
            'resumen_inventario' => [
                'cartillas' => [
                    'recibidas'  => $cartillasRecibidas,
                    'entregadas' => $totalCartillasSalida,
                    'disponible' => $cartillasDisponible,
                ],
                'stickers' => [
                    'recibidos'  => $stickersRecibidos,
                    'entregados' => $stickersEntregados,
                    'disponible' => $stickersDisponible,
                ],
                'promocionales' => [
                    'recibidos'  => $promocionalesRecibidos,
                    'entregados' => $promocionalesEntregados,
                    'disponible' => $promocionalesDisponible,
                ]
            ],
            'resumen_participaciones' => [
                'total_registros'       => $totalRegistros,
                'total_monto'           => $totalMonto,
                'total_stickers_sorteo' => $totalStickersEntregados,
                'total_cartillas_llenas'=> $totalCartillasLlenas,
            ],
            'desglose_acciones' => $desgloseAcciones,
            'promocionales_desglose' => $promocionalesDesglose,
            'cartillas_llenas_detalle' => $cartillasLlenasDetalle,
        ]);
    }

    public function global(Request $request)
    {
        // Rango de fechas de la promoción
        $mecanica = \App\Models\Cartilla\Configuracion::where('clave', 'mecanica')->first()?->valor ?? [];
        $fechaInicioPromo = !empty($mecanica['fecha_inicio']) ? $mecanica['fecha_inicio'] : null;
        $fechaFinPromo = !empty($mecanica['fecha_fin']) ? $mecanica['fecha_fin'] : null;

        $aplicarRangoFecha = function ($q) use ($fechaInicioPromo, $fechaFinPromo) {
            if ($fechaInicioPromo) {
                $q->where('created_at', '>=', $fechaInicioPromo);
            }
            if ($fechaFinPromo) {
                $q->where('created_at', '<=', $fechaFinPromo);
            }
            return $q;
        };

        // 1. INVENTARIO GLOBAL: CARTILLAS
        $cartillasIngresadasCentral = (int) MovimientoInventario::where('recurso', 'CARTILLAS')
            ->where('tipo_movimiento', 'INGRESO')
            ->sum('cantidad');

        $cartillasEntregadasGlobal = (int) $aplicarRangoFecha(Registro::where('cartilla_nueva', true))->count();
        $cartillasReposicionGlobal = (int) MovimientoInventario::where('recurso', 'CARTILLAS')
            ->where('tipo_movimiento', 'EGRESO')
            ->where('detalle', 'LIKE', '%REPOSICIÓN%')
            ->sum('cantidad');

        $totalCartillasSalidaGlobal = $cartillasEntregadasGlobal + $cartillasReposicionGlobal;
        $cartillasDisponibleGlobal = (int) InventarioStock::where('recurso', 'CARTILLAS')->sum('cantidad');

        // 2. INVENTARIO GLOBAL: STICKERS
        $stickersIngresadosCentral = (int) MovimientoInventario::where('recurso', 'STICKERS')
            ->where('tipo_movimiento', 'INGRESO')
            ->sum('cantidad');

        $stickersEntregadosGlobal = (int) $aplicarRangoFecha(Registro::query())->sum('stickers');
        $stickersDisponibleGlobal = (int) InventarioStock::where('recurso', 'STICKERS')->sum('cantidad');

        // 3. INVENTARIO GLOBAL: PROMOCIONALES
        $promocionalesIngresadosCentral = (int) MovimientoInventario::where('recurso', 'PROMOCIONAL')
            ->where('tipo_movimiento', 'INGRESO')
            ->sum('cantidad');

        $promocionalesEntregadosGlobal = (int) $aplicarRangoFecha(Registro::whereNotNull('promocional_entregado')
            ->where('promocional_entregado', '!=', ''))->count();

        $promocionalesDisponibleGlobal = (int) InventarioStock::where('recurso', 'PROMOCIONAL')->sum('cantidad');

        // Desglose por tipo de promocional
        $catalogoPromos = Promocional::where('activo', true)->get();
        $promocionalesDesglose = [];

        foreach ($catalogoPromos as $p) {
            $rec = (int) MovimientoInventario::where('recurso', 'PROMOCIONAL')
                ->where('nombre_promocional', $p->nombre)
                ->where('tipo_movimiento', 'INGRESO')
                ->sum('cantidad');

            $ent = (int) $aplicarRangoFecha(Registro::where('promocional_entregado', $p->nombre))->count();

            $disp = (int) InventarioStock::where('recurso', 'PROMOCIONAL')
                ->where('nombre_promocional', $p->nombre)
                ->sum('cantidad');

            $promocionalesDesglose[] = [
                'id'          => $p->id,
                'nombre'      => $p->nombre,
                'recibidos'   => $rec,
                'entregados'  => $ent,
                'disponible'  => $disp
            ];
        }

        // 4. PARTICIPACIONES Y SORTEOS GLOBALES
        $registrosQuery = $aplicarRangoFecha(Registro::query());
        $totalRegistros = (int) $registrosQuery->count();
        $totalMonto = (float) $registrosQuery->sum('monto');
        $totalStickersEntregados = (int) $registrosQuery->sum('stickers');
        
        $totalCartillasLlenas = (int) $aplicarRangoFecha(Registro::where('cartilla_completada', true))->count();

        // Desglose por Acción
        $acciones = ['CREDITO_NUEVO', 'PLAZO_FIJO', 'MOTOCICLETA', 'PAGO_PUNTUAL'];
        $desgloseAcciones = [];

        foreach ($acciones as $acc) {
            $q = $aplicarRangoFecha(Registro::where('accion', $acc));
            $desgloseAcciones[$acc] = [
                'cantidad' => (int) $q->count(),
                'monto'    => (float) $q->sum('monto'),
                'stickers' => (int) $q->sum('stickers'),
            ];
        }

        // Detalle de cartillas completadas globales
        $cartillasLlenasDetalle = $aplicarRangoFecha(Registro::with('agencia')
            ->where('cartilla_completada', true))
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return response()->json([
            'agencia' => [
                'id'     => null,
                'nombre' => 'Todas las Agencias (Consolidado General)',
                'codigo' => 'GLOBAL',
            ],
            'resumen_inventario' => [
                'cartillas' => [
                    'recibidas'  => $cartillasIngresadasCentral,
                    'entregadas' => $totalCartillasSalidaGlobal,
                    'disponible' => $cartillasDisponibleGlobal,
                ],
                'stickers' => [
                    'recibidos'  => $stickersIngresadosCentral,
                    'entregados' => $stickersEntregadosGlobal,
                    'disponible' => $stickersDisponibleGlobal,
                ],
                'promocionales' => [
                    'recibidos'  => $promocionalesIngresadosCentral,
                    'entregados' => $promocionalesEntregadosGlobal,
                    'disponible' => $promocionalesDisponibleGlobal,
                ]
            ],
            'resumen_participaciones' => [
                'total_registros'       => $totalRegistros,
                'total_monto'           => $totalMonto,
                'total_stickers_sorteo' => $totalStickersEntregados,
                'total_cartillas_llenas'=> $totalCartillasLlenas,
            ],
            'desglose_acciones' => $desgloseAcciones,
            'promocionales_desglose' => $promocionalesDesglose,
            'cartillas_llenas_detalle' => $cartillasLlenasDetalle,
        ]);
    }
}
