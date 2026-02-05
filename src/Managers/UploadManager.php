<?php
/**
 * Upload Manager Class
 * 
 * Handles temporary user-uploaded images for upscaling
 * 
 * @package SellMyImages
 * @since 1.2.3
 */

namespace SellMyImages\Managers;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * UploadManager class
 */
class UploadManager {
    
    /**
     * Allowed image MIME types
     */
    const ALLOWED_MIME_TYPES = array(
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp'
    );
    
    /**
     * Maximum file size in bytes (10MB)
     */
    const MAX_FILE_SIZE = 10485760;
    
    /**
     * Upload expiry time (24 hours in seconds)
     */
    const UPLOAD_EXPIRY_HOURS = 24;
    
    /**
     * Upload directory for temporary user uploads
     */
    private static $upload_dir = null;
    
    /**
     * Get secure upload directory for user uploads
     * 
     * @return string Upload directory path
     */
    private static function get_upload_dir() {
        if ( self::$upload_dir === null ) {
            $wp_upload_dir = wp_upload_dir();
            self::$upload_dir = $wp_upload_dir['basedir'] . '/sell-my-images/uploads';
            
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
     * Protect upload directory from direct browsing
     */
    private static function protect_upload_directory() {
        // Add index.php to prevent directory listing
        $index_file = self::get_upload_dir() . '/index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, '<?php // Silence is golden' );
        }
        
        // Note: We don't add .htaccess protection here because Upsampler needs to access
        // the uploaded files via URL. Files are automatically cleaned up after 24 hours.
    }
    
    /**
     * Upload and validate image file
     * 
     * @param array $file File array from $_FILES
     * @return array|WP_Error Upload data or error
     */
    public static function upload_image( $file ) {
        // Validate file was provided
        if ( empty( $file ) || ! isset( $file['tmp_name'] ) ) {
            return new \WP_Error(
                'no_file_provided',
                __( 'No file was uploaded', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new \WP_Error(
                'upload_error',
                __( 'File upload failed', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Validate file size
        if ( $file['size'] > self::MAX_FILE_SIZE ) {
            return new \WP_Error(
                'file_too_large',
                sprintf( 
                    /* translators: %s: maximum file size */
                    __( 'File size exceeds maximum allowed size of %s', 'sell-my-images' ),
                    size_format( self::MAX_FILE_SIZE )
                ),
                array( 'status' => 400 )
            );
        }
        
        // Validate MIME type
        $file_info = getimagesize( $file['tmp_name'] );
        if ( ! $file_info ) {
            return new \WP_Error(
                'invalid_image',
                __( 'File is not a valid image', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $mime_type = $file_info['mime'];
        if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
            return new \WP_Error(
                'invalid_image_type',
                __( 'Image type not supported. Please upload JPEG, PNG, or WebP images.', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Generate unique upload ID
        $upload_id = wp_generate_uuid4();
        
        // Determine file extension
        $extension_map = array(
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        );
        $extension = $extension_map[ $mime_type ];
        
        // Generate file path
        $file_name = str_replace( array( '{', '}' ), '', $upload_id ) . '.' . $extension;
        $file_path = self::get_upload_dir() . '/' . $file_name;
        
        // Move uploaded file
        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            return new \WP_Error(
                'file_move_failed',
                __( 'Failed to save uploaded file', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        // Return upload data
        return array(
            'upload_id' => $upload_id,
            'file_path' => $file_path,
            'width' => $file_info[0],
            'height' => $file_info[1],
            'mime_type' => $mime_type,
            'file_size' => filesize( $file_path ),
            'created_at' => current_time( 'mysql' ),
        );
    }
    
    /**
     * Get upload information
     * 
     * @param string $upload_id Upload ID
     * @return array|WP_Error Upload data or error
     */
    public static function get_upload( $upload_id ) {
        if ( empty( $upload_id ) ) {
            return new \WP_Error(
                'invalid_upload_id',
                __( 'Upload ID is required', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Find file with this upload ID
        $upload_dir = self::get_upload_dir();
        $clean_id = str_replace( array( '{', '}' ), '', $upload_id );
        $pattern = $upload_dir . '/' . $clean_id . '.*';
        $files = glob( $pattern );
        
        if ( empty( $files ) ) {
            return new \WP_Error(
                'upload_not_found',
                __( 'Upload not found', 'sell-my-images' ),
                array( 'status' => 404 )
            );
        }
        
        $file_path = $files[0];
        
        // Get file info
        $file_info = getimagesize( $file_path );
        if ( ! $file_info ) {
            return new \WP_Error(
                'invalid_upload',
                __( 'Upload is not a valid image', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        return array(
            'upload_id' => $upload_id,
            'file_path' => $file_path,
            'width' => $file_info[0],
            'height' => $file_info[1],
            'mime_type' => $file_info['mime'],
            'file_size' => filesize( $file_path ),
        );
    }
    
    /**
     * Delete upload file
     * 
     * @param string $upload_id Upload ID
     * @return bool|WP_Error Success status or error
     */
    public static function delete_upload( $upload_id ) {
        $upload = self::get_upload( $upload_id );
        
        if ( is_wp_error( $upload ) ) {
            return $upload;
        }
        
        if ( file_exists( $upload['file_path'] ) ) {
            if ( ! wp_delete_file( $upload['file_path'] ) ) {
                return new \WP_Error(
                    'delete_failed',
                    __( 'Failed to delete upload', 'sell-my-images' ),
                    array( 'status' => 500 )
                );
            }
        }
        
        return true;
    }
    
    /**
     * Cleanup expired uploads (older than 24 hours)
     * 
     * @return int Number of uploads cleaned up
     */
    public static function cleanup_expired_uploads() {
        $upload_dir = self::get_upload_dir();
        $cleaned_count = 0;
        
        if ( ! is_dir( $upload_dir ) ) {
            return 0;
        }
        
        // Get all upload files
        $files = glob( $upload_dir . '/*.*' );
        $cutoff_time = time() - ( self::UPLOAD_EXPIRY_HOURS * HOUR_IN_SECONDS );
        
        foreach ( $files as $file ) {
            // Skip .htaccess and index.php
            $basename = basename( $file );
            if ( in_array( $basename, array( '.htaccess', 'index.php' ), true ) ) {
                continue;
            }
            
            // Check if file is older than 24 hours
            if ( file_exists( $file ) && filemtime( $file ) < $cutoff_time ) {
                if ( wp_delete_file( $file ) ) {
                    $cleaned_count++;
                }
            }
        }
        
        return $cleaned_count;
    }
}
