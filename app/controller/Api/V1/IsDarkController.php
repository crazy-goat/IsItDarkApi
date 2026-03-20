<?php

declare(strict_types=1);

namespace app\controller\Api\V1;

use app\service\OpenTelemetryService;
use app\service\ResponseFormatterService;
use app\service\SunCalcService;
use support\Container;
use support\Request;
use support\Response;

class IsDarkController
{
    public function __construct(
        private readonly SunCalcService $sunCalc = new SunCalcService(),
        private readonly ResponseFormatterService $formatter = new ResponseFormatterService(),
        private readonly ?OpenTelemetryService $otel = null,
    ) {
    }

    public function index(Request $request): Response
    {
        $latRaw = $request->get('lat');
        $lngRaw = $request->get('lng');
        $detailed = $request->get('detailed', 'false') === 'true';

        if ($latRaw === null || $lngRaw === null) {
            return $this->errorResponse($request, 400, 'Missing required parameters: lat and lng');
        }

        $lat = (float) (is_scalar($latRaw) ? $latRaw : '');
        $lng = (float) (is_scalar($lngRaw) ? $lngRaw : '');

        $coords = $this->sunCalc->roundCoordinates($lat, $lng);
        $lat = $coords['lat'];
        $lng = $coords['lng'];

        $validation = $this->sunCalc->validate($lat, $lng);
        if (!$validation['valid']) {
            return $this->errorResponse($request, 422, $validation['error'] ?? 'Validation error');
        }

        $result = $this->sunCalc->calculate($lat, $lng);

        $otel = $this->otel ?? Container::get(OpenTelemetryService::class);
        $otel->isDarkQueryCounter()->add(1, [
            'result' => $result['is_dark'] ? 'dark' : 'light',
        ]);
        $otel->latDistribution()->record($lat);
        $otel->lngDistribution()->record($lng);

        $responseData = [
            'is_dark' => $result['is_dark'],
            'sunrise' => $result['sunrise'],
            'sunset' => $result['sunset'],
        ];

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

        $format = $this->detectFormat($request);
        $body = $this->formatter->format($responseData, $format);
        $contentType = $this->formatter->getContentType($format);

        $rawNextChange = $result['next_change_at'];
        $nextChangeAt = is_int($rawNextChange)
            ? $rawNextChange
            : (int) (is_scalar($rawNextChange) ? $rawNextChange : 0);

        // Cache until next sunrise/sunset transition
        $headers = [
            'Content-Type' => $contentType,
            'Expires' => gmdate('D, d M Y H:i:s T', $nextChangeAt),
            'Cache-Control' => 'public, max-age=' . max(0, $nextChangeAt - time()),
        ];

        return new Response(200, $headers, $body);
    }

    private function detectFormat(Request $request): string
    {
        $acceptHeaderRaw = $request->header('Accept', 'application/json');
        $acceptHeader = is_string($acceptHeaderRaw) ? $acceptHeaderRaw : 'application/json';
        return $this->formatter->detectFormat($acceptHeader);
    }

    private function errorResponse(
        Request $request,
        int $statusCode,
        string $message,
    ): Response {
        $format = $this->detectFormat($request);

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
