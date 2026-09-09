<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway OpenWA
    |--------------------------------------------------------------------------
    |
    | Laravel consume directamente la API del gateway OpenWA mediante el SDK
    | oficial de PHP.
    |
    */

    'api_url' => env('WHATSAPP_API_URL', 'http://localhost:2785'),
    'api_key' => env('WHATSAPP_API_KEY', ''),
    'session_id' => env('WHATSAPP_SESSION_ID', 'default'),
    'timeout' => (float) env('WHATSAPP_TIMEOUT', 30),

    // Prefijo por defecto para numeros locales sin codigo de pais.
    'default_country_code' => env('WHATSAPP_COUNTRY_CODE', '57'),
];
