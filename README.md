# Achraf E-commerce - Super Admin System

A Laravel-based e-commerce platform with role-based authentication system.

## Features

- Role-based authentication system
- Super Admin dashboard
- Modern and responsive UI using Tailwind CSS
- MySQL database integration
- Production-ready configuration

## Installation

1. Install dependencies:
```bash
composer install
```

2. Configure your `.env` file with database credentials:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=achraf
DB_USERNAME=root
DB_PASSWORD=
```

3. Run migrations:
```bash
php artisan migrate
```

4. Seed roles and super admin:
```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=SuperAdminSeeder
```

5. Start the development server:
```bash
php artisan serve
```

## Super Admin Credentials

**Email:** superadmin@achraf.com  
**Password:** password

## Routes

- `/` - Redirects to login
- `/login` - Login page (GET)
- `/login` - Login submission (POST)
- `/dashboard` - Super Admin dashboard (requires authentication)
- `/logout` - Logout (POST)

## Roles System

The application includes three predefined roles:

1. **Super Admin** (slug: `superadmin`) - Full system access
2. **Admin** (slug: `admin`) - Administrative access
3. **User** (slug: `user`) - Regular user access

## Middleware

- `auth` - Requires authentication
- `superadmin` - Requires Super Admin role

### Using Super Admin Middleware

```php
Route::middleware(['auth', 'superadmin'])->group(function () {
    // Your super admin routes here
});
```

## Database Structure

### Tables

1. **users** - User accounts
2. **roles** - Available roles (Super Admin, Admin, User)
3. **role_user** - Pivot table for user-role relationships
4. **cache** - Cache storage
5. **jobs** - Queue jobs
6. **sessions** - User sessions

## User Model Methods

```php
// Check if user has a specific role
$user->hasRole('superadmin');

// Check if user is super admin
$user->isSuperAdmin();

// Get user's roles
$user->roles;
```

## Production Deployment

The application includes production-ready files:

- `index.php` - Entry point in the root directory
- `.htaccess` - Apache configuration for URL rewriting

### Production Checklist

1. Update `.env`:
```
APP_ENV=production
APP_DEBUG=false
```

2. Optimize Laravel:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. Set proper permissions:
```bash
chmod -R 755 storage bootstrap/cache
```

4. Point your web server document root to the application root directory (where `index.php` is located).

## Technology Stack

- **Framework:** Laravel 11.x
- **Database:** MySQL
- **Frontend:** Tailwind CSS
- **Authentication:** Laravel's built-in authentication

## Security Features

- Password hashing using bcrypt
- CSRF protection
- Session security
- Role-based access control
- Middleware protection for admin routes

## License

This project is proprietary and confidential.
