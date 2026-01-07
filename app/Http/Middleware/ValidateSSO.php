<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\GenericUser;

class ValidateSSO
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token requerido'], 401);
        }

        try {
            $publicKeyPath = storage_path('oauth-public.key');

            if (!file_exists($publicKeyPath)) {
                throw new \Exception("Falta llave pública en servidor hijo");
            }

            $publicKey = file_get_contents($publicKeyPath);
            JWT::$leeway = 60; // Margen de error para relojes desincronizados

            // Decodificar Token con RS256
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            // Inyectar usuario en la sesión de Laravel (Memoria)
            $user = new GenericUser([
                'id' => $decoded->sub,
                'token_scopes' => $decoded->scopes ?? [],
            ]);

            Auth::setUser($user);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Acceso Denegado: ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}
