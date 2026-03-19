<?php

namespace app\middleware;

use app\service\OpenTelemetryService;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class TelemetryMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $otel = OpenTelemetryService::getInstance();
        $startTime = hrtime(true);

        $span = $otel->tracer()
            ->spanBuilder($request->method() . ' ' . $request->path())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.method', $request->method())
            ->setAttribute('http.url', $request->path())
            ->setAttribute('http.query', $request->queryString() ?? '')
            ->startSpan();

        $scope = $span->activate();

        try {
            $response = $handler($request);
            $statusCode = $response->getStatusCode();

            $span->setAttribute('http.status_code', $statusCode);

            if ($statusCode >= 400) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }

            return $response;
        } catch (\Throwable $e) {
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            throw $e;
        } finally {
            $durationMs = (hrtime(true) - $startTime) / 1_000_000;

            $route = $request->path();
            $method = $request->method();
            $status = isset($response) ? (string) $response->getStatusCode() : '500';

            $otel->requestCounter()->add(1, [
                'http.method' => $method,
                'http.route' => $route,
                'http.status_code' => $status,
            ]);

            $otel->requestDuration()->record($durationMs, [
                'http.method' => $method,
                'http.route' => $route,
                'http.status_code' => $status,
            ]);

            $span->end();
            $scope->detach();
        }
    }
}
