<?php

declare(strict_types=1);

namespace tests\Unit\Middleware;

use app\middleware\ApiErrorMiddleware;
use app\service\ResponseFormatterService;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

class ApiErrorMiddlewareTest extends TestCase
{
    private ApiErrorMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new ApiErrorMiddleware(new ResponseFormatterService());
    }

    private function createRequest(string $uri, string $accept = 'application/json'): Request
    {
        $raw = "GET {$uri} HTTP/1.1\r\nHost: localhost\r\nAccept: {$accept}\r\n\r\n";
        return new Request($raw);
    }

    public function testPassesThroughSuccessfulApiResponse(): void
    {
        $request = $this->createRequest('/api/v1/is-dark');
        $handler = fn() => new Response(200, [], '{"ok":true}');

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"ok":true}', $response->rawBody());
    }

    public function testFormats4xxApiError(): void
    {
        $request = $this->createRequest('/api/v1/is-dark');
        $handler = fn() => new Response(404);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(404, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        $this->assertTrue($body['error']);
        $this->assertEquals(404, $body['status']);
    }

    public function testDoesNotFormatNonApiErrors(): void
    {
        $request = $this->createRequest('/some-page');
        $originalBody = '<h1>Not Found</h1>';
        $handler = fn() => new Response(404, [], $originalBody);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals($originalBody, $response->rawBody());
    }

    public function testCatchesExceptionsOnApiRoutes(): void
    {
        $request = $this->createRequest('/api/v1/is-dark');
        $handler = fn() => throw new \RuntimeException('test error');

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(500, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        $this->assertTrue($body['error']);
    }

    public function testRethrowsExceptionsOnNonApiRoutes(): void
    {
        $request = $this->createRequest('/some-page');
        $handler = fn() => throw new \RuntimeException('test error');

        $this->expectException(\RuntimeException::class);
        $this->middleware->process($request, $handler);
    }

    public function testFormatsErrorAsXmlWhenRequested(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', 'application/xml');
        $handler = fn() => new Response(400);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals('application/xml', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('<error>true</error>', $response->rawBody());
    }
}
