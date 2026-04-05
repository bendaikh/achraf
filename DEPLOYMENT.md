# Production Deployment Guide - LAV'FAST

## Error: Table 'roles' doesn't exist

This error occurs because the database migrations haven't been run on your production server yet.

## Solution: Run these commands on your production server

### Step 1: Connect to your production server via SSH

```bash
ssh your_username@touhfasoft.site
```

### Step 2: Navigate to your project directory

```bash
cd /home/u158680994/domains/touhfasoft.site/public_html
```

### Step 3: Run migrations to create tables

```bash
php artisan migrate --force
```

The `--force` flag is required in production to confirm you want to run migrations.

### Step 4: Seed the roles table

```bash
php artisan db:seed --class=RoleSeeder --force
```

### Step 5: Create the super admin account

```bash
php artisan db:seed --class=SuperAdminSeeder --force
```

### Step 6: Clear all caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

### Step 7: Optimize for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 8: Set proper permissions

```bash
chmod -R 755 storage bootstrap/cache
chown -R your_username:your_username storage bootstrap/cache
```

## Alternative: One-Line Command

You can run all commands at once:

```bash
cd /home/u158680994/domains/touhfasoft.site/public_html && php artisan migrate --force && php artisan db:seed --class=RoleSeeder --force && php artisan db:seed --class=SuperAdminSeeder --force && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Verify Database Tables

After running migrations, verify the tables exist:

```bash
php artisan tinker
```

Then in tinker:

```php
\App\Models\Role::count();
\App\Models\User::count();
exit
```

## Check Migration Status

```bash
php artisan migrate:status
```

This will show you which migrations have been run.

## Super Admin Credentials

After seeding:
- **Email:** superadmin@lavfast.com
- **Password:** password

## Important Notes

1. **Database Configuration**: Make sure your `.env` file on production has the correct database credentials:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=u158680994_achraf
   DB_USERNAME=your_db_username
   DB_PASSWORD=your_db_password
   ```

2. **Production Mode**: Ensure these are set in your production `.env`:
   ```
   APP_ENV=production
   APP_DEBUG=false
   ```

3. **Application Key**: Make sure you have an app key generated:
   ```bash
   php artisan key:generate --force
   ```

## Troubleshooting

### If migrations fail:

1. Check database connection:
   ```bash
   php artisan tinker
   DB::connection()->getPdo();
   ```

2. Check if tables already exist:
   ```bash
   php artisan db:show
   php artisan db:table roles
   ```

3. Reset migrations (CAUTION: This will delete all data):
   ```bash
   php artisan migrate:fresh --force
   php artisan db:seed --class=RoleSeeder --force
   php artisan db:seed --class=SuperAdminSeeder --force
   ```

### If you can't access SSH:

Use your hosting control panel's PHP interface or file manager to:
1. Upload your migration files
2. Run migrations through cPanel's PHP interface
3. Or use PHPMyAdmin to manually create the tables

## Files Modified for Error Handling

I've updated the following files to handle missing tables gracefully:
- `app/Models/User.php` - Added try-catch to role methods
- `resources/views/dashboard.blade.php` - Added error handling for role display

This means your app won't crash if migrations haven't been run yet, but you still need to run them for full functionality.
