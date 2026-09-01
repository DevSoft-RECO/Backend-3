<?php

namespace App\Http\Controllers\Cartilla;

use App\Http\Controllers\Controller;
use App\Models\Cartilla\Agencia;

class AgenciaController extends Controller
{
    public function index()
    {
        return response()->json(Agencia::orderBy('codigo')->get());
    }
}
