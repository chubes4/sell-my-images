<?php
/**
 * Download Manager Class
 * 
 * Centralizes all download-related functionality including token management,
 * file serving, authorization, and email notifications
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Managers;

use SellMyImages\Config\Constants;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DownloadManager class
 */
class DownloadManager {
    
    /**
     * Generate secure download token
     * 
     * @return string 64-character secure token
     */
    public static function generate_download_token() {
        return wp_generate_password( Constants::DOWNLOAD_TOKEN_LENGTH, false, false );
    }
    
    /**
     * Validate download token and return job data
     * 
     * @param string $token Download token
     * @return object|WP_Error Job object or error
     */
    public static function validate_download_token( $token ) {
        // Validate token format
        if ( empty( $token ) || ! preg_match( '/^[a-zA-Z0-9]{64}$/', $token ) ) {
            return new \WP_Error(
                'invalid_token_format',
                __( 'Invalid token format', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Get job by token using JobManager
        $job = JobManager::get_job_by_token( $token );
        if ( is_wp_error( $job ) ) {
            return $job; // Return the error from JobManager
        }
        
        // Check expiry
        if ( $job->download_expires_at && strtotime( $job->download_expires_at ) < time() ) {
            return new \WP_Error(
                'link_expired',
                __( 'Download link has expired', 'sell-my-images' ),
                array( 'status' => 410 )
            );
        }
        
        return $job;
    }
    
    /**
     * Check if download is authorized for a job
     * Authorization is based on token possession and job completion status
     * 
     * @param object $job Job object from database
     * @return bool|WP_Error True if authorized, WP_Error if not
     */
    public static function is_download_authorized( $job ) {
        // Check job completion status
        if ( $job->status !== 'completed' ) {
            return new \WP_Error(
                'processing_incomplete',
                __( 'Image processing not yet complete', 'sell-my-images' ),
                array( 'status' => 425 )
            );
        }
        
        // Check file exists
        if ( empty( $job->upscaled_file_path ) || ! file_exists( $job->upscaled_file_path ) ) {
            return new \WP_Error(
                'file_not_available',
                __( 'File no longer available', 'sell-my-images' ),
                array( 'status' => 404 )
            );
        }
        
        return true;
    }
    
    /**
     * Serve download file with security checks
     * 
     * @param string $token Download token
     * @return void Exits after serving file or error
     */
    public static function serve_download( $token ) {
        // Validate token and get job
        $job = self::validate_download_token( $token );
        if ( is_wp_error( $job ) ) {
            self::send_error_response( $job );
        }
        
        // Check authorization
        $auth_check = self::is_download_authorized( $job );
        if ( is_wp_error( $auth_check ) ) {
            self::send_error_response( $auth_check );
        }
        
        // Serve the file
        self::serve_file_direct( $job->upscaled_file_path, $job->job_id );
    }
    
    /**
     * Serve file directly with security checks and chunked delivery
     * 
     * @param string $file_path Absolute path to file
     * @param string $job_id Job ID for logging
     * @return void Exits after serving file
     */
    private static function serve_file_direct( $file_path, $job_id ) {
        // Validate file path security
        $uploads_dir = wp_upload_dir();
        $safe_path = realpath( $file_path );
        $uploads_path = realpath( $uploads_dir['basedir'] );
        
        if ( ! $safe_path || ! $uploads_path || strpos( $safe_path, $uploads_path ) !== 0 ) {
            status_header( 403 );
            exit;
        }
        
        // Check file exists and is readable
        if ( ! file_exists( $safe_path ) || ! is_readable( $safe_path ) ) {
            status_header( 404 );
            exit;
        }
        
        // Get file size for Content-Length header
        $file_size = filesize( $safe_path );
        
        
        // Set headers for file download
        $filename = basename( $safe_path );
        $mime_type = self::get_file_mime_type( $safe_path );
        
        header( 'Content-Type: ' . $mime_type );
        header( 'Content-Length: ' . $file_size );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, must-revalidate' );
        header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
        header( 'Pragma: no-cache' );
        
        // Disable output buffering for large files
        if ( ob_get_level() ) {
            ob_end_clean();
        }
        
        // For file downloads, we need to use direct file operations for streaming
        // WP_Filesystem is not suitable for chunked streaming of large files
        // We've already validated the path security above
        
        // Use readfile() for better performance if file is small
        $file_size = filesize( $safe_path );
        if ( $file_size < 50 * 1024 * 1024 ) { // Less than 50MB
            readfile( $safe_path );
        } else {
            // Use chunked reading for larger files
            $handle = fopen( $safe_path, 'rb' );
            if ( ! $handle ) {
                status_header( 500 );
                exit;
            }
            
            $chunk_size = apply_filters( 'smi_download_chunk_size', Constants::DOWNLOAD_CHUNK_SIZE );
            
            while ( ! feof( $handle ) ) {
                $chunk = fread( $handle, $chunk_size );
                if ( $chunk === false ) {
                    break;
                }
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file content must not be escaped
                echo $chunk;
                flush();
            }
            
            fclose( $handle );
        }
        exit;
    }
    
    /**
     * Store processed file from Upsampler
     * 
     * @param string $upscaled_url Temporary URL from Upsampler
     * @param string $job_id Job ID
     * @return string|false Local file path or false on failure
     */
    public static function store_processed_file( $upscaled_url, $job_id ) {
        // Delegate to FileManager for actual storage
        $local_path = FileManager::download_from_upsampler( $upscaled_url, $job_id );
        
        if ( $local_path ) {
            // Generate download token and update job
            $download_token = self::generate_download_token();
            $expiry_hours = get_option( 'smi_download_expiry_hours', Constants::DEFAULT_DOWNLOAD_EXPIRY_HOURS );
            $expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_hours * 3600 ) );
            
            // Update job with download info
            self::update_job_download_data( $job_id, array(
                'upscaled_file_path' => $local_path,
                'download_token' => $download_token,
                'download_expires_at' => $expires_at,
            ) );
            
            // Send download notification
            self::send_download_notification( $job_id );
            
        }
        
        return $local_path;
    }
    
    /**
     * Send download notification email
     * 
     * Sends HTML download notification for completed jobs.
     * Part of dual email system: HTML for downloads, plain text for refunds.
     * 
     * @param string $job_id Job ID
     * @return bool Success status
     */
    public static function send_download_notification( $job_id ) {
        $job = JobManager::get_job( $job_id );
        if ( is_wp_error( $job ) || ! $job->download_token ) {
            return false;
        }
        
        // Load email template
        $email_data = self::load_email_template( $job );
        if ( ! $email_data ) {
            return false;
        }
        
        // Set custom headers for "Sarai Chinwag" sender
        $headers = array(
            'From: Sarai Chinwag <' . get_option( 'admin_email' ) . '>',
            'Reply-To: ' . get_option( 'admin_email' ),
            'Content-Type: text/html; charset=UTF-8'
        );
        
        // Send to customer only if email present
        $email_sent = false;
        if ( ! empty( $job->email ) && is_email( $job->email ) ) {
            $email_sent = wp_mail( $job->email, $email_data['subject'], $email_data['message'], $headers );
        }
        
        // Send copy to admin
        $admin_email = get_option( 'admin_email' );
        if ( $admin_email && ( empty( $job->email ) || $admin_email !== $job->email ) ) {
            $admin_subject = 'Copy: ' . $email_data['subject'];
            $admin_message = $email_data['message'];
            wp_mail( $admin_email, $admin_subject, $admin_message, $headers );
        }
        
        // Essential user flow log - download email sent
        if ( $email_sent ) {
            error_log( 'SMI: Download email sent for job ' . $job_id . ' (customer: ' . $job->email . ')' );
        }
        
        // Update email status
        self::update_job_email_status( $job_id, $email_sent );
        
        return $email_sent;
    }
    
    /**
     * Get download URL for a job
     * 
     * @param string $job_id Job ID
     * @return string|false Download URL or false if not available
     */
    public static function get_download_url( $job_id ) {
        $job = JobManager::get_job( $job_id );
        if ( is_wp_error( $job ) || ! $job->download_token ) {
            return false;
        }
        
        return rest_url( 'smi/v1/download/' . $job->download_token );
    }
    
    /**
     * Cleanup expired downloads
     * 
     * @return int Number of downloads cleaned up
     */
    public static function cleanup_expired_downloads() {
        // Use DatabaseManager cleanup utility
        return DatabaseManager::cleanup_expired_downloads();
    }
    
    /**
     * Load email template with job data
     * 
     * @param object $job Job object
     * @return array|false Email data array with subject and message, or false on failure
     */
    private static function load_email_template( $job ) {
        $template_path = SMI_PLUGIN_DIR . 'templates/email-notification.php';
        
        if ( ! file_exists( $template_path ) ) {
            return false;
        }
        
        // Prepare template variables
        $download_url = rest_url( 'smi/v1/download/' . $job->download_token );
        $expiry_date = gmdate( 'M j, Y \a\t g:i A', strtotime( $job->download_expires_at ) );
        $terms_conditions_url = get_option( 'smi_terms_conditions_url', '' );
        $site_name = get_bloginfo( 'name' );
        
        // Include template with variables in scope
        $email_data = include $template_path;
        
        return $email_data;
    }
    
    /**
     * Update job with download data
     * 
     * @param string $job_id Job ID
     * @param array $data Download data to update
     * @return bool Success status
     */
    private static function update_job_download_data( $job_id, $data ) {
        $result = DatabaseManager::update( $data, array( 'job_id' => $job_id ) );
        
        if ( is_wp_error( $result ) ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Update job email status
     * 
     * @param string $job_id Job ID
     * @param bool $email_sent Email success status
     * @return bool Success status
     */
    private static function update_job_email_status( $job_id, $email_sent ) {
        $result = DatabaseManager::update(
            array( 'email_sent' => $email_sent ? 1 : 0 ),
            array( 'job_id' => $job_id )
        );
        
        return ! is_wp_error( $result );
    }
    
    /**
     * Get file MIME type
     * 
     * @param string $file_path File path
     * @return string MIME type
     */
    private static function get_file_mime_type( $file_path ) {
        $mime_type = wp_check_filetype( $file_path );
        
        if ( $mime_type['type'] ) {
            return $mime_type['type'];
        }
        
        // Fallback for images
        if ( function_exists( 'finfo_file' ) ) {
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $detected = finfo_file( $finfo, $file_path );
            finfo_close( $finfo );
            
            if ( $detected && strpos( $detected, 'image/' ) === 0 ) {
                return $detected;
            }
        }
        
        return 'application/octet-stream';
    }
    
    /**
     * Send error response and exit
     * 
     * @param WP_Error $error Error object
     * @return void Exits
     */
    private static function send_error_response( $error ) {
        $status_code = $error->get_error_data()['status'] ?? 500;
        status_header( $status_code );
        
        header( 'Content-Type: application/json' );
        echo wp_json_encode( array(
            'error' => $error->get_error_code(),
            'message' => $error->get_error_message(),
        ) );
        
        exit;
    }
}