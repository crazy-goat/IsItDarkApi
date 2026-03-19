<?php

namespace app\bootstrap;

use app\service\OpenTelemetryService;
use OpenTelemetry\Context\Context;
use Workerman\Worker;

class OpenTelemetry
{
    public static function start(?Worker $worker): void
    {
        Context::getCurrent()->activate();
        OpenTelemetryService::getInstance();
    }
}
