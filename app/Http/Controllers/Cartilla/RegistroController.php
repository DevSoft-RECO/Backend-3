<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\Registro;
use App\Models\Cartilla\InventarioStock;
use App\Models\Cartilla\MovimientoInventario;
use App\Models\Cartilla\HistorialRegistro;
use App\Models\Cartilla\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegistroController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Registro::with(['agencia']);

        // Scope por agencia si no es admin global
        $isSuperAdmin = $user->hasRole('Super Admin');
        $hasAdminPermission = $user->hasPermissionTo('cartilla_mercadeo') || $user->hasPermissionTo('admin_promocion');

        if (!$isSuperAdmin && !$hasAdminPermission) {
            // Usuarios de agencias (edicion_promocion_agencia, lectura_promocion_agencia, etc.)
            $userAgencia = Agencia::where('codigo', $user->agencia_id ?? $user->idagencia)->first()
                ?? Agencia::find($user->agencia_id ?? $user->idagencia);
            if ($userAgencia) {
                $query->where('agencia_id', $userAgencia->id);
            } else {
                $query->whereHas('agencia', function($q) use ($user) {
                    $q->where('codigo', $user->agencia_id ?? $user->idagencia);
                });
            }
        }

        if ($request->filled('agencia_id')) {
            $query->where('agencia_id', $request->agencia_id);
        }

        if ($request->filled('accion')) {
            $query->where('accion', $request->accion);
        }

        if ($request->filled('codigo_cliente')) {
            $query->where('codigo_cliente', 'like', "%{$request->codigo_cliente}%");
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

        if ($request->has('cartilla_completada') && $request->cartilla_completada !== '' && $request->cartilla_completada !== null) {
            $query->where('cartilla_completada', filter_var($request->cartilla_completada, FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('created_at', 'desc');
        $perPage = $request->input('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // 1. Obtener configuración de mecánica para validaciones dinámicas
        $mecanica = Configuracion::where('clave', 'mecanica')->first()?->valor ?? [];
        $prefijo = $mecanica['prefijo_cuenta'] ?? '126';
        $digitos = $mecanica['digitos_cuenta'] ?? 15;

        // 2. Validación
        $data = $request->validate([
            'agencia_id'            => 'required|exists:cartilla_agencias,id',
            'codigo_cliente'        => 'required|regex:/^[0-9]{5,7}$/',
            'accion'                => 'required|in:CREDITO_NUEVO,PLAZO_FIJO,MOTOCICLETA,PAGO_PUNTUAL',
            'tipo_operacion'        => 'nullable|string',
            'monto'                 => 'required|numeric|min:0.01',
            'numero_cuenta'         => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($request, $prefijo, $digitos) {
                    // Ciertos tipos no requieren cuenta
                    $accion = $request->input('accion');
                    if (in_array($accion, ['CREDITO_NUEVO', 'PLAZO_FIJO', 'PAGO_PUNTUAL'])) {
                        if (empty($value)) {
                            return $fail('El número de cuenta es requerido para esta acción.');
                        }
                        if (!preg_match("/^{$prefijo}[0-9]{" . ($digitos - strlen($prefijo)) . "}$/", $value)) {
                            return $fail("La cuenta debe empezar con {$prefijo} y tener exactamente {$digitos} dígitos.");
                        }
                    }
                }
            ],
            'cartilla_nueva'        => 'boolean',
            'cartilla_completada'   => 'boolean',
            'promocional_entregado' => 'nullable|string|exists:cartilla_promocionales,nombre',
            'notas'                 => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $user, $mecanica) {
            // A. Calcular stickers base
            $stickers = 0;
            switch ($data['accion']) {
                case 'CREDITO_NUEVO':
                    $stickers = $mecanica['stickers_credito_nuevo'] ?? 15;
                    break;
                case 'PLAZO_FIJO':
                    $stickers = $mecanica['stickers_plazo_fijo'] ?? 15;
                    // Regla de Plazo Fijo único diario global
                    if (!empty($mecanica['plazo_fijo_unico_diario'])) {
                        $yaExiste = Registro::where('codigo_cliente', $data['codigo_cliente'])
                            ->where('accion', 'PLAZO_FIJO')
                            ->where('stickers', '>', 0)
                            ->whereDate('created_at', Carbon::today('America/Guatemala'))
                            ->lockForUpdate()
                            ->exists();

                        if ($yaExiste) {
                            $stickers = 0; // Se registra pero con 0 stickers
                        }
                    }
                    break;
                case 'MOTOCICLETA':
                    $isFinanciada = strtolower($data['tipo_operacion'] ?? '') === 'financiada';
                    $stickers = $isFinanciada 
                        ? ($mecanica['stickers_moto_financiada'] ?? 15)
                        : ($mecanica['stickers_moto_contado'] ?? 10);
                    break;
                case 'PAGO_PUNTUAL':
                    $stickers = $mecanica['stickers_pago_puntual'] ?? 5;
                    break;
            }

            $agenciaId = $data['agencia_id'];

            // B. Validar existencias físicas en la agencia (Lock for update)
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

            // C. Generar código de registro correlativo
            $sigId = DB::table('cartilla_registros')->max('id') + 1;
            $codigoRegistro = 'REG-' . str_pad($sigId, 7, '0', STR_PAD_LEFT);

            // D. Crear Registro
            $registro = Registro::create([
                'codigo'                => $codigoRegistro,
                'agencia_id'            => $agenciaId,
                'usuario_id'            => $user->id,
                'nombre_colaborador'    => $user->name ?? $user->username,
                'codigo_cliente'        => $data['codigo_cliente'],
                'accion'                => $data['accion'],
                'tipo_operacion'        => $data['tipo_operacion'] ?? null,
                'monto'                 => $data['monto'],
                'numero_cuenta'         => $data['numero_cuenta'] ?? null,
                'stickers'              => $stickers,
                'cartilla_nueva'        => $data['cartilla_nueva'] ?? false,
                'cartilla_completada'   => $data['cartilla_completada'] ?? false,
                'sorteo'                => $data['cartilla_completada'] ?? false,
                'promocional_entregado' => $data['promocional_entregado'] ?? null,
                'notas'                 => $data['notas'] ?? null,
            ]);

            // E. Aplicar descuentos a stocks e insertar en Kárdex
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
                    'codigo_cliente'     => $data['codigo_cliente'],
                    'detalle'            => "Consumo automático por registro {$codigoRegistro}",
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
                    'codigo_cliente'     => $data['codigo_cliente'],
                    'detalle'            => "Entrega de cartilla por registro {$codigoRegistro}",
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
                    'codigo_cliente'     => $data['codigo_cliente'],
                    'detalle'            => "Entrega de promocional '{$data['promocional_entregado']}' por registro {$codigoRegistro}",
                ]);
            }

            return response()->json(['msg' => 'Registro creado con éxito', 'data' => $registro], 201);
        });
    }

    public function update(Request $request, Registro $registro)
    {
        $user = $request->user();
        $mecanica = Configuracion::where('clave', 'mecanica')->first()?->valor ?? [];
        $prefijo = $mecanica['prefijo_cuenta'] ?? '126';
        $digitos = $mecanica['digitos_cuenta'] ?? 15;

        $data = $request->validate([
            'agencia_id'            => 'required|exists:cartilla_agencias,id',
            'codigo_cliente'        => 'required|regex:/^[0-9]{5,7}$/',
            'accion'                => 'required|in:CREDITO_NUEVO,PLAZO_FIJO,MOTOCICLETA,PAGO_PUNTUAL',
            'tipo_operacion'        => 'nullable|string',
            'monto'                 => 'required|numeric|min:0.01',
            'numero_cuenta'         => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($request, $prefijo, $digitos) {
                    $accion = $request->input('accion');
                    if (in_array($accion, ['CREDITO_NUEVO', 'PLAZO_FIJO', 'PAGO_PUNTUAL'])) {
                        if (empty($value)) {
                            return $fail('El número de cuenta es requerido para esta acción.');
                        }
                        if (!preg_match("/^{$prefijo}[0-9]{" . ($digitos - strlen($prefijo)) . "}$/", $value)) {
                            return $fail("La cuenta debe empezar con {$prefijo} y tener exactamente {$digitos} dígitos.");
                        }
                    }
                }
            ],
            'cartilla_nueva'        => 'boolean',
            'cartilla_completada'   => 'boolean',
            'promocional_entregado' => 'nullable|string|exists:cartilla_promocionales,nombre',
            'notes'                 => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $registro, $user, $mecanica) {
            // A. Registrar snapshot de cambio antes de revertir
            HistorialRegistro::create([
                'registro_id'     => $registro->id,
                'usuario_id'      => $user->id,
                'nombre_usuario'  => $user->name ?? $user->username,
                'estado_cambio'   => 'EDITADO',
                'snapshot'        => $registro->toArray(),
                'ejecutado_en'    => now(),
            ]);

            // B. Revertir stocks consumidos previamente por este registro
            $this->revertirInventarioRegistro($registro);

            // C. Calcular nuevos stickers
            $stickers = 0;
            switch ($data['accion']) {
                case 'CREDITO_NUEVO':
                    $stickers = $mecanica['stickers_credito_nuevo'] ?? 15;
                    break;
                case 'PLAZO_FIJO':
                    $stickers = $mecanica['stickers_plazo_fijo'] ?? 15;
                    if (!empty($mecanica['plazo_fijo_unico_diario'])) {
                        // Buscamos si existe otro plazo fijo en la misma fecha (excluyendo el registro actual)
                        $yaExiste = Registro::where('codigo_cliente', $data['codigo_cliente'])
                            ->where('id', '!=', $registro->id)
                            ->where('accion', 'PLAZO_FIJO')
                            ->where('stickers', '>', 0)
                            ->whereDate('created_at', $registro->created_at->toDateString())
                            ->lockForUpdate()
                            ->exists();

                        if ($yaExiste) {
                            $stickers = 0;
                        }
                    }
                    break;
                case 'MOTOCICLETA':
                    $isFinanciada = strtolower($data['tipo_operacion'] ?? '') === 'financiada';
                    $stickers = $isFinanciada 
                        ? ($mecanica['stickers_moto_financiada'] ?? 15)
                        : ($mecanica['stickers_moto_contado'] ?? 10);
                    break;
                case 'PAGO_PUNTUAL':
                    $stickers = $mecanica['stickers_pago_puntual'] ?? 5;
                    break;
            }

            $agenciaId = $data['agencia_id'];

            // D. Validar stock de nuevo
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

            // E. Aplicar descuentos de nuevo
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
                    'codigo_registro'    => $registro->codigo,
                    'codigo_cliente'     => $data['codigo_cliente'],
                    'detalle'            => "Consumo automático (Edición) por registro {$registro->codigo}",
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
                    'codigo_registro'    => $registro->codigo,
                    'codigo_cliente'     => $data['codigo_cliente'],
                    'detalle'            => "Entrega de cartilla (Edición) por registro {$registro->codigo}",
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
                    'codigo_registro'    => $registro->codigo,
                    'codigo_cliente'     => $data['codigo_cliente'],
                    'detalle'            => "Entrega de promocional (Edición) '{$data['promocional_entregado']}' por registro {$registro->codigo}",
                ]);
            }

            // F. Actualizar Registro
            $registro->update([
                'agencia_id'            => $agenciaId,
                'codigo_cliente'        => $data['codigo_cliente'],
                'accion'                => $data['accion'],
                'tipo_operacion'        => $data['tipo_operacion'] ?? null,
                'monto'                 => $data['monto'],
                'numero_cuenta'         => $data['numero_cuenta'] ?? null,
                'stickers'              => $stickers,
                'cartilla_nueva'        => $data['cartilla_nueva'] ?? false,
                'cartilla_completada'   => $data['cartilla_completada'] ?? false,
                'sorteo'                => $data['cartilla_completada'] ?? false,
                'promocional_entregado' => $data['promocional_entregado'] ?? null,
                'notas'                 => $data['notes'] ?? null,
            ]);

            return response()->json(['msg' => 'Registro actualizado', 'data' => $registro]);
        });
    }

    public function destroy(Registro $registro)
    {
        $user = request()->user();

        return DB::transaction(function () use ($registro, $user) {
            // A. Registrar snapshot
            HistorialRegistro::create([
                'registro_id'     => $registro->id,
                'usuario_id'      => $user->id,
                'nombre_usuario'  => $user->name ?? $user->username,
                'estado_cambio'   => 'ELIMINADO',
                'snapshot'        => $registro->toArray(),
                'ejecutado_en'    => now(),
            ]);

            // B. Revertir stocks e inventario de Kárdex
            $this->revertirInventarioRegistro($registro);

            // C. Eliminar registro
            $registro->delete();

            return response()->json(['msg' => 'Registro eliminado con éxito']);
        });
    }

    /**
     * Reversa de inventario y movimientos asociados a un registro
     */
    private function revertirInventarioRegistro(Registro $registro)
    {
        $agenciaId = $registro->agencia_id;

        // Revertir stickers
        if ($registro->stickers > 0) {
            InventarioStock::where('agencia_id', $agenciaId)
                ->where('recurso', 'STICKERS')
                ->increment('cantidad', $registro->stickers);
        }

        // Revertir cartilla nueva
        if ($registro->cartilla_nueva) {
            InventarioStock::where('agencia_id', $agenciaId)
                ->where('recurso', 'CARTILLAS')
                ->increment('cantidad', 1);
        }

        // Revertir promocional entregado
        if ($registro->promocional_entregado) {
            InventarioStock::where('agencia_id', $agenciaId)
                ->where('recurso', 'PROMOCIONAL')
                ->where('nombre_promocional', $registro->promocional_entregado)
                ->increment('cantidad', 1);
        }

        // Borrar movimientos asociados anteriores
        MovimientoInventario::where('codigo_registro', $registro->codigo)->delete();
    }
}
