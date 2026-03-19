<?php

namespace app\service;

use CrazyGoat\IsItDark\IsItDark;
use CrazyGoat\IsItDark\Location;
use DateTimeImmutable;
use DateTimeZone;
use OpenTelemetry\API\Trace\SpanKind;

class SunCalcService
{
    /**
     * Oblicza czy jest ciemno oraz szczegółowe dane astronomiczne
     *
     * @param float $lat Szerokość geograficzna (-90 do 90)
     * @param float $lng Długość geograficzna (-180 do 180)
     * @return array Szczegółowe dane o stanie dnia/nocy
     */
    public function calculate(float $lat, float $lng): array
    {
        $otel = OpenTelemetryService::getInstance();
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

    private function doCalculate(float $lat, float $lng): array
    {
        $location = new Location($lat, $lng);
        $isItDark = new IsItDark($location);
        
        $data = $isItDark->toArray();
        
        // Określamy czas ważności odpowiedzi (następna zmiana - sunrise lub sunset)
        $now = time();
        $nextSunrise = $isItDark->nextSunrise()?->getTimestamp();
        $nextSunset = $isItDark->nextSunset()?->getTimestamp();
        
        // Wybieramy to co nastąpi wcześniej
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
            // Brak wschodu/zachodu (polar day/night) - cache na 1h
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
     * Formatuje DateTimeImmutable do ISO 8601 lub zwraca null
     */
    private function formatDateTime(?DateTimeImmutable $dateTime): ?string
    {
        return $dateTime?->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Zaokrągla współrzędne do 2 miejsc po przecinku
     */
    public function roundCoordinates(float $lat, float $lng): array
    {
        return [
            'lat' => round($lat, 2),
            'lng' => round($lng, 2),
        ];
    }

    /**
     * Waliduje współrzędne
     *
     * @return array ['valid' => bool, 'error' => string|null]
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
