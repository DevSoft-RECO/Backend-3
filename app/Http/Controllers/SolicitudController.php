<?php

namespace App\Http\Controllers;

use App\Models\SolicitudApoyo;
use App\Enums\EstadoSolicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SolicitudController extends Controller
{
    // Definimos el nombre del disco para no escribirlo muchas veces
    protected $disk = 'gcs';

    // ----------------------------------------------------------------
    // LISTAR (Filtros: fecha_evento, estado)
    // ----------------------------------------------------------------
    public function index(Request $request)
    {
        $query = SolicitudApoyo::with(['comunidad.municipio', 'tipoApoyo']);

        // Filtro por rango de fecha de evento (Urgency)
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_evento', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_evento', '<=', $request->fecha_fin);
        }

        // Ordenar por urgencia (fecha mas proxima primero)
        // Por defecto ascendente para ver las que estan por vencer
        $query->orderBy('fecha_evento', 'asc');

        // Otros filtros opcionales
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        return response()->json($query->paginate(20)->through(function ($item) {
            $disk = Storage::disk($this->disk);
            $ttl = now()->addMinutes(20);

            $item->url_documento_adjunto = $item->path_documento_adjunto ? $disk->temporaryUrl($item->path_documento_adjunto, $ttl) : null;
            $item->url_documento_firmado = $item->path_documento_firmado ? $disk->temporaryUrl($item->path_documento_firmado, $ttl) : null;
            $item->url_foto_entrega = $item->path_foto_entrega ? $disk->temporaryUrl($item->path_foto_entrega, $ttl) : null;
            $item->url_foto_conocimiento = $item->path_foto_conocimiento ? $disk->temporaryUrl($item->path_foto_conocimiento, $ttl) : null;

            return $item;
        }));
    }

    // ----------------------------------------------------------------
    // ETAPA 1: CREAR (Estado -> SOLICITADO)
    // ----------------------------------------------------------------
    public function store(Request $request)
    {
        $data = $request->validate([
            'fecha_solicitud' => 'required|date',
            'fecha_evento' => 'required|date',
            'nombre_solicitante' => 'required|string',
            'telefono' => 'required|string',
            'nombre_contacto' => 'nullable|string',
            'comunidad_id' => 'required|exists:comunidades,id',
            'comentario_solicitud' => 'required|string',
            'documento_adjunto' => 'required|file|mimes:pdf|max:5120',
        ]);

        // CAMBIO: Usamos el disco 'gcs' en lugar de 'public', carpeta 'mercadeo' y nombre original con prefijo
        $file = $request->file('documento_adjunto');
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('mercadeo/solicitudes/iniciales', $filename, $this->disk);

        $user = $request->user();

        $solicitud = SolicitudApoyo::create([
            ...$data,
            'path_documento_adjunto' => $path,
            'usuario_creacion_id' => $user->id,
            'agencia_id' => $user->agencia_id ?? 1,
            'estado' => EstadoSolicitud::Solicitado,
        ]);

        return response()->json(['msg' => 'Solicitud creada en GCS', 'data' => $solicitud], 201);
    }

    // ----------------------------------------------------------------
    // ETAPA 2: GESTIONAR (Estado -> EN_GESTION)
    // ----------------------------------------------------------------
    public function gestionar(Request $request, SolicitudApoyo $solicitud)
    {
        // Validar que no esté ya finalizada o rechazada
        if ($solicitud->estado === EstadoSolicitud::Rechazado || $solicitud->estado === EstadoSolicitud::Finalizado) {
             return response()->json(['error' => 'Solicitud cerrada'], 400);
        }

        $request->validate(['comentario_gestion' => 'required|string']);

        $solicitud->update([
            'comentario_gestion' => $request->comentario_gestion,
            'usuario_gestion_id' => $request->user()->id, // Auditoría
            'fecha_inicio_gestion' => now(), // Analítica
            'estado' => EstadoSolicitud::EnGestion,
        ]);

        return response()->json(['msg' => 'Solicitud en etapa de gestión']);
    }

    // ----------------------------------------------------------------
    // ETAPA 3: APROBAR (Estado -> APROBADO)
    // ----------------------------------------------------------------
    public function aprobar(Request $request, SolicitudApoyo $solicitud)
    {
        $request->validate([
            'responsable_asignado' => 'required|string',
            'tipo_apoyo_id' => 'required|exists:tipos_apoyo,id',
            'monto' => 'nullable|numeric',
            'documento_firmado' => 'required|file|mimes:pdf|max:5120',
        ]);

        // CAMBIO: Guardamos en 'gcs' dentro de 'mercadeo'
        $file = $request->file('documento_firmado');
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('mercadeo/solicitudes/firmados', $filename, $this->disk);

        $solicitud->update([
            'responsable_asignado' => $request->responsable_asignado,
            'tipo_apoyo_id' => $request->tipo_apoyo_id,
            'monto' => $request->monto,
            'path_documento_firmado' => $path,
            'usuario_aprobacion_id' => $request->user()->id,
            'fecha_aprobacion' => now(), // Analítica
            'estado' => EstadoSolicitud::Aprobado,
        ]);

        return response()->json(['msg' => 'Solicitud Aprobada y Formalizada']);
    }

    // ----------------------------------------------------------------
    // ETAPA 4: FINALIZAR (Estado -> FINALIZADO)
    // ----------------------------------------------------------------
    public function finalizar(Request $request, SolicitudApoyo $solicitud)
    {
        if ($solicitud->estado !== EstadoSolicitud::Aprobado) {
            return response()->json(['error' => 'La solicitud debe estar APROBADA'], 400);
        }

        $request->validate([
            'foto_entrega' => 'required|image|max:2048',
            'foto_conocimiento' => 'required|image|max:2048',
        ]);

        // CAMBIO: Fotos enviadas directo a la nube carpeta 'mercadeo'
        $fileEntrega = $request->file('foto_entrega');
        $pathEntrega = $fileEntrega->storeAs('mercadeo/evidencias', uniqid() . '_' . $fileEntrega->getClientOriginalName(), $this->disk);

        $fileConocimiento = $request->file('foto_conocimiento');
        $pathConocimiento = $fileConocimiento->storeAs('mercadeo/evidencias', uniqid() . '_' . $fileConocimiento->getClientOriginalName(), $this->disk);

        $solicitud->update([
            'path_foto_entrega' => $pathEntrega,
            'path_foto_conocimiento' => $pathConocimiento,
            'estado' => EstadoSolicitud::Finalizado,
        ]);

        return response()->json(['msg' => 'Proceso Finalizado en Google Cloud']);
    }

    // ----------------------------------------------------------------
    // EXTRA: RECHAZAR (Disponible en cualquier momento)
    // ----------------------------------------------------------------
    public function rechazar(Request $request, SolicitudApoyo $solicitud)
    {
        $request->validate(['motivo_rechazo' => 'required|string|min:5']);

        $solicitud->update([
            'estado' => EstadoSolicitud::Rechazado,
            'motivo_rechazo' => $request->motivo_rechazo,
            'fecha_rechazo' => now(), // Analítica
        ]);

        return response()->json(['msg' => 'Solicitud Rechazada']);
    }

    public function reactivar(Request $request, SolicitudApoyo $solicitud)
    {
        try {
            // Se asume validación de Rol/Permiso en Frontend/Token (Mother App)

            $solicitud->update([
                'estado' => EstadoSolicitud::Solicitado,
                'motivo_rechazo' => null,
                'fecha_rechazo' => null,
            ]);

            return response()->json(['msg' => 'Solicitud Reactivada']);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage(), 'trace' => $th->getTraceAsString()], 500);
        }
    }

    // ----------------------------------------------------------------
    // ADMIN: EDITAR (Modificación directa)
    // ----------------------------------------------------------------
    public function update(Request $request, SolicitudApoyo $solicitud)
    {
        // Restricción para el Creador: Solo editar si está en SOLICITADO
        if ($request->user()->id === $solicitud->usuario_creacion_id) {
            if ($solicitud->estado !== EstadoSolicitud::Solicitado) {
                return response()->json(['error' => 'Solo puedes editar la solicitud mientras esté en estado SOLICITADO.'], 403);
            }
        }

        // Validacion flexible para admins
        $data = $request->validate([
            'fecha_solicitud' => 'nullable|date',
            'fecha_evento' => 'nullable|date',
            'nombre_solicitante' => 'nullable|string',
            'nombre_contacto' => 'nullable|string',
            'telefono' => 'nullable|string',
            'monto' => 'nullable|numeric',
            'comentario_solicitud' => 'nullable|string',
            'comentario_gestion' => 'nullable|string',
            'documento_adjunto' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        $dataToUpdate = array_filter($data);

        // Si suben nuevo archivo, borrar el anterior y guardar nuevo
        if ($request->hasFile('documento_adjunto')) {
            if ($solicitud->path_documento_adjunto) {
                Storage::disk($this->disk)->delete($solicitud->path_documento_adjunto);
            }
            $file = $request->file('documento_adjunto');
            $filename = uniqid() . '_' . $file->getClientOriginalName();
            $dataToUpdate['path_documento_adjunto'] = $file->storeAs('mercadeo/solicitudes/iniciales', $filename, $this->disk);
        }

        $solicitud->update($dataToUpdate);

        return response()->json(['msg' => 'Solicitud actualizada', 'data' => $solicitud]);
    }

    // ----------------------------------------------------------------
    // ADMIN: ELIMINAR
    // ----------------------------------------------------------------
    public function destroy(SolicitudApoyo $solicitud)
    {
        // CAMBIO: Eliminamos del disco 'gcs'
        if ($solicitud->path_documento_adjunto) {
            Storage::disk($this->disk)->delete($solicitud->path_documento_adjunto);
        }
        if ($solicitud->path_documento_firmado) {
            Storage::disk($this->disk)->delete($solicitud->path_documento_firmado);
        }
        if ($solicitud->path_foto_entrega) {
            Storage::disk($this->disk)->delete($solicitud->path_foto_entrega);
        }
        if ($solicitud->path_foto_conocimiento) {
            Storage::disk($this->disk)->delete($solicitud->path_foto_conocimiento);
        }

        $solicitud->delete();

        return response()->json(['msg' => 'Registro y archivos eliminados de GCS']);
    }

    // ----------------------------------------------------------------
    // EXTRA: OBTENER URL FIRMADA (GCS)
    // ----------------------------------------------------------------
    public function getFileUrl(Request $request, SolicitudApoyo $solicitud)
    {
        $request->validate(['type' => 'required|string|in:adjunto,firmado,entrega,conocimiento']);

        $path = null;
        switch ($request->type) {
            case 'adjunto': $path = $solicitud->path_documento_adjunto; break;
            case 'firmado': $path = $solicitud->path_documento_firmado; break;
            case 'entrega': $path = $solicitud->path_foto_entrega; break;
            case 'conocimiento': $path = $solicitud->path_foto_conocimiento; break;
        }

        if (!$path) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        // Genera URL temporal (5 minutos)
        $url = Storage::disk($this->disk)->temporaryUrl(
            $path,
            now()->addMinutes(5)
        );

        return response()->json(['url' => $url]);
    }
}
