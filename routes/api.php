<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\LocalidadController;
use App\Http\Controllers\TipoApoyoController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\CategoriaFacturaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\SSOController;

Route::middleware('sso')->group(function () {

    Route::get('/me', [SSOController::class, 'me']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // --- AUDIT ---
    Route::get('/audit/solicitudes', [AuditController::class, 'index']);
    Route::get('/audit/agencias-catalog', [AuditController::class, 'agenciasCatalog']);
    Route::get('/audit/stats', [AuditController::class, 'auditStats']);

    // --- LOCALIDADES ---
    Route::get('/departamentos', [LocalidadController::class, 'indexDepartamentos']);
    Route::post('/departamentos', [LocalidadController::class, 'storeDepartamento']);
    Route::put('/departamentos/{departamento}', [LocalidadController::class, 'updateDepartamento']);
    Route::delete('/departamentos/{departamento}', [LocalidadController::class, 'destroyDepartamento']);

    Route::get('/municipios', [LocalidadController::class, 'indexMunicipios']);
    Route::post('/municipios', [LocalidadController::class, 'storeMunicipio']);
    Route::put('/municipios/{municipio}', [LocalidadController::class, 'updateMunicipio']);
    Route::delete('/municipios/{municipio}', [LocalidadController::class, 'destroyMunicipio']);

    Route::get('/comunidades', [LocalidadController::class, 'indexComunidades']);
    Route::post('/comunidades', [LocalidadController::class, 'storeComunidad']);
    Route::put('/comunidades/{comunidad}', [LocalidadController::class, 'updateComunidad']);
    Route::delete('/comunidades/{comunidad}', [LocalidadController::class, 'destroyComunidad']);

    // --- TIPOS DE APOYO ---
    Route::get('/tipos-apoyo', [TipoApoyoController::class, 'index']);
    Route::post('/tipos-apoyo', [TipoApoyoController::class, 'store']);
    Route::put('/tipos-apoyo/{tipo}', [TipoApoyoController::class, 'update']);
    Route::delete('/tipos-apoyo/{tipo}', [TipoApoyoController::class, 'destroy']);

    // Generic CRUD
    Route::get('/solicitudes/export/csv', [SolicitudController::class, 'exportCsv']);
    Route::get('/solicitudes/{solicitud}/file-url', [SolicitudController::class, 'getFileUrl']); // Added this route
    Route::get('/solicitudes', [SolicitudController::class, 'index']);
    Route::get('/tipos-apoyo', [TipoApoyoController::class, 'index']); // Moved here
    Route::post('/solicitudes', [SolicitudController::class, 'store']);
    Route::put('/solicitudes/{solicitud}', [SolicitudController::class, 'update']);
    Route::delete('/solicitudes/{solicitud}', [SolicitudController::class, 'destroy']);

    // Etapa 2
    Route::put('/solicitudes/{solicitud}/gestionar', [SolicitudController::class, 'gestionar']);
    // Etapa 2.1
    Route::put('/solicitudes/{solicitud}/reactivar', [SolicitudController::class, 'reactivar']);
    // Etapa 3
    Route::post('/solicitudes/{solicitud}/aprobar', [SolicitudController::class, 'aprobar']);
    // Nota: Usamos POST en 'aprobar' y 'finalizar' porque enviamos archivos (Laravel a veces da problemas con archivos en PUT/PATCH)

    // Etapa 4
    Route::post('/solicitudes/{solicitud}/finalizar', [SolicitudController::class, 'finalizar']);

    // Rechazo
    Route::put('/solicitudes/{solicitud}/rechazar', [SolicitudController::class, 'rechazar']);

    // --- MÓDULO FACTURAS ---
    Route::apiResource('categorias-facturas', CategoriaFacturaController::class);

    Route::get('/facturas/export/csv', [FacturaController::class, 'exportCsv']);
    Route::apiResource('facturas', FacturaController::class);

    // --- MÓDULO LA CARTILLA GANADORA ---
    Route::prefix('cartilla')->group(function () {
        Route::get('/dashboard/agencia', [\App\Http\Controllers\Cartilla\DashboardController::class, 'agencia']);
        Route::get('/dashboard/global', [\App\Http\Controllers\Cartilla\DashboardController::class, 'global']);

        Route::get('/registros', [\App\Http\Controllers\Cartilla\RegistroController::class, 'index']);
        Route::post('/registros', [\App\Http\Controllers\Cartilla\RegistroController::class, 'store']);
        Route::put('/registros/{registro}', [\App\Http\Controllers\Cartilla\RegistroController::class, 'update']);
        Route::delete('/registros/{registro}', [\App\Http\Controllers\Cartilla\RegistroController::class, 'destroy']);

        Route::get('/inventario/movimientos', [\App\Http\Controllers\Cartilla\InventarioController::class, 'index']);
        Route::post('/inventario/movimientos', [\App\Http\Controllers\Cartilla\InventarioController::class, 'store']);
        Route::put('/inventario/movimientos/{movimiento}', [\App\Http\Controllers\Cartilla\InventarioController::class, 'update']);
        Route::delete('/inventario/movimientos/{movimiento}', [\App\Http\Controllers\Cartilla\InventarioController::class, 'destroy']);
        Route::get('/inventario/stocks', [\App\Http\Controllers\Cartilla\InventarioController::class, 'stocksResumen']);
        Route::get('/inventario/balance', [\App\Http\Controllers\Cartilla\InventarioController::class, 'balanceInventario']);

        Route::get('/historial/registros', [\App\Http\Controllers\Cartilla\HistorialRegistroController::class, 'index']);
        Route::get('/historial/inventario', [\App\Http\Controllers\Cartilla\HistorialInventarioController::class, 'index']);
        Route::post('/historial/inventario/{historial}/restaurar', [\App\Http\Controllers\Cartilla\HistorialInventarioController::class, 'restaurar']);

        Route::get('/configuracion', [\App\Http\Controllers\Cartilla\ConfiguracionController::class, 'index']);
        Route::put('/configuracion', [\App\Http\Controllers\Cartilla\ConfiguracionController::class, 'update']);
        Route::get('/configuracion/historial', [\App\Http\Controllers\Cartilla\ConfiguracionController::class, 'historial']);

        Route::apiResource('promocionales', \App\Http\Controllers\Cartilla\PromocionalController::class);
        Route::apiResource('notas-rapidas', \App\Http\Controllers\Cartilla\NotaRapidaController::class);
        Route::put('/notas-rapidas/reordenar', [\App\Http\Controllers\Cartilla\NotaRapidaController::class, 'reordenar']);
        Route::apiResource('recordatorios', \App\Http\Controllers\Cartilla\RecordatorioController::class);
        Route::put('/recordatorios/reordenar', [\App\Http\Controllers\Cartilla\RecordatorioController::class, 'reordenar']);
        Route::get('/agencias', [\App\Http\Controllers\Cartilla\AgenciaController::class, 'index']);

        Route::get('/exportar/registros', [\App\Http\Controllers\Cartilla\ExportacionController::class, 'exportarRegistros']);
        Route::get('/exportar/historial-registros', [\App\Http\Controllers\Cartilla\ExportacionController::class, 'exportarHistorialRegistros']);
        Route::get('/exportar/movimientos', [\App\Http\Controllers\Cartilla\ExportacionController::class, 'exportarMovimientos']);
        Route::get('/exportar/historial-inventario', [\App\Http\Controllers\Cartilla\ExportacionController::class, 'exportarHistorialInventario']);

        // Colocaciones / Pagos automáticos
        Route::post('/colocaciones/importar', [\App\Http\Controllers\Cartilla\ColocacionController::class, 'importar']);
        Route::get('/colocaciones/pendientes', [\App\Http\Controllers\Cartilla\ColocacionController::class, 'pendientes']);
        Route::post('/colocaciones/{pago}/reclamar', [\App\Http\Controllers\Cartilla\ColocacionController::class, 'reclamar']);
    });
});

// === BACKUP SYSTEM ===
// Rutas internas de respaldo llamadas por la APP_MADRE (Firmadas con HMAC)
Route::post('/internal/backup', [\App\Http\Controllers\InternalBackupController::class, 'generate']);
Route::delete('/internal/backup', [\App\Http\Controllers\InternalBackupController::class, 'deleteFile']);
Route::get('/internal/download-backup', [\App\Http\Controllers\InternalBackupController::class, 'download']);

