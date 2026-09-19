<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    print_r(app('OpenWA\Client')->sessions->list());
} catch (\Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
}
