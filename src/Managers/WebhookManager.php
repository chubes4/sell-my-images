<?php
/**
 * Webhook Manager - Shared webhook utilities
 * 
 * Handles common webhook functionality to eliminate code duplication
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
 * WebhookManager class
 */
class WebhookManager {
    
    /**
     * Registered webhook handlers
     * 
     * @var array
     */
    private static $handlers = array();
    
    /**
     * Initialize webhook handling
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
        add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_webhook_requests' ) );
    }
    
    /**
     * Register a webhook handler
     * 
     * @param string $service Service name (stripe, upsampler, etc.)
     * @param callable $handler Callback function to handle the webhook
     */
    public static function register_webhook( $service, $handler ) {
        self::$handlers[ $service ] = $handler;
        
        // Add rewrite rule for this service
        add_rewrite_rule(
            "smi-webhook/{$service}/?$",
            "index.php?smi_webhook={$service}",
            'top'
        );
    }
    
    /**
     * Register all rewrite rules
     */
    public static function register_rewrite_rules() {
        // Rules are registered when webhooks are registered
        // This ensures proper order and prevents conflicts
    }
    
    /**
     * Add query variables for webhook handling
     * 
     * @param array $vars Existing query vars
     * @return array Modified query vars
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'smi_webhook';
        return $vars;
    }
    
    /**
     * Handle webhook requests
     */
    public static function handle_webhook_requests() {
        $webhook_service = get_query_var( 'smi_webhook' );
        
        if ( empty( $webhook_service ) ) {
            return;
        }
        
        // Check if we have a handler for this service
        if ( ! isset( self::$handlers[ $webhook_service ] ) ) {
            status_header( 404 );
            exit;
        }
        
        // Call the registered handler
        $handler = self::$handlers[ $webhook_service ];
        if ( is_callable( $handler ) ) {
            call_user_func( $handler );
        } else {
            error_log( "SMI WebhookManager: Invalid handler for service: {$webhook_service}" );
            status_header( 500 );
            exit;
        }
    }
    
    /**
     * Common webhook security checks
     * 
     * @param string $required_method Required HTTP method (default: POST)
     * @param string $required_content_type Required content type (optional)
     * @return bool True if security checks pass
     */
    public static function verify_webhook_security( $required_method = 'POST', $required_content_type = null ) {
        // Verify request method
        if ( $_SERVER['REQUEST_METHOD'] !== $required_method ) {
            status_header( 405 );
            exit;
        }
        
        // Verify content type if specified
        if ( $required_content_type ) {
            $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
            if ( strpos( $content_type, $required_content_type ) === false ) {
                status_header( 400 );
                exit;
            }
        }
        
        return true;
    }
    
    /**
     * Read webhook payload with size limit
     * 
     * @param int $max_size Maximum payload size in bytes (default: 1MB)
     * @return string|false Payload content or false on failure
     */
    public static function read_webhook_payload( $max_size = null ) {
        if ( $max_size === null ) {
            $max_size = apply_filters( 'smi_max_webhook_payload_size', 1024 * 1024 ); // 1MB default
        }
        
        $payload = file_get_contents( 'php://input', false, null, 0, $max_size );
        
        if ( $payload === false ) {
            error_log( 'SMI WebhookManager: Failed to read webhook payload or payload too large' );
            status_header( 400 );
            exit;
        }
        
        return $payload;
    }
    
    /**
     * Send webhook response and exit
     * 
     * @param array $data Response data
     * @param int $status_code HTTP status code (default: 200)
     */
    public static function send_webhook_response( $data = array( 'status' => 'received' ), $status_code = 200 ) {
        status_header( $status_code );
        
        if ( ! empty( $data ) ) {
            header( 'Content-Type: application/json' );
            echo wp_json_encode( $data );
        }
        
        exit;
    }
    
    /**
     * Send webhook error and exit
     * 
     * @param string $message Error message
     * @param int $status_code HTTP status code (default: 400)
     */
    public static function send_webhook_error( $message = '', $status_code = 400 ) {
        if ( ! empty( $message ) ) {
            error_log( "SMI WebhookManager Error: {$message}" );
        }
        
        status_header( $status_code );
        exit;
    }
}