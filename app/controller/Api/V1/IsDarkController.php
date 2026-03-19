<?php

namespace app\controller\Api\V1;

use app\service\OpenTelemetryService;
use app\service\ResponseFormatterService;
use app\service\SunCalcService;
use support\Request;
use support\Response;

class IsDarkController
{
    public function index(Request $request): Response
    {
        $sunCalc = new SunCalcService();
        $formatter = new ResponseFormatterService();

        // Pobieramy parametry
        $lat = $request->get('lat');
        $lng = $request->get('lng');
        $detailed = $request->get('detailed', 'false') === 'true';

        // Walidacja - czy parametry istnieją
        if ($lat === null || $lng === null) {
            return $this->errorResponse($request, $formatter, 400, 'Missing required parameters: lat and lng');
        }

        // Konwersja na float
        $lat = (float) $lat;
        $lng = (float) $lng;

        // Zaokrąglenie do 2 miejsc po przecinku
        $coords = $sunCalc->roundCoordinates($lat, $lng);
        $lat = $coords['lat'];
        $lng = $coords['lng'];

        // Walidacja zakresów
        $validation = $sunCalc->validate($lat, $lng);
        if (!$validation['valid']) {
            return $this->errorResponse($request, $formatter, 422, $validation['error']);
        }

        // Obliczenia
        $result = $sunCalc->calculate($lat, $lng);

        // Metryki biznesowe
        $otel = OpenTelemetryService::getInstance();
        $otel->isDarkQueryCounter()->add(1, [
            'result' => $result['is_dark'] ? 'dark' : 'light',
        ]);
        $otel->latDistribution()->record($lat);
        $otel->lngDistribution()->record($lng);

        // Przygotowanie odpowiedzi
        $responseData = [
            'is_dark' => $result['is_dark'],
            'sunrise' => $result['sunrise'],
            'sunset' => $result['sunset'],
        ];

        // Dodajemy szczegółowe dane jeśli requested
        if ($detailed) {
            $responseData = array_merge($responseData, [
                'is_day' => $result['is_day'],
                'state' => $result['state'],
                'solar_noon' => $result['solar_noon'],
                'civil_dawn' => $result['civil_dawn'],
                'civil_dusk' => $result['civil_dusk'],
                'nautical_dawn' => $result['nautical_dawn'],
                'nautical_dusk' => $result['nautical_dusk'],
                'astronomical_dawn' => $result['astronomical_dawn'],
                'astronomical_dusk' => $result['astronomical_dusk'],
                'day_length' => $result['day_length'],
                'night_length' => $result['night_length'],
                'has_sunrise' => $result['has_sunrise'],
                'has_sunset' => $result['has_sunset'],
                'is_polar_day' => $result['is_polar_day'],
                'is_polar_night' => $result['is_polar_night'],
                'next_change' => $result['next_change'],
                'next_change_at' => $result['next_change_at'],
            ]);
        }

        // Formatowanie odpowiedzi
        $acceptHeader = $request->header('Accept', 'application/json');
        $format = $formatter->detectFormat($acceptHeader);
        $body = $formatter->format($responseData, $format);
        $contentType = $formatter->getContentType($format);

        // Headers z cache (ważne do następnej zmiany - sunrise lub sunset)
        $headers = [
            'Content-Type' => $contentType,
            'Expires' => gmdate('D, d M Y H:i:s T', $result['next_change_at']),
            'Cache-Control' => 'public, max-age=' . ($result['next_change_at'] - time()),
        ];

        return new Response(200, $headers, $body);
    }

    private function errorResponse(Request $request, ResponseFormatterService $formatter, int $statusCode, string $message): Response
    {
        $acceptHeader = $request->header('Accept', 'application/json');
        $format = $formatter->detectFormat($acceptHeader);

        $data = [
            'error' => true,
            'status' => $statusCode,
            'message' => $message,
        ];

        $body = $formatter->format($data, $format);
        $contentType = $formatter->getContentType($format);

        return new Response($statusCode, ['Content-Type' => $contentType], $body);
    }
}
