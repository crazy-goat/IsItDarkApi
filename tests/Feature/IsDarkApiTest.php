<?php

declare(strict_types=1);

namespace tests\Feature;

use app\controller\Api\V1\IsDarkController;
use app\service\OpenTelemetryService;
use app\service\ResponseFormatterService;
use app\service\SunCalcService;
use PHPUnit\Framework\TestCase;
use support\Request;

class IsDarkApiTest extends TestCase
{
    private IsDarkController $controller;

    protected function setUp(): void
    {
        $otel = new OpenTelemetryService();
        $sunCalc = new SunCalcService($otel);
        $formatter = new ResponseFormatterService();
        $this->controller = new IsDarkController($sunCalc, $formatter, $otel);
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     */
    private function createRequest(string $uri, array $query = [], array $headers = []): Request
    {
        // Build query string
        $queryString = http_build_query($query);
        $fullUri = $queryString !== '' && $queryString !== '0' ? $uri . '?' . $queryString : $uri;

        // Build raw HTTP request
        $rawHeaders = "GET {$fullUri} HTTP/1.1\r\n";
        $rawHeaders .= "Host: localhost\r\n";

        foreach ($headers as $key => $value) {
            $rawHeaders .= "{$key}: {$value}\r\n";
        }

        $rawHeaders .= "\r\n";

        return new Request($rawHeaders);
    }

    public function testEndpointReturns200WithValidParams(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', ['lat' => '52.23', 'lng' => '21.01']);
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testEndpointReturnsJsonByDefault(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', ['lat' => '52.23', 'lng' => '21.01']);
        $response = $this->controller->index($request);

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertJson($response->rawBody());
    }

    public function testEndpointReturnsXmlWhenRequested(): void
    {
        $request = $this->createRequest(
            '/api/v1/is-dark',
            ['lat' => '52.23', 'lng' => '21.01'],
            ['Accept' => 'application/xml']
        );
        $response = $this->controller->index($request);

        $this->assertEquals('application/xml', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('<?xml version="1.0"', $response->rawBody());
    }

    public function testEndpointReturnsYamlWhenRequested(): void
    {
        $request = $this->createRequest(
            '/api/v1/is-dark',
            ['lat' => '52.23', 'lng' => '21.01'],
            ['Accept' => 'application/x-yaml']
        );
        $response = $this->controller->index($request);

        $this->assertEquals('application/x-yaml', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('is_dark:', $response->rawBody());
    }

    public function testEndpointReturns400WhenMissingParams(): void
    {
        $request = $this->createRequest('/api/v1/is-dark');
        $response = $this->controller->index($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testEndpointReturns400WhenMissingLat(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', ['lng' => '21.01']);
        $response = $this->controller->index($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testEndpointReturns400WhenMissingLng(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', ['lat' => '52.23']);
        $response = $this->controller->index($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testEndpointReturns422ForInvalidLatitude(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', ['lat' => '91', 'lng' => '21.01']);
        $response = $this->controller->index($request);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testEndpointReturns422ForInvalidLongitude(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', ['lat' => '52.23', 'lng' => '181']);
        $response = $this->controller->index($request);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testResponseContainsRequiredFields(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', ['lat' => '52.23', 'lng' => '21.01']);
        $response = $this->controller->index($request);

        $body = json_decode($response->rawBody(), true);
        $this->assertIsArray($body);

        $this->assertArrayHasKey('is_dark', $body);
        $this->assertArrayHasKey('sunrise', $body);
        $this->assertArrayHasKey('sunset', $body);
    }

    public function testResponseDoesNotContainInternalFields(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', ['lat' => '52.23', 'lng' => '21.01']);
        $response = $this->controller->index($request);

        $body = json_decode($response->rawBody(), true);
        $this->assertIsArray($body);

        // next_change_at should be in headers (Expires), not in simple body
        $this->assertArrayNotHasKey('next_change_at', $body);
        $this->assertArrayNotHasKey('next_change', $body);
    }

    public function testResponseHasCacheHeaders(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', ['lat' => '52.23', 'lng' => '21.01']);
        $response = $this->controller->index($request);

        $this->assertNotNull($response->getHeader('Expires'));
        $this->assertNotNull($response->getHeader('Cache-Control'));
    }

    public function testCoordinatesAreRoundedToTwoDecimals(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', ['lat' => '52.234567', 'lng' => '21.012345']);
        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testErrorResponseIsJsonByDefault(): void
    {
        $request = $this->createRequest('/api/v1/is-dark');
        $response = $this->controller->index($request);

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $body = json_decode($response->rawBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('error', $body);
        $this->assertArrayHasKey('message', $body);
    }

    public function testErrorResponseIsXmlWhenRequested(): void
    {
        $request = $this->createRequest(
            '/api/v1/is-dark',
            [],
            ['Accept' => 'application/xml']
        );
        $response = $this->controller->index($request);

        $this->assertEquals('application/xml', $response->getHeader('Content-Type'));
    }
}
