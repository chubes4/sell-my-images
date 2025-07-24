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

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * JobManager class
 */
class JobManager {
    
    /**
     * Valid job statuses
     */
    const VALID_STATUSES = array( 'pending', 'processing', 'completed', 'failed' );
    
    /**
     * Valid payment statuses
     */
    const VALID_PAYMENT_STATUSES = array( 'pending', 'paid', 'failed' );
    
    /**
     * Valid resolutions
     */
    const VALID_RESOLUTIONS = array( '2x', '4x', '8x' );
    
    /**
     * Status transitions matrix
     */
    const STATUS_TRANSITIONS = array(
        'pending' => array( 'processing', 'failed' ),
        'processing' => array( 'completed', 'failed' ),
        'completed' => array(), // Terminal state
        'failed' => array(), // Terminal state
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
            'status'         => 'pending',
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
        
        $result = DatabaseManager::insert( $insert_data );
        
        if ( is_wp_error( $result ) ) {
            error_log( 'SMI JobManager: Failed to create job - ' . $result->get_error_message() );
            return $result;
        }
        
        $job_data = array(
            'job_id' => $job_id,
            'db_id'  => $result['id'],
        );
        
        // Log job creation
        error_log( 'SMI JobManager: Job created successfully - ID: ' . $job_id );
        
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
        if ( ! in_array( $new_status, self::VALID_STATUSES, true ) ) {
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
                sprintf( __( 'Cannot transition from %s to %s', 'sell-my-images' ), $job->status, $new_status ),
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
        
        // Log status change
        error_log( sprintf( 'SMI JobManager: Job status updated - ID: %s, Status: %s → %s', 
            $job_id, $job->status, $new_status ) );
        
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
        if ( ! in_array( $payment_status, self::VALID_PAYMENT_STATUSES, true ) ) {
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
        
        // Log payment status change
        error_log( sprintf( 'SMI JobManager: Payment status updated - ID: %s, Status: %s', 
            $job_id, $payment_status ) );
        
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
            error_log( 'SMI JobManager: Failed to delete job - ID: ' . $job_id . ' - ' . $result->get_error_message() );
            return new \WP_Error(
                'job_deletion_failed',
                __( 'Failed to delete job', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        error_log( 'SMI JobManager: Job deleted - ID: ' . $job_id );
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
        if ( ! in_array( $status, self::VALID_STATUSES, true ) ) {
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
            error_log( 'SMI JobManager: Database error getting jobs by status - ' . $jobs->get_error_message() );
            return new \WP_Error(
                'database_error',
                __( 'Database error occurred', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        return $jobs;
    }
    
    /**
     * Clean up expired jobs
     * 
     * @return int Number of jobs cleaned up
     */
    public static function cleanup_expired_jobs() {
        $failed_count = self::cleanup_failed_jobs();
        $abandoned_count = self::cleanup_abandoned_jobs();
        
        $total_cleaned = $failed_count + $abandoned_count;
        
        if ( $total_cleaned > 0 ) {
            error_log( sprintf( 'SMI JobManager: Job cleanup completed - Failed: %d, Abandoned: %d', 
                $failed_count, $abandoned_count ) );
        }
        
        return $total_cleaned;
    }
    
    /**
     * Validate job data
     * 
     * @param array $job_data Job data to validate
     * @return true|WP_Error True if valid, error if not
     */
    private static function validate_job_data( $job_data ) {
        // Check required fields
        $required_fields = array( 'image_url', 'resolution', 'email', 'post_id' );
        
        foreach ( $required_fields as $field ) {
            if ( empty( $job_data[ $field ] ) ) {
                return new \WP_Error(
                    'missing_required_field',
                    sprintf( __( 'Required field missing: %s', 'sell-my-images' ), $field ),
                    array( 'status' => 400 )
                );
            }
        }
        
        // Validate email
        if ( ! is_email( $job_data['email'] ) ) {
            return new \WP_Error(
                'invalid_email',
                __( 'Invalid email address', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Validate resolution
        if ( ! in_array( $job_data['resolution'], self::VALID_RESOLUTIONS, true ) ) {
            return new \WP_Error(
                'invalid_resolution',
                __( 'Invalid resolution specified', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Validate image URL
        if ( ! filter_var( $job_data['image_url'], FILTER_VALIDATE_URL ) ) {
            return new \WP_Error(
                'invalid_image_url',
                __( 'Invalid image URL', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        // Validate post ID
        if ( ! is_numeric( $job_data['post_id'] ) || intval( $job_data['post_id'] ) <= 0 ) {
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
            'image_url'  => esc_url_raw( $job_data['image_url'] ),
            'resolution' => sanitize_text_field( $job_data['resolution'] ),
            'email'      => sanitize_email( $job_data['email'] ),
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
            error_log( 'SMI JobManager: Failed to update job data - ID: ' . $job_id . ' - ' . $result->get_error_message() );
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
                // Job completed - this is now handled by DownloadManager
                if ( isset( $additional_data['upscaled_file_path'] ) ) {
                    // Let DownloadManager handle file storage and notifications
                    DownloadManager::store_processed_file( $additional_data['upscaled_file_path'], $job_id );
                }
                break;
                
            case 'failed':
                // Job failed - log failure reason if provided
                $reason = isset( $additional_data['failure_reason'] ) ? $additional_data['failure_reason'] : 'Unknown';
                error_log( sprintf( 'SMI JobManager: Job failed - ID: %s, Reason: %s', $job_id, $reason ) );
                break;
        }
        
        // Allow plugins to hook into status changes
        do_action( 'smi_job_status_changed', $job_id, $old_status, $new_status, $additional_data );
    }
    
    /**
     * Clean up failed jobs older than specified days
     * 
     * @param int $days Days to keep failed jobs (default: 7)
     * @return int Number of jobs cleaned up
     */
    private static function cleanup_failed_jobs( $days = 7 ) {
        return DatabaseManager::cleanup( 'failed', array( 'days' => $days ) );
    }
    
    /**
     * Clean up abandoned jobs (pending payment > 24 hours)
     * 
     * @param int $hours Hours to keep pending jobs (default: 24)
     * @return int Number of jobs cleaned up
     */
    private static function cleanup_abandoned_jobs( $hours = 24 ) {
        return DatabaseManager::cleanup( 'abandoned', array( 'hours' => $hours ) );
    }
}