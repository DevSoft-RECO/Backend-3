<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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

            // El token NO contiene roles/permisos. Debemos pedirlos a la App Madre.
            // URL de la madre (Backend port 8000)
            $motherUrl = env('MOTHER_API_URL', 'http://localhost:8000');

            // Hacemos petición a la madre usando el mismo token
            $response = Http::withToken($token)
                ->get("{$motherUrl}/api/me"); // Frontend usa /api/me. Backend AuthController tiene user(), ruta asumo /me o /user.

            if ($response->successful()) {
                $userData = $response->json();
                // $userData trae 'roles' => [...], 'permissions' => [...]
                $userData['id'] = $decoded->sub;
                $user = new GenericUser($userData);
            } else {
                // Si falla (ej. red), fallback a datos minimos del token (sin roles)
                $userData = (array) $decoded;
                $userData['id'] = $decoded->sub;
                $user = new GenericUser($userData);
                // Opcional: Log warning
            }

            Auth::setUser($user);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Acceso Denegado: ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}
