<?php
/**
 * File Manager Class
 * 
 * Handles secure file storage, downloads, and management for upscaled images
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Api;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FileManager class
 */
class FileManager {
    
    /**
     * Maximum file download size (100MB)
     */
    const MAX_DOWNLOAD_SIZE = 100 * 1024 * 1024;
    
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
        // Generate local file name
        $file_name = 'smi_' . $job_id . '_' . time() . '.jpg';
        $local_path = self::get_upload_dir() . '/' . $file_name;
        
        // Set file download limits
        $max_download_size = apply_filters( 'smi_max_upsampler_download_size', self::MAX_DOWNLOAD_SIZE );
        
        // Download the file
        $response = wp_remote_get( $upscaled_url, array(
            'timeout' => self::DOWNLOAD_TIMEOUT,
            'stream'  => true,
            'filename' => $local_path,
            'headers' => array(
                'Range' => 'bytes=0-' . ( $max_download_size - 1 ), // Limit download size
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'SMI FileManager: Failed to download from Upsampler: ' . $response->get_error_message() );
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== self::HTTP_OK ) {
            error_log( 'SMI FileManager: Upsampler download failed with status: ' . $status_code );
            return false;
        }
        
        // Verify file was created and has content
        if ( ! file_exists( $local_path ) || filesize( $local_path ) === 0 ) {
            error_log( 'SMI FileManager: Downloaded file is empty or missing' );
            return false;
        }
        
        // Check downloaded file size against limits
        $downloaded_size = filesize( $local_path );
        if ( $downloaded_size > $max_download_size ) {
            unlink( $local_path );
            error_log( 'SMI FileManager: Downloaded file exceeds size limit: ' . size_format( $downloaded_size ) );
            return false;
        }
        
        // Verify it's a valid image
        $image_info = getimagesize( $local_path );
        if ( ! $image_info ) {
            unlink( $local_path );
            error_log( 'SMI FileManager: Downloaded file is not a valid image' );
            return false;
        }
        
        // Verify MIME type is allowed
        $mime_type = $image_info['mime'];
        if ( ! in_array( $mime_type, self::ALLOWED_IMAGE_TYPES, true ) ) {
            unlink( $local_path );
            error_log( 'SMI FileManager: Downloaded file has disallowed MIME type: ' . $mime_type );
            return false;
        }
        
        // Additional security: check file extension matches MIME type
        $file_extension = pathinfo( $local_path, PATHINFO_EXTENSION );
        $expected_extensions = array(
            'image/jpeg' => array( 'jpg', 'jpeg' ),
            'image/jpg'  => array( 'jpg', 'jpeg' ),
            'image/png'  => array( 'png' ),
            'image/webp' => array( 'webp' )
        );
        
        if ( isset( $expected_extensions[ $mime_type ] ) ) {
            if ( ! in_array( strtolower( $file_extension ), $expected_extensions[ $mime_type ], true ) ) {
                unlink( $local_path );
                error_log( 'SMI FileManager: File extension does not match MIME type - Extension: ' . $file_extension . ', MIME: ' . $mime_type );
                return false;
            }
        }
        
        return $local_path;
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