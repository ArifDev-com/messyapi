<?php
// Set Laravel environment
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';

// Set up Laravel application
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Run migrations
$status = $kernel->handle(
    $input = new Symfony\Component\Console\Input\StringInput('migrate --force'),
    new Symfony\Component\Console\Output\ConsoleOutput()
);

// Terminate application
$kernel->terminate($input, $status);

dump($status ? "Failed" : "Done");

