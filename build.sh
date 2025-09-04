#!/bin/bash

# WordPress Plugin Build Script (Shell version)
# Creates a production-ready WordPress plugin zip file

set -e

PLUGIN_SLUG="sell-my-images"
DIST_DIR="./dist"

echo "🚀 Building WordPress plugin: $PLUGIN_SLUG"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Error: Composer is required but not installed"
    echo "   Please install Composer: https://getcomposer.org/"
    exit 1
fi

# Check if zip command is available
if ! command -v zip &> /dev/null; then
    echo "❌ Error: zip command is required but not available"
    exit 1
fi

# Clean previous dist
if [ -d "$DIST_DIR" ]; then
    echo "🧹 Cleaning previous dist..."
    rm -rf "$DIST_DIR"
fi

# Create directories
mkdir -p "$DIST_DIR/$PLUGIN_SLUG"

echo "📁 Copying plugin files..."

# Copy essential files and directories
cp sell-my-images.php "$DIST_DIR/$PLUGIN_SLUG/"
cp -r src "$DIST_DIR/$PLUGIN_SLUG/"
cp -r assets "$DIST_DIR/$PLUGIN_SLUG/"
cp -r templates "$DIST_DIR/$PLUGIN_SLUG/"

echo "   ✓ Core files copied"

echo "📦 Installing production dependencies..."

# Copy composer.json to dist directory
cp composer.json "$DIST_DIR/$PLUGIN_SLUG/"

# Install production dependencies
cd "$DIST_DIR/$PLUGIN_SLUG"
composer install --no-dev --optimize-autoloader --no-interaction

# Verify vendor directory exists
if [ ! -d "vendor" ]; then
    echo "❌ Error: Vendor directory not created. Build failed."
    exit 1
fi

echo "   ✓ Production dependencies installed"

# Remove composer files from dist
rm -f composer.json composer.lock

# Return to root directory
cd ../..

echo "🗜️  Creating zip archive..."

# Create zip file
cd "$DIST_DIR"
zip -r "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG/" -x "*.DS_Store" "*/.DS_Store"
cd ..

# Get file size
FILE_SIZE=$(du -h "$DIST_DIR/$PLUGIN_SLUG.zip" | cut -f1)

echo "✅ Build complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📁 Plugin folder: $DIST_DIR/$PLUGIN_SLUG/"
echo "📦 Plugin zip: $DIST_DIR/$PLUGIN_SLUG.zip"
echo "📏 File size: $FILE_SIZE"
echo "🎯 Ready for WordPress installation"

echo ""
echo "Usage: Upload the zip file to WordPress Admin → Plugins → Add New → Upload Plugin"