<?php

use app\service\OpenTelemetryService;

return [
    OpenTelemetryService::class => function () {
        return new OpenTelemetryService();
    },
];
