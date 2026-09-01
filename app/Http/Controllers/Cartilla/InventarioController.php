<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\MovimientoInventario;
use App\Models\Cartilla\InventarioStock;
use App\Models\Cartilla\HistorialInventario;
use App\Models\Cartilla\Agencia;
use App\Models\Cartilla\Promocional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = MovimientoInventario::with(['agencia', 'agenciaDestino']);

        // Aislamiento por agencia para no admins
        $isSuperAdmin = $user->hasRole('Super Admin');
        $hasAdminPermission = $user->hasPermissionTo('cartilla_mercadeo');

        if (!$isSuperAdmin && !$hasAdminPermission) {
            $query->whereHas('agencia', function($q) use ($user) {
                $q->where('codigo', $user->agencia_id ?? $user->idagencia);
            });
        }

        if ($request->filled('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->tipo_movimiento);
        }

        if ($request->filled('recurso')) {
            $query->where('recurso', $request->recurso);
        }

        if ($request->filled('agencia_id')) {
            $query->where('agencia_id', $request->agencia_id);
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

        $query->orderBy('created_at', 'desc');
        $perPage = $request->input('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $userAgencia = Agencia::where('codigo', $user->agencia_id ?? $user->idagencia)->first()
                      ?? Agencia::find($user->agencia_id ?? $user->idagencia);

        $data = $request->validate([
            'recurso'              => 'required|in:STICKERS,CARTILLAS,PROMOCIONAL',
            'nombre_promocional'   => 'nullable|required_if:recurso,PROMOCIONAL|string|exists:cartilla_promocionales,nombre',
            'tipo_movimiento'      => 'required|in:INGRESO,EGRESO',
            'cantidad'             => 'required|integer|min:1',
            'agencia_destino_id'   => 'nullable|exists:cartilla_agencias,id',
            'detalle'              => 'nullable|string|max:500',
        ]);

        if ($data['tipo_movimiento'] === 'EGRESO' && empty($data['agencia_destino_id']) && !$userAgencia) {
            return response()->json(['error' => 'Para realizar un egreso debe pertenecer a una agencia o especificar la agencia de destino.'], 422);
        }

        return DB::transaction(function () use ($data, $user, $userAgencia) {
            $codigo = 'MOV-' . str_pad(MovimientoInventario::max('id') + 1, 7, '0', STR_PAD_LEFT);

            // CASO 1: INGRESO AL CENTRAL
            if ($data['tipo_movimiento'] === 'INGRESO') {
                $stock = InventarioStock::firstOrCreate([
                    'agencia_id' => null,
                    'recurso' => $data['recurso'],
                    'nombre_promocional' => $data['nombre_promocional'] ?? null,
                ]);

                $stock->increment('cantidad', $data['cantidad']);

                $mov = MovimientoInventario::create([
                    'codigo'             => $codigo,
                    'agencia_id'         => null,
                    'usuario_id'         => $user->id,
                    'nombre_usuario'     => $user->name ?? $user->username,
                    'recurso'            => $data['recurso'],
                    'nombre_promocional' => $data['nombre_promocional'] ?? null,
                    'tipo_movimiento'    => 'INGRESO',
                    'cantidad'           => $data['cantidad'],
                    'alcance'            => 'central',
                    'detalle'            => $data['detalle'] ?? 'Ingreso manual al inventario central',
                    'es_manual'          => true,
                ]);
            } 
            // CASO 2: TRASLADO (CENTRAL A AGENCIA DESTINO)
            else if (!empty($data['agencia_destino_id'])) {
                $stockCentral = InventarioStock::where('agencia_id', null)
                    ->where('recurso', $data['recurso'])
                    ->where('nombre_promocional', $data['nombre_promocional'] ?? null)
                    ->lockForUpdate()
                    ->first();

                if (!$stockCentral || $stockCentral->cantidad < $data['cantidad']) {
                    throw new \Exception("Stock insuficiente en el almacén central para realizar el traslado.");
                }

                $stockCentral->decrement('cantidad', $data['cantidad']);

                $stockAgencia = InventarioStock::firstOrCreate([
                    'agencia_id' => $data['agencia_destino_id'],
                    'recurso' => $data['recurso'],
                    'nombre_promocional' => $data['nombre_promocional'] ?? null,
                ]);
                $stockAgencia->increment('cantidad', $data['cantidad']);

                $mov = MovimientoInventario::create([
                    'codigo'             => $codigo,
                    'agencia_id'         => null, // Sale del central
                    'usuario_id'         => $user->id,
                    'nombre_usuario'     => $user->name ?? $user->username,
                    'recurso'            => $data['recurso'],
                    'nombre_promocional' => $data['nombre_promocional'] ?? null,
                    'tipo_movimiento'    => 'EGRESO',
                    'cantidad'           => $data['cantidad'],
                    'alcance'            => 'traslado',
                    'agencia_destino_id' => $data['agencia_destino_id'],
                    'detalle'            => $data['detalle'] ?? 'Traslado central a agencia',
                    'es_manual'          => true,
                ]);
            }
            // CASO 3: EGRESO DIRECTO DE AGENCIA (REPOSICIÓN DE CARTILLA / DAÑO / REQUISICIÓN)
            else {
                $stockAgencia = InventarioStock::where('agencia_id', $userAgencia->id)
                    ->where('recurso', $data['recurso'])
                    ->where('nombre_promocional', $data['nombre_promocional'] ?? null)
                    ->lockForUpdate()
                    ->first();

                if (!$stockAgencia || $stockAgencia->cantidad < $data['cantidad']) {
                    throw new \Exception("Stock insuficiente en la agencia para completar el egreso.");
                }

                $stockAgencia->decrement('cantidad', $data['cantidad']);

                $mov = MovimientoInventario::create([
                    'codigo'             => $codigo,
                    'agencia_id'         => $userAgencia->id,
                    'usuario_id'         => $user->id,
                    'nombre_usuario'     => $user->name ?? $user->username,
                    'recurso'            => $data['recurso'],
                    'nombre_promocional' => $data['nombre_promocional'] ?? null,
                    'tipo_movimiento'    => 'EGRESO',
                    'cantidad'           => $data['cantidad'],
                    'alcance'            => 'agencia',
                    'agencia_destino_id' => null,
                    'detalle'            => $data['detalle'] ?? 'Egreso directo de agencia',
                    'es_manual'          => true,
                ]);
            }

            return response()->json(['msg' => 'Movimiento de inventario registrado con éxito', 'data' => $mov], 201);
        });
    }

    public function update(Request $request, MovimientoInventario $movimiento)
    {
        $user = $request->user();

        if (!$movimiento->es_manual) {
            return response()->json(['error' => 'No se pueden editar movimientos automáticos generados por consumos.'], 403);
        }

        $data = $request->validate([
            'recurso'              => 'required|in:STICKERS,CARTILLAS,PROMOCIONAL',
            'nombre_promocional'   => 'nullable|required_if:recurso,PROMOCIONAL|string|exists:cartilla_promocionales,nombre',
            'tipo_movimiento'      => 'required|in:INGRESO,EGRESO',
            'cantidad'             => 'required|integer|min:1',
            'agencia_destino_id'   => 'nullable|required_if:tipo_movimiento,EGRESO|exists:cartilla_agencias,id',
            'detalle'              => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $movimiento, $user) {
            // A. Snapshot antes de editar
            HistorialInventario::create([
                'movimiento_id'   => $movimiento->id,
                'usuario_id'      => $user->id,
                'nombre_usuario'  => $user->name ?? $user->username,
                'estado_cambio'   => 'EDITADO',
                'snapshot'        => $movimiento->toArray(),
                'ejecutado_en'    => now(),
            ]);

            // B. Revertir stocks del movimiento anterior
            $this->revertirMovimientoInventario($movimiento);

            // C. Aplicar nuevos stocks con validaciones
            if ($data['tipo_movimiento'] === 'INGRESO') {
                $stock = InventarioStock::firstOrCreate([
                    'agencia_id' => null,
                    'recurso' => $data['recurso'],
                    'nombre_promocional' => $data['nombre_promocional'] ?? null,
                ]);
                $stock->increment('cantidad', $data['cantidad']);
            } else {
                $stockCentral = InventarioStock::where('agencia_id', null)
                    ->where('recurso', $data['recurso'])
                    ->where('nombre_promocional', $data['nombre_promocional'] ?? null)
                    ->lockForUpdate()
                    ->first();

                if (!$stockCentral || $stockCentral->cantidad < $data['cantidad']) {
                    throw new \Exception("Stock insuficiente en central para completar el traslado.");
                }

                $stockCentral->decrement('cantidad', $data['cantidad']);

                $stockAgencia = InventarioStock::firstOrCreate([
                    'agencia_id' => $data['agencia_destino_id'],
                    'recurso' => $data['recurso'],
                    'nombre_promocional' => $data['nombre_promocional'] ?? null,
                ]);
                $stockAgencia->increment('cantidad', $data['cantidad']);
            }

            // D. Actualizar movimiento
            $movimiento->update([
                'recurso'            => $data['recurso'],
                'nombre_promocional' => $data['nombre_promocional'] ?? null,
                'tipo_movimiento'    => $data['tipo_movimiento'],
                'cantidad'           => $data['cantidad'],
                'agencia_destino_id' => $data['tipo_movimiento'] === 'EGRESO' ? $data['agencia_destino_id'] : null,
                'alcance'            => $data['tipo_movimiento'] === 'INGRESO' ? 'central' : 'traslado',
                'detalle'            => $data['detalle'] ?? $movimiento->detalle,
            ]);

            return response()->json(['msg' => 'Movimiento de inventario actualizado', 'data' => $movimiento]);
        });
    }

    public function destroy(MovimientoInventario $movimiento)
    {
        $user = request()->user();

        if (!$movimiento->es_manual) {
            return response()->json(['error' => 'No se pueden eliminar movimientos automáticos.'], 403);
        }

        return DB::transaction(function () use ($movimiento, $user) {
            // A. Snapshot antes de borrar
            HistorialInventario::create([
                'movimiento_id'   => $movimiento->id,
                'usuario_id'      => $user->id,
                'nombre_usuario'  => $user->name ?? $user->username,
                'estado_cambio'   => 'ELIMINADO',
                'snapshot'        => $movimiento->toArray(),
                'ejecutado_en'    => now(),
            ]);

            // B. Revertir stocks
            $this->revertirMovimientoInventario($movimiento);

            // C. Eliminar
            $movimiento->delete();

            return response()->json(['msg' => 'Movimiento de inventario eliminado']);
        });
    }

    public function stocksResumen(Request $request)
    {
        $central = InventarioStock::where('agencia_id', null)->get();
        $agencias = InventarioStock::whereNotNull('agencia_id')
            ->with('agencia')
            ->get()
            ->groupBy('agencia.codigo');

        return response()->json([
            'central' => $central,
            'agencias' => $agencias,
        ]);
    }

    /**
     * Balance de inventario (Entregado, Disponible, Total) para Central (Global) y Agencias Operativas
     */
    public function balanceInventario(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $hasAdminPermission = $user->hasPermissionTo('cartilla_mercadeo');

        // Determinar agencia si no es global
        $agenciaId = null;
        if ($request->filled('agencia_id') && ($isSuperAdmin || $hasAdminPermission)) {
            $agenciaId = $request->agencia_id;
        } elseif (!$isSuperAdmin && !$hasAdminPermission) {
            $userAgencia = Agencia::where('codigo', $user->agencia_id ?? $user->idagencia)->first()
                ?? Agencia::find($user->agencia_id ?? $user->idagencia);
            $agenciaId = $userAgencia?->id;
        }

        // Filtro opcional por rango de fechas
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $filtrarFechas = function ($q) use ($fechaInicio, $fechaFin) {
            if (!empty($fechaInicio)) {
                $q->whereDate('created_at', '>=', $fechaInicio);
            }
            if (!empty($fechaFin)) {
                $q->whereDate('created_at', '<=', $fechaFin);
            }
            return $q;
        };

        // 1. CARTILLAS
        $queryCartillasEnt = \App\Models\Cartilla\Registro::where('cartilla_nueva', true);
        if ($agenciaId) {
            $queryCartillasEnt->where('agencia_id', $agenciaId);
        }
        $cartillasEntregadas = (int) $filtrarFechas($queryCartillasEnt)->count();

        // Sumar reposiciones de cartillas en el periodo
        $queryCartillasRep = MovimientoInventario::where('recurso', 'CARTILLAS')
            ->where('tipo_movimiento', 'EGRESO')
            ->where('detalle', 'LIKE', '%REPOSICIÓN%');
        if ($agenciaId) {
            $queryCartillasRep->where('agencia_id', $agenciaId);
        }
        $cartillasReposicion = (int) $filtrarFechas($queryCartillasRep)->sum('cantidad');

        $totalCartillasEntregadas = $cartillasEntregadas + $cartillasReposicion;

        $queryCartillasDisp = InventarioStock::where('recurso', 'CARTILLAS');
        if ($agenciaId) {
            $queryCartillasDisp->where('agencia_id', $agenciaId);
        } else {
            $queryCartillasDisp->whereNull('agencia_id'); // Almacén Central
        }
        $cartillasDisponible = (int) $queryCartillasDisp->sum('cantidad');

        // 2. STICKERS
        $queryStickersEnt = \App\Models\Cartilla\Registro::query();
        if ($agenciaId) {
            $queryStickersEnt->where('agencia_id', $agenciaId);
        }
        $stickersEntregados = (int) $filtrarFechas($queryStickersEnt)->sum('stickers');

        $queryStickersDisp = InventarioStock::where('recurso', 'STICKERS');
        if ($agenciaId) {
            $queryStickersDisp->where('agencia_id', $agenciaId);
        } else {
            $queryStickersDisp->whereNull('agencia_id');
        }
        $stickersDisponible = (int) $queryStickersDisp->sum('cantidad');

        // 3. PROMOCIONALES (Desglose por tipo)
        $promocionales = Promocional::orderBy('nombre')->get();
        $promocionalesDesglose = [];

        foreach ($promocionales as $promo) {
            $qEnt = \App\Models\Cartilla\Registro::where('promocional_entregado', $promo->nombre);
            if ($agenciaId) {
                $qEnt->where('agencia_id', $agenciaId);
            }
            $entregados = (int) $filtrarFechas($qEnt)->count();

            $qDisp = InventarioStock::where('recurso', 'PROMOCIONAL')
                ->where('nombre_promocional', $promo->nombre);
            if ($agenciaId) {
                $qDisp->where('agencia_id', $agenciaId);
            } else {
                $qDisp->whereNull('agencia_id');
            }
            $disponible = (int) $qDisp->sum('cantidad');

            $promocionalesDesglose[] = [
                'id'         => $promo->id,
                'nombre'     => $promo->nombre,
                'activo'     => $promo->activo,
                'entregado'  => $entregados,
                'disponible' => $disponible,
                'total'      => $entregados + $disponible,
            ];
        }

        $agenciaInfo = null;
        if ($agenciaId) {
            $ag = Agencia::find($agenciaId);
            $agenciaInfo = [
                'id'     => $ag?->id,
                'nombre' => $ag?->nombre,
                'codigo' => $ag?->codigo,
            ];
        } else {
            $agenciaInfo = [
                'id'     => null,
                'nombre' => 'Almacén Central de Mercadeo (Global)',
                'codigo' => 'CENTRAL',
            ];
        }

        return response()->json([
            'agencia' => $agenciaInfo,
            'balance' => [
                'cartillas' => [
                    'entregado'  => $totalCartillasEntregadas,
                    'disponible' => $cartillasDisponible,
                    'total'      => $totalCartillasEntregadas + $cartillasDisponible,
                ],
                'stickers' => [
                    'entregado'  => $stickersEntregados,
                    'disponible' => $stickersDisponible,
                    'total'      => $stickersEntregados + $stickersDisponible,
                ],
                'promocionales' => $promocionalesDesglose,
            ]
        ]);
    }

    /**
     * Reversa física de stock de un movimiento manual
     */
    private function revertirMovimientoInventario(MovimientoInventario $mov)
    {
        if ($mov->tipo_movimiento === 'INGRESO') {
            // Se resta lo que ingresó
            $stock = InventarioStock::where('agencia_id', null)
                ->where('recurso', $mov->recurso)
                ->where('nombre_promocional', $mov->nombre_promocional)
                ->first();

            if ($stock) {
                if ($stock->cantidad < $mov->cantidad) {
                    throw new \Exception("No se puede revertir el ingreso porque el saldo actual del central quedaría en negativo.");
                }
                $stock->decrement('cantidad', $mov->cantidad);
            }
        } else {
            // Traslado: Devolver a central, restar de agencia
            $stockAgencia = InventarioStock::where('agencia_id', $mov->agencia_destino_id)
                ->where('recurso', $mov->recurso)
                ->where('nombre_promocional', $mov->nombre_promocional)
                ->first();

            if ($stockAgencia) {
                if ($stockAgencia->cantidad < $mov->cantidad) {
                    throw new \Exception("No se puede revertir el traslado porque la agencia destino ya consumió parte del inventario.");
                }
                $stockAgencia->decrement('cantidad', $mov->cantidad);
            }

            // Devolver a central
            $stockCentral = InventarioStock::firstOrCreate([
                'agencia_id' => null,
                'recurso' => $mov->recurso,
                'nombre_promocional' => $mov->nombre_promocional,
            ]);
            $stockCentral->increment('cantidad', $mov->cantidad);
        }
    }
}
