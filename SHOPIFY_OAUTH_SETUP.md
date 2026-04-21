# Shopify OAuth Integration Setup Guide

## Overview

Your Shopify integration has been successfully upgraded to use OAuth 2.0 authentication! This is the modern, secure way to connect your Laravel application with Shopify.

## What Changed?

### Before (API Token Method)
- Manual token entry in the UI
- Had to create a custom app and copy/paste tokens
- Tokens had to be manually managed

### After (OAuth Method)
- One-click app installation
- Secure OAuth 2.0 flow
- Automatic token management
- Better security and user experience

## Setup Instructions

### Step 1: Add Your Credentials to .env

Based on your Shopify app screenshot, add these to your `.env` file:

```env
# Shopify OAuth Configuration
SHOPIFY_CLIENT_ID=41bc4eaef71d627965ded15a8ec82c9e
SHOPIFY_CLIENT_SECRET=your_secret_here
SHOPIFY_API_VERSION=2024-01
SHOPIFY_SCOPES=read_orders,read_products,read_customers
```

**Important:** 
- Replace `your_secret_here` with the actual secret from your Shopify app (the one shown as dots in the screenshot)
- The Client ID is already filled in from your screenshot

### Step 2: Configure OAuth Redirect URL in Shopify

1. Go to your Shopify app settings (the one you created)
2. Find the **App URL** or **Allowed redirection URL(s)** section
3. Add this URL:
   ```
   http://localhost/integrations/shopify/callback
   ```
   Or if you're using a different domain:
   ```
   https://yourdomain.com/integrations/shopify/callback
   ```

### Step 3: Set Required API Scopes in Shopify

Make sure your Shopify app has these scopes enabled:
- `read_orders` - To fetch order data
- `read_products` - To match products (optional but recommended)
- `read_customers` - To sync customer info (optional)

### Step 4: Install the App

1. Go to `/integrations/shopify` in your Laravel application
2. Enter your shop domain (e.g., `your-store.myshopify.com`)
3. Click **"Install App on Shopify"**
4. You'll be redirected to Shopify to authorize the app
5. Click **"Install"** on Shopify
6. You'll be redirected back to your app with the integration configured!

## How OAuth Flow Works

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   Your App  │────1───>│   Shopify    │         │  Your App   │
│             │         │   OAuth Page │         │  (Callback) │
└─────────────┘         └──────────────┘         └─────────────┘
                              │                          │
                              └────────2─────────────────┘
                        (User Authorizes)
                              │
                        ┌─────▼──────┐
                        │ Access Token│
                        │   Stored    │
                        └────────────┘
```

1. User enters shop domain and clicks "Install App"
2. App redirects to Shopify OAuth page
3. User authorizes the app on Shopify
4. Shopify redirects back with authorization code
5. App exchanges code for access token
6. Token is securely stored (encrypted) in database
7. Ready to sync orders!

## Features

### ✅ Secure OAuth 2.0 Authentication
No more manual token management. OAuth handles everything securely.

### ✅ Automatic Token Storage
Access tokens are encrypted and stored in your database automatically.

### ✅ HMAC Verification
All OAuth callbacks are verified using HMAC signatures to prevent tampering.

### ✅ State Verification
CSRF protection using state parameter to ensure requests come from your app.

### ✅ Backward Compatible
Still supports manual API token entry for advanced users or custom apps.

## Database Changes

New fields added to `shopify_integrations` table:
- `shop_domain` - Full Shopify domain (e.g., mystore.myshopify.com)
- `oauth_access_token` - Encrypted OAuth access token
- `oauth_scope` - Scopes granted by the user
- `oauth_state` - Temporary state for CSRF protection

## API Client Updates

The `ShopifyApiClient` now automatically uses:
1. OAuth access token (if available) - **Preferred**
2. API access token (fallback) - For backward compatibility

## Usage

### Sync Orders Manually
```bash
php artisan shopify:sync-orders
```

### Sync Orders with Options
```bash
php artisan shopify:sync-orders --days=30
php artisan shopify:sync-orders --limit=100
```

### Schedule Automatic Sync
Add to `routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('shopify:sync-orders')->hourly();
```

## Troubleshooting

### "Invalid OAuth callback parameters"
- Make sure your redirect URL is correctly configured in Shopify
- Check that SHOPIFY_CLIENT_ID is set in .env

### "Invalid HMAC signature"
- Verify SHOPIFY_CLIENT_SECRET is correct in .env
- Make sure you're using the secret from the same app as the client ID

### "Failed to obtain access token"
- Check your Client ID and Secret are correct
- Ensure the app is installed on the correct shop
- Verify your shop domain is correct (include .myshopify.com)

### "Integration is not enabled"
- After OAuth, the integration should be automatically enabled
- Check the database to ensure `enabled` = 1

## Security Notes

1. **Never commit .env file** - Keep your credentials secret
2. **Use HTTPS in production** - OAuth requires secure connections
3. **Tokens are encrypted** - All access tokens are encrypted in the database
4. **HMAC verification** - All OAuth callbacks are verified
5. **State parameter** - CSRF protection is built-in

## Your Credentials Reference

From your screenshot:
- **Client ID**: `41bc4eaef71d627965ded15a8ec82c9e`
- **Client Secret**: Check your Shopify app settings (click "Faire pivoter" to regenerate if needed)
- **Email**: achrafqssd@gmail.com
- **Service Account**: delivery@shopify-pubsub-webhooks.iam.gserviceaccount.com

## Next Steps

1. ✅ Add credentials to .env
2. ✅ Configure redirect URL in Shopify
3. ✅ Install the app via OAuth flow
4. ✅ Test order sync
5. ✅ Set up automatic scheduling (optional)

## Files Modified

- `app/Models/ShopifyIntegration.php` - Added OAuth fields
- `app/Http/Controllers/ShopifyIntegrationController.php` - Added OAuth flow methods
- `app/Services/ShopifyApiClient.php` - Updated to use OAuth token
- `resources/views/integrations/shopify.blade.php` - New OAuth UI
- `routes/web.php` - Added OAuth routes
- `config/services.php` - Added Shopify configuration
- `database/migrations/2026_04_19_*_add_oauth_fields_to_shopify_integrations_table.php` - New migration

---

**Ready to connect?** Add your credentials to `.env` and go to `/integrations/shopify` to install the app!
