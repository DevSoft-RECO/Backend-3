<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\HistorialInventario;
use App\Models\Cartilla\MovimientoInventario;
use App\Models\Cartilla\InventarioStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistorialInventarioController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = HistorialInventario::with(['movimiento.agencia', 'movimiento.agenciaDestino']);

        $isSuperAdmin = $user->hasRole('Super Admin');
        $hasAdminPermission = $user->hasPermissionTo('cartilla_mercadeo');

        if (!$isSuperAdmin && !$hasAdminPermission) {
            $agenciaCodigo = $user->agencia_id ?? $user->idagencia;
            $agenciaId = \App\Models\Cartilla\Agencia::where('codigo', $agenciaCodigo)->value('id');
            $query->where(function($q) use ($agenciaCodigo, $agenciaId) {
                $q->whereHas('movimiento.agencia', function($subQ) use ($agenciaCodigo) {
                    $subQ->where('codigo', $agenciaCodigo);
                });
                if ($agenciaId) {
                    $q->orWhere('snapshot->agencia_id', $agenciaId)
                      ->orWhere('snapshot->agencia_destino_id', $agenciaId);
                }
            });
        }

        if ($request->filled('estado_cambio')) {
            $query->where('estado_cambio', $request->estado_cambio);
        }

        $query->orderBy('ejecutado_en', 'desc');
        $perPage = $request->input('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    public function restaurar(HistorialInventario $historial)
    {
        $user = request()->user();

        if ($historial->restaurado) {
            return response()->json(['error' => 'Este snapshot de historial ya fue restaurado anteriormente.'], 409);
        }

        return DB::transaction(function () use ($historial, $user) {
            $movimiento = MovimientoInventario::find($historial->movimiento_id);
            $snapshot = $historial->snapshot;

            if ($movimiento) {
                // A. Revertir stocks del movimiento actual
                $this->revertirMovimientoStocks($movimiento);

                // B. Validar y re-aplicar stocks desde el snapshot
                if ($snapshot['tipo_movimiento'] === 'INGRESO') {
                    $stock = InventarioStock::firstOrCreate([
                        'agencia_id' => null,
                        'recurso' => $snapshot['recurso'],
                        'nombre_promocional' => $snapshot['nombre_promocional'] ?? null,
                    ]);
                    $stock->increment('cantidad', $snapshot['cantidad']);
                } else {
                    $stockCentral = InventarioStock::where('agencia_id', null)
                        ->where('recurso', $snapshot['recurso'])
                        ->where('nombre_promocional', $snapshot['nombre_promocional'] ?? null)
                        ->lockForUpdate()
                        ->first();

                    if (!$stockCentral || $stockCentral->cantidad < $snapshot['cantidad']) {
                        throw new \Exception("Stock central insuficiente para restaurar este traslado.");
                    }

                    $stockCentral->decrement('cantidad', $snapshot['cantidad']);

                    $stockAgencia = InventarioStock::firstOrCreate([
                        'agencia_id' => $snapshot['agencia_destino_id'],
                        'recurso' => $snapshot['recurso'],
                        'nombre_promocional' => $snapshot['nombre_promocional'] ?? null,
                    ]);
                    $stockAgencia->increment('cantidad', $snapshot['cantidad']);
                }

                // C. Actualizar el movimiento con los datos del snapshot
                $movimiento->update([
                    'recurso'            => $snapshot['recurso'],
                    'nombre_promocional' => $snapshot['nombre_promocional'] ?? null,
                    'tipo_movimiento'    => $snapshot['tipo_movimiento'],
                    'cantidad'           => $snapshot['cantidad'],
                    'agencia_destino_id' => $snapshot['agencia_destino_id'] ?? null,
                    'alcance'            => $snapshot['alcance'],
                    'detalle'            => $snapshot['detalle'] ?? $movimiento->detalle,
                ]);
            } else {
                // Restauración de un movimiento que fue ELIMINADO
                if ($snapshot['tipo_movimiento'] === 'INGRESO') {
                    $stock = InventarioStock::firstOrCreate([
                        'agencia_id' => null,
                        'recurso' => $snapshot['recurso'],
                        'nombre_promocional' => $snapshot['nombre_promocional'] ?? null,
                    ]);
                    $stock->increment('cantidad', $snapshot['cantidad']);
                } else {
                    $stockCentral = InventarioStock::where('agencia_id', null)
                        ->where('recurso', $snapshot['recurso'])
                        ->where('nombre_promocional', $snapshot['nombre_promocional'] ?? null)
                        ->lockForUpdate()
                        ->first();

                    if (!$stockCentral || $stockCentral->cantidad < $snapshot['cantidad']) {
                        throw new \Exception("Stock central insuficiente para restaurar este traslado.");
                    }

                    $stockCentral->decrement('cantidad', $snapshot['cantidad']);

                    $stockAgencia = InventarioStock::firstOrCreate([
                        'agencia_id' => $snapshot['agencia_destino_id'],
                        'recurso' => $snapshot['recurso'],
                        'nombre_promocional' => $snapshot['nombre_promocional'] ?? null,
                    ]);
                    $stockAgencia->increment('cantidad', $snapshot['cantidad']);
                }

                $nuevoMov = MovimientoInventario::create([
                    'codigo'             => $snapshot['codigo'] ?? ('MOV-' . str_pad(MovimientoInventario::max('id') + 1, 7, '0', STR_PAD_LEFT)),
                    'agencia_id'         => $snapshot['agencia_id'] ?? null,
                    'usuario_id'         => $user->id,
                    'nombre_usuario'     => $user->name ?? $user->username,
                    'recurso'            => $snapshot['recurso'],
                    'nombre_promocional' => $snapshot['nombre_promocional'] ?? null,
                    'tipo_movimiento'    => $snapshot['tipo_movimiento'],
                    'cantidad'           => $snapshot['cantidad'],
                    'alcance'            => $snapshot['alcance'],
                    'agencia_destino_id' => $snapshot['agencia_destino_id'] ?? null,
                    'detalle'            => ($snapshot['detalle'] ?? '') . ' (Restaurado desde historial)',
                    'es_manual'          => true,
                ]);

                $historial->movimiento_id = $nuevoMov->id;
            }

            $historial->update([
                'restaurado' => true,
            ]);

            return response()->json(['msg' => 'Movimiento de inventario restaurado exitosamente']);
        });
    }

    private function revertirMovimientoStocks(MovimientoInventario $mov)
    {
        if ($mov->tipo_movimiento === 'INGRESO') {
            $stock = InventarioStock::where('agencia_id', null)
                ->where('recurso', $mov->recurso)
                ->where('nombre_promocional', $mov->nombre_promocional)
                ->first();

            if ($stock) {
                if ($stock->cantidad < $mov->cantidad) {
                    throw new \Exception("No se puede restaurar porque el stock central quedaría negativo.");
                }
                $stock->decrement('cantidad', $mov->cantidad);
            }
        } else {
            $stockAgencia = InventarioStock::where('agencia_id', $mov->agencia_destino_id)
                ->where('recurso', $mov->recurso)
                ->where('nombre_promocional', $mov->nombre_promocional)
                ->first();

            if ($stockAgencia) {
                if ($stockAgencia->cantidad < $mov->cantidad) {
                    throw new \Exception("No se puede restaurar porque la agencia destino ya consumió parte del stock.");
                }
                $stockAgencia->decrement('cantidad', $mov->cantidad);
            }

            $stockCentral = InventarioStock::firstOrCreate([
                'agencia_id' => null,
                'recurso' => $mov->recurso,
                'nombre_promocional' => $mov->nombre_promocional,
            ]);
            $stockCentral->increment('cantidad', $mov->cantidad);
        }
    }
}
