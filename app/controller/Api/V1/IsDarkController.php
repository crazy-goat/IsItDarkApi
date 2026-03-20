<?php

declare(strict_types=1);

namespace app\controller\Api\V1;

use app\service\OpenTelemetryService;
use app\service\ResponseFormatterService;
use app\service\SunCalcService;
use support\Request;
use support\Response;

class IsDarkController
{
    public function __construct(
        private readonly SunCalcService $sunCalc = new SunCalcService(),
        private readonly ResponseFormatterService $formatter = new ResponseFormatterService(),
    ) {
    }

    public function index(Request $request): Response
    {
        $sunCalc = $this->sunCalc;
        $formatter = $this->formatter;

        // Pobieramy parametry
        $latRaw = $request->get('lat');
        $lngRaw = $request->get('lng');
        $detailed = $request->get('detailed', 'false') === 'true';

        // Walidacja - czy parametry istnieją
        if ($latRaw === null || $lngRaw === null) {
            return $this->errorResponse($request, $formatter, 400, 'Missing required parameters: lat and lng');
        }

        // Konwersja na float
        $lat = (float) (is_scalar($latRaw) ? $latRaw : '');
        $lng = (float) (is_scalar($lngRaw) ? $lngRaw : '');

        // Zaokrąglenie do 2 miejsc po przecinku
        $coords = $sunCalc->roundCoordinates($lat, $lng);
        $lat = $coords['lat'];
        $lng = $coords['lng'];

        // Walidacja zakresów
        $validation = $sunCalc->validate($lat, $lng);
        if (!$validation['valid']) {
            return $this->errorResponse($request, $formatter, 422, $validation['error'] ?? 'Validation error');
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
        $acceptHeaderRaw = $request->header('Accept', 'application/json');
        $acceptHeader = is_string($acceptHeaderRaw) ? $acceptHeaderRaw : 'application/json';
        $format = $formatter->detectFormat($acceptHeader);
        $body = $formatter->format($responseData, $format);
        $contentType = $formatter->getContentType($format);

        $rawNextChange = $result['next_change_at'];
        $nextChangeAt = is_int($rawNextChange)
            ? $rawNextChange
            : (int) (is_scalar($rawNextChange) ? $rawNextChange : 0);

        // Headers z cache (ważne do następnej zmiany - sunrise lub sunset)
        $headers = [
            'Content-Type' => $contentType,
            'Expires' => gmdate('D, d M Y H:i:s T', $nextChangeAt),
            'Cache-Control' => 'public, max-age=' . max(0, $nextChangeAt - time()),
        ];

        return new Response(200, $headers, $body);
    }

    private function errorResponse(
        Request $request,
        ResponseFormatterService $formatter,
        int $statusCode,
        string $message,
    ): Response {
        $acceptHeaderRaw = $request->header('Accept', 'application/json');
        $acceptHeader = is_string($acceptHeaderRaw) ? $acceptHeaderRaw : 'application/json';
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
