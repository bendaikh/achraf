# Shopify Manual Configuration Guide (Advanced)

## When to Use Manual Configuration

Use manual configuration instead of OAuth if:
- You already have a custom app with an Admin API access token
- You want more control over token management
- You're using a development/private app
- OAuth redirect URLs are problematic in your environment

## Step-by-Step Instructions

### Step 1: Create a Custom App in Shopify

1. Log in to your Shopify Admin
2. Go to **Settings** → **Apps and sales channels**
3. Click **Develop apps** (or **Develop apps for your store**)
4. Click **Create an app**
5. Give your app a name (e.g., "My Laravel Integration")
6. Click **Create app**

### Step 2: Configure API Scopes

1. In your new app, click **Configure Admin API scopes**
2. Select the scopes you need:
   - ✅ `read_orders` - **Required** to fetch orders
   - ✅ `read_products` - Recommended to match products
   - ✅ `read_customers` - Optional for customer data
3. Click **Save**

### Step 3: Install the App and Get Access Token

1. Click **Install app** at the top of the page
2. Confirm the installation
3. Go to **API credentials** tab
4. Under **Admin API access token**, click **Reveal token once**
5. **IMPORTANT:** Copy this token immediately - it will only be shown once!
   - The token looks like: `shpat_1234567890abcdefghijklmnopqrstuvwxyz`

### Step 4: Get Your Shop Name

Your shop name is the part before `.myshopify.com` in your store URL.

Example:
- If your store is `my-awesome-store.myshopify.com`
- Your shop name is: `my-awesome-store`

### Step 5: Configure in Laravel

1. Go to your Laravel app at `/integrations/shopify`
2. Fill in the **Manual Configuration** form:

   **Integration Name**: Give it a friendly name (e.g., "Shopify Store")
   
   **Shop Name**: Enter your shop name (without .myshopify.com)
   ```
   my-awesome-store
   ```
   
   **API Access Token**: Paste the Admin API access token you copied
   ```
   shpat_1234567890abcdefghijklmnopqrstuvwxyz
   ```
   
   **API Version**: Use the latest stable version
   ```
   2024-01
   ```
   
   **Enable this integration**: ✅ Check this box

3. Click **Update Integration**

### Step 6: Test the Connection

After saving, the system will automatically test the connection:
- ✅ **Success**: "Integration updated successfully."
- ❌ **Error**: Check your shop name and token are correct

### Step 7: Sync Orders

Once connected, you can:

1. **Manual Sync** - Click "Sync Orders Now" button
2. **Command Line** - Run `php artisan shopify:sync-orders`
3. **Scheduled Sync** - Set up automatic hourly sync (see below)

## Example Configuration

```
Integration Name: My Shopify Store
Shop Name: my-awesome-store
API Access Token: shpat_abc123def456ghi789jkl012mno345pqr678stu901vwx234yz
API Version: 2024-01
Enable: ✅ Checked
```

## Scheduled Automatic Sync (Optional)

To automatically sync orders every hour, add this to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('shopify:sync-orders')->hourly();
```

Then make sure your Laravel scheduler is running:

```bash
# Add this to your crontab (Linux/Mac)
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1

# Or on Windows, use Task Scheduler to run this every minute:
php artisan schedule:run
```

## API Token vs OAuth - Comparison

| Feature | Manual API Token | OAuth (Recommended) |
|---------|-----------------|---------------------|
| Setup Complexity | Medium | Easy |
| Token Management | Manual | Automatic |
| Security | Good | Better |
| User Experience | Developer-focused | User-friendly |
| Token Rotation | Manual | Automatic |
| Best For | Private apps, dev | Production, public apps |

## Troubleshooting

### "Integration saved but failed to connect to Shopify API"

**Causes:**
- Shop name is incorrect (don't include .myshopify.com)
- API token is incorrect or expired
- API token doesn't have required scopes

**Solutions:**
1. Verify your shop name (check your browser URL when logged in to Shopify)
2. Generate a new API token in Shopify and update it
3. Check that your app has `read_orders` scope enabled

### "Failed to connect to Shopify API. Please check your credentials."

**Causes:**
- Network connectivity issues
- Shop name format is wrong
- Token is from a different shop

**Solutions:**
1. Test shop name format: should be just `shop-name` without `.myshopify.com`
2. Verify the token is from the correct store
3. Try regenerating the token in Shopify

### "API credentials not configured"

**Causes:**
- You didn't enter an API token
- Integration is not enabled

**Solutions:**
1. Make sure you pasted the API token
2. Check the "Enable this integration" checkbox
3. Click "Update Integration" to save

## Security Best Practices

1. **Never commit tokens to git** - Keep them in `.env` only
2. **Use environment-specific tokens** - Different tokens for dev/staging/production
3. **Limit scopes** - Only request the permissions you need
4. **Rotate regularly** - Change tokens periodically for security
5. **Monitor usage** - Check Shopify API logs for suspicious activity

## Required API Scopes Explained

### read_orders (Required)
- Fetch order information
- Get order history
- Access order details

### read_products (Recommended)
- Match Shopify products to your inventory
- Sync product SKUs
- Update product information

### read_customers (Optional)
- Sync customer information
- Match customers with orders
- Create customer profiles

## Current Token Status

You can check if your token is working:

1. Go to `/integrations/shopify`
2. Look for the status indicator:
   - 🟢 **Active** = Token is working
   - ⚫ **Inactive** = Integration disabled or no token

## Switching to OAuth Later

If you want to switch to OAuth later:

1. Your manual token will still work as a fallback
2. Simply use the "Install App on Shopify" button
3. After OAuth, the OAuth token takes priority
4. Manual token remains as backup

## Command Line Usage

### Sync all orders from last 7 days
```bash
php artisan shopify:sync-orders
```

### Sync orders from last 30 days
```bash
php artisan shopify:sync-orders --days=30
```

### Limit to 100 orders
```bash
php artisan shopify:sync-orders --limit=100
```

### Sync from specific order ID onwards
```bash
php artisan shopify:sync-orders --since-id=1234567890
```

## Need Help?

If you encounter issues:
1. Check the Laravel logs: `storage/logs/laravel.log`
2. Verify Shopify app installation is complete
3. Test API connection in Shopify admin
4. Ensure all required scopes are granted

---

**Ready to configure manually?** Follow the steps above and you'll be syncing orders in minutes!
