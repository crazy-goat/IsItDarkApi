<?php

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
        $otel = OpenTelemetryService::getInstance();

        if ($otel->isEnabled()) {
            Timer::add(5, function () use ($otel) {
                $otel->forceFlush();
            });
        }
    }
}
