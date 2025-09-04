<?php
/**
 * Configuration Constants
 * 
 * Centralizes all hardcoded values and system constants for easy maintenance
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Config;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Constants class
 */
class Constants {
    
    /**
     * Upsampler API Configuration
     */
    const UPSAMPLER_COST_PER_CREDIT = 0.04; // $0.04 per credit
    const UPSAMPLER_CREDITS_PER_MEGAPIXEL = 0.25; // 1 credit per 4 megapixels
    const UPSAMPLER_API_BASE_URL = 'https://upsampler.com/api/v1';
    const UPSAMPLER_PRECISE_UPSCALE_ENDPOINT = '/precise-upscale';
    
    /**
     * Pricing Configuration
     */
    const DEFAULT_MARKUP_PERCENTAGE = 500; // 500% markup (6x cost)
    const STRIPE_MINIMUM_PAYMENT = 0.50; // Stripe minimum payment requirement
    const VALID_RESOLUTIONS = array( '4x', '8x' );
    const RESOLUTION_MULTIPLIERS = array(
        '4x' => 4,
        '8x' => 8,
    );
    
    /**
     * Download Configuration
     */
    const DOWNLOAD_TOKEN_LENGTH = 64;
    const DOWNLOAD_CHUNK_SIZE = 8192; // 8KB chunks
    const DEFAULT_DOWNLOAD_EXPIRY_HOURS = 24;
    
    /**
     * Image Processing Configuration
     */
    const MIN_IMAGE_SIZE = 100; // Minimum width/height in pixels
    const EXCLUDED_IMAGE_TYPES = array( 'image/svg+xml' );
    
    /**
     * Job Management Configuration
     */
    const VALID_JOB_STATUSES = array( 'awaiting_payment', 'pending', 'processing', 'completed', 'failed', 'abandoned' );
    const VALID_PAYMENT_STATUSES = array( 'pending', 'paid', 'failed' );
    
    /**
     * Cleanup Configuration
     */
    const DEFAULT_FAILED_JOB_CLEANUP_DAYS = 7;
    const DEFAULT_ABANDONED_JOB_CLEANUP_HOURS = 24;
    
    /**
     * Webhook Configuration
     */
    const MAX_WEBHOOK_PAYLOAD_SIZE = 1048576; // 1MB
    
    /**
     * Default Plugin Options
     */
    const DEFAULT_OPTIONS = array(
        'smi_enabled'                      => '1',
        'smi_upsampler_api_key'            => '',
        'smi_stripe_test_mode'             => '1',
        'smi_stripe_test_publishable_key'  => '',
        'smi_stripe_test_secret_key'       => '',
        'smi_stripe_live_publishable_key'  => '',
        'smi_stripe_live_secret_key'       => '',
        'smi_stripe_webhook_secret'        => '',
        'smi_download_expiry_hours'        => '24',
        'smi_markup_percentage'            => '500',
        'smi_terms_conditions_url'         => '',
    );
    
    /**
     * Get upscale factor for a resolution
     * 
     * @param string $resolution Resolution string (2x, 4x, 8x)
     * @return int|false Upscale factor or false if invalid
     */
    public static function get_upscale_factor( $resolution ) {
        return self::RESOLUTION_MULTIPLIERS[ $resolution ] ?? false;
    }
    
    /**
     * Get default option value
     * 
     * @param string $option_name Option name
     * @return mixed Default value or null if not found
     */
    public static function get_default_option( $option_name ) {
        return self::DEFAULT_OPTIONS[ $option_name ] ?? null;
    }
    
    /**
     * Check if resolution is valid
     * 
     * @param string $resolution Resolution to check
     * @return bool True if valid
     */
    public static function is_valid_resolution( $resolution ) {
        return in_array( $resolution, self::VALID_RESOLUTIONS, true );
    }
    
    /**
     * Check if job status is valid
     * 
     * @param string $status Status to check
     * @return bool True if valid
     */
    public static function is_valid_job_status( $status ) {
        return in_array( $status, self::VALID_JOB_STATUSES, true );
    }
    
    /**
     * Check if payment status is valid
     * 
     * @param string $status Status to check
     * @return bool True if valid
     */
    public static function is_valid_payment_status( $status ) {
        return in_array( $status, self::VALID_PAYMENT_STATUSES, true );
    }
}