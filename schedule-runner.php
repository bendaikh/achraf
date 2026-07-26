<?php

/**
 * Hostinger-safe Laravel scheduler entrypoint.
 *
 * Prefer this over `php artisan schedule:run >> /dev/null 2>&1` in hPanel,
 * because Hostinger may pass shell redirects as literal arguments and break Artisan.
 *
 * hPanel cron command (every minute):
 *   /usr/bin/php /home/u158680994/domains/libromart.com/public_html/schedule-runner.php
 */

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$status = $kernel->call('schedule:run');

$kernel->terminate(new Symfony\Component\Console\Input\ArrayInput(['command' => 'schedule:run']), $status);

exit($status);
