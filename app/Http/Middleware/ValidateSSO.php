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
use Exception; // Importar Exception correctamente

class ValidateSSO
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token requerido'], 401);
        }

        try {
            // 1. Validar Llave Pública
            $publicKeyPath = storage_path('oauth-public.key');

            if (!file_exists($publicKeyPath)) {
                // Loguear el error ayuda mucho más que solo lanzar excepción
                \Log::error("SSO Error: No se encuentra la llave pública en: " . $publicKeyPath);
                throw new Exception("Error de configuración del servidor (Llave pública faltante)");
            }

            $publicKey = file_get_contents($publicKeyPath);
            JWT::$leeway = 60;

            // 2. Decodificar Token
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            // 3. Obtener URL de la Madre (CORRECCIÓN AQUÍ)
            // Usamos config() en lugar de env() para que funcione en producción
            // Asegúrate de que config/services.php tenga la entrada 'app_madre'
            $motherUrl = config('services.app_madre.url');

            if (empty($motherUrl)) {
                throw new Exception("La URL de la App Madre no está configurada en services.php");
            }

            // 4. Petición a la App Madre
            $response = Http::withToken($token)->get("{$motherUrl}/api/me");

            if ($response->successful()) {
                $userData = $response->json();
                $userData['id'] = $decoded->sub;
                $user = new GenericUser($userData);
            } else {
                // Fallback si la madre no responde (para no bloquear al usuario totalmente)
                \Log::warning("SSO Warning: No se pudo conectar con App Madre. Usando datos básicos del token.");
                $userData = (array) $decoded;
                $userData['id'] = $decoded->sub;
                $user = new GenericUser($userData);
            }

            Auth::setUser($user);

        } catch (Exception $e) {
            // Loguear el error real para que lo veas en laravel.log
            \Log::error("SSO Falló: " . $e->getMessage());
            return response()->json(['message' => 'Acceso Denegado: ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}
