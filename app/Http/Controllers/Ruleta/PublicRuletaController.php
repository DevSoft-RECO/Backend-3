<?php

namespace App\Http\Controllers\Ruleta;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\Agencia;
use App\Models\Ruleta\Ruleta;
use App\Models\Ruleta\RuletaGiro;
use App\Models\Ruleta\RuletaPremio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicRuletaController extends Controller
{
    /**
     * Lista las agencias activas para que la app móvil seleccione en qué agencia está instalada.
     */
    public function agencias()
    {
        $agencias = Agencia::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        return response()->json([
            'data' => $agencias,
        ]);
    }
    /**
     * Lista las ruletas activas disponibles para la app móvil (sin autenticación).
     */
    public function index()
    {
        $ruletas = Ruleta::with(['agencia:id,codigo,nombre', 'premios' => function ($query) {
            $query->where('activo', true)->orderBy('orden')->orderBy('id');
        }])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(function (Ruleta $ruleta) {
                $premiosActivos = $ruleta->premios;
                $premiosDisponibles = $premiosActivos->filter(fn (RuletaPremio $p) => $p->disponible);
                $stockTotal = $premiosActivos
                    ->where('stock_ilimitado', false)
                    ->sum('stock_actual');

                return [
                    'id' => $ruleta->id,
                    'nombre' => $ruleta->nombre,
                    'descripcion' => $ruleta->descripcion,
                    'agencia_o_evento' => $ruleta->agencia_o_evento ?: ($ruleta->agencia?->nombre ?? 'General'),
                    'total_premios' => $premiosActivos->count(),
                    'premios_disponibles' => $premiosDisponibles->count(),
                    'stock_total_disponible' => (int) $stockTotal,
                    'tiene_ilimitados' => $premiosActivos->contains('stock_ilimitado', true),
                ];
            });

        return response()->json([
            'data' => $ruletas,
        ]);
    }

    /**
     * Devuelve el detalle de una ruleta activa y sus segmentos/premios para la app móvil.
     */
    public function show(Ruleta $ruleta)
    {
        if (!$ruleta->activo) {
            return response()->json([
                'error' => 'Esta ruleta no se encuentra activa en este momento.',
            ], 404);
        }

        $ruleta->load([
            'agencia:id,codigo,nombre',
            'premios' => function ($query) {
                $query->where('activo', true)->orderBy('orden')->orderBy('id');
            },
        ]);

        return response()->json([
            'data' => [
                'id' => $ruleta->id,
                'nombre' => $ruleta->nombre,
                'descripcion' => $ruleta->descripcion,
                'agencia_o_evento' => $ruleta->agencia_o_evento ?: ($ruleta->agencia?->nombre ?? 'General'),
                'premios' => $ruleta->premios->map(fn (RuletaPremio $p) => $this->formatPremio($p))->values(),
            ],
        ]);
    }

    /**
     * Registra un giro elegido por la app móvil y descuenta 1 unidad del stock si aplica.
     */
    public function registrarGiro(Request $request, Ruleta $ruleta)
    {
        if (!$ruleta->activo) {
            return response()->json([
                'error' => 'Esta ruleta ya no está activa.',
            ], 404);
        }

        $data = $request->validate([
            'premio_id' => 'required|integer',
            'dispositivo' => 'nullable|string|max:120',
            'agencia_nombre' => 'nullable|string|max:255',
        ]);

        try {
            $resultado = DB::transaction(function () use ($ruleta, $data) {
                $premio = RuletaPremio::where('id', $data['premio_id'])
                    ->where('ruleta_id', $ruleta->id)
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->first();

                if (!$premio) {
                    return ['status' => 404, 'error' => 'El premio seleccionado no existe o está inactivo.'];
                }

                $stockDescontado = false;
                if (!$premio->stock_ilimitado) {
                    if ($premio->stock_actual <= 0) {
                        return ['status' => 409, 'error' => 'El premio seleccionado ya no tiene stock disponible.'];
                    }
                    $premio->decrement('stock_actual');
                    $premio->refresh();
                    $stockDescontado = true;
                }

                $agenciaNombre = !empty($data['agencia_nombre'])
                    ? trim($data['agencia_nombre'])
                    : ($ruleta->agencia?->nombre ?: $ruleta->agencia_o_evento);

                $giro = RuletaGiro::create([
                    'ruleta_id' => $ruleta->id,
                    'premio_id' => $premio->id,
                    'premio_nombre' => $premio->nombre,
                    'es_premio' => $premio->es_premio,
                    'stock_descontado' => $stockDescontado,
                    'dispositivo' => $data['dispositivo'] ?? null,
                    'agencia_nombre' => $agenciaNombre ?: null,
                    'created_at' => now(),
                ]);

                return [
                    'status' => 201,
                    'giro' => $giro,
                    'premio' => $premio,
                ];
            });

            $premiosActualizados = $ruleta->premios()
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('id')
                ->get()
                ->map(fn (RuletaPremio $p) => $this->formatPremio($p))
                ->values();

            if ($resultado['status'] !== 201) {
                return response()->json([
                    'error' => $resultado['error'],
                    'premios' => $premiosActualizados,
                ], $resultado['status']);
            }

            return response()->json([
                'msg' => 'Giro registrado correctamente',
                'giro' => $resultado['giro'],
                'premio' => $this->formatPremio($resultado['premio']),
                'premios' => $premiosActualizados,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Error al registrar el giro: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function formatPremio(RuletaPremio $p): array
    {
        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'imagen_path' => $p->imagen_path,
            'imagen_url' => $p->imagen_url,
            'modo_visualizacion' => $p->modo_visualizacion ?: 'texto',
            'probabilidad' => (float) ($p->probabilidad ?? 10.0),
            'stock_inicial' => $p->stock_inicial,
            'stock_actual' => $p->stock_actual,
            'stock_ilimitado' => $p->stock_ilimitado,
            'es_premio' => $p->es_premio,
            'orden' => $p->orden,
            'disponible' => $p->disponible,
        ];
    }
}
