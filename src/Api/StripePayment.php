<?php
/**
 * Stripe Payment Class
 * 
 * Handles Stripe payment processing and checkout sessions
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
 * StripePayment class
 */
class StripePayment {
    
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
        // Webhook handler for Stripe callbacks
        add_action( 'init', array( $this, 'add_webhook_endpoint' ) );
        add_action( 'template_redirect', array( $this, 'handle_webhook_endpoint' ) );
        
    }
    
    /**
     * Add custom webhook endpoint for Stripe
     */
    public function add_webhook_endpoint() {
        add_rewrite_rule(
            'smi-webhook/stripe/?$',
            'index.php?smi_webhook=stripe',
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
        
        if ( $webhook_type === 'stripe' ) {
            $this->handle_webhook();
            exit;
        }
    }
    
    
    /**
     * Get Stripe API key based on test mode setting
     * 
     * @param bool $publishable Whether to get publishable key (default: secret key)
     * @return string|false API key or false if not configured
     */
    public static function get_stripe_api_key( $publishable = false ) {
        $test_mode = get_option( 'smi_stripe_test_mode', '1' );
        
        if ( $test_mode === '1' ) {
            $key = $publishable ? 
                get_option( 'smi_stripe_test_publishable_key', '' ) :
                get_option( 'smi_stripe_test_secret_key', '' );
        } else {
            $key = $publishable ? 
                get_option( 'smi_stripe_live_publishable_key', '' ) :
                get_option( 'smi_stripe_live_secret_key', '' );
        }
        
        return empty( $key ) ? false : $key;
    }
    
    /**
     * Initialize Stripe with API key
     * 
     * @return bool True if initialized successfully
     */
    private static function init_stripe() {
        $api_key = self::get_stripe_api_key();
        
        if ( ! $api_key ) {
            return false;
        }
        
        try {
            \Stripe\Stripe::setApiKey( $api_key );
            return true;
        } catch ( Exception $e ) {
            error_log( 'SMI Stripe Init Error: ' . $e->getMessage() );
            return false;
        }
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
    public static function create_checkout_session( $image_data, $resolution, $email, $job_id ) {
        if ( ! self::init_stripe() ) {
            return new \WP_Error( 'stripe_not_configured', __( 'Payment system not configured', 'sell-my-images' ) );
        }
        
        // Calculate pricing
        $cost_data = CostCalculator::calculate_cost_detailed( $image_data, $resolution );
        
        if ( $cost_data['customer_price'] <= 0 ) {
            return new \WP_Error( 'invalid_price', __( 'Unable to calculate price', 'sell-my-images' ) );
        }
        
        try {
            // Create Stripe checkout session
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => sprintf( 
                                __( 'High-Resolution Image (%s)', 'sell-my-images' ), 
                                $resolution 
                            ),
                            'description' => sprintf(
                                __( 'Upscaled to %dx%d pixels (%s MP)', 'sell-my-images' ),
                                $cost_data['output_dimensions']['width'],
                                $cost_data['output_dimensions']['height'],
                                $cost_data['output_megapixels']
                            ),
                        ],
                        'unit_amount' => $cost_data['customer_price'] * 100, // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => home_url( '/' ),
                'cancel_url' => home_url( '/' ),
                'customer_email' => $email,
                'metadata' => [
                    'job_id' => $job_id,
                    'image_url' => $image_data['src'],
                    'resolution' => $resolution,
                    'credits_used' => $cost_data['credits'],
                    'amount_cost' => $cost_data['cost_usd'],
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'job_id' => $job_id,
                        'plugin' => 'sell-my-images',
                    ],
                ],
            ]);
            
            return array(
                'success' => true,
                'checkout_url' => $session->url,
                'session_id' => $session->id,
                'amount' => $cost_data['customer_price'],
                'cost_data' => $cost_data,
            );
            
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            error_log( 'SMI Stripe Checkout Error: ' . $e->getMessage() );
            return new \WP_Error( 'stripe_error', $e->getUserMessage() );
        } catch ( Exception $e ) {
            error_log( 'SMI Payment Error: ' . $e->getMessage() );
            return new \WP_Error( 'payment_error', __( 'Payment system error', 'sell-my-images' ) );
        }
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_webhook() {
        // Verify request method
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            status_header( 405 );
            exit;
        }
        
        // Limit webhook payload size to prevent memory exhaustion
        $max_payload_size = apply_filters( 'smi_max_webhook_payload_size', 1024 * 1024 ); // 1MB default
        $payload = file_get_contents( 'php://input', false, null, 0, $max_payload_size );
        
        if ( $payload === false ) {
            error_log( 'SMI: Failed to read webhook payload or payload too large' );
            status_header( 400 );
            exit;
        }
        
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        
        try {
            if ( ! self::init_stripe() ) {
                status_header( 500 );
                exit;
            }
            
            // Verify webhook signature - REQUIRED for security
            $endpoint_secret = get_option( 'smi_stripe_webhook_secret', '' );
            
            if ( empty( $endpoint_secret ) ) {
                error_log( 'SMI Stripe Webhook Error: Webhook secret not configured' );
                status_header( 500 );
                exit;
            }
            
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
            
            // Handle the event
            $this->process_webhook_event( $event );
            
        } catch ( \UnexpectedValueException $e ) {
            error_log( 'SMI Stripe Webhook Error - Invalid payload: ' . $e->getMessage() );
            status_header( 400 );
            exit;
        } catch ( \Stripe\Exception\SignatureVerificationException $e ) {
            error_log( 'SMI Stripe Webhook Error - Invalid signature: ' . $e->getMessage() );
            status_header( 400 );
            exit;
        }
        
        status_header( 200 );
        echo wp_json_encode( array( 'status' => 'received' ) );
        exit;
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
                
            default:
                error_log( 'SMI Stripe: Unhandled event type: ' . $event['type'] );
        }
    }
    
    /**
     * Handle checkout session completed
     * 
     * @param array $session Checkout session data
     */
    private function handle_checkout_completed( $session ) {
        $job_id = $session['metadata']['job_id'] ?? null;
        
        if ( ! $job_id ) {
            error_log( 'SMI Stripe: No job_id in checkout session metadata' );
            return;
        }
        
        // Update job with payment info
        $this->update_job_payment_status( 
            $job_id, 
            'paid', 
            $session['id'], 
            $session['payment_intent'] ?? null,
            $session['amount_total'] / 100 // Convert from cents
        );
        
        // Start upscaling process
        $this->start_upscaling_after_payment( $job_id );
        
        error_log( 'SMI Stripe: Checkout completed for job: ' . $job_id );
    }
    
    /**
     * Handle payment succeeded
     * 
     * @param array $payment_intent Payment intent data
     */
    private function handle_payment_succeeded( $payment_intent ) {
        $job_id = $payment_intent['metadata']['job_id'] ?? null;
        
        if ( ! $job_id ) {
            return;
        }
        
        error_log( 'SMI Stripe: Payment succeeded for job: ' . $job_id );
    }
    
    /**
     * Handle payment failed
     * 
     * @param array $payment_intent Payment intent data
     */
    private function handle_payment_failed( $payment_intent ) {
        $job_id = $payment_intent['metadata']['job_id'] ?? null;
        
        if ( ! $job_id ) {
            return;
        }
        
        // Update job status to failed
        $this->update_job_payment_status( $job_id, 'failed' );
        
        error_log( 'SMI Stripe: Payment failed for job: ' . $job_id );
    }
    
    /**
     * Update job payment status using JobManager
     * 
     * @param string $job_id Job ID
     * @param string $payment_status Payment status
     * @param string $session_id Checkout session ID
     * @param string $payment_intent_id Payment intent ID
     * @param float $amount_charged Amount charged
     */
    private function update_job_payment_status( $job_id, $payment_status, $session_id = null, $payment_intent_id = null, $amount_charged = null ) {
        // Prepare payment data
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
        
        // Use JobManager for centralized payment status updates
        $result = JobManager::update_payment_status( $job_id, $payment_status, $payment_data );
        
        if ( is_wp_error( $result ) ) {
            error_log( 'SMI Stripe: Failed to update payment status via JobManager - Job: ' . $job_id . ' - Error: ' . $result->get_error_message() );
        }
    }
    
    /**
     * Start upscaling process after successful payment using JobManager
     * 
     * @param string $job_id Job ID
     */
    private function start_upscaling_after_payment( $job_id ) {
        // Get job data using JobManager
        $job = JobManager::get_job( $job_id );
        
        if ( is_wp_error( $job ) ) {
            error_log( 'SMI Stripe: Job not found for upscaling: ' . $job_id . ' - Error: ' . $job->get_error_message() );
            return;
        }
        
        // Get complete image data from attachment metadata
        // Extract attachment ID from job's image URL if possible, or get from stored data
        $image_data = $this->get_image_data_for_upscaling( $job );
        
        // Start upscaling
        $result = Upscaler::upscale_image( $image_data, $job->resolution );
        
        if ( is_wp_error( $result ) ) {
            error_log( 'SMI Stripe: Failed to start upscaling after payment: ' . $result->get_error_message() );
            
            // Update job status to failed using JobManager
            $update_result = JobManager::update_job_status( $job_id, 'failed', array(
                'failure_reason' => $result->get_error_message()
            ) );
            
            if ( is_wp_error( $update_result ) ) {
                error_log( 'SMI Stripe: Failed to update job status to failed: ' . $update_result->get_error_message() );
            }
        } else {
            // Store Upsampler job ID for webhook linking
            $upsampler_result = JobManager::update_upsampler_job_id( $job_id, $result['job_id'] );
            if ( is_wp_error( $upsampler_result ) ) {
                error_log( 'SMI Stripe: Failed to store Upsampler job ID: ' . $upsampler_result->get_error_message() );
            }
            
            // Update job status to processing using JobManager
            $update_result = JobManager::update_job_status( $job_id, 'processing' );
            
            if ( is_wp_error( $update_result ) ) {
                error_log( 'SMI Stripe: Failed to update job status to processing: ' . $update_result->get_error_message() );
            } else {
                error_log( 'SMI Stripe: Upscaling started for paid job: ' . $job_id . ' (Upsampler ID: ' . $result['job_id'] . ')' );
            }
        }
    }
    
    /**
     * Validate Stripe configuration
     * 
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate_configuration() {
        $secret_key = self::get_stripe_api_key();
        $publishable_key = self::get_stripe_api_key( true );
        
        if ( ! $secret_key || ! $publishable_key ) {
            return new \WP_Error( 
                'missing_keys', 
                __( 'Stripe API keys not configured', 'sell-my-images' ) 
            );
        }
        
        // Test the secret key
        try {
            \Stripe\Stripe::setApiKey( $secret_key );
            \Stripe\Account::retrieve();
            return true;
        } catch ( \Stripe\Exception\AuthenticationException $e ) {
            return new \WP_Error( 
                'invalid_secret_key', 
                __( 'Invalid Stripe secret key', 'sell-my-images' ) 
            );
        } catch ( Exception $e ) {
            return new \WP_Error( 
                'stripe_connection_error', 
                __( 'Unable to connect to Stripe', 'sell-my-images' ) 
            );
        }
    }
    
    /**
     * Get complete image data for upscaling from job record
     * 
     * @param object $job Job object from database
     * @return array Image data with dimensions from stored job data
     */
    private function get_image_data_for_upscaling( $job ) {
        // Use stored dimensions from job record (no HTTP calls needed)
        $image_data = array(
            'src' => $job->image_url,
            'unique_id' => $job->job_id,
            'width' => intval( $job->image_width ?? 0 ),
            'height' => intval( $job->image_height ?? 0 ),
        );
        
        // Add attachment_id if available
        if ( isset( $job->attachment_id ) && $job->attachment_id > 0 ) {
            $image_data['attachment_id'] = intval( $job->attachment_id );
        }
        
        // Log warning if dimensions are missing (shouldn't happen with current job creation)
        if ( $image_data['width'] === 0 || $image_data['height'] === 0 ) {
            error_log( 'SMI StripePayment: Missing image dimensions in job record ' . $job->job_id . ' - may affect upscaling quality' );
        }
        
        return $image_data;
    }
}