<?php
/**
 * Upsampler - Pure Upsampler.com API Client
 * 
 * Handles only Upsampler.com API communication without business logic
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Api;

use SellMyImages\Config\Constants;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Upsampler class - Pure API client
 */
class Upsampler {
    
    /**
     * Get API key from WordPress options
     * 
     * @return string|false API key or false if not configured
     */
    public static function get_api_key() {
        return get_option( 'smi_upsampler_api_key', '' );
    }
    
    /**
     * Upscale image using Upsampler Precise Upscale endpoint
     * 
     * @param array $image_data Image data including URL and dimensions
     * @param string $resolution Resolution multiplier (2x, 4x, 8x)
     * @param string $webhook_url Webhook URL for completion notification
     * @return array|WP_Error Result array with jobId or WP_Error on failure
     */
    public static function upscale_image( $image_data, $resolution, $webhook_url = null ) {
        $api_key = self::get_api_key();
        
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', __( 'Upsampler API key not configured', 'sell-my-images' ) );
        }
        
        // Validate resolution and get upscale factor
        $upscale_factor = Constants::get_upscale_factor( $resolution );
        if ( ! $upscale_factor ) {
            return new \WP_Error( 'invalid_resolution', __( 'Invalid resolution specified', 'sell-my-images' ) );
        }
        
        // Prepare image URL for API
        $image_url = self::prepare_image_url( $image_data );
        if ( is_wp_error( $image_url ) ) {
            return $image_url;
        }
        
        // Use provided webhook URL or generate default
        if ( ! $webhook_url ) {
            $webhook_url = self::get_webhook_url();
        }
        
        // Prepare API request
        $request_data = array(
            'webhook' => $webhook_url,
            'input' => array(
                'imageUrl' => $image_url,
                'inputImageType' => 'universal',
                'upscaleFactor' => $upscale_factor,
                'globalCreativity' => 7,  // Default setting for balanced creativity
                'detail' => 8,            // High detail preservation for precise upscaling
            ),
        );
        
        // Make API request
        return self::make_api_request( Constants::UPSAMPLER_PRECISE_UPSCALE_ENDPOINT, $request_data );
    }
    
    
    /**
     * Make API request to Upsampler
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|WP_Error Response data or error
     */
    private static function make_api_request( $endpoint, $data ) {
        $api_key = self::get_api_key();
        $url = Constants::UPSAMPLER_API_BASE_URL . $endpoint;
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'SMI Upsampler: Making API request to: ' . $url );
            error_log( 'SMI Upsampler: Request data: ' . wp_json_encode( $data ) );
        }
        
        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( $data ),
            'timeout' => 60,
        ) );
        
        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'api_request_failed', __( 'Failed to connect to Upsampler API', 'sell-my-images' ) );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'SMI Upsampler: Response status: ' . $status_code );
            error_log( 'SMI Upsampler: Response body: ' . $body );
        }
        
        $data = json_decode( $body, true );
        
        if ( $status_code !== 200 ) {
            $error_message = isset( $data['error'] ) ? $data['error'] : 'Unknown API error';
            // Ensure error message is always a string
            if ( is_array( $error_message ) ) {
                $error_message = wp_json_encode( $error_message );
            }
            return new \WP_Error( 'api_error', $error_message, array( 'status' => $status_code ) );
        }
        
        if ( ! $data || json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'invalid_response', __( 'Invalid API response format', 'sell-my-images' ) );
        }
        
        // Fix: Upsampler returns 'id', not 'jobId'
        if ( ! isset( $data['id'] ) ) {
            return new \WP_Error( 'invalid_response', __( 'Invalid API response format - missing job ID', 'sell-my-images' ) );
        }
        
        return $data;
    }
    
    /**
     * Prepare image URL for API consumption
     * 
     * @param array $image_data Image data array
     * @return string|WP_Error Image URL or error
     */
    private static function prepare_image_url( $image_data ) {
        // Use the URL from image data
        $image_url = $image_data['url'] ?? $image_data['src'] ?? '';
        
        if ( empty( $image_url ) ) {
            return new \WP_Error( 'no_image_url', __( 'No image URL provided', 'sell-my-images' ) );
        }
        
        // Ensure the URL is accessible
        if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
            return new \WP_Error( 'invalid_image_url', __( 'Invalid image URL', 'sell-my-images' ) );
        }
        
        return $image_url;
    }
    
    /**
     * Get webhook URL for Upsampler callbacks
     * 
     * @return string Webhook URL
     */
    private static function get_webhook_url() {
        return home_url( '/smi-webhook/upsampler/' );
    }
}