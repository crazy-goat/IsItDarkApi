<?php

namespace app\service;

class HtmlCacheService
{
    private string $html;

    public function __construct()
    {
        $this->loadHtml();
    }

    private function loadHtml(): void
    {
        $path = base_path('resources/views/index.html');
        
        if (!file_exists($path)) {
            $this->html = $this->getDefaultHtml();
            return;
        }
        
        $this->html = file_get_contents($path) ?: $this->getDefaultHtml();
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    private function getDefaultHtml(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <title>Is It Dark?</title>
</head>
<body>
    <h1>Is It Dark API</h1>
    <p>Frontend not configured yet.</p>
</body>
</html>';
    }
}
