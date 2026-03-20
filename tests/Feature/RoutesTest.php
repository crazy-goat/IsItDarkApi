<?php

declare(strict_types=1);

namespace tests\Feature;

use PHPUnit\Framework\TestCase;

class RoutesTest extends TestCase
{
    public function testCitiesDataIsValidArray(): void
    {
        $citiesFile = __DIR__ . '/../../app/Config/cities.php';
        $this->assertFileExists($citiesFile);

        $cities = require $citiesFile;
        $this->assertIsArray($cities);
        $this->assertNotEmpty($cities);

        foreach ($cities as $city) {
            $this->assertIsArray($city);
            $this->assertArrayHasKey('name', $city);
            $this->assertArrayHasKey('lat', $city);
            $this->assertArrayHasKey('lng', $city);
            $this->assertArrayHasKey('country', $city);
            $this->assertIsString($city['name']);
            $this->assertIsFloat($city['lat']);
            $this->assertIsFloat($city['lng']);
        }
    }

    public function testMapFileExists(): void
    {
        $mapFile = __DIR__ . '/../../public/map/world.svg';
        $this->assertFileExists($mapFile);

        $content = file_get_contents($mapFile);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('<svg', $content);
    }
}
