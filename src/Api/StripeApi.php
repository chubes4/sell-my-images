<?php
/**
 * StripeApi - Pure Stripe HTTP Client
 * 
 * Handles only Stripe API communication without business logic
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
 * StripeApi class - Pure API client
 */
class StripeApi {
    
    /**
     * Get Stripe API key based on test mode setting
     * 
     * @param bool $publishable Whether to get publishable key (default: secret key)
     * @return string|false API key or false if not configured
     */
    public static function get_api_key( $publishable = false ) {
        $test_mode = get_option( 'smi_stripe_test_mode', '1' );
        
        if ( $test_mode ) {
            return $publishable ? 
                get_option( 'smi_stripe_test_publishable_key', '' ) :
                get_option( 'smi_stripe_test_secret_key', '' );
        } else {
            return $publishable ? 
                get_option( 'smi_stripe_live_publishable_key', '' ) :
                get_option( 'smi_stripe_live_secret_key', '' );
        }
    }
    
    /**
     * Initialize Stripe SDK with API key
     * 
     * @return bool True if initialized successfully
     */
    public static function init_stripe() {
        $api_key = self::get_api_key();
        
        if ( ! $api_key ) {
            return false;
        }
        
        try {
            \Stripe\Stripe::setApiKey( $api_key );
            return true;
        } catch ( \Exception $e ) {
            return false;
        }
    }
    
    /**
     * Create Stripe checkout session
     * 
     * @param array $session_data Checkout session configuration
     * @return array|WP_Error Session data or error
     */
    public static function create_checkout_session( $session_data ) {
        if ( ! self::init_stripe() ) {
            return new \WP_Error( 'stripe_not_configured', __( 'Stripe not configured', 'sell-my-images' ) );
        }
        
        try {
            $session = \Stripe\Checkout\Session::create( $session_data );
            
            return array(
                'session_id' => $session->id,
                'checkout_url' => $session->url,
                'amount' => $session->amount_total / 100, // Convert cents to dollars
                'payment_intent' => $session->payment_intent,
                'customer_email' => $session->customer_email,
                'amount_total' => $session->amount_total,
                'currency' => $session->currency
            );
            
        } catch ( \Stripe\Exception\CardException $e ) {
            return new \WP_Error( 'stripe_card_error', $e->getUserMessage() );
        } catch ( \Stripe\Exception\RateLimitException $e ) {
            return new \WP_Error( 'stripe_rate_limit', __( 'Too many requests', 'sell-my-images' ) );
        } catch ( \Stripe\Exception\InvalidRequestException $e ) {
            return new \WP_Error( 'stripe_invalid_request', $e->getMessage() );
        } catch ( \Stripe\Exception\AuthenticationException $e ) {
            return new \WP_Error( 'stripe_auth_error', __( 'Authentication failed', 'sell-my-images' ) );
        } catch ( \Stripe\Exception\ApiConnectionException $e ) {
            return new \WP_Error( 'stripe_connection_error', __( 'Network error', 'sell-my-images' ) );
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            return new \WP_Error( 'stripe_api_error', $e->getMessage() );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'stripe_error', __( 'Payment system error', 'sell-my-images' ) );
        }
    }
    
    /**
     * Construct webhook event from payload and signature
     * 
     * @param string $payload Webhook payload
     * @param string $signature Stripe signature header
     * @param string $endpoint_secret Webhook endpoint secret
     * @return array|WP_Error Event data or error
     */
    public static function construct_webhook_event( $payload, $signature, $endpoint_secret ) {
        if ( ! self::init_stripe() ) {
            return new \WP_Error( 'stripe_not_configured', __( 'Stripe not configured', 'sell-my-images' ) );
        }
        
        try {
            $event = \Stripe\Webhook::constructEvent( $payload, $signature, $endpoint_secret );
            return $event;
        } catch ( \UnexpectedValueException $e ) {
            return new \WP_Error( 'invalid_payload', 'Invalid webhook payload: ' . $e->getMessage() );
        } catch ( \Stripe\Exception\SignatureVerificationException $e ) {
            return new \WP_Error( 'invalid_signature', 'Invalid webhook signature: ' . $e->getMessage() );
        }
    }
    
    /**
     * Create refund for a payment intent
     * 
     * @param string $payment_intent_id Payment intent ID
     * @param string $reason Refund reason
     * @return array|WP_Error Refund data or error
     */
    public static function create_refund( $payment_intent_id, $reason = '' ) {
        if ( ! self::init_stripe() ) {
            return new \WP_Error( 'stripe_not_configured', __( 'Stripe not configured', 'sell-my-images' ) );
        }
        
        try {
            $refund = \Stripe\Refund::create( array(
                'payment_intent' => $payment_intent_id,
                'reason' => 'requested_by_customer',
                'metadata' => array(
                    'reason' => $reason,
                    'plugin' => 'sell-my-images'
                )
            ) );
            
            return array(
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100, // Convert cents to dollars
                'status' => $refund->status,
                'reason' => $reason
            );
            
        } catch ( \Stripe\Exception\CardException $e ) {
            return new \WP_Error( 'stripe_card_error', $e->getUserMessage() );
        } catch ( \Stripe\Exception\RateLimitException $e ) {
            return new \WP_Error( 'stripe_rate_limit', __( 'Too many requests', 'sell-my-images' ) );
        } catch ( \Stripe\Exception\InvalidRequestException $e ) {
            return new \WP_Error( 'stripe_invalid_request', $e->getMessage() );
        } catch ( \Stripe\Exception\AuthenticationException $e ) {
            return new \WP_Error( 'stripe_auth_error', __( 'Authentication failed', 'sell-my-images' ) );
        } catch ( \Stripe\Exception\ApiConnectionException $e ) {
            return new \WP_Error( 'stripe_connection_error', __( 'Network error', 'sell-my-images' ) );
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            return new \WP_Error( 'stripe_api_error', $e->getMessage() );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'stripe_error', __( 'Refund system error', 'sell-my-images' ) );
        }
    }

    /**
     * Validate Stripe configuration
     * 
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate_configuration() {
        $secret_key = self::get_api_key();
        $publishable_key = self::get_api_key( true );
        
        if ( ! $secret_key || ! $publishable_key ) {
            return new \WP_Error( 
                'missing_keys', 
                __( 'Stripe API keys not configured', 'sell-my-images' ) 
            );
        }
        
        // Test the secret key by making a simple API call
        try {
            \Stripe\Stripe::setApiKey( $secret_key );
            \Stripe\Account::retrieve();
            return true;
        } catch ( \Stripe\Exception\AuthenticationException $e ) {
            return new \WP_Error( 
                'invalid_secret_key', 
                __( 'Invalid Stripe secret key', 'sell-my-images' ) 
            );
        } catch ( \Exception $e ) {
            return new \WP_Error( 
                'stripe_connection_error', 
                __( 'Could not connect to Stripe', 'sell-my-images' ) 
            );
        }
    }
}