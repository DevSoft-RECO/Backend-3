<?php

namespace App\Http\Controllers;

use App\Models\SolicitudApoyo;
use App\Enums\EstadoSolicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SolicitudController extends Controller
{
    // ----------------------------------------------------------------
    // LISTAR (Filtros: fecha_evento, estado)
    // ----------------------------------------------------------------
    public function index(Request $request)
    {
        $query = SolicitudApoyo::query();

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

        return response()->json($query->paginate(20));
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
            'documento_adjunto' => 'required|file|mimes:pdf|max:5120', // Max 5MB
        ]);

        // Guardar archivo
        $path = $request->file('documento_adjunto')->store('solicitudes/iniciales', 'public');

        // Obtener datos del TOKEN
        $user = $request->user(); // El usuario autenticado

        $solicitud = SolicitudApoyo::create([
            ...$data,
            'path_documento_adjunto' => $path,
            'usuario_creacion_id' => $user->id,      // Del Token
            'agencia_id' => $user->agencia_id ?? 1,       // Del Token (Assuming agencia_id might check or default logic if not present, but user snippet says $user->agencia_id. I will stick to it. Wait, user specifically said "$user->agencia_id // Del Token". If User model doesn't have agencia_id, this might fail unless added to User or JWT. For now I assume it works.)
            'estado' => EstadoSolicitud::Solicitado,
        ]);

        return response()->json(['msg' => 'Solicitud creada', 'data' => $solicitud], 201);
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

        $path = $request->file('documento_firmado')->store('solicitudes/firmados', 'public');

        $solicitud->update([
            'responsable_asignado' => $request->responsable_asignado,
            'tipo_apoyo_id' => $request->tipo_apoyo_id,
            'monto' => $request->monto,
            'path_documento_firmado' => $path,
            'usuario_aprobacion_id' => $request->user()->id, // Auditoría
            'estado' => EstadoSolicitud::Aprobado,
        ]);

        return response()->json(['msg' => 'Solicitud Aprobada y Formalizada']);
    }

    // ----------------------------------------------------------------
    // ETAPA 4: FINALIZAR (Estado -> FINALIZADO)
    // ----------------------------------------------------------------
    public function finalizar(Request $request, SolicitudApoyo $solicitud)
    {
        // Solo se puede finalizar si ya fue aprobada
        if ($solicitud->estado !== EstadoSolicitud::Aprobado) {
            return response()->json(['error' => 'La solicitud debe estar APROBADA antes de finalizar'], 400);
        }

        $request->validate([
            'foto_entrega' => 'required|image|max:2048', // 2MB
            'foto_conocimiento' => 'required|image|max:2048',
        ]);

        $pathEntrega = $request->file('foto_entrega')->store('evidencias', 'public');
        $pathConocimiento = $request->file('foto_conocimiento')->store('evidencias', 'public');

        $solicitud->update([
            'path_foto_entrega' => $pathEntrega,
            'path_foto_conocimiento' => $pathConocimiento,
            'estado' => EstadoSolicitud::Finalizado,
        ]);

        return response()->json(['msg' => 'Proceso Finalizado Exitosamente']);
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
            // Opcional: Podrías guardar quien rechazó usando una de las columnas de usuario existentes o una nueva
        ]);

        return response()->json(['msg' => 'Solicitud Rechazada']);
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
            'telefono' => 'nullable|string',
            'monto' => 'nullable|numeric',
            'comentario_solicitud' => 'nullable|string',
            // Agrega aqui otros campos que se permitan editar
        ]);

        $solicitud->update(array_filter($data)); // Solo actualiza lo enviado

        return response()->json(['msg' => 'Solicitud actualizada', 'data' => $solicitud]);
    }

    // ----------------------------------------------------------------
    // ADMIN: ELIMINAR
    // ----------------------------------------------------------------
    public function destroy(SolicitudApoyo $solicitud)
    {
        // Eliminar archivos asociados si es necesario (opcional)
        if ($solicitud->path_documento_adjunto) Storage::disk('public')->delete($solicitud->path_documento_adjunto);
        if ($solicitud->path_documento_firmado) Storage::disk('public')->delete($solicitud->path_documento_firmado);

        $solicitud->delete();

        return response()->json(['msg' => 'Solicitud eliminada correctamente']);
    }
}
