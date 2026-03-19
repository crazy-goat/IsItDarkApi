<?php

namespace app\bootstrap;

use app\service\OpenTelemetryService;
use Workerman\Worker;

class OpenTelemetry
{
    public static function start(?Worker $worker): void
    {
        OpenTelemetryService::getInstance();
    }
}
