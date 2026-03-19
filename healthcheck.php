<?php
$response = @file_get_contents('http://localhost:8787/health');
exit($response && str_contains($response, 'ok') ? 0 : 1);
