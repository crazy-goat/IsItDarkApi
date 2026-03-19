#!/usr/bin/env php
<?php
chdir('/app');
require_once '/app/vendor/autoload.php';

use Workerman\Worker;

// Set user to nobody (user ID 65534) to avoid "unknown" user warnings
Worker::$user = 'nobody';

// Set worker count from env or default to 4 (avoid using nproc which doesn't exist in scratch)
Worker::$count = getenv('WEBMAN_WORKER_COUNT') ? (int)getenv('WEBMAN_WORKER_COUNT') : 4;

support\App::run();
