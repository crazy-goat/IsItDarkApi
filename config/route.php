<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;

// Frontend
Route::get('/', [app\controller\IndexController::class, 'index']);

// API v1
Route::get('/api/v1/is-dark', [app\controller\Api\V1\IsDarkController::class, 'index']);

// Cities endpoint (for autocomplete)
Route::get('/api/cities', function () {
    $citiesFile = base_path('app/Config/cities.php');
    if (!file_exists($citiesFile)) {
        return json([]);
    }
    $cities = require $citiesFile;
    return json($cities);
});

// Health check
Route::get('/health', function () {
    return json(['status' => 'ok']);
});

// Map file
Route::get('/map/world.svg', function () {
    $mapFile = base_path('public/map/world.svg');
    if (!file_exists($mapFile)) {
        return new \Webman\Http\Response(404);
    }
    return (new \Webman\Http\Response(200, ['Content-Type' => 'image/svg+xml'], file_get_contents($mapFile)));
});






