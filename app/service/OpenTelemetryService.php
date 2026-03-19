<?php

namespace app\service;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Common\Time\ClockInterface;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\View\CriteriaViewRegistry;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryService
{
    private static ?self $instance = null;

    private TracerInterface $tracer;
    private MeterInterface $meter;
    private ?TracerProvider $tracerProvider = null;
    private ?MeterProvider $meterProvider = null;
    private CounterInterface $requestCounter;
    private HistogramInterface $requestDuration;
    private CounterInterface $isDarkQueryCounter;
    private HistogramInterface $latDistribution;
    private HistogramInterface $lngDistribution;
    private bool $enabled;

    private function __construct()
    {
        $endpoint = getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: '';
        $serviceName = getenv('OTEL_SERVICE_NAME') ?: 'isitdark-api';
        $this->enabled = $endpoint !== '';

        if (!$this->enabled) {
            $this->initNoop($serviceName);
            return;
        }

        $resource = ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => $serviceName,
            ResourceAttributes::SERVICE_VERSION => '1.0.0',
        ]));

        $this->initTracer($endpoint, $resource);
        $this->initMeter($endpoint, $resource);
        $this->registerMetrics();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function tracer(): TracerInterface
    {
        return $this->tracer;
    }

    public function meter(): MeterInterface
    {
        return $this->meter;
    }

    public function requestCounter(): CounterInterface
    {
        return $this->requestCounter;
    }

    public function requestDuration(): HistogramInterface
    {
        return $this->requestDuration;
    }

    public function isDarkQueryCounter(): CounterInterface
    {
        return $this->isDarkQueryCounter;
    }

    public function latDistribution(): HistogramInterface
    {
        return $this->latDistribution;
    }

    public function lngDistribution(): HistogramInterface
    {
        return $this->lngDistribution;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function forceFlush(): void
    {
        $this->tracerProvider?->forceFlush();
        $this->meterProvider?->forceFlush();
    }

    private function initNoop(string $serviceName): void
    {
        $resource = ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => $serviceName,
        ]));

        $tracerProvider = new TracerProvider(resource: $resource);
        $this->tracer = $tracerProvider->getTracer('isitdark');

        $meterProvider = MeterProvider::builder()
            ->setResource($resource)
            ->build();
        $this->meter = $meterProvider->getMeter('isitdark');

        $this->registerMetrics();
    }

    private function initTracer(string $endpoint, ResourceInfo $resource): void
    {
        $transport = PsrTransportFactory::discover()->create(
            rtrim($endpoint, '/') . '/v1/traces',
            'application/x-protobuf',
            [],
            null,
            1.0,
            0,
            1,
        );

        $exporter = new \OpenTelemetry\Contrib\Otlp\SpanExporter($transport);

        $spanProcessor = new BatchSpanProcessor(
            $exporter,
            ClockFactory::getDefault(),
            maxQueueSize: 2048,
            scheduledDelayMillis: 5000,
            exportTimeoutMillis: 5000,
            maxExportBatchSize: 512,
            autoFlush: false,
        );

        $this->tracerProvider = TracerProvider::builder()
            ->addSpanProcessor($spanProcessor)
            ->setResource($resource)
            ->build();

        $this->tracer = $this->tracerProvider->getTracer('isitdark');
    }

    private function initMeter(string $endpoint, ResourceInfo $resource): void
    {
        $transport = PsrTransportFactory::discover()->create(
            rtrim($endpoint, '/') . '/v1/metrics',
            'application/x-protobuf',
            [],
            null,
            1.0,
            0,
            1,
        );

        $exporter = new \OpenTelemetry\Contrib\Otlp\MetricExporter($transport);
        $reader = new ExportingReader($exporter);

        $this->meterProvider = MeterProvider::builder()
            ->setResource($resource)
            ->addReader($reader)
            ->build();

        $this->meter = $this->meterProvider->getMeter('isitdark');
    }

    private function registerMetrics(): void
    {
        $this->requestCounter = $this->meter->createCounter(
            'http.server.request.count',
            '{request}',
            'Total number of HTTP requests',
        );

        $this->requestDuration = $this->meter->createHistogram(
            'http.server.request.duration',
            'ms',
            'HTTP request duration in milliseconds',
        );

        $this->isDarkQueryCounter = $this->meter->createCounter(
            'is_dark.query.count',
            '{query}',
            'Number of is-dark queries by result',
        );

        $this->latDistribution = $this->meter->createHistogram(
            'is_dark.query.lat',
            'degrees',
            'Latitude distribution of queries',
        );

        $this->lngDistribution = $this->meter->createHistogram(
            'is_dark.query.lng',
            'degrees',
            'Longitude distribution of queries',
        );
    }
}
