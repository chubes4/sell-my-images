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
        add_action( 'smi_payment_completed', array( $this, 'handle_payment_completed' ), 10, 1 );
        
        // Register webhook handler for Upsampler callbacks
        WebhookManager::register_webhook( 'upsampler', array( $this, 'handle_webhook' ) );
    }
    
    /**
     * Handle payment completion and start upscaling
     * 
     * @param string $job_id Job ID
     */
    public function handle_payment_completed( $job_id ) {
        error_log( 'SMI UpscalingService: Starting upscaling workflow for job: ' . $job_id );
        
        $this->start_upscaling_process( $job_id );
    }
    
    /**
     * Start upscaling process for a paid job
     * 
     * @param string $job_id Job ID
     */
    public function start_upscaling_process( $job_id ) {
        // Get job data using JobManager
        $job = JobManager::get_job( $job_id );
        
        if ( is_wp_error( $job ) ) {
            error_log( 'SMI UpscalingService: Job not found for upscaling: ' . $job_id . ' - Error: ' . $job->get_error_message() );
            return;
        }
        
        // Verify job is paid before proceeding
        if ( $job->payment_status !== 'paid' ) {
            error_log( 'SMI UpscalingService: Attempted to start upscaling for unpaid job: ' . $job_id );
            return;
        }
        
        // Get complete image data from attachment metadata
        $image_data = $this->get_image_data_for_upscaling( $job );
        
        // Generate webhook URL for this specific service
        $webhook_url = $this->get_webhook_url();
        
        // Start upscaling using injected instance
        $result = $this->upsampler->upscale_image( $image_data, $job->resolution, $webhook_url );
        
        if ( is_wp_error( $result ) ) {
            error_log( 'SMI UpscalingService: Failed to start upscaling: ' . $result->get_error_message() );
            
            // Update job status to failed using JobManager
            $update_result = JobManager::update_job_status( $job_id, 'failed' );
            
            if ( is_wp_error( $update_result ) ) {
                error_log( 'SMI UpscalingService: Failed to update job status to failed: ' . $update_result->get_error_message() );
            }
            
            return;
        }
        
        // Update job with Upsampler's job ID and set to processing
        $upsampler_job_id = $result['jobId'];
        
        $update_result = JobManager::update_job_status( $job_id, 'processing', array(
            'upsampler_job_id' => $upsampler_job_id,
            'upscaling_started_at' => current_time( 'mysql' )
        ) );
        
        if ( is_wp_error( $update_result ) ) {
            error_log( 'SMI UpscalingService: Failed to update job status to processing: ' . $update_result->get_error_message() );
        } else {
            error_log( 'SMI UpscalingService: Successfully started upscaling for job: ' . $job_id . ' (Upsampler ID: ' . $upsampler_job_id . ')' );
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
        
        $data = json_decode( $input, true );
        
        if ( ! $data || json_last_error() !== JSON_ERROR_NONE ) {
            WebhookManager::send_webhook_error( 'Invalid JSON payload', 400 );
        }
        
        // Validate required fields according to Upsampler's actual format
        if ( ! isset( $data['id'], $data['status'] ) ) {
            WebhookManager::send_webhook_error( 'Missing required fields: id, status', 400 );
        }
        
        // Validate jobId format (should be UUID-like)
        if ( ! preg_match( '/^[a-f0-9\-]{36}$/i', $data['id'] ) ) {
            WebhookManager::send_webhook_error( 'Invalid job ID format', 400 );
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
            error_log( 'SMI UpscalingService: Job not found for Upsampler ID: ' . $upsampler_job_id . ' - ' . $job->get_error_message() );
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
                error_log( 'SMI UpscalingService: Unknown status from Upsampler webhook: ' . $status );
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
            error_log( 'SMI UpscalingService: No imageUrl in completed webhook for job: ' . $job->job_id );
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
            error_log( 'SMI UpscalingService: Failed to update job to completed: ' . $update_result->get_error_message() );
        } else {
            error_log( 'SMI UpscalingService: Job completed successfully: ' . $job->job_id );
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
        
        JobManager::update_job_status( $job->job_id, 'failed', array(
            'failed_at' => current_time( 'mysql' )
        ) );
        
        error_log( 'SMI UpscalingService: Job failed: ' . $job->job_id . ' - ' . $error_message );
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