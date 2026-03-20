<?php

declare(strict_types=1);

namespace tests\Unit\Service;

use app\service\ResponseFormatterService;
use PHPUnit\Framework\TestCase;

class ResponseFormatterServiceTest extends TestCase
{
    private ResponseFormatterService $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ResponseFormatterService();
    }

    public function testFormatJson(): void
    {
        $data = ['is_dark' => true, 'sunrise' => '2024-01-15T06:00:00Z'];
        $result = $this->formatter->format($data, 'json');

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertEquals($data, $decoded);
    }

    public function testFormatXml(): void
    {
        $data = ['is_dark' => true, 'sunrise' => '2024-01-15T06:00:00Z'];
        $result = $this->formatter->format($data, 'xml');

        $this->assertStringContainsString('<?xml version="1.0"', $result);
        $this->assertStringContainsString('<response>', $result);
        $this->assertStringContainsString('<is_dark>true</is_dark>', $result);
        $this->assertStringContainsString('<sunrise>2024-01-15T06:00:00Z</sunrise>', $result);
    }

    public function testFormatYaml(): void
    {
        $data = ['is_dark' => true, 'sunrise' => '2024-01-15T06:00:00Z'];
        $result = $this->formatter->format($data, 'yaml');

        $this->assertStringContainsString('is_dark: true', $result);
        $this->assertStringContainsString('sunrise:', $result);
    }

    public function testFormatDefaultToJson(): void
    {
        $data = ['test' => 'value'];
        $result = $this->formatter->format($data, 'unknown');

        $this->assertJson($result);
    }

    public function testFormatNestedArray(): void
    {
        $data = [
            'is_dark' => true,
            'times' => [
                'sunrise' => '06:00',
                'sunset' => '18:00',
            ],
        ];

        $json = $this->formatter->format($data, 'json');
        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertEquals($data, $decoded);

        $xml = $this->formatter->format($data, 'xml');
        $this->assertStringContainsString('<times>', $xml);
        $this->assertStringContainsString('<sunrise>06:00</sunrise>', $xml);
    }

    public function testFormatBooleanValues(): void
    {
        $data = ['is_dark' => true, 'is_light' => false];

        $xml = $this->formatter->format($data, 'xml');
        $this->assertStringContainsString('<is_dark>true</is_dark>', $xml);
        $this->assertStringContainsString('<is_light>false</is_light>', $xml);

        $yaml = $this->formatter->format($data, 'yaml');
        $this->assertStringContainsString('is_dark: true', $yaml);
        $this->assertStringContainsString('is_light: false', $yaml);
    }

    public function testGetContentType(): void
    {
        $this->assertEquals('application/json', $this->formatter->getContentType('json'));
        $this->assertEquals('application/xml', $this->formatter->getContentType('xml'));
        $this->assertEquals('application/x-yaml', $this->formatter->getContentType('yaml'));
        $this->assertEquals('application/json', $this->formatter->getContentType('unknown'));
    }

    public function testDetectFormatJson(): void
    {
        $this->assertEquals('json', $this->formatter->detectFormat('application/json'));
        $this->assertEquals('json', $this->formatter->detectFormat('application/json, text/html'));
    }

    public function testDetectFormatXml(): void
    {
        $this->assertEquals('xml', $this->formatter->detectFormat('application/xml'));
        $this->assertEquals('xml', $this->formatter->detectFormat('text/xml'));
        $this->assertEquals('xml', $this->formatter->detectFormat('application/xml, application/json'));
    }

    public function testDetectFormatYaml(): void
    {
        $this->assertEquals('yaml', $this->formatter->detectFormat('application/x-yaml'));
        $this->assertEquals('yaml', $this->formatter->detectFormat('text/yaml'));
    }

    public function testDetectFormatDefault(): void
    {
        $this->assertEquals('json', $this->formatter->detectFormat('text/html'));
        $this->assertEquals('json', $this->formatter->detectFormat(''));
    }

    public function testXmlEscapesSpecialCharacters(): void
    {
        $data = ['message' => 'Test <script> & "quotes"'];
        $xml = $this->formatter->format($data, 'xml');

        $this->assertStringNotContainsString('<script>', $xml);
        $this->assertStringContainsString('&lt;script&gt;', $xml);
        $this->assertStringContainsString('&amp;', $xml);
        $this->assertStringContainsString('&quot;', $xml);
    }

    public function testYamlEscapesSpecialCharacters(): void
    {
        $data = ['message' => 'Test: value # comment'];
        $yaml = $this->formatter->format($data, 'yaml');

        $this->assertStringContainsString("'Test: value # comment'", $yaml);
    }

    public function testYamlHandlesNullValues(): void
    {
        $data = ['key' => null, 'other' => 'value'];
        $yaml = $this->formatter->format($data, 'yaml');

        $this->assertStringContainsString('key: null', $yaml);
        $this->assertStringContainsString('other: value', $yaml);
    }

    public function testXmlHandlesNullValues(): void
    {
        $data = ['key' => null, 'other' => 'value'];
        $xml = $this->formatter->format($data, 'xml');

        $this->assertStringContainsString('<key/>', $xml);
        $this->assertStringContainsString('<other>value</other>', $xml);
    }

    public function testXmlOutputContainsRealNewlines(): void
    {
        $data = ['key' => 'value'];
        $result = $this->formatter->format($data, 'xml');

        $this->assertStringNotContainsString('\n', $result);
        $this->assertStringContainsString("\n", $result);
    }
}
