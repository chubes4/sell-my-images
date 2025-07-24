<?php
/**
 * REST API Class
 * 
 * Handles WordPress REST API endpoints for the plugin
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
 * RestApi class
 */
class RestApi {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'smi/v1';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
    }
    
    /**
     * Register REST API endpoints
     */
    public function register_endpoints() {
        
        // Calculate pricing for all resolutions at once
        register_rest_route( self::NAMESPACE, '/calculate-all-prices', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'calculate_all_prices' ),
            'permission_callback' => '__return_true', // Public endpoint
            'args'                => array(
                'attachment_id' => array(
                    'required'    => true,
                    'type'        => 'integer',
                    'description' => 'WordPress attachment ID for Gutenberg block images',
                ),
                'post_id' => array(
                    'required'    => true,
                    'type'        => 'integer',
                    'description' => 'WordPress post ID where the image appears',
                ),
            ),
        ) );
        
        // Create checkout session (replaces old direct upscaling)
        register_rest_route( self::NAMESPACE, '/create-checkout', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_checkout' ),
            'permission_callback' => '__return_true', // Public endpoint
            'args'                => array(
                'attachment_id' => array(
                    'required'    => true,
                    'type'        => 'integer',
                    'description' => 'WordPress attachment ID for Gutenberg block images',
                ),
                'post_id' => array(
                    'required'    => true,
                    'type'        => 'integer',
                    'description' => 'WordPress post ID where the image appears',
                ),
                'resolution' => array(
                    'required'    => true,
                    'type'        => 'string',
                    'enum'        => array( '2x', '4x', '8x' ),
                    'description' => 'Resolution multiplier',
                ),
                'email'      => array(
                    'required'    => true,
                    'type'        => 'string',
                    'format'      => 'email',
                    'description' => 'Email address for notification',
                ),
            ),
        ) );
        
        
        // Download processed image with token
        register_rest_route( self::NAMESPACE, '/download/(?P<token>[a-zA-Z0-9]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'download_image' ),
            'permission_callback' => '__return_true', // Public endpoint
            'args'                => array(
                'token' => array(
                    'required'    => true,
                    'type'        => 'string',
                    'description' => 'Download token for the processed image',
                ),
            ),
        ) );
        
    }
    
    
    /**
     * Calculate pricing for all resolutions at once
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function calculate_all_prices( $request ) {
        // Check if plugin is enabled
        if ( ! get_option( 'smi_enabled', '1' ) ) {
            return new \WP_Error(
                'plugin_disabled',
                __( 'Plugin is currently disabled', 'sell-my-images' ),
                array( 'status' => 503 )
            );
        }
        
        $attachment_id = $request->get_param( 'attachment_id' );
        $post_id = $request->get_param( 'post_id' );
        
        // Require attachment ID (Gutenberg blocks only)
        if ( ! $attachment_id ) {
            return new \WP_Error(
                'missing_attachment_id',
                __( 'Attachment ID is required for Gutenberg block images', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Require post ID for analytics
        if ( ! $post_id ) {
            return new \WP_Error(
                'missing_post_id',
                __( 'Post ID is required for analytics tracking', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $image_data = $this->get_image_data_from_attachment( $attachment_id );
        if ( is_wp_error( $image_data ) ) {
            return $image_data;
        }
        
        // Calculate pricing for all resolutions
        $resolutions = array( '2x', '4x', '8x' );
        $pricing_data = array();
        
        foreach ( $resolutions as $resolution ) {
            $cost_data = CostCalculator::calculate_cost_detailed( $image_data, $resolution );
            $pricing_data[$resolution] = $cost_data;
        }
        
        return new \WP_REST_Response( array(
            'success' => true,
            'pricing' => $pricing_data,
            'image_info' => array(
                'width' => $image_data['width'],
                'height' => $image_data['height'],
            ),
        ), 200 );
    }
    
    /**
     * Create checkout session for payment
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function create_checkout( $request ) {
        // Check if plugin is enabled
        if ( ! get_option( 'smi_enabled', '1' ) ) {
            return new \WP_Error(
                'plugin_disabled',
                __( 'Plugin is currently disabled', 'sell-my-images' ),
                array( 'status' => 503 )
            );
        }
        
        // Check if Stripe is configured using PaymentService
        $payment_service = new \SellMyImages\Services\PaymentService();
        $stripe_config = $payment_service->validate_configuration();
        if ( is_wp_error( $stripe_config ) ) {
            return new \WP_Error(
                'payment_not_configured',
                __( 'Payment system not configured', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        $attachment_id = $request->get_param( 'attachment_id' );
        $post_id = $request->get_param( 'post_id' );
        $resolution = $request->get_param( 'resolution' );
        $email = $request->get_param( 'email' );
        
        // Require attachment ID (Gutenberg blocks only)
        if ( ! $attachment_id ) {
            return new \WP_Error(
                'missing_attachment_id',
                __( 'Attachment ID is required for Gutenberg block images', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Require post ID for analytics
        if ( ! $post_id ) {
            return new \WP_Error(
                'missing_post_id',
                __( 'Post ID is required for analytics tracking', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Get image data from attachment
        $image_data = $this->get_image_data_from_attachment( $attachment_id );
        if ( is_wp_error( $image_data ) ) {
            return $image_data;
        }
        
        // Create job record first using JobManager
        $job_data = JobManager::create_job( array(
            'image_url'     => $image_data['src'],
            'resolution'    => $resolution,
            'email'         => $email,
            'post_id'       => $post_id,
            'attachment_id' => $image_data['attachment_id'],
            'image_width'   => $image_data['width'],
            'image_height'  => $image_data['height'],
        ) );
        
        if ( is_wp_error( $job_data ) ) {
            return $job_data;
        }
        
        // Store cost data in job record
        $cost_data = CostCalculator::calculate_cost_detailed( $image_data, $resolution );
        JobManager::update_cost_data( $job_data['job_id'], $cost_data );
        
        // Create Stripe checkout session using PaymentService
        $checkout_result = $payment_service->create_checkout_session( 
            $image_data, 
            $resolution, 
            $email, 
            $job_data['job_id'] 
        );
        
        if ( is_wp_error( $checkout_result ) ) {
            // Clean up job record on failure
            JobManager::delete_job( $job_data['job_id'] );
            return $checkout_result;
        }
        
        // Update job with checkout session ID
        JobManager::update_checkout_session( $job_data['job_id'], $checkout_result['session_id'] );
        
        return new \WP_REST_Response( array(
            'success' => true,
            'job_id' => $job_data['job_id'],
            'checkout_url' => $checkout_result['checkout_url'],
            'amount' => $checkout_result['amount'],
            'message' => __( 'Redirecting to payment...', 'sell-my-images' ),
        ), 200 );
    }
    
    
    
    /**
     * Download processed image
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function download_image( $request ) {
        $token = $request->get_param( 'token' );
        
        // Delegate to DownloadManager for centralized download handling
        DownloadManager::serve_download( $token );
        
        // This won't be reached as serve_download exits
        exit;
    }
    
    /**
     * Get image data from WordPress attachment ID
     * 
     * @param int $attachment_id WordPress attachment ID
     * @return array|WP_Error Image data or error
     */
    private function get_image_data_from_attachment( $attachment_id ) {
        $attachment_id = intval( $attachment_id );
        
        // Verify attachment exists and is an image
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return new \WP_Error(
                'invalid_attachment',
                __( 'Invalid image attachment', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $image_meta = wp_get_attachment_metadata( $attachment_id );
        $image_url = wp_get_attachment_url( $attachment_id );
        
        if ( ! $image_meta || ! $image_url ) {
            return new \WP_Error(
                'attachment_data_error',
                __( 'Unable to get attachment data', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        return array(
            'attachment_id' => $attachment_id,
            'src'           => $image_url,
            'width'         => $image_meta['width'],
            'height'        => $image_meta['height'],
        );
    }
}