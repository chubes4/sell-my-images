<?php
/**
 * Payment Service - Handles payment workflow
 * 
 * Business logic for managing payments and coordination
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Services;

use SellMyImages\Api\StripeApi;
use SellMyImages\Managers\JobManager;
use SellMyImages\Api\CostCalculator;
use SellMyImages\Managers\WebhookManager;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PaymentService class
 */
class PaymentService {
    
    /**
     * StripeApi instance
     * 
     * @var StripeApi
     */
    private $stripe_api;
    
    /**
     * Constructor
     * 
     * @param StripeApi $stripe_api StripeApi instance (optional, for dependency injection)
     */
    public function __construct( $stripe_api = null ) {
        $this->stripe_api = $stripe_api ?: new StripeApi();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Register webhook handler with shared WebhookManager
        WebhookManager::register_webhook( 'stripe', array( $this, 'handle_webhook' ) );
    }
    
    /**
     * Create checkout session for image upscaling
     * 
     * @param array $image_data Image data including dimensions
     * @param string $resolution Resolution multiplier
     * @param string $email Customer email
     * @param string $job_id Job ID for tracking
     * @return array|WP_Error Checkout session data or error
     */
    public function create_checkout_session( $image_data, $resolution, $email, $job_id ) {
        // Calculate pricing
        $cost_data = CostCalculator::calculate_cost_detailed( $image_data, $resolution );
        
        if ( is_wp_error( $cost_data ) ) {
            return new \WP_Error( 'invalid_price', __( 'Unable to calculate price', 'sell-my-images' ) );
        }
        
        // Prepare Stripe session data
    $session_data = array(
            'payment_method_types' => ['card'],
            'line_items' => array(
                array(
                    'price_data' => array(
                        'currency' => 'usd',
                        'product_data' => array(
                            'name' => sprintf(
                                /* translators: %s: resolution multiplier (2x, 4x, 8x) */
                                __( 'High-Resolution Image (%s)', 'sell-my-images' ),
                                $resolution
                            ),
                            'description' => sprintf(
                                /* translators: 1: original width, 2: original height, 3: new width, 4: new height */
                                __( 'Upscaled from %1$dx%2$d to %3$dx%4$d pixels', 'sell-my-images' ),
                                $image_data['width'],
                                $image_data['height'],
                                $cost_data['output_dimensions']['width'],
                                $cost_data['output_dimensions']['height']
                            ),
                        ),
                        'unit_amount' => intval( $cost_data['customer_price'] * 100 ), // Convert to cents
                    ),
                    'quantity' => 1,
                ),
            ),
            'mode' => 'payment',
            // Let Stripe collect the email; do not force customer_email here
            'success_url' => $this->get_current_page_url() . '?smi_payment=success&session_id={CHECKOUT_SESSION_ID}&job_id=' . $job_id,
            'cancel_url' => $this->get_current_page_url() . '?smi_payment=cancelled&job_id=' . $job_id,
            'metadata' => array(
                'job_id' => $job_id,
                'resolution' => $resolution,
                'source' => 'sell-my-images',
            ),
        );
        
        // Create session using StripeApi
        $result = $this->stripe_api->create_checkout_session( $session_data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        
        return $result;
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_webhook() {
        // Use shared webhook security checks
        WebhookManager::verify_webhook_security( 'POST' );
        
        // Get webhook payload using shared method
        $payload = WebhookManager::read_webhook_payload();
        
        $sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';
        
        // Get webhook secret
        $endpoint_secret = get_option( 'smi_stripe_webhook_secret', '' );
        
        if ( empty( $endpoint_secret ) ) {
            WebhookManager::send_webhook_error( 'SMI PaymentService: Webhook secret not configured', 500 );
        }
        
        // Construct event using StripeApi
        $event = $this->stripe_api->construct_webhook_event( $payload, $sig_header, $endpoint_secret );
        
        if ( is_wp_error( $event ) ) {
            WebhookManager::send_webhook_error( 'SMI PaymentService: ' . $event->get_error_message(), 400 );
        }
        
        // Handle the event
        $this->process_webhook_event( $event );
        
        WebhookManager::send_webhook_response();
    }
    
    /**
     * Process Stripe webhook event
     * 
     * @param array $event Stripe event data
     */
    private function process_webhook_event( $event ) {
        switch ( $event['type'] ) {
            case 'checkout.session.completed':
                $this->handle_checkout_completed( $event['data']['object'] );
                break;
                
            case 'payment_intent.succeeded':
                $this->handle_payment_succeeded( $event['data']['object'] );
                break;
                
            case 'payment_intent.payment_failed':
                $this->handle_payment_failed( $event['data']['object'] );
                break;
                
            case 'charge.refunded':
                $this->handle_charge_refunded( $event['data']['object'] );
                break;
                
            case 'checkout.session.expired':
                $this->handle_checkout_expired( $event['data']['object'] );
                break;
                
            default:
                // Unhandled event type
        }
    }
    
    /**
     * Handle checkout completed event
     * 
     * @param array $session Stripe session data
     */
    private function handle_checkout_completed( $session ) {
        $job_id = $session['metadata']['job_id'] ?? null;
        
        if ( ! $job_id ) {
            return;
        }
        
        // Update job payment status
        $result = $this->update_job_payment_status(
            $job_id,
            'paid',
            $session['id'],
            $session['payment_intent'] ?? null,
            $session['amount_total'] / 100 // Convert from cents
        );
        
        if ( is_wp_error( $result ) ) {
            return;
        }
        
        // Backfill customer email from Stripe if available and job missing email
        $stripe_email = null;
        if ( isset( $session['customer_details']['email'] ) && ! empty( $session['customer_details']['email'] ) ) {
            $stripe_email = sanitize_email( $session['customer_details']['email'] );
        } elseif ( isset( $session['customer_email'] ) && ! empty( $session['customer_email'] ) ) {
            $stripe_email = sanitize_email( $session['customer_email'] );
        }

        if ( $stripe_email ) {
            $job = JobManager::get_job( $job_id );
            if ( ! is_wp_error( $job ) && ( empty( $job->email ) || ! is_email( $job->email ) ) ) {
                JobManager::update_job_status( $job_id, $job->status, array( 'email' => $stripe_email ) );
            }
        }

        // Move job from 'awaiting_payment' to 'pending' now that payment is complete
        JobManager::update_job_status( $job_id, 'pending', array(
            'paid_at' => current_time( 'mysql' )
        ) );
        
        // Essential user flow log - payment completed
        error_log( 'SMI: Payment completed for job ' . $job_id );
        
        // Trigger upscaling via action (separation of concerns)
        do_action( 'smi_payment_completed', $job_id );
        
    }
    
    /**
     * Handle payment succeeded event
     * 
     * @param array $payment_intent Payment intent data
     */
    private function handle_payment_succeeded( $payment_intent ) {
        // Extract job_id from metadata if available
        $job_id = $payment_intent['metadata']['job_id'] ?? null;
        
        if ( $job_id ) {
            $this->update_job_payment_status( $job_id, 'paid', null, $payment_intent['id'] );
        }
    }
    
    /**
     * Handle payment failed event
     * 
     * @param array $payment_intent Payment intent data
     */
    private function handle_payment_failed( $payment_intent ) {
        // Extract job_id from metadata if available
        $job_id = $payment_intent['metadata']['job_id'] ?? null;
        
        if ( $job_id ) {
            $failure_reason = $payment_intent['last_payment_error']['message'] ?? 'Payment failed';
            
            $this->update_job_payment_status( $job_id, 'failed' );
            
            // Also update job status to failed
            JobManager::update_job_status( $job_id, 'failed' );
        }
    }
    
    /**
     * Handle charge refunded event
     * 
     * @param array $charge Stripe charge data
     */
    private function handle_charge_refunded( $charge ) {
        $job_id = $charge['metadata']['job_id'] ?? null;
        
        if ( $job_id ) {
            $this->update_job_payment_status( $job_id, 'refunded', null, null, $charge['amount'] / 100 );
            
            // Also update job status to refunded
            JobManager::update_job_status( $job_id, 'refunded' );
        }
    }
    
    /**
     * Handle checkout session expired event
     * 
     * @param array $session Stripe session data
     */
    private function handle_checkout_expired( $session ) {
        $job_id = $session['metadata']['job_id'] ?? null;
        
        if ( $job_id ) {
            // Update job status to abandoned
            JobManager::update_job_status( $job_id, 'abandoned' );
            
            // Essential user flow log - checkout abandoned
            error_log( 'SMI: Checkout session expired for job ' . $job_id );
        }
    }
    
    /**
     * Update job payment status in database
     * 
     * @param string $job_id Job ID
     * @param string $payment_status Payment status
     * @param string $session_id Session ID (optional)
     * @param string $payment_intent_id Payment intent ID (optional)
     * @param float $amount_charged Amount charged (optional)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function update_job_payment_status( $job_id, $payment_status, $session_id = null, $payment_intent_id = null, $amount_charged = null ) {
        $payment_data = array();
        
        if ( $session_id ) {
            $payment_data['stripe_checkout_session_id'] = $session_id;
        }
        
        if ( $payment_intent_id ) {
            $payment_data['stripe_payment_intent_id'] = $payment_intent_id;
        }
        
        if ( $amount_charged ) {
            $payment_data['amount_charged'] = $amount_charged;
        }
        
        $result = JobManager::update_payment_status( $job_id, $payment_status, $payment_data );
        
        if ( is_wp_error( $result ) ) {
            // Payment status update failed
        }
        
        return $result;
    }
    
    /**
     * Get current page URL for redirect
     * 
     * @return string Current page URL
     */
    private function get_current_page_url() {
        // Try to get from HTTP_REFERER first (most reliable for checkout flow)
        if ( isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            $referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
            // Make sure it's from the same domain
            if ( strpos( $referer, home_url() ) === 0 ) {
                return $referer;
            }
        }
        
        // Fallback to current page
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        
        return $protocol . $host . $uri;
    }
    
    /**
     * Validate payment configuration
     * 
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_configuration() {
        return $this->stripe_api->validate_configuration();
    }
}