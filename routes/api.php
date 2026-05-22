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
});
