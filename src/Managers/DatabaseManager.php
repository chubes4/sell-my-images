<?php
/**
 * Database Manager Class
 * 
 * Centralizes all database operations including schema management,
 * standardized CRUD operations, and query building
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
 * DatabaseManager class
 */
class DatabaseManager {
    
    /**
     * Table name constant
     */
    const JOBS_TABLE = 'smi_jobs';
    
    /**
     * Get full table name with WordPress prefix
     * 
     * @return string Full table name
     */
    public static function get_jobs_table() {
        global $wpdb;
        return $wpdb->prefix . self::JOBS_TABLE;
    }
    
    /**
     * Create database tables
     * 
     * @return bool True on success, false on failure
     */
    public static function create_tables() {
        global $wpdb;
        
        $table_jobs = self::get_jobs_table();
        
        $sql_jobs = "CREATE TABLE $table_jobs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id varchar(255) NOT NULL,
            image_url text NOT NULL,
            resolution varchar(10) NOT NULL,
            email varchar(255) NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            attachment_id bigint(20) unsigned DEFAULT NULL,
            image_width int unsigned DEFAULT NULL,
            image_height int unsigned DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            upsampler_job_id varchar(255) DEFAULT NULL,
            upscaled_url text DEFAULT NULL,
            upscaled_file_path text DEFAULT NULL,
            stripe_payment_intent_id varchar(255) DEFAULT NULL,
            stripe_checkout_session_id varchar(255) DEFAULT NULL,
            payment_status varchar(20) DEFAULT 'pending',
            amount_charged decimal(10,2) DEFAULT NULL,
            amount_cost decimal(10,2) DEFAULT NULL,
            credits_used int DEFAULT NULL,
            download_token varchar(64) DEFAULT NULL,
            download_expires_at datetime DEFAULT NULL,
            email_sent tinyint(1) DEFAULT 0,
            paid_at datetime DEFAULT NULL,
            processing_started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            failed_at datetime DEFAULT NULL,
            refunded_at datetime DEFAULT NULL,
            refund_reason text DEFAULT NULL,
            refund_amount decimal(10,2) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY job_id (job_id),
            KEY post_id (post_id),
            KEY attachment_id (attachment_id),
            KEY post_attachment (post_id, attachment_id),
            KEY status (status),
            KEY payment_status (payment_status),
            KEY download_token (download_token),
            KEY stripe_checkout_session_id (stripe_checkout_session_id),
            KEY upsampler_job_id (upsampler_job_id),
            KEY email (email),
            KEY created_at (created_at)
        ) {$wpdb->get_charset_collate()};";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        $result = dbDelta( $sql_jobs );
        
        if ( empty( $result ) ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Insert a new record
     * 
     * @param array $data Data to insert
     * @return array|WP_Error Insert result with ID or error
     */
    public static function insert( $data ) {
        global $wpdb;
        
        if ( empty( $data ) ) {
            return new \WP_Error(
                'empty_data',
                __( 'No data provided for insert', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $table = self::get_jobs_table();
        $formats = self::detect_formats( $data );
        
        $result = $wpdb->insert( $table, $data, $formats );
        
        if ( $result === false ) {
            return new \WP_Error(
                'insert_failed',
                __( 'Failed to insert record', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        return array(
            'id' => $wpdb->insert_id,
            'rows_affected' => $result
        );
    }
    
    /**
     * Update records
     * 
     * @param array $data Data to update
     * @param array $where WHERE conditions
     * @return bool|WP_Error True on success, error on failure
     */
    public static function update( $data, $where ) {
        global $wpdb;
        
        if ( empty( $data ) ) {
            return new \WP_Error(
                'empty_data',
                __( 'No data provided for update', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        if ( empty( $where ) ) {
            return new \WP_Error(
                'empty_where',
                __( 'WHERE conditions required for update', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $table = self::get_jobs_table();
        $data_formats = self::detect_formats( $data );
        $where_formats = self::detect_formats( $where );
        
        $result = $wpdb->update( $table, $data, $where, $data_formats, $where_formats );
        
        if ( $result === false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI DatabaseManager: Update failed - ' . $wpdb->last_error  );
            }
            return new \WP_Error(
                'update_failed',
                __( 'Failed to update record', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        return true;
    }
    
    /**
     * Delete records
     * 
     * @param array $where WHERE conditions
     * @return int|WP_Error Number of rows deleted or error
     */
    public static function delete( $where ) {
        global $wpdb;
        
        if ( empty( $where ) ) {
            return new \WP_Error(
                'empty_where',
                __( 'WHERE conditions required for delete', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $table = self::get_jobs_table();
        $where_formats = self::detect_formats( $where );
        
        $result = $wpdb->delete( $table, $where, $where_formats );
        
        if ( $result === false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'SMI DatabaseManager: Delete failed - ' . $wpdb->last_error  );
            }
            return new \WP_Error(
                'delete_failed',
                __( 'Failed to delete record', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        return $result;
    }
    
    /**
     * Get a single record
     * 
     * @param array $where WHERE conditions  
     * @return object|WP_Error Record object or error
     */
    public static function get_row( $where ) {
        global $wpdb;
        
        if ( empty( $where ) ) {
            return new \WP_Error(
                'empty_where',
                __( 'WHERE conditions required', 'sell-my-images' ),
                array( 'status' => 400 )
            );
        }
        
        $table = self::get_jobs_table();
        $where_clause = self::build_where_clause( $where );
        $values = array_values( $where );
        
        $query = "SELECT * FROM $table WHERE $where_clause";
        $row = $wpdb->get_row( $wpdb->prepare( $query, $values ) );
        
        if ( $wpdb->last_error ) {
            return new \WP_Error(
                'query_failed',
                __( 'Database query failed', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        if ( ! $row ) {
            return new \WP_Error(
                'record_not_found',
                __( 'Record not found', 'sell-my-images' ),
                array( 'status' => 404 )
            );
        }
        
        return $row;
    }
    
    /**
     * Get multiple records
     * 
     * @param array $args Query arguments
     * @return array|WP_Error Array of records or error
     */
    public static function get_results( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'where' => array(),
            'order_by' => 'created_at',
            'order' => 'DESC',
            'limit' => null,
            'offset' => null,
        );
        
        $args = wp_parse_args( $args, $defaults );
        $table = self::get_jobs_table();
        
        $query = "SELECT * FROM $table";
        $values = array();
        
        // Add WHERE clause
        if ( ! empty( $args['where'] ) ) {
            $where_clause = self::build_where_clause( $args['where'] );
            $query .= " WHERE $where_clause";
            $values = array_merge( $values, array_values( $args['where'] ) );
        }
        
        // Add ORDER BY
        $query .= " ORDER BY " . sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] );
        
        // Add LIMIT
        if ( $args['limit'] ) {
            $query .= " LIMIT %d";
            $values[] = intval( $args['limit'] );
            
            if ( $args['offset'] ) {
                $query .= " OFFSET %d";
                $values[] = intval( $args['offset'] );
            }
        }
        
        $results = empty( $values ) ? $wpdb->get_results( $query ) : $wpdb->get_results( $wpdb->prepare( $query, $values ) );
        
        if ( $wpdb->last_error ) {
            return new \WP_Error(
                'query_failed',
                __( 'Database query failed', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        return $results ?: array();
    }
    
    /**
     * Execute a custom query
     * 
     * @param string $query SQL query
     * @param array $values Values for prepared statement
     * @return mixed Query result
     */
    public static function query( $query, $values = array() ) {
        global $wpdb;
        
        if ( empty( $values ) ) {
            $result = $wpdb->get_results( $query );
        } else {
            $result = $wpdb->get_results( $wpdb->prepare( $query, $values ) );
        }
        
        if ( $wpdb->last_error ) {
            return new \WP_Error(
                'query_failed',
                __( 'Database query failed', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        return $result;
    }
    
    /**
     * Get count of records
     * 
     * @param array $where WHERE conditions
     * @return int|WP_Error Count or error
     */
    public static function get_count( $where = array() ) {
        global $wpdb;
        
        $table = self::get_jobs_table();
        $query = "SELECT COUNT(*) FROM $table";
        $values = array();
        
        if ( ! empty( $where ) ) {
            $where_clause = self::build_where_clause( $where );
            $query .= " WHERE $where_clause";
            $values = array_values( $where );
        }
        
        $count = empty( $values ) ? $wpdb->get_var( $query ) : $wpdb->get_var( $wpdb->prepare( $query, $values ) );
        
        if ( $wpdb->last_error ) {
            return new \WP_Error(
                'query_failed',
                __( 'Database query failed', 'sell-my-images' ),
                array( 'status' => 500 )
            );
        }
        
        return intval( $count );
    }
    
    /**
     * Cleanup expired downloads - removes physical files and download tokens while preserving job records
     * 
     * @return int Number of downloads cleaned up
     */
    public static function cleanup_expired_downloads() {
        global $wpdb;
        
        $table = self::get_jobs_table();
        $current_time = current_time( 'mysql' );
        $cleaned_count = 0;
        
        // Get expired downloads to delete files
        $expired_jobs = $wpdb->get_results( $wpdb->prepare(
            "SELECT job_id, upscaled_file_path FROM $table 
             WHERE download_expires_at < %s 
             AND download_expires_at IS NOT NULL 
             AND upscaled_file_path IS NOT NULL",
            $current_time
        ) );
        
        foreach ( $expired_jobs as $job ) {
            // Delete physical file
            if ( file_exists( $job->upscaled_file_path ) ) {
                if ( wp_delete_file( $job->upscaled_file_path ) ) {
                    // File deleted successfully
                } else {
                    continue; // Skip database update if file deletion failed
                }
            }
            
            // Clear download data from database (preserves job record)
            $updated = $wpdb->update(
                $table,
                array(
                    'upscaled_file_path' => null,
                    'download_token' => null,
                    'download_expires_at' => null,
                ),
                array( 'job_id' => $job->job_id ),
                array( '%s', '%s', '%s' ),
                array( '%s' )
            );
            
            if ( $updated ) {
                $cleaned_count++;
            }
        }
        
        return intval( $cleaned_count );
    }
    
    /**
     * Detect format array from data types
     * 
     * @param array $data Data array
     * @return array Format array
     */
    private static function detect_formats( $data ) {
        $formats = array();
        
        foreach ( $data as $value ) {
            if ( is_int( $value ) ) {
                $formats[] = '%d';
            } elseif ( is_float( $value ) ) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        
        return $formats;
    }
    
    /**
     * Build WHERE clause from array
     * 
     * @param array $where WHERE conditions
     * @return string WHERE clause
     */
    private static function build_where_clause( $where ) {
        $conditions = array();
        
        foreach ( array_keys( $where ) as $column ) {
            $conditions[] = sanitize_key( $column ) . ' = %s';
        }
        
        return implode( ' AND ', $conditions );
    }
}