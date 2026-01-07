<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});



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
