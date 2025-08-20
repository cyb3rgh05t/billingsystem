<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'version' => '2.0.0',
    'php_version' => PHP_VERSION,
    'app_name' => 'KFZ Fac Pro PHP',
    'api_version' => '1.0'
]);
