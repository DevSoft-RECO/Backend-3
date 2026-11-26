<?php

namespace App\Http\Controllers\Ruleta;

use App\Http\Controllers\Controller;
use App\Models\Ruleta\Ruleta;
use App\Models\Ruleta\RuletaGiro;
use App\Models\Ruleta\RuletaPremio;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminRuletaController extends Controller
{
    private const MAX_IMAGE_BYTES = 102400; // 100 KB
    private const MAX_TITLE_IMAGE_BYTES = 184320; // 180 KB para nitidez 4K en banner de título

    public function index()
    {
        $ruletas = Ruleta::with(['agencia:id,codigo,nombre', 'premios'])
            ->withCount(['premios', 'giros'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Ruleta $ruleta) {
                $stockTotal = $ruleta->premios
                    ->where('activo', true)
                    ->where('stock_ilimitado', false)
                    ->sum('stock_actual');

                $ruleta->stock_total_disponible = (int) $stockTotal;
                $ruleta->premios_disponibles_count = $ruleta->premios
                    ->filter(fn (RuletaPremio $p) => $p->disponible)
                    ->count();

                return $ruleta;
            });

        return response()->json($ruletas);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'titulo_imagen' => 'nullable|image|mimes:png,webp|max:10240',
            'usar_imagen_titulo' => 'sometimes|boolean',
            'agencia_o_evento' => 'nullable|string|max:255',
            'agencia_id' => 'nullable|integer|exists:cartilla_agencias,id',
            'activo' => 'sometimes|boolean',
        ]);

        unset($data['titulo_imagen']);
        if ($request->hasFile('titulo_imagen')) {
            $data['titulo_imagen_path'] = $this->procesarYGuardarImagenTitulo($request->file('titulo_imagen'));
        }

        $ruleta = Ruleta::create($data);
        $ruleta->load(['agencia:id,codigo,nombre', 'premios']);

        return response()->json([
            'msg' => 'Ruleta creada correctamente',
            'data' => $ruleta,
        ], 201);
    }

    public function show(Ruleta $ruleta)
    {
        $ruleta->load(['agencia:id,codigo,nombre', 'premios']);
        $ruleta->loadCount('giros');

        return response()->json($ruleta);
    }

    public function update(Request $request, Ruleta $ruleta)
    {
        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'titulo_imagen' => 'nullable|image|mimes:png,webp|max:10240',
            'eliminar_titulo_imagen' => 'sometimes|boolean',
            'usar_imagen_titulo' => 'sometimes|boolean',
            'agencia_o_evento' => 'nullable|string|max:255',
            'agencia_id' => 'nullable|integer|exists:cartilla_agencias,id',
            'activo' => 'sometimes|boolean',
        ]);

        $eliminarTituloImagen = filter_var($data['eliminar_titulo_imagen'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['titulo_imagen'], $data['eliminar_titulo_imagen']);

        if ($eliminarTituloImagen && $ruleta->titulo_imagen_path) {
            $this->eliminarImagenFisica($ruleta->titulo_imagen_path);
            $data['titulo_imagen_path'] = null;
        }

        if ($request->hasFile('titulo_imagen')) {
            $this->eliminarImagenFisica($ruleta->titulo_imagen_path);
            $data['titulo_imagen_path'] = $this->procesarYGuardarImagenTitulo($request->file('titulo_imagen'));
        }

        $ruleta->update($data);
        $ruleta->load(['agencia:id,codigo,nombre', 'premios']);

        return response()->json([
            'msg' => 'Ruleta actualizada correctamente',
            'data' => $ruleta,
        ]);
    }

    public function destroy(Ruleta $ruleta)
    {
        // Limpiar imágenes físicas asociadas en public/uploads/ruleta
        $this->eliminarImagenFisica($ruleta->titulo_imagen_path);
        foreach ($ruleta->premios as $premio) {
            $this->eliminarImagenFisica($premio->imagen_path);
        }

        $ruleta->delete();

        return response()->json(['msg' => 'Ruleta eliminada correctamente']);
    }

    public function storePremio(Request $request, Ruleta $ruleta)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'imagen' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:10240',
            'modo_visualizacion' => 'sometimes|in:texto,imagen',
            'probabilidad' => 'sometimes|numeric|min:0.01|max:1000',
            'stock_inicial' => 'nullable|integer|min:0',
            'stock_actual' => 'nullable|integer|min:0',
            'stock_ilimitado' => 'sometimes|boolean',
            'es_premio' => 'sometimes|boolean',
            'orden' => 'sometimes|integer|min:0',
            'activo' => 'sometimes|boolean',
        ]);

        $esPremio = array_key_exists('es_premio', $data)
            ? filter_var($data['es_premio'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $data['es_premio'] = $esPremio;

        if (!$esPremio) {
            // Casilla sin premio (consuelo): no maneja stock, solo su probabilidad
            $data['stock_ilimitado'] = true;
            $data['stock_inicial'] = 0;
            $data['stock_actual'] = 0;
        } else {
            $data['stock_inicial'] = (int) ($data['stock_inicial'] ?? 0);
            if (!isset($data['stock_actual'])) {
                $data['stock_actual'] = $data['stock_inicial'];
            }
        }

        if (!isset($data['probabilidad'])) {
            $data['probabilidad'] = 10.00;
        }
        if (!isset($data['orden'])) {
            $data['orden'] = ((int) $ruleta->premios()->max('orden')) + 1;
        }
        if (!isset($data['modo_visualizacion'])) {
            $data['modo_visualizacion'] = 'texto';
        }

        unset($data['imagen']);
        if ($request->hasFile('imagen')) {
            $data['imagen_path'] = $this->procesarYGuardarImagen($request->file('imagen'));
        }

        $premio = $ruleta->premios()->create($data);

        return response()->json([
            'msg' => 'Premio agregado correctamente',
            'data' => $premio->fresh(),
        ], 201);
    }

    public function updatePremio(Request $request, RuletaPremio $premio)
    {
        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'imagen' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:10240',
            'eliminar_imagen' => 'sometimes|boolean',
            'modo_visualizacion' => 'sometimes|in:texto,imagen',
            'probabilidad' => 'sometimes|numeric|min:0.01|max:1000',
            'stock_inicial' => 'sometimes|integer|min:0',
            'stock_actual' => 'sometimes|integer|min:0',
            'stock_ilimitado' => 'sometimes|boolean',
            'es_premio' => 'sometimes|boolean',
            'orden' => 'sometimes|integer|min:0',
            'activo' => 'sometimes|boolean',
        ]);

        if (array_key_exists('es_premio', $data)) {
            $esPremio = filter_var($data['es_premio'], FILTER_VALIDATE_BOOLEAN);
            $data['es_premio'] = $esPremio;
            if (!$esPremio) {
                $data['stock_ilimitado'] = true;
                $data['stock_inicial'] = 0;
                $data['stock_actual'] = 0;
            }
        }

        $eliminarImagen = filter_var($data['eliminar_imagen'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['imagen'], $data['eliminar_imagen']);

        if ($eliminarImagen && $premio->imagen_path) {
            $this->eliminarImagenFisica($premio->imagen_path);
            $data['imagen_path'] = null;
        }

        if ($request->hasFile('imagen')) {
            $this->eliminarImagenFisica($premio->imagen_path);
            $data['imagen_path'] = $this->procesarYGuardarImagen($request->file('imagen'));
        }

        $premio->update($data);

        return response()->json([
            'msg' => 'Premio actualizado correctamente',
            'data' => $premio->fresh(),
        ]);
    }

    public function destroyPremio(RuletaPremio $premio)
    {
        $this->eliminarImagenFisica($premio->imagen_path);
        $premio->delete();

        return response()->json(['msg' => 'Premio eliminado correctamente']);
    }

    public function resetStock(Ruleta $ruleta)
    {
        DB::transaction(function () use ($ruleta) {
            $ruleta->premios()
                ->where('stock_ilimitado', false)
                ->update(['stock_actual' => DB::raw('stock_inicial')]);
        });

        return response()->json([
            'msg' => 'Stock restaurado al valor inicial',
            'premios' => $ruleta->premios()->get(),
        ]);
    }

    public function giros(Request $request, Ruleta $ruleta)
    {
        $perPage = min(max((int) $request->query('per_page', 25), 5), 100);
        $giros = $ruleta->giros()->paginate($perPage);

        $resumenPorPremio = RuletaGiro::where('ruleta_id', $ruleta->id)
            ->select('premio_id', 'premio_nombre', 'es_premio', DB::raw('COUNT(*) as total_giros'))
            ->groupBy('premio_id', 'premio_nombre', 'es_premio')
            ->orderByDesc('total_giros')
            ->get();

        $totalGiros = RuletaGiro::where('ruleta_id', $ruleta->id)->count();
        $totalPremiosGanados = RuletaGiro::where('ruleta_id', $ruleta->id)
            ->where('es_premio', true)
            ->count();

        return response()->json([
            'stats' => [
                'total_giros' => $totalGiros,
                'total_premios_ganados' => $totalPremiosGanados,
                'resumen_por_premio' => $resumenPorPremio,
            ],
            'giros' => $giros,
        ]);
    }

    public function updateGiro(Request $request, RuletaGiro $giro)
    {
        $data = $request->validate([
            'observacion_ganador' => 'nullable|string|max:500',
        ]);

        $giro->update([
            'observacion_ganador' => $data['observacion_ganador'] !== null
                ? trim($data['observacion_ganador']) ?: null
                : null,
        ]);

        return response()->json([
            'msg' => 'Registro actualizado correctamente',
            'data' => $giro->fresh(),
        ]);
    }

    /**
     * Comprime y redimensiona cualquier imagen subida para que pese menos de 100 KB,
     * guardándola directamente en public/uploads/ruleta/ (sin enlaces simbólicos).
     */
    private function procesarYGuardarImagen(UploadedFile $file): string
    {
        $destDir = public_path('uploads/ruleta');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $rawBytes = file_get_contents($file->getRealPath());
        $srcImage = @imagecreatefromstring($rawBytes);

        // Si por alguna razón GD no pudiera decodificar, guardamos el archivo original directamente
        if (!$srcImage) {
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'premio_' . Str::uuid()->toString() . '.' . $ext;
            $file->move($destDir, $filename);
            return 'uploads/ruleta/' . $filename;
        }

        $origW = imagesx($srcImage);
        $origH = imagesy($srcImage);

        // Redimensionar inicialmente a máximo 540px por lado manteniendo proporción
        $maxSide = 540;
        $scale = min(1.0, $maxSide / max($origW, $origH, 1));
        $targetW = max(1, (int) round($origW * $scale));
        $targetH = max(1, (int) round($origH * $scale));

        $useWebp = function_exists('imagewebp');
        $ext = $useWebp ? 'webp' : 'jpg';
        $filename = 'premio_' . Str::uuid()->toString() . '.' . $ext;
        $fullPath = $destDir . DIRECTORY_SEPARATOR . $filename;

        $workingImage = $this->redimensionarGd($srcImage, $origW, $origH, $targetW, $targetH, $useWebp);
        imagedestroy($srcImage);

        // Reducir calidad y, si hace falta, dimensiones hasta quedar por debajo de 100 KB
        $bestBinary = null;
        for ($pass = 0; $pass < 5; $pass++) {
            foreach ([82, 72, 62, 50, 38, 28] as $quality) {
                ob_start();
                if ($useWebp) {
                    imagewebp($workingImage, null, $quality);
                } else {
                    imagejpeg($workingImage, null, $quality);
                }
                $binary = ob_get_clean();
                $bestBinary = $binary;

                if ($binary !== false && strlen($binary) <= self::MAX_IMAGE_BYTES) {
                    file_put_contents($fullPath, $binary);
                    imagedestroy($workingImage);
                    return 'uploads/ruleta/' . $filename;
                }
            }

            // Si aún supera 100 KB, reducimos dimensiones un 22% y reintentamos
            $curW = imagesx($workingImage);
            $curH = imagesy($workingImage);
            $nextW = max(96, (int) round($curW * 0.78));
            $nextH = max(96, (int) round($curH * 0.78));
            $resized = $this->redimensionarGd($workingImage, $curW, $curH, $nextW, $nextH, $useWebp);
            imagedestroy($workingImage);
            $workingImage = $resized;
        }

        if ($bestBinary !== null) {
            file_put_contents($fullPath, $bestBinary);
        }
        imagedestroy($workingImage);

        return 'uploads/ruleta/' . $filename;
    }

    /**
     * Procesa la imagen de título (PNG/WebP sin fondo) conservando transparencia,
     * recortando bordes transparentes vacíos y guardándola en public/uploads/ruleta/.
     */
    private function procesarYGuardarImagenTitulo(UploadedFile $file): string
    {
        $destDir = public_path('uploads/ruleta');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $rawBytes = file_get_contents($file->getRealPath());
        $srcImage = @imagecreatefromstring($rawBytes);

        if (!$srcImage) {
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'titulo_' . Str::uuid()->toString() . '.' . $ext;
            $file->move($destDir, $filename);
            return 'uploads/ruleta/' . $filename;
        }

        imagealphablending($srcImage, false);
        imagesavealpha($srcImage, true);

        // Auto-recortar márgenes transparentes vacíos para aprovechar bien la caja del título
        if (function_exists('imagecropauto')) {
            $cropped = @imagecropauto($srcImage, IMG_CROP_TRANSPARENT);
            if ($cropped !== false && imagesx($cropped) > 8 && imagesy($cropped) > 8) {
                imagedestroy($srcImage);
                $srcImage = $cropped;
                imagealphablending($srcImage, false);
                imagesavealpha($srcImage, true);
            } elseif ($cropped !== false) {
                imagedestroy($cropped);
            }
        }

        $origW = imagesx($srcImage);
        $origH = imagesy($srcImage);

        // Máximo 1200x450 px conservando proporción (nitidez 4K)
        $scale = min(1.0, 1200 / max($origW, 1), 450 / max($origH, 1));
        $targetW = max(1, (int) round($origW * $scale));
        $targetH = max(1, (int) round($origH * $scale));

        $useWebp = function_exists('imagewebp');
        $ext = $useWebp ? 'webp' : 'png';
        $filename = 'titulo_' . Str::uuid()->toString() . '.' . $ext;
        $fullPath = $destDir . DIRECTORY_SEPARATOR . $filename;

        $workingImage = $this->redimensionarGd($srcImage, $origW, $origH, $targetW, $targetH, true);
        imagedestroy($srcImage);

        if (!$useWebp) {
            imagepng($workingImage, $fullPath, 8);
            imagedestroy($workingImage);
            return 'uploads/ruleta/' . $filename;
        }

        $bestBinary = null;
        for ($pass = 0; $pass < 4; $pass++) {
            foreach ([88, 78, 68, 56, 44] as $quality) {
                ob_start();
                imagewebp($workingImage, null, $quality);
                $binary = ob_get_clean();
                $bestBinary = $binary;

                if ($binary !== false && strlen($binary) <= self::MAX_TITLE_IMAGE_BYTES) {
                    file_put_contents($fullPath, $binary);
                    imagedestroy($workingImage);
                    return 'uploads/ruleta/' . $filename;
                }
            }

            $curW = imagesx($workingImage);
            $curH = imagesy($workingImage);
            $nextW = max(240, (int) round($curW * 0.80));
            $nextH = max(80, (int) round($curH * 0.80));
            $resized = $this->redimensionarGd($workingImage, $curW, $curH, $nextW, $nextH, true);
            imagedestroy($workingImage);
            $workingImage = $resized;
        }

        if ($bestBinary !== null) {
            file_put_contents($fullPath, $bestBinary);
        }
        imagedestroy($workingImage);

        return 'uploads/ruleta/' . $filename;
    }

    private function redimensionarGd($src, int $srcW, int $srcH, int $dstW, int $dstH, bool $preserveAlpha)
    {
        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($preserveAlpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
        } else {
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        return $dst;
    }

    private function eliminarImagenFisica(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }
        $fullPath = public_path($relativePath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
