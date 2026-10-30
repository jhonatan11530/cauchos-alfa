<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'prompt' => env('PROMPT_GEMINI', 'Mejora la imagen aplicando un estilo de fotografía de estudio en primer plano y de alta calidad sobre un fondo blanco puro y continuo, utilizando iluminación natural con enfoque nítido y sombras suaves en la base para asentar el objeto de forma realista. Es indispensable lograr una alineación geométrica absoluta: el producto debe quedar perfectamente recto, nivelado y con 0 grados de inclinación de extremo a extremo, respetando su orientación natural (estrictamente horizontal o vertical) y quedando totalmente paralelo a los bordes del encuadre, sin ladeos, desniveles ni caídas accidentales hacia ningún lado. Asimismo, aplica una corrección de perspectiva rigurosa para obtener una vista frontal plana y ortogonal, eliminando por completo cualquier fuga diagonal, distorsión de lente o ángulo de cámara que haga que una parte se vea más cercana o distorsionada, asegurando una simetría y estabilidad visual perfectas. Finalmente, conserva con absoluta fidelidad la forma, proporciones, materiales y texturas originales del objeto, centrándolo en la composición con un espacio blanco equilibrado alrededor, sin marcas de agua ni elementos adicionales, para entregar un acabado hiperrealista, nítido y profesional ideal para un catálogo online.'),
    ],

];
