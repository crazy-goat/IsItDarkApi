<?php

declare(strict_types=1);

namespace app\controller;

use support\Request;
use support\Response;

class IndexController
{
    public function index(Request $request): Response
    {
        $html = file_get_contents(base_path('resources/views/index.html')) ?: '';

        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }
}
