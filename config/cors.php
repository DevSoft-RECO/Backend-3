<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration - MERCADEO
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // ---------------------------------------------------------
        // 1. ENTORNO LOCAL (Desarrollo)
        // ---------------------------------------------------------
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175', // Tu puerto específico para Mercadeo local
        'http://127.0.0.1:5174',
        'http://127.0.0.1:5175',

        // ---------------------------------------------------------
        // 2. ENTORNO PRODUCCIÓN (Ecosistema Yaman Kutx)
        // ---------------------------------------------------------
        'https://portal.yamankutx.com.gt',       // Indispensable para validar sesión con la Madre
        'https://api-portal.yamankutx.com.gt',   // Backend de la Madre para canje de tokens
        'https://mercadeo.yamankutx.com.gt',     // La propia App
    ],

    'allowed_origins_patterns' => [
        // PATRÓN COMODÍN: Seguridad total para cualquier subdominio del ecosistema
        '#^https://.*\.yamankutx\.com\.gt$#',
        '#^https://yamankutx\.com\.gt$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400, // Cache de 24 horas para optimizar el rendimiento

    // CRÍTICO: Permite compartir la sesión y los tokens entre dominios (SSO)
    'supports_credentials' => true,

];
