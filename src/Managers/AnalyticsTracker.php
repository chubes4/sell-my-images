<?php
/**
 * Analytics Tracker Manager Class
 * 
 * Handles post meta-based tracking of download button clicks
 * providing analytics data without requiring database schema changes
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
 * AnalyticsTracker class
 */
class AnalyticsTracker {
    
    /**
     * Meta key for storing click analytics data
     */
    const META_KEY = '_smi_click_analytics';
    
    /**
     * Track a button click for a specific post and attachment
     * 
     * @param int $post_id WordPress post ID
     * @param int $attachment_id WordPress attachment ID
     * @return bool True on success, false on failure
     */
    public static function track_button_click( $post_id, $attachment_id ) {
        $post_id = intval( $post_id );
        $attachment_id = intval( $attachment_id );
        
        // Validate inputs
        if ( $post_id <= 0 || $attachment_id <= 0 ) {
            return false;
        }
        
        // Verify post exists
        if ( ! get_post( $post_id ) ) {
            return false;
        }
        
        // Verify attachment exists
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return false;
        }
        
        // Get current analytics data
        $analytics_data = self::get_analytics_data( $post_id );
        
        // Initialize if this is the first click for this post
        if ( empty( $analytics_data ) ) {
            $analytics_data = self::initialize_analytics_data();
        }
        
        // Increment click count for this attachment
        $attachment_key = 'attachment_' . $attachment_id;
        
        if ( ! isset( $analytics_data[ $attachment_key ] ) ) {
            $analytics_data[ $attachment_key ] = 0;
        }
        
        $analytics_data[ $attachment_key ]++;
        $analytics_data['total_clicks']++;
        $analytics_data['last_click_date'] = current_time( 'mysql' );
        
        // Update post meta
        $result = update_post_meta( $post_id, self::META_KEY, $analytics_data );
        
        // For update_post_meta, false means failure, anything else (true or meta_id) means success
        if ( $result !== false ) {
            // Essential user flow log - button click tracked
            error_log( 'SMI: Button clicked - Post ' . $post_id . ', Attachment ' . $attachment_id );
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Get click counts for a specific post
     * 
     * @param int $post_id WordPress post ID
     * @param int $attachment_id Optional specific attachment ID
     * @return array|int Analytics data array or specific count
     */
    public static function get_click_counts( $post_id, $attachment_id = null ) {
        $post_id = intval( $post_id );
        
        if ( $post_id <= 0 ) {
            return $attachment_id ? 0 : array();
        }
        
        $analytics_data = self::get_analytics_data( $post_id );
        
        if ( empty( $analytics_data ) ) {
            return $attachment_id ? 0 : array();
        }
        
        // Return specific attachment count
        if ( $attachment_id ) {
            $attachment_key = 'attachment_' . intval( $attachment_id );
            return isset( $analytics_data[ $attachment_key ] ) ? intval( $analytics_data[ $attachment_key ] ) : 0;
        }
        
        // Return all analytics data
        return $analytics_data;
    }
    
    /**
     * Get total clicks for a post
     * 
     * @param int $post_id WordPress post ID
     * @return int Total click count
     */
    public static function get_total_clicks( $post_id ) {
        $analytics_data = self::get_click_counts( $post_id );
        
        return isset( $analytics_data['total_clicks'] ) ? intval( $analytics_data['total_clicks'] ) : 0;
    }
    
    /**
     * Get click counts for multiple posts (bulk operation)
     * 
     * @param array $post_ids Array of post IDs
     * @return array Associative array of post_id => analytics_data
     */
    public static function get_bulk_click_counts( $post_ids ) {
        if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
            return array();
        }
        
        $post_ids = array_map( 'intval', $post_ids );
        $post_ids = array_filter( $post_ids, function( $id ) { return $id > 0; } );
        
        if ( empty( $post_ids ) ) {
            return array();
        }
        
        $results = array();
        
        foreach ( $post_ids as $post_id ) {
            $results[ $post_id ] = self::get_click_counts( $post_id );
        }
        
        return $results;
    }
    
    /**
     * Calculate conversion rates for posts with both clicks and purchases
     * 
     * @param array $post_analytics Analytics data from AnalyticsPage
     * @return array Enhanced analytics with conversion data
     */
    public static function enhance_with_conversion_data( $post_analytics ) {
        if ( empty( $post_analytics ) ) {
            return array();
        }
        
        // Get all post IDs from analytics data
        $post_ids = array();
        foreach ( $post_analytics as $post_data ) {
            if ( isset( $post_data->post_id ) ) {
                $post_ids[] = $post_data->post_id;
            }
        }
        
        // Get click data for all posts
        $click_data = self::get_bulk_click_counts( $post_ids );
        
        // Enhance each post's analytics with click data
        foreach ( $post_analytics as $post_data ) {
            $post_id = $post_data->post_id;
            $post_clicks = isset( $click_data[ $post_id ] ) ? $click_data[ $post_id ] : array();
            
            // Add post-level click data (only if not already set by click-only posts method)
            if ( ! isset( $post_data->total_clicks ) ) {
                $post_data->total_clicks = isset( $post_clicks['total_clicks'] ) ? intval( $post_clicks['total_clicks'] ) : 0;
                $post_data->first_click_date = isset( $post_clicks['first_click_date'] ) ? $post_clicks['first_click_date'] : null;
                $post_data->last_click_date = isset( $post_clicks['last_click_date'] ) ? $post_clicks['last_click_date'] : null;
            }
            
            // Calculate post-level conversion rate (always recalculate to ensure accuracy)
            $post_data->conversion_rate = $post_data->total_clicks > 0 ? 
                ( $post_data->total_sales / $post_data->total_clicks ) * 100 : 0;
            
            // Enhance attachment-level data with clicks
            if ( isset( $post_data->attachments ) && is_array( $post_data->attachments ) ) {
                foreach ( $post_data->attachments as $attachment ) {
                    $attachment_key = 'attachment_' . $attachment->attachment_id;
                    $attachment->click_count = isset( $post_clicks[ $attachment_key ] ) ? 
                        intval( $post_clicks[ $attachment_key ] ) : 0;
                    
                    // Calculate attachment-level conversion rate
                    $attachment->conversion_rate = $attachment->click_count > 0 ? 
                        ( $attachment->sales_count / $attachment->click_count ) * 100 : 0;
                }
            }
        }
        
        return $post_analytics;
    }
    
    /**
     * Get summary statistics including click data
     * 
     * @param object $purchase_stats Existing purchase statistics
     * @return object Enhanced statistics with click data
     */
    public static function enhance_summary_stats( $purchase_stats ) {
        if ( ! $purchase_stats ) {
            return $purchase_stats;
        }
        
        // Get all posts that have click tracking data (not just those with sales)
        $posts_with_clicks = self::get_posts_with_click_data();
        
        if ( empty( $posts_with_clicks ) ) {
            $purchase_stats->total_clicks = 0;
            $purchase_stats->avg_conversion_rate = 0;
            return $purchase_stats;
        }
        
        // Calculate total clicks across all posts with click data
        $total_clicks = 0;
        foreach ( $posts_with_clicks as $post_id ) {
            $total_clicks += self::get_total_clicks( $post_id );
        }
        
        $purchase_stats->total_clicks = $total_clicks;
        
        // Calculate overall conversion rate
        $purchase_stats->avg_conversion_rate = $total_clicks > 0 ? 
            ( $purchase_stats->total_paid_jobs / $total_clicks ) * 100 : 0;
        
        return $purchase_stats;
    }
    
    /**
     * Get all posts that have click tracking data
     * 
     * @return array Array of post IDs with click data
     */
    public static function get_posts_with_click_data() {
        global $wpdb;
        
        $post_ids = $wpdb->get_col( $wpdb->prepare( "
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s
            AND meta_value != ''
        ", self::META_KEY ) );
        
        return array_map( 'intval', $post_ids );
    }
    
    /**
     * Get click-only posts data (posts with clicks but no sales)
     * 
     * @param array $params Optional parameters for pagination and sorting
     * @return array Array of post data for posts with clicks but no sales
     */
    public static function get_click_only_posts_data( $params = array() ) {
        global $wpdb;
        
        $jobs_table = DatabaseManager::get_jobs_table();
        
        // Get posts with clicks
        $posts_with_clicks = self::get_posts_with_click_data();
        
        if ( empty( $posts_with_clicks ) ) {
            return array();
        }
        
        $post_ids_string = implode( ',', $posts_with_clicks );
        
        // Get posts with clicks but no paid sales
        $query = "
            SELECT p.ID as post_id, p.post_title, p.post_date
            FROM {$wpdb->posts} p
            WHERE p.ID IN ({$post_ids_string})
            AND p.ID NOT IN (
                SELECT DISTINCT post_id 
                FROM {$jobs_table} 
                WHERE payment_status = 'paid' 
                AND post_id IS NOT NULL
            )
            AND p.post_status = 'publish'
            ORDER BY p.post_date DESC
        ";
        
        $click_only_posts = $wpdb->get_results( $query );
        
        // Format data to match AnalyticsPage structure
        $formatted_posts = array();
        foreach ( $click_only_posts as $post ) {
            $click_data = self::get_click_counts( $post->post_id );
            
            // Build attachment data from click tracking
            $attachments = array();
            if ( ! empty( $click_data ) ) {
                foreach ( $click_data as $key => $value ) {
                    if ( strpos( $key, 'attachment_' ) === 0 ) {
                        $attachment_id = intval( str_replace( 'attachment_', '', $key ) );
                        
                        // Get attachment metadata
                        $attachment_meta = wp_get_attachment_metadata( $attachment_id );
                        $attachment_url = wp_get_attachment_url( $attachment_id );
                        
                        $attachments[] = (object) array(
                            'attachment_id' => $attachment_id,
                            'image_url' => $attachment_url ?: '',
                            'image_width' => isset( $attachment_meta['width'] ) ? $attachment_meta['width'] : null,
                            'image_height' => isset( $attachment_meta['height'] ) ? $attachment_meta['height'] : null,
                            'sales_count' => 0,
                            'revenue' => 0,
                            'total_cost' => 0,
                            'profit' => 0,
                            'avg_price' => 0,
                            'avg_cost' => 0,
                            'resolutions_sold' => '',
                            'last_sale_date' => null,
                            'first_sale_date' => null,
                            'click_count' => intval( $value ),
                            'conversion_rate' => 0 // No sales, so 0% conversion
                        );
                    }
                }
            }
            
            $formatted_posts[] = (object) array(
                'post_id' => $post->post_id,
                'post_title' => $post->post_title,
                'post_date' => $post->post_date,
                'total_sales' => 0,
                'total_revenue' => 0,
                'total_cost' => 0,
                'total_profit' => 0,
                'unique_images_sold' => 0,
                'customer_emails' => array(),
                'attachments' => $attachments,
                'total_clicks' => isset( $click_data['total_clicks'] ) ? intval( $click_data['total_clicks'] ) : 0,
                'first_click_date' => isset( $click_data['first_click_date'] ) ? $click_data['first_click_date'] : null,
                'last_click_date' => isset( $click_data['last_click_date'] ) ? $click_data['last_click_date'] : null,
                'conversion_rate' => 0, // No sales, so 0% conversion
                'avg_sale_price' => 0,
                'avg_cost' => 0,
                'unique_customers' => 0
            );
        }
        
        return $formatted_posts;
    }
    
    /**
     * Get count of click-only posts (posts with clicks but no sales)
     * 
     * @return int Count of click-only posts
     */
    public static function get_click_only_posts_count() {
        global $wpdb;
        
        $jobs_table = DatabaseManager::get_jobs_table();
        
        // Get posts with clicks
        $posts_with_clicks = self::get_posts_with_click_data();
        
        if ( empty( $posts_with_clicks ) ) {
            return 0;
        }
        
        $post_ids_string = implode( ',', $posts_with_clicks );
        
        // Count posts with clicks but no paid sales
        $count = $wpdb->get_var( "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            WHERE p.ID IN ({$post_ids_string})
            AND p.ID NOT IN (
                SELECT DISTINCT post_id 
                FROM {$jobs_table} 
                WHERE payment_status = 'paid' 
                AND post_id IS NOT NULL
            )
            AND p.post_status = 'publish'
        " );
        
        return intval( $count );
    }
    
    /**
     * Clean up old analytics data (optional maintenance)
     * 
     * @param int $days Days to keep analytics data (default: 365)
     * @return int Number of posts cleaned
     */
    public static function cleanup_old_analytics( $days = 365 ) {
        global $wpdb;
        
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        $cleaned_count = 0;
        
        // Get posts with analytics data older than cutoff
        $post_ids = $wpdb->get_col( $wpdb->prepare( "
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s
        ", self::META_KEY ) );
        
        foreach ( $post_ids as $post_id ) {
            $analytics_data = self::get_analytics_data( $post_id );
            
            if ( ! empty( $analytics_data['first_click_date'] ) ) {
                if ( $analytics_data['first_click_date'] < $cutoff_date ) {
                    delete_post_meta( $post_id, self::META_KEY );
                    $cleaned_count++;
                }
            }
        }
        
        
        return $cleaned_count;
    }
    
    /**
     * Get raw analytics data from post meta
     * 
     * @param int $post_id WordPress post ID
     * @return array Analytics data array
     */
    private static function get_analytics_data( $post_id ) {
        $data = get_post_meta( $post_id, self::META_KEY, true );
        
        // Ensure we return an array even if meta doesn't exist
        return is_array( $data ) ? $data : array();
    }
    
    /**
     * Initialize analytics data structure for a new post
     * 
     * @return array Initial analytics data structure
     */
    private static function initialize_analytics_data() {
        return array(
            'total_clicks' => 0,
            'first_click_date' => current_time( 'mysql' ),
            'last_click_date' => null,
        );
    }
}