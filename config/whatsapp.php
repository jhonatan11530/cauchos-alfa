<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Microservicio de WhatsApp (OpenWA)
    |--------------------------------------------------------------------------
    |
    | El envio de mensajes y archivos se delega a un microservicio Node open
    | source basado en @open-wa/wa-automate, ubicado en /whatsapp-server.
    |
    */

    'base_url' => env('WHATSAPP_SERVER_URL', 'http://localhost:3010'),

    // Token compartido opcional para proteger el microservicio.
    'token' => env('WHATSAPP_SERVER_TOKEN', ''),

    // Prefijo por defecto para numeros locales sin codigo de pais.
    'default_country_code' => env('WHATSAPP_COUNTRY_CODE', '58'),
];
