<?php
/**
 * Job Manager Class
 * 
 * Centralizes all job management functionality including CRUD operations,
 * status management, validation, and lifecycle orchestration
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Managers;

use SellMyImages\Config\Constants;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * JobManager class
 */
class JobManager {
    
    /**
     * Status transitions matrix
     */
    const STATUS_TRANSITIONS = array(
        'awaiting_payment' => array( 'pending', 'processing', 'failed', 'abandoned' ),
        'pending' => array( 'processing', 'failed' ),
        'processing' => array( 'completed', 'failed' ),
        'completed' => array(), // Terminal state
        'failed' => array(), // Terminal state
        'abandoned' => array(), // Terminal state
    );
    
    /**
     * Create new job record
     * 
     * @param array $job_data Job data array
     * @return array|WP_Error Job data with job_id or error
     */
    public static function create_job( $job_data ) {
        // Validate required fields
        $validation = self::validate_job_data( $job_data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }
        
        // Sanitize job data
        $sanitized_data = self::sanitize_job_data( $job_data );
        
        // Generate unique job ID
        $job_id = wp_generate_uuid4();
        
        $insert_data = array(
            'job_id'         => $job_id,
            'image_url'      => $sanitized_data['image_url'],
            'resolution'     => $sanitized_data['resolution'],
            'email'          => $sanitized_data['email'],
            'post_id'        => $sanitized_data['post_id'],
            'status'         => 'awaiting_payment',
            'payment_status' => 'pending',
            'created_at'     => current_time( 'mysql' ),
        );
        
        // Add optional image metadata if provided
        if ( isset( $sanitized_data['attachment_id'] ) ) {
            $insert_data['attachment_id'] = $sanitized_data['attachment_id'];
        }
        
        if ( isset( $sanitized_data['image_width'] ) ) {
            $insert_data['image_width'] = $sanitized_data['image_width'];
        }
        
        if ( isset( $sanitized_data['image_height'] ) ) {
            $insert_data['image_height'] = $sanitized_data['image_height'];
        }
        
        if ( isset( $sanitized_data['source_type'] ) ) {
            $insert_data['source_type'] = $sanitized_data['source_type'];
        }
        
        if ( isset( $sanitized_data['upload_file_path'] ) ) {
            $insert_data['upload_file_path'] = $sanitized_data['upload_file_path'];
        }
        
        $result = DatabaseManager::insert( $insert_data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $job_data = array(
            'job_id' => $job_id,
            'db_id'  => $result['id'],
        );
        
        // Essential user flow log - job created
        error_log( 'SMI: Job created - ' . $job_id . ' (customer: ' . $sanitized_data['email'] . ')' );
        
        return $job_data;
    }
    
    /**
     * Get job by ID
     * 
     * @param string $job_id Job ID
     * @return object|WP_Error Job object or error
     */
    public static function get_job( $job_id ) {
        if ( empty( $job_id ) ) {
            return new \WP_Error(
                'invalid_job_id',
                __( 'Job ID is required', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $job = DatabaseManager::get_row( array( 'job_id' => $job_id ) );
        
        if ( is_wp_error( $job ) ) {
            // If it's a not found error, maintain the same error code for compatibility
            if ( $job->get_error_code() === 'record_not_found' ) {
                return new \WP_Error(
                    'job_not_found',
                    __( 'Job not found', 'sell-my-images' ),
                    array( 'status' => 404 )
                );
            }
            return $job;
        }
        
        return $job;
    }
    
    /**
     * Get job by download token
     * 
     * @param string $token Download token
     * @return object|WP_Error Job object or error
     */
    public static function get_job_by_token( $token ) {
        if ( empty( $token ) ) {
            return new \WP_Error(
                'invalid_token',
                __( 'Download token is required', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $job = DatabaseManager::get_row( array( 'download_token' => $token ) );
        
        if ( is_wp_error( $job ) ) {
            // If it's a not found error, maintain the same error code for compatibility
            if ( $job->get_error_code() === 'record_not_found' ) {
                return new \WP_Error(
                    'invalid_token',
                    __( 'Invalid or expired download token', 'sell-my-images' ),
                    array( 'status' => 404 )
                );
            }
            return $job;
        }
        
        return $job;
    }
    
    /**
     * Get job by Stripe checkout session ID
     * 
     * @param string $session_id Stripe checkout session ID
     * @return object|WP_Error Job object or error
     */
    public static function get_job_by_checkout_session( $session_id ) {
        if ( empty( $session_id ) ) {
            return new \WP_Error(
                'invalid_session_id',
                __( 'Checkout session ID is required', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $job = DatabaseManager::get_row( array( 'stripe_checkout_session_id' => $session_id ) );
        
        if ( is_wp_error( $job ) ) {
            // If it's a not found error, maintain the same error code for compatibility
            if ( $job->get_error_code() === 'record_not_found' ) {
                return new \WP_Error(
                    'job_not_found',
                    __( 'Job not found for session', 'sell-my-images' ),
                    array( 'status' => 404 )
                );
            }
            return $job;
        }
        
        return $job;
    }
    
    /**
     * Get job by Upsampler job ID
     * 
     * @param string $upsampler_job_id Upsampler's external job ID
     * @return object|WP_Error Job object or error
     */
    public static function get_job_by_upsampler_id( $upsampler_job_id ) {
        if ( empty( $upsampler_job_id ) ) {
            return new \WP_Error(
                'invalid_upsampler_job_id',
                __( 'Upsampler job ID is required', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $job = DatabaseManager::get_row( array( 'upsampler_job_id' => $upsampler_job_id ) );
        
        if ( is_wp_error( $job ) ) {
            // If it's a not found error, maintain the same error code for compatibility
            if ( $job->get_error_code() === 'record_not_found' ) {
                return new \WP_Error(
                    'job_not_found',
                    __( 'Job not found for Upsampler job ID', 'sell-my-images' ),
                    array( 'status' => 404 )
                );
            }
            return $job;
        }
        
        return $job;
    }
    
    /**
     * Update Upsampler job ID for a job
     * 
     * @param string $job_id Our internal job ID
     * @param string $upsampler_job_id Upsampler's external job ID
     * @return bool|WP_Error Success status or error
     */
    public static function update_upsampler_job_id( $job_id, $upsampler_job_id ) {
        if ( empty( $job_id ) || empty( $upsampler_job_id ) ) {
            return new \WP_Error(
                'invalid_parameters',
                __( 'Job ID and Upsampler job ID are required', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $result = DatabaseManager::update( 
            array( 'upsampler_job_id' => $upsampler_job_id ), 
            array( 'job_id' => $job_id ) 
        );
        
        if ( is_wp_error( $result ) ) {
            return new \WP_Error(
                'database_update_failed',
                __( 'Failed to update Upsampler job ID', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        return true;
    }
    
    /**
     * Update job status with validation
     * 
     * @param string $job_id Job ID
     * @param string $new_status New status
     * @param array $additional_data Additional data to update
     * @return bool|WP_Error Success status or error
     */
    public static function update_job_status( $job_id, $new_status, $additional_data = array() ) {
        // Validate new status
        if ( ! Constants::is_valid_job_status( $new_status ) ) {
            return new \WP_Error(
                'invalid_status',
                __( 'Invalid job status', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Get current job
        $job = self::get_job( $job_id );
        if ( is_wp_error( $job ) ) {
            return $job;
        }
        
        // Validate status transition
        if ( ! self::is_valid_status_transition( $job->status, $new_status ) ) {
            return new \WP_Error(
                'invalid_status_transition',
                /* translators: 1: current status, 2: new status */
                sprintf( __( 'Cannot transition from %1$s to %2$s', 'sell-my-images' ), $job->status, $new_status ),
                array( 'status' => 400 )
            );
        }
        
        // Prepare update data
        $update_data = array( 'status' => $new_status );
        
        // Add additional data if provided
        if ( ! empty( $additional_data ) ) {
            $update_data = array_merge( $update_data, $additional_data );
        }
        
        // Update job in database
        $result = self::update_job_data( $job_id, $update_data );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        
        // Trigger status change hooks
        self::handle_status_change( $job_id, $job->status, $new_status, $additional_data );
        
        return true;
    }
    
    /**
     * Update job payment status
     * 
     * @param string $job_id Job ID
     * @param string $payment_status Payment status
     * @param array $payment_data Payment metadata
     * @return bool|WP_Error Success status or error
     */
    public static function update_payment_status( $job_id, $payment_status, $payment_data = array() ) {
        // Validate payment status
        if ( ! Constants::is_valid_payment_status( $payment_status ) ) {
            return new \WP_Error(
                'invalid_payment_status',
                __( 'Invalid payment status', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Prepare update data
        $update_data = array( 'payment_status' => $payment_status );
        
        // Add payment timestamp if paid
        if ( $payment_status === 'paid' ) {
            $update_data['paid_at'] = current_time( 'mysql' );
        }
        
        // Add payment metadata
        if ( ! empty( $payment_data ) ) {
            $sanitized_payment_data = self::sanitize_payment_data( $payment_data );
            $update_data = array_merge( $update_data, $sanitized_payment_data );
        }
        
        // Update job
        $result = self::update_job_data( $job_id, $update_data );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        
        return true;
    }
    
    /**
     * Update job with cost data
     * 
     * @param string $job_id Job ID
     * @param array $cost_data Cost calculation data
     * @return bool|WP_Error Success status or error
     */
    public static function update_cost_data( $job_id, $cost_data ) {
        $sanitized_data = array();
        
        if ( isset( $cost_data['cost_usd'] ) ) {
            $sanitized_data['amount_cost'] = floatval( $cost_data['cost_usd'] );
        }
        
        if ( isset( $cost_data['credits'] ) ) {
            $sanitized_data['credits_used'] = intval( $cost_data['credits'] );
        }
        
        if ( isset( $cost_data['customer_price'] ) ) {
            $sanitized_data['amount_charged'] = floatval( $cost_data['customer_price'] );
        }
        
        if ( empty( $sanitized_data ) ) {
            return new \WP_Error(
                'invalid_cost_data',
                __( 'No valid cost data provided', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        return self::update_job_data( $job_id, $sanitized_data );
    }
    
    /**
     * Update job with Stripe checkout session data
     * 
     * @param string $job_id Job ID
     * @param string $session_id Stripe checkout session ID
     * @param string $payment_intent_id Optional payment intent ID
     * @return bool|WP_Error Success status or error
     */
    public static function update_checkout_session( $job_id, $session_id, $payment_intent_id = null ) {
        $update_data = array(
            'stripe_checkout_session_id' => sanitize_text_field( $session_id ),
        );
        
        if ( $payment_intent_id ) {
            $update_data['stripe_payment_intent_id'] = sanitize_text_field( $payment_intent_id );
        }
        
        return self::update_job_data( $job_id, $update_data );
    }
    
    /**
     * Delete job record
     * 
     * @param string $job_id Job ID
     * @return bool|WP_Error Success status or error
     */
    public static function delete_job( $job_id ) {
        // Verify job exists
        $job = self::get_job( $job_id );
        if ( is_wp_error( $job ) ) {
            return $job;
        }
        
        $result = DatabaseManager::delete( array( 'job_id' => $job_id ) );
        
        if ( is_wp_error( $result ) ) {
            return new \WP_Error(
                'job_deletion_failed',
                __( 'Failed to delete job', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        return true;
    }
    
    /**
     * Get jobs by status
     * 
     * @param string $status Job status
     * @param int $limit Optional limit
     * @return array|WP_Error Jobs array or error
     */
    public static function get_jobs_by_status( $status, $limit = null ) {
        if ( ! Constants::is_valid_job_status( $status ) ) {
            return new \WP_Error(
                'invalid_status',
                __( 'Invalid job status', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $args = array(
            'where' => array( 'status' => $status ),
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        if ( $limit ) {
            $args['limit'] = intval( $limit );
        }
        
        $jobs = DatabaseManager::get_results( $args );
        
        if ( is_wp_error( $jobs ) ) {
            return new \WP_Error(
                'database_error',
                __( 'Database error occurred', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        return $jobs;
    }
    
    
    /**
     * Validate job data
     * 
     * @param array $job_data Job data to validate
     * @return true|WP_Error True if valid, error if not
     */
    private static function validate_job_data( $job_data ) {
        // Check required fields
        $required_fields = array( 'image_url', 'resolution', 'post_id' );
        
        foreach ( $required_fields as $field ) {
            // Allow post_id to be 0 for uploads
            if ( $field === 'post_id' && isset( $job_data['post_id'] ) && $job_data['post_id'] === 0 ) {
                continue;
            }
            if ( empty( $job_data[ $field ] ) ) {
                return new \WP_Error(
                    'missing_required_field',
                    /* translators: %s: field name */
                    sprintf( __( 'Required field missing: %s', 'sell-my-images' ), $field ),
                    array( 'status' => 400 )
                );
            }
        }
        
        // Email is optional at creation; if provided, validate it
        if ( isset( $job_data['email'] ) && $job_data['email'] !== '' && ! is_email( $job_data['email'] ) ) {
            return new \WP_Error(
                'invalid_email',
                __( 'Invalid email address', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Validate resolution
        if ( ! Constants::is_valid_resolution( $job_data['resolution'] ) ) {
            return new \WP_Error(
                'invalid_resolution',
                __( 'Invalid resolution specified', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Validate image URL (unless it's an upload with a local file path)
        $is_upload = isset( $job_data['source_type'] ) && $job_data['source_type'] === 'upload';
        if ( ! $is_upload && ! filter_var( $job_data['image_url'], FILTER_VALIDATE_URL ) ) {
            return new \WP_Error(
                'invalid_image_url',
                __( 'Invalid image URL', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Validate post ID (allow 0 for uploads)
        if ( ! is_numeric( $job_data['post_id'] ) || intval( $job_data['post_id'] ) < 0 ) {
            return new \WP_Error(
                'invalid_post_id',
                __( 'Invalid post ID', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        return true;
    }
    
    /**
     * Sanitize job data
     * 
     * @param array $job_data Raw job data
     * @return array Sanitized job data
     */
    private static function sanitize_job_data( $job_data ) {
        $sanitized = array(
            'image_url'  => isset( $job_data['source_type'] ) && $job_data['source_type'] === 'upload' 
                ? sanitize_text_field( $job_data['image_url'] ) 
                : esc_url_raw( $job_data['image_url'] ),
            'resolution' => sanitize_text_field( $job_data['resolution'] ),
            'email'      => isset( $job_data['email'] ) ? sanitize_email( $job_data['email'] ) : '',
            'post_id'    => intval( $job_data['post_id'] ),
        );
        
        // Add optional image metadata if provided
        if ( isset( $job_data['attachment_id'] ) ) {
            $sanitized['attachment_id'] = intval( $job_data['attachment_id'] );
        }
        
        if ( isset( $job_data['image_width'] ) ) {
            $sanitized['image_width'] = intval( $job_data['image_width'] );
        }
        
        if ( isset( $job_data['image_height'] ) ) {
            $sanitized['image_height'] = intval( $job_data['image_height'] );
        }
        
        if ( isset( $job_data['source_type'] ) ) {
            $sanitized['source_type'] = sanitize_text_field( $job_data['source_type'] );
        }
        
        if ( isset( $job_data['upload_file_path'] ) ) {
            $sanitized['upload_file_path'] = sanitize_text_field( $job_data['upload_file_path'] );
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize payment data
     * 
     * @param array $payment_data Raw payment data
     * @return array Sanitized payment data
     */
    private static function sanitize_payment_data( $payment_data ) {
        $sanitized = array();
        
        if ( isset( $payment_data['stripe_payment_intent_id'] ) ) {
            $sanitized['stripe_payment_intent_id'] = sanitize_text_field( $payment_data['stripe_payment_intent_id'] );
        }
        
        if ( isset( $payment_data['stripe_checkout_session_id'] ) ) {
            $sanitized['stripe_checkout_session_id'] = sanitize_text_field( $payment_data['stripe_checkout_session_id'] );
        }
        
        if ( isset( $payment_data['amount_charged'] ) ) {
            $sanitized['amount_charged'] = floatval( $payment_data['amount_charged'] );
        }
        
        return $sanitized;
    }
    
    /**
     * Update job data in database
     * 
     * @param string $job_id Job ID
     * @param array $update_data Data to update
     * @return bool|WP_Error Success status or error
     */
    private static function update_job_data( $job_id, $update_data ) {
        if ( empty( $update_data ) ) {
            return new \WP_Error(
                'no_update_data',
                __( 'No data provided for update', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $result = DatabaseManager::update( $update_data, array( 'job_id' => $job_id ) );
        
        if ( is_wp_error( $result ) ) {
            return new \WP_Error(
                'job_update_failed',
                __( 'Failed to update job', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        return true;
    }
    
    /**
     * Check if status transition is valid
     * 
     * @param string $current_status Current status
     * @param string $new_status New status
     * @return bool True if transition is valid
     */
    private static function is_valid_status_transition( $current_status, $new_status ) {
        // Allow same status (idempotent updates)
        if ( $current_status === $new_status ) {
            return true;
        }
        
        // Check if transition is allowed
        if ( ! isset( self::STATUS_TRANSITIONS[ $current_status ] ) ) {
            return false;
        }
        
        return in_array( $new_status, self::STATUS_TRANSITIONS[ $current_status ], true );
    }
    
    /**
     * Handle status change events
     * 
     * @param string $job_id Job ID
     * @param string $old_status Previous status
     * @param string $new_status New status
     * @param array $additional_data Additional data from update
     */
    private static function handle_status_change( $job_id, $old_status, $new_status, $additional_data = array() ) {
        // Handle specific status transitions
        switch ( $new_status ) {
            case 'processing':
                // Job started processing
                break;
                
            case 'completed':
                // Job completed - file storage and notifications handled by UpscalingService
                break;
                
            case 'failed':
                // Job failed - detailed reason should be logged by the calling service
                break;
        }
        
        // Allow plugins to hook into status changes
        do_action( 'smi_job_status_changed', $job_id, $old_status, $new_status, $additional_data );
    }
    
}