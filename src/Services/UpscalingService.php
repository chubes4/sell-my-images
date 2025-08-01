<?php
/**
 * Upscaling Service - Handles upscaling workflow
 * 
 * Business logic for managing upscaling jobs and coordination
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Services;

use SellMyImages\Api\Upsampler;
use SellMyImages\Managers\JobManager;
use SellMyImages\Managers\WebhookManager;
use SellMyImages\Managers\DownloadManager;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * UpscalingService class
 */
class UpscalingService {
    
    /**
     * Upsampler instance
     * 
     * @var Upsampler
     */
    private $upsampler;
    
    /**
     * Constructor
     * 
     * @param Upsampler $upsampler Upsampler instance (optional, for dependency injection)
     */
    public function __construct( $upsampler = null ) {
        $this->upsampler = $upsampler ?: new Upsampler();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Listen for payment completion to start upscaling
        add_action( 'smi_payment_completed', array( $this, 'handle_payment_completed' ), 10, 2 );
        
        // Register webhook handler for Upsampler callbacks
        WebhookManager::register_webhook( 'upsampler', array( $this, 'handle_webhook' ) );
    }
    
    /**
     * Handle payment completion and start upscaling
     * 
     * @param string $job_id Job ID
     * @param array $context Context data (e.g., admin_override)
     */
    public function handle_payment_completed( $job_id, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'SMI UpscalingService: Starting upscaling workflow for job: ' . $job_id  );
        }
        
        $this->start_upscaling_process( $job_id, $context );
    }
    
    /**
     * Start upscaling process for a paid job
     * 
     * @param string $job_id Job ID
     * @param array $context Context data (e.g., admin_override)
     */
    public function start_upscaling_process( $job_id, $context = array() ) {
        // Get job data using JobManager
        $job = JobManager::get_job( $job_id );
        
        if ( is_wp_error( $job ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Job not found for upscaling: ' . $job_id . ' - Error: ' . $job->get_error_message()  );
            }
            return;
        }
        
        // Verify job is paid before proceeding (allow admin override)
        $is_admin_override = ! empty( $context['admin_override'] );
        if ( $job->payment_status !== 'paid' && ! $is_admin_override ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Attempted to start upscaling for unpaid job: ' . $job_id  );
            }
            return;
        }
        
        // Log admin override for audit trail
        if ( $is_admin_override ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Admin override - processing job with payment_status: ' . $job->payment_status . ' for job: ' . $job_id );
            }
        }
        
        // Get complete image data from attachment metadata
        $image_data = $this->get_image_data_for_upscaling( $job );
        
        // Generate webhook URL for this specific service
        $webhook_url = $this->get_webhook_url();
        
        // Start upscaling using injected instance
        $result = $this->upsampler->upscale_image( $image_data, $job->resolution, $webhook_url );
        
        if ( is_wp_error( $result ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Failed to start upscaling: ' . $result->get_error_message()  );
            }
            JobManager::update_job_status( $job_id, 'failed' );
            return;
        }
        
        // Store Upsampler job ID (they return 'id', not 'jobId')
        $upsampler_job_id = $result['id'];
        
        // Update job with Upsampler job ID and processing status
        $update_result = JobManager::update_job_status( $job_id, 'processing', array(
            'upsampler_job_id' => $upsampler_job_id,
            'processing_started_at' => current_time( 'mysql' )
        ) );
        
        if ( is_wp_error( $update_result ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Failed to update job status to processing: ' . $update_result->get_error_message()  );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Successfully started upscaling for job: ' . $job_id . ' (Upsampler ID: ' . $upsampler_job_id . ')'  );
            }
        }
    }
    
    /**
     * Handle Upsampler webhook (called by WebhookManager)
     */
    public function handle_webhook() {
        // Use shared webhook security checks (POST method, JSON content-type)
        WebhookManager::verify_webhook_security( 'POST', 'application/json' );
        
        // Get webhook payload using shared method
        $input = WebhookManager::read_webhook_payload();
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'SMI UpscalingService: Raw webhook payload: ' . $input );
        }
        
        $data = json_decode( $input, true );
        
        if ( ! $data || json_last_error() !== JSON_ERROR_NONE ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: JSON decode error: ' . json_last_error_msg() );
            }
            WebhookManager::send_webhook_error( 'Invalid JSON payload', 400 );
        }
        
        // Validate required fields according to Upsampler's actual format
        if ( ! isset( $data['id'], $data['status'] ) ) {
            WebhookManager::send_webhook_error( 'Missing required fields: id, status', 400 );
        }
        
        // Process webhook
        $this->handle_upsampler_webhook( $data );
        
        // Send success response using shared method
        WebhookManager::send_webhook_response();
    }
    
    /**
     * Handle Upsampler webhook callbacks
     * 
     * @param array $webhook_data Webhook data from Upsampler
     */
    private function handle_upsampler_webhook( $webhook_data ) {
        $upsampler_job_id = $webhook_data['id'];
        $status = $webhook_data['status'];
        
        // Get our internal job using Upsampler's job ID
        $job = JobManager::get_job_by_upsampler_id( $upsampler_job_id );
        if ( is_wp_error( $job ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Job not found for Upsampler ID: ' . $upsampler_job_id . ' - ' . $job->get_error_message()  );
            }
            return;
        }
        
        // Process based on Upsampler's actual status values
        switch ( $status ) {
            case 'SUCCESS':
                $this->handle_upscaling_completed( $job, $webhook_data );
                break;
                
            case 'FAILED':
                $this->handle_upscaling_failed( $job, $webhook_data );
                break;
                
            default:
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'SMI UpscalingService: Unknown status from Upsampler webhook: ' . $status  );
                }
        }
    }
    
    /**
     * Handle completed upscaling
     * 
     * @param object $job Job object
     * @param array $webhook_data Webhook data
     */
    private function handle_upscaling_completed( $job, $webhook_data ) {
        // Use imageUrl (full quality PNG) from Upsampler's webhook payload
        $upscaled_url = $webhook_data['imageUrl'] ?? null;
        
        if ( ! $upscaled_url ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: No imageUrl in completed webhook for job: ' . $job->job_id  );
            }
            JobManager::update_job_status( $job->job_id, 'failed' );
            return;
        }
        
        // Prepare update data with Upsampler credit cost if available
        $update_data = array(
            'upscaled_url' => $upscaled_url,
            'completed_at' => current_time( 'mysql' )
        );
        
        // Store credit cost for analytics if provided
        if ( isset( $webhook_data['creditCost'] ) ) {
            $update_data['credits_used'] = intval( $webhook_data['creditCost'] );
        }
        
        // Update job with completed status and data
        $update_result = JobManager::update_job_status( $job->job_id, 'completed', $update_data );
        
        if ( is_wp_error( $update_result ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Failed to update job to completed: ' . $update_result->get_error_message()  );
            }
            return;
        }
        
        // Download and store the upscaled file locally
        $local_path = \SellMyImages\Managers\DownloadManager::store_processed_file( $upscaled_url, $job->job_id );
        
        if ( $local_path ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: File stored successfully for job: ' . $job->job_id . ' at: ' . $local_path  );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Failed to store file for job: ' . $job->job_id  );
            }
            // Don't fail the job completely, but log the issue
            // The user can still download from the original URL if needed
        }
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Job completed successfully: ' . $job->job_id  );
        }
    }
    
    /**
     * Handle failed upscaling
     * 
     * @param object $job Job object
     * @param array $webhook_data Webhook data
     */
    private function handle_upscaling_failed( $job, $webhook_data ) {
        $error_message = $webhook_data['error'] ?? 'Unknown upscaling error';
        
        // Update job status to failed
        JobManager::update_job_status( $job->job_id, 'failed', array(
            'failed_at' => current_time( 'mysql' )
        ) );
        
        // Process automatic refund for failed upscaling
        $this->process_automatic_refund( $job, $error_message );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'SMI UpscalingService: Job failed: ' . $job->job_id . ' - ' . $error_message  );
        }
        }
    
    /**
     * Process automatic refund for failed jobs
     * 
     * @param object $job Job object
     * @param string $error_message Error message
     */
    private function process_automatic_refund( $job, $error_message ) {
        // Only refund if payment was successful
        if ( $job->payment_status !== 'paid' ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Skipping refund for unpaid job: ' . $job->job_id );
            }
            return;
        }
        
        // Only refund if we have a Stripe payment intent
        if ( empty( $job->stripe_payment_intent_id ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: No payment intent found for refund: ' . $job->job_id );
            }
            return;
        }
        
        try {
            // Use StripeApi to process refund
            $stripe_api = new \SellMyImages\Api\StripeApi();
            $refund_result = $stripe_api->create_refund( $job->stripe_payment_intent_id, $error_message );
            
            if ( is_wp_error( $refund_result ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'SMI UpscalingService: Refund failed: ' . $refund_result->get_error_message() );
                }
            } else {
                // Update job with refund information
                JobManager::update_job_status( $job->job_id, 'refunded', array(
                    'refunded_at' => current_time( 'mysql' ),
                    'refund_reason' => $error_message,
                    'refund_amount' => $job->amount_charged
                ) );
                
                // Send refund notification email
                $this->send_refund_notification( $job, $error_message );
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'SMI UpscalingService: Automatic refund processed for job: ' . $job->job_id );
                }
            }
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI UpscalingService: Exception during refund: ' . $e->getMessage() );
            }
        }
    }
    
    /**
     * Send refund notification email
     * 
     * @param object $job Job object
     * @param string $error_message Error message
     * @return bool Success status
     */
    private static function send_refund_notification( $job, $error_message ) {
        $subject = sprintf( 
            /* translators: %s: site name */
            __( 'Refund Processed - %s', 'sell-my-images' ),
            get_bloginfo( 'name' )
        );
        
        $message = sprintf(
            /* translators: 1: customer name or email, 2: amount, 3: error message, 4: site name, 5: support email */
            __( 'Dear %1$s,

We regret to inform you that we were unable to process your image upscaling request. Your payment of $%2$s has been automatically refunded to your original payment method.

Reason for failure: %3$s

The refund will appear in your account within 3-5 business days, depending on your bank or credit card provider.

If you have any questions about this refund, please contact us at %5$s.

Thank you for your understanding.

Best regards,
Sarai Chinwag
%4$s Team', 'sell-my-images' ),
            $job->email,
            number_format( $job->amount_charged, 2 ),
            $error_message,
            get_bloginfo( 'name' ),
            get_option( 'admin_email' )
        );
        
        // Set custom headers for "Sarai Chinwag" sender
        $headers = array(
            'From: Sarai Chinwag <' . get_option( 'admin_email' ) . '>',
            'Reply-To: ' . get_option( 'admin_email' ),
            'Content-Type: text/html; charset=UTF-8'
        );
        
        // Send to customer
        $email_sent = wp_mail( $job->email, $subject, $message, $headers );
        
        // Send copy to admin
        $admin_email = get_option( 'admin_email' );
        if ( $admin_email && $admin_email !== $job->email ) {
            $admin_subject = 'Copy: ' . $subject;
            $admin_message = $message;
            wp_mail( $admin_email, $admin_subject, $admin_message, $headers );
        }
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( $email_sent ) {
                error_log( 'SMI UpscalingService: Refund notification email sent for job: ' . $job->job_id );
            } else {
                error_log( 'SMI UpscalingService: Failed to send refund notification email for job: ' . $job->job_id );
            }
        }
        
        return $email_sent;
    }
    
    /**
     * Get image data for upscaling from job
     * 
     * @param object $job Job object
     * @return array Image data array
     */
    private function get_image_data_for_upscaling( $job ) {
        // Try to get attachment data if we have attachment_id
        if ( ! empty( $job->attachment_id ) ) {
            $attachment_data = wp_get_attachment_metadata( $job->attachment_id );
            $attachment_url = wp_get_attachment_url( $job->attachment_id );
            
            if ( $attachment_data && $attachment_url ) {
                return array(
                    'url' => $attachment_url,
                    'width' => $attachment_data['width'] ?? 0,
                    'height' => $attachment_data['height'] ?? 0,
                    'attachment_id' => $job->attachment_id
                );
            }
        }
        
        // Fallback to job data
        return array(
            'url' => $job->image_url,
            'width' => $job->image_width ?? 0,
            'height' => $job->image_height ?? 0,
            'attachment_id' => $job->attachment_id ?? null
        );
    }
    
    /**
     * Get webhook URL for Upsampler callbacks
     * 
     * @return string Webhook URL
     */
    private function get_webhook_url() {
        return home_url( '/smi-webhook/upsampler/' );
    }
}