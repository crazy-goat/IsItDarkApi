<?php

declare(strict_types=1);

namespace tests\Unit\Service;

use app\service\SunCalcService;
use PHPUnit\Framework\TestCase;

class SunCalcServiceTest extends TestCase
{
    private SunCalcService $service;

    protected function setUp(): void
    {
        $this->service = new SunCalcService();
    }

    public function testCalculateReturnsRequiredFields(): void
    {
        $result = $this->service->calculate(52.23, 21.01); // Warsaw

        $this->assertArrayHasKey('is_dark', $result);
        $this->assertArrayHasKey('is_day', $result);
        $this->assertArrayHasKey('state', $result);
        $this->assertArrayHasKey('sunrise', $result);
        $this->assertArrayHasKey('sunset', $result);
        $this->assertArrayHasKey('next_change', $result);
        $this->assertArrayHasKey('next_change_at', $result);
    }

    public function testCalculateReturnsDetailedFields(): void
    {
        $result = $this->service->calculate(52.23, 21.01);

        // Szczegółowe pola (cache'owalne - stałe dla danego dnia)
        $this->assertArrayHasKey('solar_noon', $result);
        $this->assertArrayHasKey('civil_dawn', $result);
        $this->assertArrayHasKey('civil_dusk', $result);
        $this->assertArrayHasKey('nautical_dawn', $result);
        $this->assertArrayHasKey('nautical_dusk', $result);
        $this->assertArrayHasKey('astronomical_dawn', $result);
        $this->assertArrayHasKey('astronomical_dusk', $result);
        $this->assertArrayHasKey('day_length', $result);
        $this->assertArrayHasKey('night_length', $result);
        $this->assertArrayHasKey('has_sunrise', $result);
        $this->assertArrayHasKey('has_sunset', $result);
        $this->assertArrayHasKey('is_polar_day', $result);
        $this->assertArrayHasKey('is_polar_night', $result);
        $this->assertArrayHasKey('next_change', $result);
        $this->assertArrayHasKey('next_change_at', $result);
    }

    public function testCalculateReturnsBooleanIsDark(): void
    {
        $result = $this->service->calculate(52.23, 21.01);

        $this->assertIsBool($result['is_dark']);
        $this->assertIsBool($result['is_day']);
    }

    public function testIsDayIsOppositeOfIsDark(): void
    {
        $result = $this->service->calculate(52.23, 21.01);

        $this->assertNotEquals($result['is_dark'], $result['is_day']);
    }

    public function testStateIsValidEnum(): void
    {
        $result = $this->service->calculate(52.23, 21.01);
        $validStates = ['day', 'civil_twilight', 'nautical_twilight', 'astronomical_twilight', 'night'];

        $this->assertContains($result['state'], $validStates);
    }

    public function testCalculateReturnsValidIso8601DatesOrNull(): void
    {
        $result = $this->service->calculate(52.23, 21.01);

        // Tylko pola cache'owalne (stałe dla danego dnia)
        $dateFields = ['sunrise', 'sunset', 'solar_noon', 'civil_dawn', 'civil_dusk',
                      'nautical_dawn', 'nautical_dusk', 'astronomical_dawn', 'astronomical_dusk'];

        foreach ($dateFields as $field) {
            if ($result[$field] !== null) {
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
                    is_string($result[$field]) ? $result[$field] : '',
                    "Field {$field} should be valid ISO 8601 or null"
                );
            }
        }
    }

    public function testCalculateReturnsValidTimestamps(): void
    {
        $result = $this->service->calculate(52.23, 21.01);

        $this->assertIsInt($result['next_change_at']);
        $this->assertGreaterThan(time(), $result['next_change_at']);
        $this->assertContains($result['next_change'], ['sunrise', 'sunset', null]);
    }

    public function testDayAndNightLengthAreIntegers(): void
    {
        $result = $this->service->calculate(52.23, 21.01);

        $this->assertIsInt($result['day_length']);
        $this->assertIsInt($result['night_length']);
        $this->assertGreaterThanOrEqual(0, $result['day_length']);
        $this->assertGreaterThanOrEqual(0, $result['night_length']);
        // Dzień + noc = 24h (86400s)
        $this->assertEquals(86400, $result['day_length'] + $result['night_length']);
    }

    public function testHasSunriseAndSunsetAreBoolean(): void
    {
        $result = $this->service->calculate(52.23, 21.01);

        $this->assertIsBool($result['has_sunrise']);
        $this->assertIsBool($result['has_sunset']);
    }

    public function testPolarFlagsAreBoolean(): void
    {
        $result = $this->service->calculate(52.23, 21.01);

        $this->assertIsBool($result['is_polar_day']);
        $this->assertIsBool($result['is_polar_night']);
    }

    public function testCalculateForNorthPole(): void
    {
        // North Pole - extreme case (polar day or night)
        $result = $this->service->calculate(90.0, 0.0);

        $this->assertIsBool($result['is_dark']);
        $this->assertTrue($result['is_polar_day'] || $result['is_polar_night']);
    }

    public function testCalculateForSouthPole(): void
    {
        // South Pole - extreme case
        $result = $this->service->calculate(-90.0, 0.0);

        $this->assertIsBool($result['is_dark']);
        $this->assertTrue($result['is_polar_day'] || $result['is_polar_night']);
    }

    public function testRoundCoordinates(): void
    {
        $result = $this->service->roundCoordinates(52.234567, 21.012345);

        $this->assertEquals(52.23, $result['lat']);
        $this->assertEquals(21.01, $result['lng']);
    }

    public function testValidateValidCoordinates(): void
    {
        $result = $this->service->validate(52.23, 21.01);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    public function testValidateInvalidLatitudeTooHigh(): void
    {
        $result = $this->service->validate(91.0, 0.0);

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
    }

    public function testValidateInvalidLatitudeTooLow(): void
    {
        $result = $this->service->validate(-91.0, 0.0);

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
    }

    public function testValidateInvalidLongitudeTooHigh(): void
    {
        $result = $this->service->validate(0.0, 181.0);

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
    }

    public function testValidateInvalidLongitudeTooLow(): void
    {
        $result = $this->service->validate(0.0, -181.0);

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
    }

    public function testValidateBoundaryValues(): void
    {
        // Boundary values should be valid
        $this->assertTrue($this->service->validate(90.0, 180.0)['valid']);
        $this->assertTrue($this->service->validate(-90.0, -180.0)['valid']);
        $this->assertTrue($this->service->validate(0.0, 0.0)['valid']);
    }
}
