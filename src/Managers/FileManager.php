<?php
/**
 * File Manager Class
 * 
 * Handles secure file storage, downloads, and management for upscaled images
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Managers;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FileManager class
 */
class FileManager {
    
    
    /**
     * HTTP timeout for large file downloads (5 minutes)
     */
    const DOWNLOAD_TIMEOUT = 300;
    
    /**
     * HTTP success status code
     */
    const HTTP_OK = 200;
    
    /**
     * Allowed image MIME types for security
     */
    const ALLOWED_IMAGE_TYPES = array(
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/webp'
    );
    
    /**
     * Upload directory for processed images
     */
    private static $upload_dir = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // No hooks needed currently - DownloadManager handles cleanup
    }
    
    /**
     * Get secure upload directory for SMI files
     * 
     * @return string Upload directory path
     */
    private static function get_upload_dir() {
        if ( self::$upload_dir === null ) {
            $wp_upload_dir = wp_upload_dir();
            self::$upload_dir = $wp_upload_dir['basedir'] . '/sell-my-images';
            
            // Create directory if it doesn't exist
            if ( ! file_exists( self::$upload_dir ) ) {
                wp_mkdir_p( self::$upload_dir );
                
                // Add htaccess protection
                self::protect_upload_directory();
            }
        }
        
        return self::$upload_dir;
    }
    
    /**
     * Protect upload directory from direct access
     */
    private static function protect_upload_directory() {
        $htaccess_file = self::get_upload_dir() . '/.htaccess';
        
        if ( ! file_exists( $htaccess_file ) ) {
            $htaccess_content = "# Sell My Images - Deny direct access\n";
            $htaccess_content .= "Order deny,allow\n";
            $htaccess_content .= "Deny from all\n";
            
            file_put_contents( $htaccess_file, $htaccess_content );
        }
        
        // Add index.php to prevent directory listing
        $index_file = self::get_upload_dir() . '/index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, '<?php // Silence is golden' );
        }
    }
    
    /**
     * Download file from Upsampler temporary URL and store locally
     * 
     * @param string $upscaled_url Temporary URL from Upsampler
     * @param string $job_id Job ID
     * @return string|false Local file path or false on failure
     */
    public static function download_from_upsampler( $upscaled_url, $job_id ) {
        // Generate temporary file name (we'll rename after determining the actual type)
        $temp_file_name = 'smi_' . $job_id . '_' . time() . '_temp';
        $temp_path = self::get_upload_dir() . '/' . $temp_file_name;
        
        // Download the file to temporary location
        $response = wp_remote_get( $upscaled_url, array(
            'timeout' => self::DOWNLOAD_TIMEOUT,
            'stream'  => true,
            'filename' => $temp_path,
        ) );
        
        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI FileManager: Failed to download from Upsampler: ' . $response->get_error_message()  );
            }
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== self::HTTP_OK ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI FileManager: Upsampler download failed with status: ' . $status_code  );
            }
            return false;
        }
        
        // Verify file was created and has content
        if ( ! file_exists( $temp_path ) || filesize( $temp_path ) === 0 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI FileManager: Downloaded file is empty or missing'  );
            }
            return false;
        }
        
        // Verify it's a valid image and get MIME type
        $image_info = getimagesize( $temp_path );
        if ( ! $image_info ) {
            unlink( $temp_path );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI FileManager: Downloaded file is not a valid image'  );
            }
            return false;
        }
        
        // Verify MIME type is allowed
        $mime_type = $image_info['mime'];
        if ( ! in_array( $mime_type, self::ALLOWED_IMAGE_TYPES, true ) ) {
            unlink( $temp_path );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI FileManager: Downloaded file has disallowed MIME type: ' . $mime_type  );
            }
            return false;
        }
        
        // Determine proper file extension based on MIME type
        $extension_map = array(
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        );
        
        $extension = $extension_map[ $mime_type ] ?? 'jpg'; // Default to jpg if unknown
        
        // Generate final file name with correct extension
        $final_file_name = 'smi_' . $job_id . '_' . time() . '.' . $extension;
        $final_path = self::get_upload_dir() . '/' . $final_file_name;
        
        // Rename temporary file to final location
        if ( ! rename( $temp_path, $final_path ) ) {
            unlink( $temp_path );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI FileManager: Failed to rename temporary file to final location'  );
            }
            return false;
        }
        
        return $final_path;
    }
    
    
    /**
     * Get file storage statistics
     * 
     * @return array Storage stats
     */
    public static function get_storage_stats() {
        $upload_dir = self::get_upload_dir();
        $total_size = 0;
        $file_count = 0;
        
        if ( is_dir( $upload_dir ) ) {
            $files = glob( $upload_dir . '/smi_*' );
            $file_count = count( $files );
            
            foreach ( $files as $file ) {
                if ( is_file( $file ) ) {
                    $total_size += filesize( $file );
                }
            }
        }
        
        return array(
            'file_count' => $file_count,
            'total_size' => $total_size,
            'total_size_formatted' => size_format( $total_size ),
        );
    }
}