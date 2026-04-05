#!/bin/bash

# LAV'FAST - Production Setup Script
# This script will set up your Laravel application on production

echo "============================================"
echo "LAV'FAST - Production Setup"
echo "============================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored messages
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}➜ $1${NC}"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "Error: artisan file not found. Please run this script from your Laravel root directory."
    exit 1
fi

print_success "Found Laravel installation"
echo ""

# Step 1: Check database connection
print_info "Step 1: Checking database connection..."
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connected successfully';" 2>&1
if [ $? -eq 0 ]; then
    print_success "Database connection successful"
else
    print_error "Database connection failed. Please check your .env file"
    exit 1
fi
echo ""

# Step 2: Run migrations
print_info "Step 2: Running database migrations..."
php artisan migrate --force
if [ $? -eq 0 ]; then
    print_success "Migrations completed successfully"
else
    print_error "Migrations failed"
    exit 1
fi
echo ""

# Step 3: Seed roles
print_info "Step 3: Seeding roles..."
php artisan db:seed --class=RoleSeeder --force
if [ $? -eq 0 ]; then
    print_success "Roles seeded successfully"
else
    print_error "Role seeding failed"
    exit 1
fi
echo ""

# Step 4: Create super admin
print_info "Step 4: Creating super admin account..."
php artisan db:seed --class=SuperAdminSeeder --force
if [ $? -eq 0 ]; then
    print_success "Super admin created successfully"
else
    print_error "Super admin creation failed"
    exit 1
fi
echo ""

# Step 5: Clear caches
print_info "Step 5: Clearing all caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
print_success "Caches cleared"
echo ""

# Step 6: Optimize for production
print_info "Step 6: Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
print_success "Application optimized"
echo ""

# Step 7: Set permissions
print_info "Step 7: Setting proper permissions..."
chmod -R 755 storage bootstrap/cache
if [ $? -eq 0 ]; then
    print_success "Permissions set successfully"
else
    print_error "Failed to set permissions (you may need to run this manually with sudo)"
fi
echo ""

# Verify installation
print_info "Verifying installation..."
USERS_COUNT=$(php artisan tinker --execute="echo App\Models\User::count();" 2>&1 | tail -1)
ROLES_COUNT=$(php artisan tinker --execute="echo App\Models\Role::count();" 2>&1 | tail -1)

echo ""
echo "============================================"
echo "Setup completed successfully!"
echo "============================================"
echo ""
echo "Database Statistics:"
echo "  - Users: $USERS_COUNT"
echo "  - Roles: $ROLES_COUNT"
echo ""
echo "Super Admin Credentials:"
echo "  Email: superadmin@lavfast.com"
echo "  Password: password"
echo ""
echo "IMPORTANT: Please change the super admin password after first login!"
echo ""
print_success "Your application is ready to use!"
