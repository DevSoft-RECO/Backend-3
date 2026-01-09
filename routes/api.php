<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\LocalidadController;
use App\Http\Controllers\TipoApoyoController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\CategoriaFacturaController;

// Asegúrate de que el middleware 'sso' esté registrado en bootstrap/app.php
Route::middleware('sso')->group(function () {

    // --- LOCALIDADES ---
    Route::get('/departamentos', [LocalidadController::class, 'indexDepartamentos']);
    Route::post('/departamentos', [LocalidadController::class, 'storeDepartamento']);

    Route::get('/municipios', [LocalidadController::class, 'indexMunicipios']);
    Route::post('/municipios', [LocalidadController::class, 'storeMunicipio']);

    Route::get('/comunidades', [LocalidadController::class, 'indexComunidades']);
    Route::post('/comunidades', [LocalidadController::class, 'storeComunidad']);

    // --- TIPOS DE APOYO ---
    Route::get('/tipos-apoyo', [TipoApoyoController::class, 'index']);
    Route::post('/tipos-apoyo', [TipoApoyoController::class, 'store']);

    // Generic CRUD
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
