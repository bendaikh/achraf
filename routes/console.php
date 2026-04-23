<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic Shopify synchronization every 5 minutes for near real-time updates
Schedule::command('shopify:sync-orders')->everyFiveMinutes();
Schedule::command('shopify:sync-products')->everyFiveMinutes();

