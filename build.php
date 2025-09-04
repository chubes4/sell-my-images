<?php
/**
 * WordPress Plugin Build Script
 * 
 * Creates a production-ready WordPress plugin zip file
 * Excludes development files and includes only production dependencies
 */

// Configuration
$plugin_slug = 'sell-my-images';
$dist_dir = __DIR__ . '/dist';

echo "🚀 Building WordPress plugin: {$plugin_slug}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Clean previous dist
if (is_dir($dist_dir)) {
    echo "🧹 Cleaning previous dist...\n";
    deleteDirectory($dist_dir);
}

// Create dist directory
mkdir($dist_dir, 0755, true);

$plugin_dist_dir = $dist_dir . '/' . $plugin_slug;
mkdir($plugin_dist_dir, 0755, true);

echo "📁 Copying plugin files...\n";

// Copy files and directories
$items_to_copy = [
    'sell-my-images.php',
    'src/',
    'assets/',
    'templates/'
];

foreach ($items_to_copy as $item) {
    $source = __DIR__ . '/' . $item;
    $destination = $plugin_dist_dir . '/' . $item;
    
    if (is_file($source)) {
        copy($source, $destination);
        echo "   ✓ {$item}\n";
    } elseif (is_dir($source)) {
        // Create the destination directory first
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        copyDirectory($source, $destination);
        echo "   ✓ {$item}/\n";
    }
}

echo "📦 Installing production dependencies...\n";

// Copy composer.json to dist directory first
copy(__DIR__ . '/composer.json', $plugin_dist_dir . '/composer.json');

// Install production dependencies in dist directory
$composer_command = "cd " . escapeshellarg($plugin_dist_dir) . " && composer install --no-dev --optimize-autoloader --no-interaction 2>&1";
$output = shell_exec($composer_command);

echo "   Composer output: " . trim($output) . "\n";

// Verify vendor directory exists
if (!is_dir($plugin_dist_dir . '/vendor')) {
    echo "❌ Error: Vendor directory not created. Build failed.\n";
    exit(1);
}

echo "   ✓ Production dependencies installed\n";

// Remove composer files from dist
$composer_files = [
    $plugin_dist_dir . '/composer.json',
    $plugin_dist_dir . '/composer.lock'
];

foreach ($composer_files as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}

echo "🗜️  Creating zip archive...\n";

// Create zip file
$zip_file = $dist_dir . '/' . $plugin_slug . '.zip';
$zip = new ZipArchive();

if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    echo "❌ Error: Cannot create zip file\n";
    exit(1);
}

// Add files to zip
addToZip($zip, $plugin_dist_dir, $plugin_slug);
$zip->close();

// Get file size
$file_size = formatBytes(filesize($zip_file));

echo "✅ Build complete!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📁 Plugin folder: {$plugin_dist_dir}/\n";
echo "📦 Plugin zip: {$zip_file}\n";
echo "📏 File size: {$file_size}\n";
echo "🎯 Ready for WordPress installation\n";

/**
 * Recursively copy directory
 */
function copyDirectory($source, $destination) {
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $relative_path = $iterator->getSubPathName();
        
        // Skip .DS_Store and other hidden files
        if (strpos($relative_path, '.DS_Store') !== false || strpos(basename($relative_path), '.') === 0) {
            continue;
        }
        
        $dest_path = $destination . DIRECTORY_SEPARATOR . $relative_path;
        
        if ($item->isDir()) {
            if (!is_dir($dest_path)) {
                mkdir($dest_path, 0755, true);
            }
        } else {
            copy($item, $dest_path);
        }
    }
}


/**
 * Add directory contents to zip
 */
function addToZip($zip, $source_dir, $zip_dir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $relative_path = $iterator->getSubPathName();
        
        // Skip .DS_Store and other hidden files
        if (strpos($relative_path, '.DS_Store') !== false || strpos(basename($relative_path), '.') === 0) {
            continue;
        }
        
        $zip_path = $zip_dir . '/' . $relative_path;
        
        if ($item->isDir()) {
            $zip->addEmptyDir($zip_path);
        } else {
            $zip->addFile($item, $zip_path);
        }
    }
}

/**
 * Recursively delete directory
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item != '.' && $item != '..') {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
    }
    rmdir($dir);
}

/**
 * Format bytes to human readable format
 */
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}