# Quick Fix for Production Error

## The Problem
Your production server shows: `Table 'u158680994_achraf.roles' doesn't exist`

This means the database tables haven't been created yet.

## Quick Solution (Choose ONE method)

### Method 1: Via SSH (Recommended)
```bash
# Connect to your server
ssh your_username@touhfasoft.site

# Go to your project folder
cd /home/u158680994/domains/touhfasoft.site/public_html

# Run this single command
php artisan migrate --force && php artisan db:seed --class=RoleSeeder --force && php artisan db:seed --class=SuperAdminSeeder --force && php artisan cache:clear && php artisan config:cache
```

### Method 2: Via Browser (If no SSH access)
1. Upload `setup.php` to your server's public_html folder
2. Visit: `https://touhfasoft.site/setup.php?key=achraf-setup-2026`
3. Wait for completion
4. **DELETE the setup.php file immediately!**

### Method 3: Via cPanel Terminal
1. Login to cPanel
2. Open "Terminal" or "Terminal Emulator"
3. Run the commands from Method 1

### Method 4: Via File Manager + PHPMyAdmin
If you can't run commands, manually import the SQL:

1. Go to PHPMyAdmin
2. Select database `u158680994_achraf`
3. Run this SQL:

```sql
-- Create roles table
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create role_user table
CREATE TABLE `role_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_user_role_id_foreign` (`role_id`),
  KEY `role_user_user_id_foreign` (`user_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert roles
INSERT INTO `roles` (`name`, `slug`, `description`, `created_at`, `updated_at`) VALUES
('Super Admin', 'superadmin', 'Full access to all system features', NOW(), NOW()),
('Admin', 'admin', 'Administrative access', NOW(), NOW()),
('User', 'user', 'Regular user access', NOW(), NOW());

-- Insert super admin user (if user with ID 1 exists)
INSERT INTO `role_user` (`role_id`, `user_id`, `created_at`, `updated_at`) 
SELECT 1, 1, NOW(), NOW()
WHERE EXISTS (SELECT 1 FROM `users` WHERE `id` = 1);

-- Or create super admin user if it doesn't exist
INSERT INTO `users` (`name`, `email`, `email_verified_at`, `password`, `created_at`, `updated_at`) 
VALUES ('Super Admin', 'superadmin@lavfast.com', NOW(), '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5esnRWwVq.Kci', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = `name`;

-- Link super admin to role
INSERT INTO `role_user` (`role_id`, `user_id`, `created_at`, `updated_at`) 
SELECT 1, `id`, NOW(), NOW() FROM `users` WHERE `email` = 'superadmin@lavfast.com'
ON DUPLICATE KEY UPDATE `role_id` = `role_id`;
```

## After Setup

1. Clear browser cache
2. Visit your site: https://touhfasoft.site
3. Login with:
   - Email: `superadmin@lavfast.com`
   - Password: `password`

## Verification

After running the setup, verify it worked:
```bash
php artisan tinker
>>> \App\Models\Role::count()
=> 3
>>> \App\Models\User::first()->roles
>>> exit
```

## Still Having Issues?

Check your `.env` file has correct database credentials:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u158680994_achraf
DB_USERNAME=u158680994_youruser
DB_PASSWORD=your_password
```

Then run:
```bash
php artisan config:clear
php artisan cache:clear
```
