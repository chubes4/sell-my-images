<?php
/**
 * Upscaler Class - Upsampler.com API Integration
 * 
 * Handles image upscaling using Upsampler.com Precise Upscale endpoint
 * for faithful, high-fidelity upscaling up to 16x resolution
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
 * Upscaler class
 */
class Upscaler {
    
    /**
     * Upsampler API base URL
     */
    const API_BASE_URL = 'https://upsampler.com/api/v1';
    
    /**
     * Precise upscale endpoint
     */
    const PRECISE_UPSCALE_ENDPOINT = '/precise-upscale';
    
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
        // Webhook handler for Upsampler callbacks
        add_action( 'wp_ajax_smi_upsampler_webhook', array( $this, 'handle_webhook' ) );
        add_action( 'wp_ajax_nopriv_smi_upsampler_webhook', array( $this, 'handle_webhook' ) );
        
        // Custom endpoint for webhook (more secure than admin-ajax)
        add_action( 'init', array( $this, 'add_webhook_endpoint' ) );
        add_action( 'template_redirect', array( $this, 'handle_webhook_endpoint' ) );
    }
    
    /**
     * Add custom webhook endpoint
     */
    public function add_webhook_endpoint() {
        add_rewrite_rule(
            'smi-webhook/upsampler/?$',
            'index.php?smi_webhook=upsampler',
            'top'
        );
        
        // Add query var
        add_filter( 'query_vars', function( $vars ) {
            $vars[] = 'smi_webhook';
            return $vars;
        });
    }
    
    /**
     * Handle webhook endpoint requests
     */
    public function handle_webhook_endpoint() {
        $webhook_type = get_query_var( 'smi_webhook' );
        
        if ( $webhook_type === 'upsampler' ) {
            $this->handle_webhook();
            exit;
        }
    }
    
    /**
     * Upscale image using Upsampler Precise Upscale endpoint
     * 
     * @param array $image_data Image data from detector
     * @param string $resolution Resolution multiplier (2x, 4x, 8x)
     * @return array|WP_Error Result array or WP_Error on failure
     */
    public static function upscale_image( $image_data, $resolution ) {
        $api_key = get_option( 'smi_upsampler_api_key' );
        
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', __( 'Upsampler API key not configured', 'sell-my-images' ) );
        }
        
        // Validate resolution
        $upscale_factor = CostCalculator::get_upscale_factor( $resolution );
        if ( ! $upscale_factor ) {
            return new \WP_Error( 'invalid_resolution', __( 'Invalid resolution specified', 'sell-my-images' ) );
        }
        
        // Prepare image URL for API
        $image_url = self::prepare_image_url( $image_data );
        if ( is_wp_error( $image_url ) ) {
            return $image_url;
        }
        
        // Generate webhook URL
        $webhook_url = self::get_webhook_url();
        
        // Prepare API request
        $request_data = array(
            'webhook' => $webhook_url,
            'input' => array(
                'imageUrl' => $image_url,
                'upscaleFactor' => $upscale_factor,
                'globalCreativity' => 7,  // Default setting for balanced creativity
                'detail' => 8,            // High detail preservation for precise upscaling
            ),
        );
        
        // Make API request
        $response = self::make_api_request( $api_key, $request_data );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        
        return array(
            'success' => true,
            'job_id' => $response['jobId'],
            'status' => 'processing',
            'message' => __( 'Image processing started. You will be notified when complete.', 'sell-my-images' ),
        );
    }
    
    
    /**
     * Prepare image URL for API request
     * 
     * @param array $image_data Image data
     * @return string Image URL (WordPress attachments are always absolute and accessible)
     */
    private static function prepare_image_url( $image_data ) {
        // WordPress attachment URLs are already absolute and guaranteed accessible
        return $image_data['src'];
    }
    
    
    
    /**
     * Get webhook URL for Upsampler callbacks
     * 
     * @return string Webhook URL
     */
    private static function get_webhook_url() {
        return home_url( 'smi-webhook/upsampler/' );
    }
    
    /**
     * Make API request to Upsampler
     * 
     * @param string $api_key API key
     * @param array $request_data Request data
     * @return array|WP_Error Response or error
     */
    private static function make_api_request( $api_key, $request_data ) {
        $url = self::API_BASE_URL . self::PRECISE_UPSCALE_ENDPOINT;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
            'User-Agent'    => 'SellMyImages/' . SMI_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
        );
        
        $body = wp_json_encode( $request_data );
        
        $args = array(
            'method'      => 'POST',
            'headers'     => $headers,
            'body'        => $body,
            'timeout'     => 30,
            'data_format' => 'body',
        );
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'api_request_failed', __( 'Failed to connect to Upsampler API', 'sell-my-images' ) );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( $status_code !== 200 ) {
            $error_message = isset( $data['error'] ) ? $data['error'] : __( 'API request failed', 'sell-my-images' );
            return new \WP_Error( 'api_error', $error_message, array( 'status' => $status_code ) );
        }
        
        if ( ! isset( $data['jobId'] ) ) {
            return new \WP_Error( 'invalid_response', __( 'Invalid API response format', 'sell-my-images' ) );
        }
        
        return $data;
    }
    
    
    /**
     * Handle webhook callback from Upsampler
     */
    public function handle_webhook() {
        // Verify request method
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            status_header( 405 );
            exit;
        }
        
        // Basic security: verify content type
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        if ( strpos( $content_type, 'application/json' ) === false ) {
            status_header( 400 );
            exit;
        }
        
        // Verify webhook authentication using secret token
        $webhook_secret = get_option( 'smi_upsampler_webhook_secret', '' );
        if ( empty( $webhook_secret ) ) {
            error_log( 'SMI Upsampler Webhook Error: Webhook secret not configured' );
            status_header( 500 );
            exit;
        }
        
        // Check for authentication header
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
        if ( empty( $auth_header ) || $auth_header !== $webhook_secret ) {
            error_log( 'SMI Upsampler Webhook Error: Invalid authentication' );
            status_header( 401 );
            exit;
        }
        
        // Get webhook payload with size limit
        $max_payload_size = apply_filters( 'smi_max_webhook_payload_size', 1024 * 1024 ); // 1MB default
        $input = file_get_contents( 'php://input', false, null, 0, $max_payload_size );
        
        if ( $input === false ) {
            error_log( 'SMI: Failed to read webhook payload or payload too large' );
            status_header( 400 );
            exit;
        }
        
        $data = json_decode( $input, true );
        
        if ( ! $data || json_last_error() !== JSON_ERROR_NONE ) {
            status_header( 400 );
            exit;
        }
        
        // Validate required fields and format
        if ( ! isset( $data['jobId'], $data['status'] ) ) {
            status_header( 400 );
            exit;
        }
        
        // Validate jobId format (should be UUID-like)
        if ( ! preg_match( '/^[a-f0-9\-]{36}$/i', $data['jobId'] ) ) {
            status_header( 400 );
            exit;
        }
        
        // Process webhook
        $this->process_webhook_data( $data );
        
        // Send success response
        status_header( 200 );
        echo wp_json_encode( array( 'status' => 'received' ) );
        exit;
    }
    
    /**
     * Process webhook data
     * 
     * @param array $data Webhook data
     */
    private function process_webhook_data( $data ) {
        $upsampler_job_id = $data['jobId'];
        $status = $data['status'];
        
        // Get our internal job using Upsampler's job ID
        $job = JobManager::get_job_by_upsampler_id( $upsampler_job_id );
        if ( is_wp_error( $job ) ) {
            error_log( 'SMI Upscaler: Job not found for Upsampler ID: ' . $upsampler_job_id . ' - ' . $job->get_error_message() );
            return;
        }
        
        $job_id = $job->job_id;
        
        if ( $status === 'completed' && isset( $data['output']['imageUrl'] ) ) {
            // Job completed successfully - delegate to DownloadManager
            $upscaled_url = $data['output']['imageUrl'];
            
            // Use DownloadManager to handle file storage and notifications
            $local_file_path = DownloadManager::store_processed_file( $upscaled_url, $job_id );
            
            if ( $local_file_path ) {
                // Update job status to completed using JobManager
                $result = JobManager::update_job_status( $job_id, 'completed', array(
                    'upscaled_file_path' => $local_file_path
                ) );
                
                if ( is_wp_error( $result ) ) {
                    error_log( 'SMI Upscaler: Failed to update job status to completed: ' . $result->get_error_message() );
                } else {
                    error_log( 'SMI Upscaler: Job completed successfully: ' . $job_id );
                }
            } else {
                // Failed to download/store file using JobManager
                $result = JobManager::update_job_status( $job_id, 'failed', array(
                    'failure_reason' => 'Failed to download processed file from Upsampler'
                ) );
                
                if ( is_wp_error( $result ) ) {
                    error_log( 'SMI Upscaler: Failed to update job status to failed: ' . $result->get_error_message() );
                } else {
                    error_log( 'SMI Upscaler: Failed to download processed file for job: ' . $job_id );
                }
            }
            
        } elseif ( $status === 'failed' ) {
            // Job failed
            $error_message = isset( $data['error'] ) ? $data['error'] : __( 'Unknown error', 'sell-my-images' );
            
            // Update job status using JobManager
            $result = JobManager::update_job_status( $job_id, 'failed', array(
                'failure_reason' => $error_message
            ) );
            
            if ( is_wp_error( $result ) ) {
                error_log( 'SMI Upscaler: Failed to update job status to failed: ' . $result->get_error_message() );
            }
            
            error_log( 'SMI Upscaler: Job failed: ' . $job_id . ' - ' . $error_message );
        }
    }
    
    
    
    /**
     * Check if API key is valid
     * 
     * @param string $api_key API key to test
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate_api_key( $api_key ) {
        // Test API key with a minimal request
        $test_url = self::API_BASE_URL . '/account'; // Assuming account endpoint exists
        
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'User-Agent'    => 'SellMyImages/' . SMI_VERSION,
        );
        
        $response = wp_remote_get( $test_url, array(
            'headers' => $headers,
            'timeout' => 10,
        ) );
        
        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'connection_failed', __( 'Could not connect to Upsampler API', 'sell-my-images' ) );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        
        if ( $status_code === 401 ) {
            return new \WP_Error( 'invalid_api_key', __( 'Invalid API key', 'sell-my-images' ) );
        }
        
        if ( $status_code !== 200 ) {
            return new \WP_Error( 'api_error', __( 'API validation failed', 'sell-my-images' ) );
        }
        
        return true;
    }
    
}