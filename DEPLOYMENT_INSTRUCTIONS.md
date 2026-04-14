# Product Image Fix - Deployment Instructions

## What Was Changed

We fixed the 403 error by creating a custom image URL accessor in the Product model that uses the direct storage path (`/storage/app/public/`) instead of relying on symlinks.

## Files Modified

1. `app/Models/Product.php` - Added `getImageUrlAttribute()` accessor
2. `resources/views/products/index.blade.php` - Updated to use `$product->image_url`
3. `resources/views/products/show.blade.php` - Updated to use `$product->image_url`
4. `resources/views/products/edit.blade.php` - Updated to use `$product->image_url`
5. `public/.htaccess` - Added `Options +FollowSymLinks`

## Deployment Steps

### 1. Upload Updated Files to Production

Upload these files to your production server:
```bash
app/Models/Product.php
resources/views/products/index.blade.php
resources/views/products/show.blade.php
resources/views/products/edit.blade.php
public/.htaccess
```

### 2. Clear Laravel Cache

```bash
ssh user@libromart.com
cd /home/u158680994/domains/libromart.com/public_html

# Clear all caches
php artisan config:cache
php artisan cache:clear
php artisan view:clear
php artisan route:cache
```

### 3. Test the Images

Visit your products page and verify images load correctly:
```
https://libromart.com/products
```

The images should now load using this URL format:
```
https://libromart.com/storage/app/public/products/[filename].png
```

## Why This Works

- Your shared hosting blocks symlink access via HTTP (403 error)
- Direct path access works: `/storage/app/public/products/...`
- We created a model accessor that generates the correct URL
- All views now use `$product->image_url` instead of manually building the path

## Verification

After deployment, check:
1. Product list page shows images ✓
2. Product detail page shows images ✓
3. Product edit page shows preview ✓
4. No 403 errors in browser console ✓

## Rollback (if needed)

If something goes wrong, simply revert the Product.php file and change views back to:
```php
asset('storage/' . $product->image)
```
