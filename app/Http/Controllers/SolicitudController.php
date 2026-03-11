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

        // Filtro INDEPENDIENTE por ID
        if ($request->filled('id')) {
            $query->where('id', $request->id);
            // Si buscamos por ID, ignoramos fechas y estados para encontrarlo directo
            return response()->json($query->orderBy('created_at', 'desc')->paginate(20)->through(function ($item) {
                $disk = Storage::disk($this->disk);
                $ttl = now()->addMinutes(20);

                $item->url_documento_adjunto = $item->path_documento_adjunto ? $disk->temporaryUrl($item->path_documento_adjunto, $ttl) : null;
                $item->url_documento_firmado = $item->path_documento_firmado ? $disk->temporaryUrl($item->path_documento_firmado, $ttl) : null;
                $item->url_foto_entrega = $item->path_foto_entrega ? $disk->temporaryUrl($item->path_foto_entrega, $ttl) : null;
                $item->url_foto_conocimiento = $item->path_foto_conocimiento ? $disk->temporaryUrl($item->path_foto_conocimiento, $ttl) : null;

                return $item;
            }));
        }

        // Filtro por rango de fecha de evento (Urgency)
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_evento_inicio', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_evento_inicio', '<=', $request->fecha_fin);
        }

        // Ordenar: Si hay filtros de fecha, ordenar por urgencia (fecha_evento_inicio asc)
        // Si no hay filtros de fecha, ordenar por más reciente (created_at desc)
        if ($request->filled('fecha_inicio') || $request->filled('fecha_fin')) {
            $query->orderBy('fecha_evento_inicio', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Otros filtros opcionales
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // PERMISOS DE VISUALIZACIÓN
        // Si NO es Super Admin y NO tiene permiso de ver todo (e.g., admin_mercadeo),
        // mostrar solo sus propias solicitudes.
        $user = $request->user();

        // Asumiendo Spatie o lógica simple de roles/permisos en User model
        // Si no usas paquete de permisos, verifica tu implementación de hasRole/hasPermissionTo
        // Aquí usaremos la lógica genérica que mencionaste (Super Admin ve todo).
        // Ajusta 'admin_mercadeo' si es el permiso correcto para "Ver Todo".

        // Helper safely check array
        $roles = $user->roles ?? [];
        $permissions = $user->permissions ?? [];

        $checkRole = function($haystack, $needle) {
            return is_array($haystack) && !empty(array_filter($haystack, function($item) use ($needle) {
                return strtolower($item) === strtolower($needle);
            }));
        };

        $isSuperAdmin = $checkRole($roles, 'Super Admin');
        $hasAdminPermission = $checkRole($permissions, 'admin_mercadeo');

        // Filtro obligarorios:
        // 1. Si el usuario NO tiene permisos de admin (Super Admin o admin_mercadeo), ve solo SU AGENCIA.
        // 2. Si el cliente solicita explícitamente "own", ve solo SU USUARIO.

        if (!$isSuperAdmin && !$hasAdminPermission) {
            // Usamos 'idagencia' que viene del token SSO
            $query->where('agencia_id', $user->idagencia);
        }

        // Paginación: 10 por defecto para rapidez, o el valor solicitado
        $perPage = $request->input('per_page', 10);
        return response()->json($query->paginate($perPage));
    }

    // ----------------------------------------------------------------
    // ETAPA 1: CREAR (Estado -> SOLICITADO)
    // ----------------------------------------------------------------
    public function store(Request $request)
    {
        $data = $request->validate([
            'fecha_solicitud' => 'required|date',
            'fecha_evento_inicio' => 'required|date',
            'fecha_evento_fin' => 'required|date|after_or_equal:fecha_evento_inicio',
            'nombre_solicitante' => 'required|string|max:255',
            'telefono' => 'required|string|max:20',
            'nombre_contacto' => 'nullable|string|max:255',
            'comunidad_id' => 'required|exists:comunidades,id',
            'comentario_solicitud' => 'required|string|max:1000',
            'documento_adjunto' => 'required|file|mimes:pdf|max:5120',
        ]);

        // CAMBIO: Usamos el disco 'gcs' en lugar de 'public', carpeta 'mercadeo' y nombre original con prefijo
        $file = $request->file('documento_adjunto');
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('mercadeo/solicitudes/iniciales', $filename, $this->disk);

        if (!$path) {
            return response()->json(['error' => 'Error al subir documento adjunto al disco ' . $this->disk], 500);
        }

        $user = $request->user();

        $solicitud = SolicitudApoyo::create([
            ...$data,
            'path_documento_adjunto' => $path,
            'usuario_creacion' => $user->username ?? $user->name ?? 'Usuario ' . $user->id,
            'agencia_id' => $user->idagencia ?? 1, // Usamos idagencia del token
            'estado' => EstadoSolicitud::Solicitado,
        ]);

        return response()->json(['msg' => 'Solicitud creada en GCS', 'data' => $solicitud], 201);
    }

    // ----------------------------------------------------------------
    // ETAPA 2: GESTIONAR (Estado -> EN_GESTION)
    // ----------------------------------------------------------------
    public function gestionar(Request $request, SolicitudApoyo $solicitud)
    {
        try {
            // Validar que no esté ya finalizada o rechazada
            if ($solicitud->estado === EstadoSolicitud::Rechazado || $solicitud->estado === EstadoSolicitud::Finalizado) {
                 return response()->json(['error' => 'Solicitud cerrada'], 400);
            }

            $request->validate(['comentario_gestion' => 'required|string']);

            $user = $request->user();
            // Prioridad: Nombre enviado desde Frontend > Objeto User > Username > Fallback
            $userName = $request->input('nombre_usuario')
                ?? $user->name
                ?? $user->username
                ?? 'Usuario ' . $user->id;

            $solicitud->update([
                'comentario_gestion' => $request->comentario_gestion,
                'usuario_gestion_id' => $user->id, // Auditoría
                'nombre_usuario_gestion' => $userName,
                'fecha_inicio_gestion' => now(), // Analítica
                'estado' => EstadoSolicitud::EnGestion,
            ]);

            return response()->json(['msg' => 'Solicitud en etapa de gestión']);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage(), 'file' => $th->getFile(), 'line' => $th->getLine()], 500);
        }
    }

    // ----------------------------------------------------------------
    // ETAPA 3: APROBAR (Estado -> APROBADO)
    // ----------------------------------------------------------------
    public function aprobar(Request $request, SolicitudApoyo $solicitud)
    {
        try {
            $request->validate([
                'responsable_asignado' => 'required|string',
                'tipo_apoyo_id' => 'required|exists:tipos_apoyo,id',
                'monto' => 'nullable|numeric',
                'comentario_aprobacion' => 'nullable|string',
                'documento_firmado' => 'required|file|mimes:pdf|max:5120',
            ]);

            // CAMBIO: Guardamos en 'gcs' dentro de 'mercadeo'
            $file = $request->file('documento_firmado');
            $filename = uniqid() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('mercadeo/solicitudes/firmados', $filename, $this->disk);

            if (!$path) {
                return response()->json(['error' => 'Error al subir documento firmado'], 500);
            }

            $user = $request->user();
            $userName = $request->input('nombre_usuario')
                ?? $user->name
                ?? $user->username
                ?? 'Usuario ' . $user->id;

            $solicitud->update([
                'responsable_asignado' => $request->responsable_asignado,
                'tipo_apoyo_id' => $request->tipo_apoyo_id,
                'monto' => $request->monto,
                'comentario_aprobacion' => $request->input('comentario_aprobacion'),
                'path_documento_firmado' => $path,
                'usuario_aprobacion_id' => $user->id,
                'nombre_usuario_aprobacion' => $userName,
                'fecha_aprobacion' => now(), // Analítica
                'estado' => EstadoSolicitud::Aprobado,
            ]);

            return response()->json(['msg' => 'Solicitud Aprobada y Formalizada']);
        } catch (\Throwable $th) {
             return response()->json(['error' => $th->getMessage()], 500);
        }
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
            'documento_evidencia' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // CAMBIO: Archivos enviados directo a la nube carpeta 'mercadeo'
        $file = $request->file('documento_evidencia');
        $path = $file->storeAs('mercadeo/evidencias', uniqid() . '_' . $file->getClientOriginalName(), $this->disk);

        if (!$path) {
             return response()->json(['error' => 'Error al subir documento evidencia'], 500);
        }

        $solicitud->update([
            'path_documento_evidencia' => $path,
            'estado' => EstadoSolicitud::Finalizado,
        ]);

        return response()->json(['msg' => 'Proceso Finalizado en Google Cloud']);
    }

    // ----------------------------------------------------------------
    // EXTRA: RECHAZAR (Disponible en cualquier momento)
    // ----------------------------------------------------------------
    public function rechazar(Request $request, SolicitudApoyo $solicitud)
    {
        try {
            $request->validate(['motivo_rechazo' => 'required|string|min:5']);

            $user = $request->user();
            $userName = $request->input('nombre_usuario')
                ?? $user->name
                ?? $user->username
                ?? 'Usuario ' . $user->id;

            $solicitud->update([
                'estado' => EstadoSolicitud::Rechazado,
                'motivo_rechazo' => $request->motivo_rechazo,
                'usuario_rechazo_id' => $user->id,
                'nombre_usuario_rechazo' => $userName,
                'fecha_rechazo' => now(), // Analítica
            ]);

            return response()->json(['msg' => 'Solicitud Rechazada']);
        } catch (\Throwable $th) {
             return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function reactivar(Request $request, SolicitudApoyo $solicitud)
    {
        try {
            // Se asume validación de Rol/Permiso en Frontend/Token (Mother App)

            $solicitud->update([
                'estado' => EstadoSolicitud::Solicitado,
                'motivo_rechazo' => null,
                'usuario_rechazo_id' => null,
                'nombre_usuario_rechazo' => null,
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
        // Restricción para el Creador: Solo editar si está en SOLICITADO
        // Comparamos nombres de usuario
        $currentUserName = $request->user()->username ?? $request->user()->name ?? 'Usuario ' . $request->user()->id;

        if ($currentUserName === $solicitud->usuario_creacion) {
            if ($solicitud->estado !== EstadoSolicitud::Solicitado) {
                return response()->json(['error' => 'Solo puedes editar la solicitud mientras esté en estado SOLICITADO.'], 403);
            }
        }

        // Validacion flexible para admins
        $data = $request->validate([
            'fecha_solicitud' => 'nullable|date',
            'fecha_evento_inicio' => 'nullable|date',
            'fecha_evento_fin' => 'nullable|date',
            'nombre_solicitante' => 'nullable|string|max:255',
            'nombre_contacto' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'monto' => 'nullable|numeric',
            'comentario_solicitud' => 'nullable|string|max:1000',
            'comentario_gestion' => 'nullable|string|max:1000',
            'comentario_aprobacion' => 'nullable|string|max:1000',
            'responsable_asignado' => 'nullable|string|max:255',
            'tipo_apoyo_id' => 'nullable|exists:tipos_apoyo,id',
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
            $path = $file->storeAs('mercadeo/solicitudes/iniciales', $filename, $this->disk);

            if (!$path) {
                return response()->json(['error' => 'Error al subir documento adjunto (Update)'], 500);
            }

            $dataToUpdate['path_documento_adjunto'] = $path;
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
        if ($solicitud->path_documento_evidencia) {
            Storage::disk($this->disk)->delete($solicitud->path_documento_evidencia);
        }

        $solicitud->delete();

        return response()->json(['msg' => 'Registro y archivos eliminados de GCS']);
    }

    // ----------------------------------------------------------------
    // EXTRA: OBTENER URL FIRMADA (GCS)
    // ----------------------------------------------------------------
    public function getFileUrl(Request $request, SolicitudApoyo $solicitud)
    {
        $request->validate(['type' => 'required|string|in:adjunto,firmado,evidencia']);

        $path = null;
        switch ($request->type) {
            case 'adjunto': $path = $solicitud->path_documento_adjunto; break;
            case 'firmado': $path = $solicitud->path_documento_firmado; break;
            case 'evidencia': $path = $solicitud->path_documento_evidencia; break;
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

    // ----------------------------------------------------------------
    // EXPORT: CSV para Analítica
    // ----------------------------------------------------------------
    public function exportCsv(Request $request)
    {
        $solicitudes = SolicitudApoyo::with(['comunidad.municipio.departamento', 'tipoApoyo'])
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'solicitudes_export_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($solicitudes) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados
            fputcsv($file, [
                'ID',
                'Agencia ID',
                'Usuario Creación',
                'Estado',
                'FechaCreado',
                'Fecha Solicitud',
                'Fecha Evento Inicio',
                'Fecha Evento Fin',
                'Nombre Solicitante',
                'Teléfono',
                'Nombre Contacto',
                'Departamento',
                'Municipio',
                'Comunidad',
                'Comentario Solicitud',
                'Comentario Gestión',
                'Usuario Gestión',
                'Fecha Inicio Gestión',
                'Tipo Apoyo',
                'Responsable Asignado',
                'Monto',
                'Usuario Aprobación',
                'Fecha Aprobación',
                'Comentario Aprobación',
                'Motivo Rechazo',
                'Usuario Rechazo',
                'Fecha Rechazo',
                'Tiene Doc Adjunto',
                'Tiene Doc Firmado',
                'Tiene Doc Evidencia',
                'Actualizado'
            ]);

            // Datos
            foreach ($solicitudes as $s) {
                fputcsv($file, [
                    $s->id,
                    $s->agencia_id,         // Matches 'Agencia ID' header
                    $s->usuario_creacion,   // Matches 'Usuario Creación' header
                    $s->estado->value,      // Matches 'Estado' header
                    $s->created_at,
                    $s->fecha_solicitud,
                    $s->fecha_evento_inicio,
                    $s->fecha_evento_fin,
                    $s->nombre_solicitante,
                    $s->telefono,
                    $s->nombre_contacto,
                    $s->comunidad->municipio->departamento->nombre ?? '',
                    $s->comunidad->municipio->nombre ?? '',
                    $s->comunidad->nombre ?? '',
                    $s->comentario_solicitud,
                    $s->comentario_gestion,
                    $s->nombre_usuario_gestion,
                    $s->fecha_inicio_gestion,
                    $s->tipoApoyo->nombre ?? '',
                    $s->responsable_asignado,
                    $s->monto,
                    $s->nombre_usuario_aprobacion,
                    $s->fecha_aprobacion,
                    $s->comentario_aprobacion,
                    $s->motivo_rechazo,
                    $s->nombre_usuario_rechazo,
                    $s->fecha_rechazo,
                    $s->path_documento_adjunto ? 'Sí' : 'No',
                    $s->path_documento_firmado ? 'Sí' : 'No',
                    $s->path_documento_evidencia ? 'Sí' : 'No',
                    $s->updated_at
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
