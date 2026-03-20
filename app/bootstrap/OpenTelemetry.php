<?php

declare(strict_types=1);

namespace app\bootstrap;

use app\service\OpenTelemetryService;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;
use Workerman\Timer;
use Workerman\Worker;

class OpenTelemetry
{
    public static function start(?Worker $worker): void
    {
        Context::setStorage(new ContextStorage());
        $otel = resolve(OpenTelemetryService::class);

        if ($otel->isEnabled()) {
            Timer::add(5, function () use ($otel): void {
                $otel->forceFlush();
            });
        }
    }
}
