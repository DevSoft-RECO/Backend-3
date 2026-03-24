<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Ruta Anti-JSON / Rescate de Sesión Expirada
Route::get('/login', function () {
    // Si falla el JWT y Laravel intenta redirigir al "login", lo mandamos de vuelta al portal Madre
    $frontendUrl = env('APP_URL_FRONTEND', 'http://localhost:5173');
    return redirect($frontendUrl . '/login?session_expired=true');
})->name('login');

Route::get('/debug-gcs', function () {
    // Esto obliga al SDK de Google a mostrar el error real de la API
    config(['filesystems.disks.gcs.throw' => true]);

    try {
        $disco = Storage::disk('gcs');
        return $disco->put('test.txt', 'Contenido de prueba');
    } catch (\Exception $e) {
        return "EL ERROR REAL ES: " . $e->getMessage();
    }
});
