<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Asegúrate de que el middleware 'sso' esté registrado en bootstrap/app.php
Route::middleware('sso')->group(function () {


});
