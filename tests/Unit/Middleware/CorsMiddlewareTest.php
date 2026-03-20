<?php

declare(strict_types=1);

namespace tests\Unit\Middleware;

use app\middleware\CorsMiddleware;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

class CorsMiddlewareTest extends TestCase
{
    private CorsMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new CorsMiddleware();
    }

    private function createRequest(string $method, string $uri): Request
    {
        $raw = "{$method} {$uri} HTTP/1.1\r\nHost: localhost\r\n\r\n";
        return new Request($raw);
    }

    public function testOptionsPreflightReturns204(): void
    {
        $request = $this->createRequest('OPTIONS', '/api/v1/is-dark');
        $handler = fn(): \Webman\Http\Response => new Response(200, [], 'should not reach');

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('', $response->rawBody());
    }

    public function testOptionsPreflightHasCorsHeaders(): void
    {
        $request = $this->createRequest('OPTIONS', '/api/v1/is-dark');
        $handler = fn(): \Webman\Http\Response => new Response(200);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));
        $allowMethods = $response->getHeader('Access-Control-Allow-Methods');
        $this->assertIsString($allowMethods);
        $this->assertStringContainsString('GET', $allowMethods);
        $this->assertStringContainsString('OPTIONS', $allowMethods);
    }

    public function testGetRequestHasCorsHeaders(): void
    {
        $request = $this->createRequest('GET', '/api/v1/is-dark');
        $handler = fn(): \Webman\Http\Response => new Response(200, [], 'ok');

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testGetRequestPassesThroughToHandler(): void
    {
        $request = $this->createRequest('GET', '/test');
        $handlerCalled = false;
        $handler = function () use (&$handlerCalled): \Webman\Http\Response {
            $handlerCalled = true;
            return new Response(200, [], 'handler response');
        };

        $response = $this->middleware->process($request, $handler);

        $this->assertTrue($handlerCalled);
        $this->assertEquals('handler response', $response->rawBody());
    }
}
