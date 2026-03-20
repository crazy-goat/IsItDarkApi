<?php

use Webman\Route;

// Frontend
Route::get('/', [app\controller\IndexController::class, 'index']);

// API v1
Route::get('/api/v1/is-dark', [app\controller\Api\V1\IsDarkController::class, 'index']);

// Cities endpoint (for autocomplete)
Route::get('/api/cities', function () {
    static $cities = null;
    if ($cities === null) {
        $citiesFile = base_path('app/Config/cities.php');
        $cities = file_exists($citiesFile) ? require $citiesFile : [];
    }
    return json($cities);
});

// Health check
Route::get('/health', function () {
    return json(['status' => 'ok']);
});

// Map file
Route::get('/map/world.svg', function () {
    static $svgContent = null;
    if ($svgContent === null) {
        $mapFile = base_path('public/map/world.svg');
        if (!file_exists($mapFile)) {
            return new \Webman\Http\Response(404);
        }
        $svgContent = file_get_contents($mapFile);
    }
    return new \Webman\Http\Response(200, ['Content-Type' => 'image/svg+xml'], $svgContent);
});






