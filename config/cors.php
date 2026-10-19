<?php

return [
    'paths' => ['api/*', 'storage/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    // Domaines autorisés à appeler l'API depuis un navigateur (application Flutter Web).
    // Surcharge possible dans .env : CORS_ALLOWED_ORIGINS=https://a.com,https://b.com
    // Les applications mobiles ne sont pas concernées par le CORS.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'https://livraison.ma3hsarl.com'))
    ))),
    // Autorise aussi les tests en local (localhost / 127.0.0.1, n'importe quel port).
    'allowed_origins_patterns' => ['#^http://(localhost|127\.0\.0\.1)(:\d+)?$#'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
