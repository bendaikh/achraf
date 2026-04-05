<?php

/**
 * Production Setup Script - LAV'FAST
 * 
 * WARNING: DELETE THIS FILE AFTER RUNNING IT!
 * This file should only be used once to set up your production database.
 * 
 * To use: Visit this file in your browser (e.g., https://touhfasoft.site/setup.php)
 * Or run from command line: php setup.php
 */

// Prevent running in non-production or if already set up
$setupKey = $_GET['key'] ?? $_SERVER['argv'][1] ?? '';

if ($setupKey !== 'achraf-setup-2026') {
    die('Access denied. Please provide the setup key in the URL: ?key=achraf-setup-2026');
}

echo "<pre>";
echo "============================================\n";
echo "LAV'FAST - Production Setup\n";
echo "============================================\n\n";

// Load Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$success = true;

try {
    // Step 1: Test database connection
    echo "Step 1: Testing database connection...\n";
    DB::connection()->getPdo();
    echo "✓ Database connected successfully\n\n";
    
    // Step 2: Run migrations
    echo "Step 2: Running migrations...\n";
    Artisan::call('migrate', ['--force' => true]);
    echo Artisan::output();
    echo "✓ Migrations completed\n\n";
    
    // Step 3: Seed roles
    echo "Step 3: Seeding roles...\n";
    Artisan::call('db:seed', ['--class' => 'RoleSeeder', '--force' => true]);
    echo Artisan::output();
    echo "✓ Roles seeded\n\n";
    
    // Step 4: Create super admin
    echo "Step 4: Creating super admin...\n";
    Artisan::call('db:seed', ['--class' => 'SuperAdminSeeder', '--force' => true]);
    echo Artisan::output();
    echo "✓ Super admin created\n\n";
    
    // Step 5: Clear caches
    echo "Step 5: Clearing caches...\n";
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('view:clear');
    Artisan::call('route:clear');
    echo "✓ Caches cleared\n\n";
    
    // Step 6: Optimize
    echo "Step 6: Optimizing application...\n";
    Artisan::call('config:cache');
    Artisan::call('route:cache');
    Artisan::call('view:cache');
    echo "✓ Application optimized\n\n";
    
    // Verify
    echo "Verifying installation...\n";
    $usersCount = App\Models\User::count();
    $rolesCount = App\Models\Role::count();
    
    echo "\n============================================\n";
    echo "Setup completed successfully!\n";
    echo "============================================\n\n";
    echo "Database Statistics:\n";
    echo "  - Users: $usersCount\n";
    echo "  - Roles: $rolesCount\n\n";
    echo "Super Admin Credentials:\n";
    echo "  Email: superadmin@lavfast.com\n";
    echo "  Password: password\n\n";
    echo "IMPORTANT:\n";
    echo "  1. DELETE THIS FILE (setup.php) NOW!\n";
    echo "  2. Change the super admin password after first login\n\n";
    echo "✓ Your application is ready to use!\n";
    
} catch (Exception $e) {
    $success = false;
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";

if ($success) {
    echo "<h2 style='color: green;'>✓ Setup completed successfully!</h2>";
    echo "<p><strong style='color: red;'>IMPORTANT: DELETE THIS FILE NOW!</strong></p>";
    echo "<p><a href='/login'>Go to Login Page</a></p>";
} else {
    echo "<h2 style='color: red;'>✗ Setup failed</h2>";
    echo "<p>Please check the error messages above and try again.</p>";
}
