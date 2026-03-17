<?php

namespace app\controller\Api\V1;

use app\service\ResponseFormatterService;
use app\service\SunCalcService;
use support\Request;
use support\Response;

class IsDarkController
{
    private SunCalcService $sunCalc;
    private ResponseFormatterService $formatter;

    public function __construct(SunCalcService $sunCalc, ResponseFormatterService $formatter)
    {
        $this->sunCalc = $sunCalc;
        $this->formatter = $formatter;
    }

    public function index(Request $request): Response
    {
        // Pobieramy parametry
        $lat = $request->get('lat');
        $lng = $request->get('lng');
        $detailed = $request->get('detailed', 'false') === 'true';

        // Walidacja - czy parametry istnieją
        if ($lat === null || $lng === null) {
            return $this->errorResponse($request, 400, 'Missing required parameters: lat and lng');
        }

        // Konwersja na float
        $lat = (float) $lat;
        $lng = (float) $lng;

        // Zaokrąglenie do 2 miejsc po przecinku
        $coords = $this->sunCalc->roundCoordinates($lat, $lng);
        $lat = $coords['lat'];
        $lng = $coords['lng'];

        // Walidacja zakresów
        $validation = $this->sunCalc->validate($lat, $lng);
        if (!$validation['valid']) {
            return $this->errorResponse($request, 422, $validation['error']);
        }

        // Obliczenia
        $result = $this->sunCalc->calculate($lat, $lng);

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
            ]);
        }

        // Formatowanie odpowiedzi
        $acceptHeader = $request->header('Accept', 'application/json');
        $format = $this->formatter->detectFormat($acceptHeader);
        $body = $this->formatter->format($responseData, $format);
        $contentType = $this->formatter->getContentType($format);

        // Headers z cache (ważne do następnej zmiany - sunrise lub sunset)
        $headers = [
            'Content-Type' => $contentType,
            'Expires' => gmdate('D, d M Y H:i:s T', $result['next_change_at']),
            'Cache-Control' => 'public, max-age=' . ($result['next_change_at'] - time()),
        ];

        return new Response(200, $headers, $body);
    }

    private function errorResponse(Request $request, int $statusCode, string $message): Response
    {
        $acceptHeader = $request->header('Accept', 'application/json');
        $format = $this->formatter->detectFormat($acceptHeader);
        
        $data = [
            'error' => true,
            'status' => $statusCode,
            'message' => $message,
        ];
        
        $body = $this->formatter->format($data, $format);
        $contentType = $this->formatter->getContentType($format);
        
        return new Response($statusCode, ['Content-Type' => $contentType], $body);
    }
}
