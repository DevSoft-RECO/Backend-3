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
use Exception;

class ValidateSSO
{
    public function handle(Request $request, Closure $next): Response
    {
        // Obtener el token del encabezado Authorization: Bearer ...
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token requerido'], 401);
        }

        try {
            // 1. Validar existencia de la Llave Pública
            $publicKeyPath = storage_path('oauth-public.key');

            if (!file_exists($publicKeyPath)) {
                throw new Exception("Error de servidor: Falta llave pública de validación.");
            }

            $publicKey = file_get_contents($publicKeyPath);
            JWT::$leeway = 60; // Margen de 60s por si los relojes de los servidores no están sincronizados

            // 2. Decodificar y Validar firma del Token (RS256)
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            // 3. Obtener URL de la App Madre
            // NOTA: Usamos config() porque en producción env() devuelve null si la caché está activa.
            $motherUrl = config('services.app_madre.url');

            if (empty($motherUrl)) {
                throw new Exception("Configuración incompleta: URL Madre no definida.");
            }

            // 4. Intentar obtener datos frescos (Roles/Permisos) desde la Madre
            $response = Http::withToken($token)->get("{$motherUrl}/api/me");

            if ($response->successful()) {
                $userData = $response->json();
                
                // Desempaquetar si viene envuelto en 'data' (AppResource)
                if (isset($userData['data'])) {
                    $userData = $userData['data'];
                }

                // CRÍTICO: "Aplanar" Arrays de Objetos Spatie -> Strings puros
                if (isset($userData['roles']) && is_array($userData['roles'])) {
                    $userData['roles'] = array_map(function($r) { 
                        return is_array($r) ? ($r['name'] ?? $r) : (is_object($r) ? ($r->name ?? $r) : $r); 
                    }, $userData['roles']);
                }
                
                if (isset($userData['permisos']) && is_array($userData['permisos'])) {
                    $userData['permisos'] = array_map(function($p) { 
                        return is_array($p) ? ($p['name'] ?? $p) : (is_object($p) ? ($p->name ?? $p) : $p); 
                    }, $userData['permisos']);
                }

                // Estandarizar para el frontend
                $userData['permissions'] = $userData['permisos'] ?? [];
                $userData['id'] = $decoded->sub;
                
                $user = new GenericUser($userData);
            } else {
                // FALLBACK: Datos del Token
                $userData = (array) $decoded;
                $userData['id'] = $decoded->sub;
                $user = new GenericUser($userData);
            }

            // Establecer el usuario en la sesión actual de la solicitud
            Auth::setUser($user);

        } catch (Exception $e) {
            // Si el token es inválido, expirado o manipulado, devolvemos 401
            return response()->json(['message' => 'Acceso Denegado: ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}
