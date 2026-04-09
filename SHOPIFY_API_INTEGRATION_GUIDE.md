# Shopify Integration - API Mode Setup Guide

## Overview
Your Shopify integration has been successfully converted from webhook-based to API-based integration.

## What Changed

### 1. Database Changes
- Added `api_access_token` field (encrypted) to store your Shopify Admin API token
- Added `api_version` field to specify which Shopify API version to use (default: 2024-01)

### 2. New Services & Commands

#### ShopifyApiClient Service
- Location: `app/Services/ShopifyApiClient.php`
- Handles all API communication with Shopify
- Methods available:
  - `getOrders($params)` - Fetch orders with custom filters
  - `getOrder($orderId)` - Get a single order by ID
  - `getOrdersSince($sinceId)` - Get orders after a specific order ID
  - `getOrdersCreatedAfter($dateTime)` - Get orders created after a date
  - `testConnection()` - Verify API credentials are working

#### SyncShopifyOrders Command
- Location: `app/Console/Commands/SyncShopifyOrders.php`
- Command: `php artisan shopify:sync-orders`
- Options:
  - `--since-id=123456` - Fetch orders after this order ID
  - `--limit=50` - Maximum number of orders to fetch
  - `--days=7` - Fetch orders from the last N days (default: 7)

### 3. Updated UI
- The integration page now shows API credential fields instead of webhook configuration
- Added a "Sync Orders Now" button for manual syncing
- Updated instructions for creating a Shopify custom app
- Added API scopes requirements and setup guide

### 4. Updated Controller
- Location: `app/Http/Controllers/ShopifyIntegrationController.php`
- New `sync()` method to trigger manual order sync from the UI
- API connection test when saving credentials
- Updated validation to require shop name and API token

## How to Set Up

### Step 1: Create a Custom App in Shopify

1. Go to your Shopify Admin
2. Navigate to **Settings → Apps and sales channels**
3. Click **Develop apps**
4. Click **Create an app**
5. Give it a name (e.g., "My Store Integration")

### Step 2: Configure API Scopes

1. Click **Configure Admin API scopes**
2. Grant at least these permissions:
   - `read_orders` (required)
   - `read_products` (optional, for better product matching)
   - `read_customers` (optional, for customer sync)
3. Save the scopes

### Step 3: Install the App

1. Click **Install app**
2. After installation, click **Reveal token once**
3. Copy the **Admin API access token** (you won't be able to see it again!)

### Step 4: Configure in Your Laravel App

1. Go to your integration page: `/integrations/shopify`
2. Fill in:
   - **Shop Name**: Your store name (e.g., if your store is `my-store.myshopify.com`, enter `my-store`)
   - **API Access Token**: Paste the token you copied
   - **API Version**: Use `2024-01` or a newer version
3. Check "Enable this integration"
4. Click "Update Integration"
5. The system will test the connection automatically

### Step 5: Sync Orders

#### Manual Sync
Click the "Sync Orders Now" button on the integration page

#### Command Line Sync
```bash
# Sync orders from the last 7 days
php artisan shopify:sync-orders

# Sync orders from the last 30 days
php artisan shopify:sync-orders --days=30

# Sync up to 100 orders
php artisan shopify:sync-orders --limit=100

# Sync orders after a specific order ID
php artisan shopify:sync-orders --since-id=5678901234567
```

### Step 6: Automated Sync (Optional)

To automatically sync orders every hour, add this to your Laravel scheduler:

**For Laravel 11+ (routes/console.php):**
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('shopify:sync-orders')->hourly();
```

**For Laravel 10 and below (app/Console/Kernel.php):**
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('shopify:sync-orders')->hourly();
}
```

Then make sure your cron is set up:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Key Features

### API Benefits vs Webhooks
- ✅ **Pull on demand** - Sync orders whenever you want
- ✅ **Historical data** - Can fetch old orders anytime
- ✅ **More control** - You decide when and how often to sync
- ✅ **No webhook setup** - No need to expose public URLs or manage webhook secrets
- ✅ **Better for testing** - Easy to test without setting up webhooks
- ✅ **Batch processing** - Can fetch multiple orders in one API call

### How Orders Are Matched
- Orders are matched by SKU to your product `ref` field
- Duplicate orders (same Shopify order ID) are automatically skipped
- Customer data is synced and matched by email
- All orders are marked as source: `shopify` with the original Shopify order ID

## Testing

To test the integration:

1. Make sure you have some orders in your Shopify store
2. Click "Sync Orders Now" in the UI
3. Check the logs at `storage/logs/laravel.log` for any errors
4. Visit your POS Sales page to see the imported orders

## Troubleshooting

### "Failed to connect to Shopify API"
- Check your shop name (should not include `.myshopify.com`)
- Verify your API access token is correct
- Make sure the custom app is installed in your Shopify store

### "No new orders to sync"
- Check if you have orders in the date range you're syncing
- Try increasing the `--days` option (e.g., `--days=30`)

### Orders not appearing
- Check `storage/logs/laravel.log` for error messages
- Verify your products have a `ref` field that matches Shopify SKUs
- Make sure the integration is enabled

## Files Modified/Created

### Created:
- `app/Services/ShopifyApiClient.php` - Shopify API client
- `app/Console/Commands/SyncShopifyOrders.php` - Sync command
- `database/migrations/2026_04_09_180750_add_api_credentials_to_shopify_integrations_table.php` - Database migration

### Modified:
- `app/Models/ShopifyIntegration.php` - Added API credential fields
- `app/Http/Controllers/ShopifyIntegrationController.php` - Added sync method and API validation
- `resources/views/integrations/shopify.blade.php` - Updated UI for API mode
- `routes/web.php` - Added sync route

### Legacy Files (Can be removed if you want):
- `app/Http/Controllers/ShopifyWebhookController.php` - No longer needed for API integration
- The webhook route in `routes/web.php` (line 31-32) - Can be removed

## Next Steps

1. Set up your Shopify custom app and get the API credentials
2. Configure the integration in your Laravel app
3. Test manual sync to ensure everything works
4. Set up automated hourly sync in the scheduler
5. Monitor the logs for any issues

---

**Note**: The old webhook functionality is still present in the code but not actively used. You can safely remove `ShopifyWebhookController.php` and the webhook route if you're fully committed to API-based integration.
