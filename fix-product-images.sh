#!/bin/bash

# Diagnostic and Fix Script for Product Images 403 Error
# Run this on your production server

echo "=== Product Images 403 Error Fix ==="
echo ""

PROJECT_PATH="/path/to/your/project"  # Change this to your actual project path

echo "1. Checking if products folder exists..."
if [ -d "$PROJECT_PATH/storage/app/public/products" ]; then
    echo "✓ Products folder exists"
    echo "   Permissions: $(stat -c '%a' $PROJECT_PATH/storage/app/public/products)"
else
    echo "✗ Products folder NOT found - Creating it..."
    mkdir -p "$PROJECT_PATH/storage/app/public/products"
    echo "✓ Products folder created"
fi

echo ""
echo "2. Checking image files..."
IMAGE_COUNT=$(ls -1 "$PROJECT_PATH/storage/app/public/products" 2>/dev/null | wc -l)
echo "   Found $IMAGE_COUNT image file(s)"
if [ $IMAGE_COUNT -gt 0 ]; then
    echo "   First 5 files:"
    ls "$PROJECT_PATH/storage/app/public/products" | head -5
fi

echo ""
echo "3. Checking storage symlink..."
if [ -L "$PROJECT_PATH/public/storage" ]; then
    echo "✓ Symlink exists"
    echo "   Points to: $(readlink $PROJECT_PATH/public/storage)"
else
    echo "✗ Symlink NOT found - Creating it..."
    cd "$PROJECT_PATH"
    php artisan storage:link
fi

echo ""
echo "4. Fixing permissions..."
chmod -R 775 "$PROJECT_PATH/storage"
chmod -R 755 "$PROJECT_PATH/public/storage"
echo "✓ Permissions fixed"

echo ""
echo "5. Fixing ownership..."
# Auto-detect web server user
if id "www-data" &>/dev/null; then
    WEB_USER="www-data"
elif id "apache" &>/dev/null; then
    WEB_USER="apache"
elif id "nginx" &>/dev/null; then
    WEB_USER="nginx"
else
    echo "⚠ Could not auto-detect web server user"
    echo "   Please run manually: chown -R YOUR_USER:YOUR_USER storage public/storage"
    WEB_USER=""
fi

if [ ! -z "$WEB_USER" ]; then
    chown -R "$WEB_USER:$WEB_USER" "$PROJECT_PATH/storage"
    chown -R "$WEB_USER:$WEB_USER" "$PROJECT_PATH/public/storage"
    echo "✓ Ownership set to $WEB_USER"
fi

echo ""
echo "6. Clearing Laravel cache..."
cd "$PROJECT_PATH"
php artisan config:cache
php artisan cache:clear
echo "✓ Cache cleared"

echo ""
echo "7. Testing image access..."
TEST_IMAGE=$(ls "$PROJECT_PATH/storage/app/public/products" 2>/dev/null | head -1)
if [ ! -z "$TEST_IMAGE" ]; then
    echo "   Testing: /storage/products/$TEST_IMAGE"
    if [ -r "$PROJECT_PATH/storage/app/public/products/$TEST_IMAGE" ]; then
        echo "✓ Image is readable"
    else
        echo "✗ Image is NOT readable (permission issue)"
    fi
fi

echo ""
echo "=== Verification ==="
echo "Storage folder permissions:"
ls -la "$PROJECT_PATH/storage/app/public" | grep products

echo ""
echo "Public storage symlink:"
ls -la "$PROJECT_PATH/public" | grep storage

echo ""
echo "=== Next Steps ==="
echo "1. If images still don't exist, upload them to: storage/app/public/products/"
echo "2. Test image URL: https://libromart.com/storage/products/FILENAME.png"
echo "3. If still 403, check web server error logs"
echo "4. Restart web server:"
echo "   sudo systemctl restart nginx    # or apache2"
