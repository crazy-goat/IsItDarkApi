<?php

declare(strict_types=1);

namespace app\controller;

use support\Request;
use support\Response;

class IndexController
{
    private static ?string $cachedHtml = null;

    public function index(Request $request): Response
    {
        if (self::$cachedHtml === null) {
            self::$cachedHtml = file_get_contents(base_path('resources/views/index.html')) ?: '';
        }

        return new Response(200, ['Content-Type' => 'text/html'], self::$cachedHtml);
    }
}
