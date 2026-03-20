<?php

declare(strict_types=1);

namespace app\service;

use CrazyGoat\IsItDark\IsItDark;
use CrazyGoat\IsItDark\Location;
use DateTimeImmutable;
use OpenTelemetry\API\Trace\SpanKind;

class SunCalcService
{
    public function __construct(private readonly ?OpenTelemetryService $otel = null)
    {
    }

    /**
     * Calculates whether it is dark and detailed astronomical data
     *
     * @param float $lat Latitude (-90 to 90)
     * @param float $lng Longitude (-180 to 180)
     * @return array<mixed> Detailed day/night state data
     */
    public function calculate(float $lat, float $lng): array
    {
        $otel = $this->otel ?? resolve(OpenTelemetryService::class);
        $span = $otel->tracer()
            ->spanBuilder('SunCalcService::calculate')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute('geo.lat', $lat)
            ->setAttribute('geo.lng', $lng)
            ->startSpan();
        $scope = $span->activate();

        try {
            return $this->doCalculate($lat, $lng);
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    /** @return array<mixed> */
    private function doCalculate(float $lat, float $lng): array
    {
        $location = new Location($lat, $lng);
        $isItDark = new IsItDark($location);

        $data = $isItDark->toArray();

        $now = time();
        $nextSunrise = $isItDark->nextSunrise()?->getTimestamp();
        $nextSunset = $isItDark->nextSunset()?->getTimestamp();

        if ($nextSunrise && $nextSunset) {
            $expiresAt = min($nextSunrise, $nextSunset);
            $nextChange = ($nextSunrise < $nextSunset) ? 'sunrise' : 'sunset';
        } elseif ($nextSunrise) {
            $expiresAt = $nextSunrise;
            $nextChange = 'sunrise';
        } elseif ($nextSunset) {
            $expiresAt = $nextSunset;
            $nextChange = 'sunset';
        } else {
            // Polar day/night - no sunrise/sunset, cache for 1h
            $expiresAt = $now + 3600;
            $nextChange = null;
        }

        return [
            'is_dark' => $isItDark->isDark(),
            'is_day' => $isItDark->isDay(),
            'state' => $data['state'],
            'sunrise' => $this->formatDateTime($isItDark->sunrise()),
            'sunset' => $this->formatDateTime($isItDark->sunset()),
            'solar_noon' => $this->formatDateTime($isItDark->solarNoon()),
            'civil_dawn' => $this->formatDateTime($isItDark->civilDawn()),
            'civil_dusk' => $this->formatDateTime($isItDark->civilDusk()),
            'nautical_dawn' => $this->formatDateTime($isItDark->nauticalDawn()),
            'nautical_dusk' => $this->formatDateTime($isItDark->nauticalDusk()),
            'astronomical_dawn' => $this->formatDateTime($isItDark->astronomicalDawn()),
            'astronomical_dusk' => $this->formatDateTime($isItDark->astronomicalDusk()),
            'day_length' => $isItDark->dayLength(),
            'night_length' => $isItDark->nightLength(),
            'has_sunrise' => $isItDark->hasSunrise(),
            'has_sunset' => $isItDark->hasSunset(),
            'is_polar_day' => $isItDark->isPolarDay(),
            'is_polar_night' => $isItDark->isPolarNight(),
            'next_change' => $nextChange,
            'next_change_at' => $expiresAt,
        ];
    }

    /**
     * Formats DateTimeImmutable to ISO 8601 or returns null
     */
    private function formatDateTime(?DateTimeImmutable $dateTime): ?string
    {
        return $dateTime?->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Rounds coordinates to 2 decimal places
     */
    /** @return array{lat: float, lng: float} */
    public function roundCoordinates(float $lat, float $lng): array
    {
        return [
            'lat' => round($lat, 2),
            'lng' => round($lng, 2),
        ];
    }

    /**
     * Validates coordinates
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validate(float $lat, float $lng): array
    {
        try {
            new Location($lat, $lng);
            return ['valid' => true, 'error' => null];
        } catch (\InvalidArgumentException $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}
