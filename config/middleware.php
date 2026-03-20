<?php

return [
    // Global middleware
    '' => [
        app\middleware\TelemetryMiddleware::class,
        app\middleware\CorsMiddleware::class,
    ],
    
    // API middleware
    'api' => [
        app\middleware\ApiErrorMiddleware::class,
    ],
];
