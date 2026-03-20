<?php

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class CorsMiddleware implements MiddlewareInterface
{
    private const array CORS_HEADERS = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Accept',
        'Access-Control-Max-Age' => '86400',
    ];

    public function process(Request $request, callable $handler): Response
    {
        if ($request->method() === 'OPTIONS') {
            return new Response(204, self::CORS_HEADERS);
        }

        /** @var Response $response */
        $response = $handler($request);
        $response->withHeaders(self::CORS_HEADERS);

        return $response;
    }
}
