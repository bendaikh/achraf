<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic marketplace synchronization (Hostinger cron must call: php artisan schedule:run)
Schedule::command('shopify:sync-orders')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('shopify:sync-products')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Keep Shopify webhooks registered (adds missing topics like fulfillments/tracking).
// No --force: skips already-correct webhooks; updates only missing/outdated ones.
Schedule::command('shopify:register-webhooks')
    ->daily()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/shopify-webhooks.log'));

Schedule::command('jumia:sync-orders')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/jumia-sync.log'));

Schedule::command('jumia:sync-stock')
    ->everyTenMinutes()
    ->withoutOverlapping(20)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/jumia-stock-sync.log'));

Schedule::command('exports:process --max=2')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('expenses:generate-recurring')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/recurring-expenses.log'));
