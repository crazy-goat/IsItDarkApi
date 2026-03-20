#!/usr/bin/env php
<?php

declare(strict_types=1);

chdir('/app');
require_once '/app/vendor/autoload.php';
support\App::run();
