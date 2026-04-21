# Shopify OAuth Integration - Complete Setup Guide

## Overview

Your Shopify integration now uses a **complete UI-based setup** - no need to manually edit `.env` files! Everything can be configured directly through the web interface.

## Quick Start (3 Easy Steps)

### Step 1: Enter OAuth Credentials
1. Go to `/integrations/shopify` in your Laravel application
2. In the **"Step 1: Enter Your OAuth Credentials"** section, enter:
   - **Client ID**: `41bc4eaef71d627965ded15a8ec82c9e` (from your screenshot)
   - **Client Secret**: (Click the eye icon in your Shopify app to reveal and copy)
3. Click **"Save OAuth Credentials"**

### Step 2: Configure Redirect URL in Shopify
1. Go to your Shopify app settings
2. Find the **OAuth redirect URLs** section
3. Add this URL:
   ```
   http://localhost/integrations/shopify/callback
   ```
   (Or your production domain if deployed)

### Step 3: Install the App
1. After saving credentials in Step 1, the **"Step 2: Install App on Shopify"** section will appear
2. Enter your shop domain (e.g., `your-store.myshopify.com`)
3. Click **"Install App on Shopify"**
4. Authorize the app on Shopify
5. Done! Start syncing orders

## Features

### ✨ No Manual .env Editing Required!
- All credentials stored securely in the database (encrypted)
- Configure everything through the web UI
- Much easier for non-technical users

### 🔒 Secure Credential Storage
- Client ID and Secret are encrypted in the database
- Access tokens are encrypted
- No plaintext credentials in configuration files

### 🎯 Two-Step Setup Process
1. **Configure OAuth Credentials** - Enter your Shopify app's Client ID and Secret
2. **Install App** - Connect your specific store using OAuth

### 🔄 Backward Compatible
- Still supports manual API token entry as fallback
- Works with existing integrations
- Smooth upgrade path

## Where to Find Your Shopify Credentials

### Client ID and Secret
1. Log in to your Shopify admin
2. Go to **Settings** → **Apps and sales channels**
3. Click **Develop apps**
4. Click on your app
5. Go to **API credentials** tab
6. Copy:
   - **Client ID** - Visible directly
   - **Client secret** - Click eye icon or "Faire pivoter" to reveal

From your screenshot:
- **Client ID**: `41bc4eaef71d627965ded15a8ec82c9e`
- **Email**: achrafqssd@gmail.com

## OAuth Redirect URL

Add this to your Shopify app settings:

**Development:**
```
http://localhost/integrations/shopify/callback
```

**Production:**
```
https://yourdomain.com/integrations/shopify/callback
```

## Required API Scopes

Make sure your Shopify app has these scopes:
- `read_orders` - Required to fetch orders
- `read_products` - Recommended to match products
- `read_customers` - Optional for customer data

## Complete Setup Flow

```
1. User visits /integrations/shopify
   ↓
2. Enters Client ID and Secret in UI
   ↓
3. Clicks "Save OAuth Credentials"
   ↓ (Credentials saved to database, encrypted)
4. "Install App" button appears
   ↓
5. User enters shop domain
   ↓
6. Clicks "Install App on Shopify"
   ↓ (Redirected to Shopify)
7. User authorizes app on Shopify
   ↓ (Shopify redirects back with code)
8. Access token automatically exchanged and saved
   ↓
9. ✅ Connected! Ready to sync orders
```

## Using the Integration

### Manual Sync
Click **"Sync Orders Now"** button in the UI to fetch orders from the last 7 days.

### Command Line Sync
```bash
php artisan shopify:sync-orders
```

### Scheduled Automatic Sync
Add to `routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('shopify:sync-orders')->hourly();
```

## Database Structure

The `shopify_integrations` table now stores:
- `oauth_client_id` - Your app's Client ID (encrypted)
- `oauth_client_secret` - Your app's Client Secret (encrypted)
- `oauth_access_token` - Store access token after OAuth (encrypted)
- `oauth_scope` - Scopes granted during OAuth
- `oauth_state` - Temporary CSRF protection state
- `shop_domain` - Full shop domain (e.g., store.myshopify.com)
- `shop_name` - Shop name only (e.g., store)
- Plus other fields for integration settings

## Advantages Over .env Configuration

| Feature | UI Configuration | .env Configuration |
|---------|------------------|-------------------|
| Ease of use | ⭐⭐⭐⭐⭐ Very Easy | ⭐⭐ Technical |
| Multiple stores | ✅ Possible | ❌ Limited |
| Security | 🔒 Encrypted in DB | ⚠️ File-based |
| Updates | 🔄 Real-time via UI | 🔄 Requires restart |
| User-friendly | ✅ Yes | ❌ Developer-only |
| Deployment | ✅ Easy | ⚠️ Env vars needed |

## Troubleshooting

### "OAuth Client ID is not configured"
**Solution:** You need to enter your Client ID first in Step 1 before proceeding to installation.

### "Invalid OAuth callback parameters"
**Solution:** Make sure the redirect URL is configured correctly in your Shopify app settings.

### "Invalid HMAC signature"
**Solution:** Verify your Client Secret is correct. Try re-entering it in the UI.

### "Failed to obtain access token"
**Solution:** 
- Check Client ID and Secret are correct
- Verify redirect URL matches exactly
- Ensure the app is published/installed in Shopify

## Security Best Practices

1. ✅ **All credentials encrypted** - Client ID, Secret, and tokens are encrypted at rest
2. ✅ **HMAC verification** - All OAuth callbacks are verified
3. ✅ **State parameter** - CSRF protection built-in
4. ✅ **No code changes needed** - Everything configurable via UI
5. ✅ **Audit trail** - Database tracks when credentials were added/updated

## Comparison: Old vs New Approach

### Old Approach (Environment Variables)
```env
# In .env file (requires server access)
SHOPIFY_CLIENT_ID=41bc4eaef71d627965ded15a8ec82c9e
SHOPIFY_CLIENT_SECRET=secret_here
```
❌ Requires server/file access
❌ Needs app restart after changes
❌ Not user-friendly for non-developers

### New Approach (UI Configuration)
```
1. Open /integrations/shopify
2. Fill in form fields
3. Click Save
```
✅ No server access needed
✅ Instant updates
✅ User-friendly interface
✅ Better security (encrypted)
✅ Works for all users

## Migration from .env to UI

If you previously had credentials in `.env`:

1. Go to `/integrations/shopify`
2. Enter your Client ID and Secret in the UI
3. Click "Save OAuth Credentials"
4. The system will now use UI values (takes priority over `.env`)
5. You can optionally remove the old `.env` entries (but keeping them as fallback is fine)

## Next Steps After Setup

1. ✅ Credentials configured in UI
2. ✅ OAuth redirect URL added to Shopify
3. ✅ App installed via OAuth
4. ✅ Test manual sync
5. ✅ Set up scheduled sync (optional)
6. ✅ Monitor orders in your application

---

**Ready to connect?** Head to `/integrations/shopify` and start with Step 1!

## Support

If you encounter any issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify credentials are correct in Shopify
3. Ensure redirect URL matches exactly
4. Test connection using "Sync Orders Now" button

**Your Shopify App Details:**
- Client ID: `41bc4eaef71d627965ded15a8ec82c9e`
- Email: achrafqssd@gmail.com
- Service Account: delivery@shopify-pubsub-webhooks.iam.gserviceaccount.com
