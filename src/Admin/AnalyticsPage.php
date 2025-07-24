<?php
/**
 * Analytics Page Class
 * 
 * Displays analytics data organized by post with attachment breakdowns
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Admin;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AnalyticsPage class
 */
class AnalyticsPage {
    
    /**
     * Constructor
     */
    public function __construct() {
        // This class is instantiated when needed
    }
    
    /**
     * Get analytics data organized by post
     * 
     * @return array Analytics data grouped by post
     */
    public function get_analytics_data() {
        global $wpdb;
        
        $jobs_table = \SellMyImages\Api\DatabaseManager::get_jobs_table();
        
        // Get post-level analytics with paid jobs only
        $query = "
            SELECT 
                j.post_id,
                p.post_title,
                p.post_date,
                COUNT(*) as total_sales,
                SUM(j.amount_charged) as total_revenue,
                SUM(j.amount_cost) as total_cost,
                SUM(j.amount_charged - COALESCE(j.amount_cost, 0)) as total_profit,
                AVG(j.amount_charged) as avg_sale_price,
                AVG(j.amount_cost) as avg_cost,
                COUNT(DISTINCT j.attachment_id) as unique_images_sold
            FROM {$jobs_table} j
            LEFT JOIN {$wpdb->posts} p ON j.post_id = p.ID
            WHERE j.payment_status = 'paid'
            GROUP BY j.post_id
            ORDER BY total_revenue DESC
        ";
        
        $post_analytics = $wpdb->get_results( $query );
        
        // Get attachment-level data for each post
        foreach ( $post_analytics as &$post_data ) {
            $post_data->attachments = $this->get_post_attachment_analytics( $post_data->post_id );
        }
        
        return $post_analytics;
    }
    
    /**
     * Get attachment analytics for a specific post
     * 
     * @param int $post_id Post ID
     * @return array Attachment analytics data
     */
    private function get_post_attachment_analytics( $post_id ) {
        global $wpdb;
        
        $jobs_table = \SellMyImages\Api\DatabaseManager::get_jobs_table();
        
        $query = $wpdb->prepare( "
            SELECT 
                j.attachment_id,
                j.image_url,
                j.image_width,
                j.image_height,
                COUNT(*) as sales_count,
                SUM(j.amount_charged) as revenue,
                SUM(j.amount_cost) as total_cost,
                SUM(j.amount_charged - COALESCE(j.amount_cost, 0)) as profit,
                AVG(j.amount_charged) as avg_price,
                AVG(j.amount_cost) as avg_cost,
                GROUP_CONCAT(DISTINCT j.resolution ORDER BY j.resolution) as resolutions_sold,
                MAX(j.created_at) as last_sale_date,
                MIN(j.created_at) as first_sale_date
            FROM {$jobs_table} j
            WHERE j.post_id = %d 
            AND j.payment_status = 'paid'
            AND j.attachment_id IS NOT NULL
            GROUP BY j.attachment_id, j.image_url
            ORDER BY revenue DESC
        ", $post_id );
        
        return $wpdb->get_results( $query );
    }
    
    /**
     * Get summary statistics
     * 
     * @return object Summary statistics
     */
    public function get_summary_stats() {
        global $wpdb;
        
        $jobs_table = \SellMyImages\Api\DatabaseManager::get_jobs_table();
        
        $query = "
            SELECT 
                COUNT(*) as total_paid_jobs,
                SUM(amount_charged) as total_revenue,
                SUM(amount_cost) as total_cost,
                SUM(amount_charged - COALESCE(amount_cost, 0)) as total_profit,
                AVG(amount_charged) as avg_sale_price,
                AVG(amount_cost) as avg_cost,
                COUNT(DISTINCT post_id) as posts_with_sales,
                COUNT(DISTINCT attachment_id) as unique_images_sold,
                COUNT(DISTINCT email) as unique_customers
            FROM {$jobs_table}
            WHERE payment_status = 'paid'
        ";
        
        return $wpdb->get_row( $query );
    }
    
    /**
     * Render the analytics page
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'sell-my-images' ) );
        }
        
        $analytics_data = $this->get_analytics_data();
        $summary_stats = $this->get_summary_stats();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Sell My Images - Analytics', 'sell-my-images' ); ?></h1>
            
            <?php $this->render_summary_stats( $summary_stats ); ?>
            
            <div class="smi-analytics-container">
                <?php if ( empty( $analytics_data ) ) : ?>
                    <div class="notice notice-info">
                        <p><?php esc_html_e( 'No sales data found. Sales will appear here once customers purchase upscaled images.', 'sell-my-images' ); ?></p>
                    </div>
                <?php else : ?>
                    <?php $this->render_analytics_table( $analytics_data ); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .smi-summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .smi-stat-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            text-align: center;
        }
        
        .smi-stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #2271b1;
            display: block;
        }
        
        .smi-stat-label {
            color: #646970;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .smi-analytics-table {
            margin-top: 20px;
        }
        
        .smi-post-row {
            background: #fff;
            border: 1px solid #ccd0d4;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .smi-post-header {
            padding: 15px 20px;
            background: #f6f7f7;
            border-bottom: 1px solid #ccd0d4;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .smi-post-title {
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .smi-post-stats {
            display: flex;
            gap: 20px;
            font-size: 0.9em;
            color: #646970;
        }
        
        .smi-attachments {
            padding: 0;
            display: none;
        }
        
        .smi-attachments.expanded {
            display: block;
        }
        
        .smi-attachment-row {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f1;
            display: grid;
            grid-template-columns: 60px 1fr auto;
            gap: 15px;
            align-items: center;
        }
        
        .smi-attachment-row:last-child {
            border-bottom: none;
        }
        
        .smi-attachment-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .smi-attachment-details h4 {
            margin: 0 0 5px 0;
            font-size: 0.95em;
        }
        
        .smi-attachment-meta {
            font-size: 0.85em;
            color: #646970;
        }
        
        .smi-attachment-stats {
            text-align: right;
            font-size: 0.9em;
        }
        
        .smi-revenue {
            font-weight: bold;
            color: #00a32a;
        }
        
        .smi-stat-value.profit {
            color: #00a32a;
        }
        
        .smi-toggle-icon {
            transition: transform 0.2s;
        }
        
        .smi-toggle-icon.expanded {
            transform: rotate(90deg);
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.smi-post-header').click(function() {
                var $attachments = $(this).siblings('.smi-attachments');
                var $icon = $(this).find('.smi-toggle-icon');
                
                $attachments.toggleClass('expanded');
                $icon.toggleClass('expanded');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render summary statistics cards
     * 
     * @param object $stats Summary statistics
     */
    private function render_summary_stats( $stats ) {
        if ( ! $stats ) {
            return;
        }
        
        ?>
        <div class="smi-summary-stats">
            <div class="smi-stat-card">
                <span class="smi-stat-value">$<?php echo esc_html( number_format( $stats->total_revenue ?: 0, 2 ) ); ?></span>
                <div class="smi-stat-label"><?php esc_html_e( 'Total Revenue', 'sell-my-images' ); ?></div>
            </div>
            
            <div class="smi-stat-card">
                <span class="smi-stat-value profit">$<?php echo esc_html( number_format( $stats->total_profit ?: 0, 2 ) ); ?></span>
                <div class="smi-stat-label"><?php esc_html_e( 'Total Profit', 'sell-my-images' ); ?></div>
            </div>
            
            <div class="smi-stat-card">
                <span class="smi-stat-value"><?php echo esc_html( number_format( $stats->total_paid_jobs ?: 0 ) ); ?></span>
                <div class="smi-stat-label"><?php esc_html_e( 'Total Sales', 'sell-my-images' ); ?></div>
            </div>
            
            <div class="smi-stat-card">
                <span class="smi-stat-value">
                    <?php 
                    $profit_margin = 0;
                    if ( $stats->total_revenue && $stats->total_cost ) {
                        $profit_margin = ( ( $stats->total_revenue - $stats->total_cost ) / $stats->total_revenue ) * 100;
                    }
                    echo esc_html( number_format( $profit_margin, 1 ) ); 
                    ?>%
                </span>
                <div class="smi-stat-label"><?php esc_html_e( 'Profit Margin', 'sell-my-images' ); ?></div>
            </div>
            
            <div class="smi-stat-card">
                <span class="smi-stat-value">$<?php echo esc_html( number_format( $stats->avg_sale_price ?: 0, 2 ) ); ?></span>
                <div class="smi-stat-label"><?php esc_html_e( 'Average Sale', 'sell-my-images' ); ?></div>
            </div>
            
            <div class="smi-stat-card">
                <span class="smi-stat-value"><?php echo esc_html( number_format( $stats->unique_customers ?: 0 ) ); ?></span>
                <div class="smi-stat-label"><?php esc_html_e( 'Unique Customers', 'sell-my-images' ); ?></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render the main analytics table
     * 
     * @param array $analytics_data Analytics data grouped by post
     */
    private function render_analytics_table( $analytics_data ) {
        ?>
        <div class="smi-analytics-table">
            <?php foreach ( $analytics_data as $post_data ) : ?>
                <div class="smi-post-row">
                    <div class="smi-post-header">
                        <div>
                            <div class="smi-post-title">
                                <?php echo esc_html( $post_data->post_title ?: __( 'Unknown Post', 'sell-my-images' ) ); ?>
                                <span class="smi-toggle-icon">▶</span>
                            </div>
                            <div class="smi-post-meta">
                                <?php printf( 
                                    esc_html__( 'Published: %s', 'sell-my-images' ), 
                                    $post_data->post_date ? esc_html( date( 'M j, Y', strtotime( $post_data->post_date ) ) ) : esc_html__( 'Unknown', 'sell-my-images' )
                                ); ?>
                            </div>
                        </div>
                        
                        <div class="smi-post-stats">
                            <div>
                                <strong class="smi-revenue">$<?php echo esc_html( number_format( $post_data->total_revenue, 2 ) ); ?></strong><br>
                                <small><?php esc_html_e( 'Revenue', 'sell-my-images' ); ?></small>
                            </div>
                            <div>
                                <strong style="color: #00a32a;">$<?php echo esc_html( number_format( $post_data->total_profit ?: 0, 2 ) ); ?></strong><br>
                                <small><?php esc_html_e( 'Profit', 'sell-my-images' ); ?></small>
                            </div>
                            <div>
                                <strong><?php echo esc_html( $post_data->total_sales ); ?></strong><br>
                                <small><?php esc_html_e( 'Sales', 'sell-my-images' ); ?></small>
                            </div>
                            <div>
                                <strong>
                                    <?php 
                                    $post_margin = 0;
                                    if ( $post_data->total_revenue && $post_data->total_cost ) {
                                        $post_margin = ( ( $post_data->total_revenue - $post_data->total_cost ) / $post_data->total_revenue ) * 100;
                                    }
                                    echo esc_html( number_format( $post_margin, 1 ) ); 
                                    ?>%
                                </strong><br>
                                <small><?php esc_html_e( 'Margin', 'sell-my-images' ); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="smi-attachments">
                        <?php if ( empty( $post_data->attachments ) ) : ?>
                            <div class="smi-attachment-row">
                                <div colspan="3" style="text-align: center; color: #646970; font-style: italic;">
                                    <?php esc_html_e( 'No individual image data available', 'sell-my-images' ); ?>
                                </div>
                            </div>
                        <?php else : ?>
                            <?php foreach ( $post_data->attachments as $attachment ) : ?>
                                <div class="smi-attachment-row">
                                    <div>
                                        <?php if ( $attachment->image_url ) : ?>
                                            <img src="<?php echo esc_url( $attachment->image_url ); ?>" 
                                                 alt="<?php esc_attr_e( 'Attachment thumbnail', 'sell-my-images' ); ?>" 
                                                 class="smi-attachment-thumbnail">
                                        <?php else : ?>
                                            <div class="smi-attachment-thumbnail" style="background: #f0f0f1; display: flex; align-items: center; justify-content: center;">
                                                <span style="font-size: 20px;">📷</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="smi-attachment-details">
                                        <h4><?php printf( esc_html__( 'Image ID: %d', 'sell-my-images' ), $attachment->attachment_id ?: 0 ); ?></h4>
                                        <div class="smi-attachment-meta">
                                            <?php if ( $attachment->image_width && $attachment->image_height ) : ?>
                                                <div><?php printf( esc_html__( 'Dimensions: %dx%d', 'sell-my-images' ), $attachment->image_width, $attachment->image_height ); ?></div>
                                            <?php endif; ?>
                                            <?php if ( $attachment->resolutions_sold ) : ?>
                                                <div><?php printf( esc_html__( 'Resolutions sold: %s', 'sell-my-images' ), esc_html( $attachment->resolutions_sold ) ); ?></div>
                                            <?php endif; ?>
                                            <div><?php printf( 
                                                esc_html__( 'Sales period: %s to %s', 'sell-my-images' ), 
                                                esc_html( date( 'M j', strtotime( $attachment->first_sale_date ) ) ),
                                                esc_html( date( 'M j, Y', strtotime( $attachment->last_sale_date ) ) )
                                            ); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="smi-attachment-stats">
                                        <div class="smi-revenue">$<?php echo esc_html( number_format( $attachment->revenue, 2 ) ); ?></div>
                                        <div style="color: #00a32a;">$<?php echo esc_html( number_format( $attachment->profit ?: 0, 2 ) ); ?> profit</div>
                                        <div><?php printf( esc_html__( '%d sales', 'sell-my-images' ), $attachment->sales_count ); ?></div>
                                        <div><?php printf( esc_html__( '$%s avg', 'sell-my-images' ), number_format( $attachment->avg_price, 2 ) ); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}