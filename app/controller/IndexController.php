<?php

namespace app\controller;

use app\service\HtmlCacheService;
use support\Request;
use support\Response;

class IndexController
{
    private HtmlCacheService $htmlCache;

    public function __construct(HtmlCacheService $htmlCache)
    {
        $this->htmlCache = $htmlCache;
    }

    public function index(Request $request): Response
    {
        $html = $this->htmlCache->getHtml();
        
        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }
}
