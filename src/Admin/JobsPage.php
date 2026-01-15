<?php
/**
 * Jobs Page Admin Interface
 * 
 * Displays all job transactions with management controls
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Admin;

use SellMyImages\Managers\DatabaseManager;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * JobsPage class
 */
class JobsPage {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handler is registered in AdminInit.php for proper timing
    }
    
    /**
     * Render the jobs page
     */
    public function render() {
        // Handle any actions
        $this->handle_page_actions();
        
        // Get jobs data
        $jobs_data = $this->get_jobs_data();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Jobs Management', 'sell-my-images' ); ?></h1>
            
            <?php $this->render_page_notices(); ?>
            
            <div class="smi-jobs-controls">
                <?php $this->render_filters(); ?>
                <?php $this->render_pagination_info( $jobs_data ); ?>
            </div>
            
            <div class="smi-jobs-table-container">
                <?php $this->render_jobs_table( $jobs_data ); ?>
            </div>
            
            <div class="smi-pagination-container">
                <?php $this->render_pagination_controls( $jobs_data ); ?>
            </div>
        </div>
        
        
        <script>
        jQuery(document).ready(function($) {
            $('.smi-retry-btn').on('click', function() {
                var button = $(this);
                var jobId = button.data('job-id');

                if (!jobId) {
                    alert('<?php esc_js( esc_html_e( 'Invalid job ID', 'sell-my-images' ) ); ?>');
                    return;
                }

                button.prop('disabled', true).text('<?php esc_js( esc_html_e( 'Processing...', 'sell-my-images' ) ); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smi_retry_upscale',
                        job_id: jobId,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'smi_retry_upscale' ) ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            button.text('<?php esc_js( esc_html_e( 'Started!', 'sell-my-images' ) ); ?>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            alert('<?php esc_js( esc_html_e( 'Error: ', 'sell-my-images' ) ); ?>' + (response.data || '<?php esc_js( esc_html_e( 'Unknown error', 'sell-my-images' ) ); ?>'));
                            button.prop('disabled', false).text('<?php esc_js( esc_html_e( 'Retry Upscale', 'sell-my-images' ) ); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php esc_js( esc_html_e( 'Network error occurred', 'sell-my-images' ) ); ?>');
                        button.prop('disabled', false).text('<?php esc_js( esc_html_e( 'Retry Upscale', 'sell-my-images' ) ); ?>');
                    }
                });
            });

            $('.smi-resend-email-btn').on('click', function() {
                var button = $(this);
                var jobId = button.data('job-id');

                if (!jobId) {
                    alert('<?php esc_js( esc_html_e( 'Invalid job ID', 'sell-my-images' ) ); ?>');
                    return;
                }

                button.prop('disabled', true).text('<?php esc_js( esc_html_e( 'Sending...', 'sell-my-images' ) ); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smi_resend_email',
                        job_id: jobId,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'smi_resend_email' ) ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            button.text('<?php esc_js( esc_html_e( 'Sent!', 'sell-my-images' ) ); ?>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            alert('<?php esc_js( esc_html_e( 'Error: ', 'sell-my-images' ) ); ?>' + (response.data || '<?php esc_js( esc_html_e( 'Unknown error', 'sell-my-images' ) ); ?>'));
                            button.prop('disabled', false).text('<?php esc_js( esc_html_e( 'Resend Email', 'sell-my-images' ) ); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php esc_js( esc_html_e( 'Network error occurred', 'sell-my-images' ) ); ?>');
                        button.prop('disabled', false).text('<?php esc_js( esc_html_e( 'Resend Email', 'sell-my-images' ) ); ?>');
                    }
                });
            });

            $('.smi-fix-job-btn').on('click', function() {
                var button = $(this);
                var jobId = button.data('job-id');

                if (!jobId) {
                    alert('<?php esc_js( esc_html_e( 'Invalid job ID', 'sell-my-images' ) ); ?>');
                    return;
                }

                if (!confirm('<?php esc_js( esc_html_e( 'This will generate a new download token and send the email. Continue?', 'sell-my-images' ) ); ?>')) {
                    return;
                }

                button.prop('disabled', true).text('<?php esc_js( esc_html_e( 'Fixing...', 'sell-my-images' ) ); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smi_fix_broken_job',
                        job_id: jobId,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'smi_fix_broken_job' ) ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            button.text('<?php esc_js( esc_html_e( 'Fixed!', 'sell-my-images' ) ); ?>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            alert('<?php esc_js( esc_html_e( 'Error: ', 'sell-my-images' ) ); ?>' + (response.data || '<?php esc_js( esc_html_e( 'Unknown error', 'sell-my-images' ) ); ?>'));
                            button.prop('disabled', false).text('<?php esc_js( esc_html_e( 'Fix Broken Job', 'sell-my-images' ) ); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php esc_js( esc_html_e( 'Network error occurred', 'sell-my-images' ) ); ?>');
                        button.prop('disabled', false).text('<?php esc_js( esc_html_e( 'Fix Broken Job', 'sell-my-images' ) ); ?>');
                    }
                });
            });

            // Per-page change handler
            $('.smi-per-page-select').on('change', function() {
                var perPage = $(this).val();
                var url = new URL(window.location);
                url.searchParams.set('per_page', perPage);
                url.searchParams.delete('paged'); // Reset to first page
                window.location = url.toString();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle page actions
     */
    private function handle_page_actions() {
        // Handle any form submissions or actions here
        if ( isset( $_POST['smi_action'] ) && wp_verify_nonce( $_POST['smi_nonce'] ?? '', 'smi_jobs_action' ) ) {
            // Future: Handle bulk actions
        }
    }
    
    /**
     * Render page notices
     */
    private function render_page_notices() {
        if ( isset( $_GET['message'] ) ) {
            $message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
            switch ( $message ) {
                case 'retry_success':
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Upscaling process started successfully.', 'sell-my-images' ) . '</p></div>';
                    break;
                case 'retry_error':
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to start upscaling process.', 'sell-my-images' ) . '</p></div>';
                    break;
            }
        }
    }
    
    /**
     * Render filter controls
     */
    private function render_filters() {
        $current_status = $_GET['status'] ?? 'all';
        $current_payment_status = $_GET['payment_status'] ?? 'all';
        ?>
        <div class="smi-filter-controls">
            <div class="smi-filter-group">
                <label><?php esc_html_e( 'Status:', 'sell-my-images' ); ?></label>
                <select name="status" onchange="this.form.submit();">
                    <option value="all" <?php selected( $current_status, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'sell-my-images' ); ?></option>
                    <option value="pending" <?php selected( $current_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'sell-my-images' ); ?></option>
                    <option value="processing" <?php selected( $current_status, 'processing' ); ?>><?php esc_html_e( 'Processing', 'sell-my-images' ); ?></option>
                    <option value="completed" <?php selected( $current_status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'sell-my-images' ); ?></option>
                    <option value="failed" <?php selected( $current_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'sell-my-images' ); ?></option>
                    <option value="awaiting_payment" <?php selected( $current_status, 'awaiting_payment' ); ?>><?php esc_html_e( 'Awaiting Payment', 'sell-my-images' ); ?></option>
                    <option value="abandoned" <?php selected( $current_status, 'abandoned' ); ?>><?php esc_html_e( 'Abandoned', 'sell-my-images' ); ?></option>
                </select>
            </div>
            
            <div class="smi-filter-group">
                <label><?php esc_html_e( 'Payment:', 'sell-my-images' ); ?></label>
                <select name="payment_status" onchange="this.form.submit();">
                    <option value="all" <?php selected( $current_payment_status, 'all' ); ?>><?php esc_html_e( 'All Payments', 'sell-my-images' ); ?></option>
                    <option value="pending" <?php selected( $current_payment_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'sell-my-images' ); ?></option>
                    <option value="paid" <?php selected( $current_payment_status, 'paid' ); ?>><?php esc_html_e( 'Paid', 'sell-my-images' ); ?></option>
                    <option value="refunded" <?php selected( $current_payment_status, 'refunded' ); ?>><?php esc_html_e( 'Refunded', 'sell-my-images' ); ?></option>
                </select>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get jobs data with filtering and pagination
     * 
     * @return array Jobs data with pagination info
     */
    private function get_jobs_data() {
        global $wpdb;
        
        $jobs_table = DatabaseManager::get_jobs_table();
        
        // Pagination parameters
        $per_page = isset( $_GET['per_page'] ) ? intval( $_GET['per_page'] ) : 25;
        $per_page = max( 10, min( 100, $per_page ) ); // Limit between 10-100
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;
        
        // Build WHERE clause for filtering
        $where_conditions = array( '1=1' );
        $where_values = array();
        
        // Status filter
        if ( isset( $_GET['status'] ) && $_GET['status'] !== 'all' ) {
            $where_conditions[] = 'status = %s';
            $where_values[] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
        }
        
        // Payment status filter
        if ( isset( $_GET['payment_status'] ) && $_GET['payment_status'] !== 'all' ) {
            $where_conditions[] = 'payment_status = %s';
            $where_values[] = sanitize_text_field( wp_unslash( $_GET['payment_status'] ) );
        }
        
        // Search functionality
        if ( isset( $_GET['search'] ) && ! empty( $_GET['search'] ) ) {
            $search = sanitize_text_field( wp_unslash( $_GET['search'] ) );
            $where_conditions[] = '(email LIKE %s OR job_id LIKE %s)';
            $where_values[] = '%' . $wpdb->esc_like( $search ) . '%';
            $where_values[] = '%' . $wpdb->esc_like( $search ) . '%';
        }
        
        $where_clause = implode( ' AND ', $where_conditions );
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM {$jobs_table} WHERE {$where_clause}";
        if ( ! empty( $where_values ) ) {
            $count_query = $wpdb->prepare( $count_query, ...$where_values );
        }
        $total_items = $wpdb->get_var( $count_query );
        
        // Build the main query with pagination
        $query = "SELECT * FROM {$jobs_table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge( $where_values, array( $per_page, $offset ) );
        $query = $wpdb->prepare( $query, ...$query_values );
        
        $jobs = $wpdb->get_results( $query );
        
        return array(
            'jobs' => $jobs,
            'pagination' => array(
                'current_page' => $current_page,
                'per_page' => $per_page,
                'total_items' => intval( $total_items ),
                'total_pages' => ceil( $total_items / $per_page ),
            ),
        );
    }
    
    /**
     * Render jobs table
     * 
     * @param array $jobs_data Jobs data with pagination
     */
    private function render_jobs_table( $jobs_data ) {
        $jobs = $jobs_data['jobs'] ?? array();
        
        if ( empty( $jobs ) ) {
            echo '<div class="smi-empty-state">';
            esc_html_e( 'No jobs found.', 'sell-my-images' );
            echo '</div>';
            return;
        }
        ?>
        <table class="smi-jobs-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Job ID', 'sell-my-images' ); ?></th>
                    <th><?php esc_html_e( 'Image', 'sell-my-images' ); ?></th>
                    <th><?php esc_html_e( 'Customer', 'sell-my-images' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'sell-my-images' ); ?></th>
                    <th><?php esc_html_e( 'Payment', 'sell-my-images' ); ?></th>
                    <th><?php esc_html_e( 'Resolution', 'sell-my-images' ); ?></th>
                    <th><?php esc_html_e( 'Amount', 'sell-my-images' ); ?></th>
                    <th><?php esc_html_e( 'Created', 'sell-my-images' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'sell-my-images' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $jobs as $job ) : ?>
                <tr>
                    <td>
                        <div class="smi-job-id" title="<?php echo esc_attr( $job->job_id ); ?>">
                            <?php echo esc_html( substr( $job->job_id, 0, 8 ) . '...' ); ?>
                        </div>
                    </td>
                    <td>
                        <?php if ( $job->image_url ) : ?>
                            <img src="<?php echo esc_url( $job->image_url ); ?>" 
                                 alt="<?php esc_attr_e( 'Image preview', 'sell-my-images' ); ?>"
                                 class="smi-image-preview">
                        <?php else : ?>
                            <div class="smi-image-preview">
                                <?php esc_html_e( 'No image', 'sell-my-images' ); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="smi-email" title="<?php echo esc_attr( $job->email ); ?>">
                            <?php echo esc_html( $job->email ); ?>
                        </div>
                    </td>
                    <td>
                        <span class="smi-status <?php echo esc_attr( $job->status ); ?>">
                            <?php echo esc_html( ucfirst( $job->status ) ); ?>
                        </span>
                    </td>
                    <td>
                        <span class="smi-payment-status <?php echo esc_attr( $job->payment_status ); ?>">
                            <?php echo esc_html( ucfirst( $job->payment_status ) ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( $job->resolution ); ?></td>
                    <td>
                        <?php if ( $job->amount_charged ) : ?>
                            $<?php echo esc_html( number_format( $job->amount_charged, 2 ) ); ?>
                        <?php else : ?>
                            <span class="smi-muted-text">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html( gmdate( 'M j, Y g:i A', strtotime( $job->created_at ) ) ); ?>
                    </td>
                    <td>
                        <?php if ( $job->status === 'pending' || $job->status === 'failed' ||
                                  ($job->status === 'awaiting_payment' && $job->payment_status === 'paid') ) : ?>
                            <button type="button"
                                    class="smi-retry-btn"
                                    data-job-id="<?php echo esc_attr( $job->job_id ); ?>">
                                <?php esc_html_e( 'Retry Upscale', 'sell-my-images' ); ?>
                            </button>
                        <?php elseif ( $job->status === 'completed' && ! empty( $job->download_token ) ) : ?>
                            <button type="button"
                                    class="smi-resend-email-btn"
                                    data-job-id="<?php echo esc_attr( $job->job_id ); ?>">
                                <?php esc_html_e( 'Resend Email', 'sell-my-images' ); ?>
                            </button>
                        <?php elseif ( $job->status === 'completed' && empty( $job->download_token ) && ! $job->email_sent ) : ?>
                            <button type="button"
                                    class="smi-fix-job-btn"
                                    data-job-id="<?php echo esc_attr( $job->job_id ); ?>"
                                    style="background-color: #dc3232;">
                                <?php esc_html_e( 'Fix Broken Job', 'sell-my-images' ); ?>
                            </button>
                        <?php elseif ( $job->status === 'completed' && empty( $job->download_token ) && $job->email_sent ) : ?>
                            <span class="smi-status-expired"><?php esc_html_e( 'Download Expired', 'sell-my-images' ); ?></span>
                        <?php elseif ( $job->status === 'processing' ) : ?>
                            <span class="smi-status-processing">⏳ <?php esc_html_e( 'Processing...', 'sell-my-images' ); ?></span>
                        <?php elseif ( $job->status === 'awaiting_payment' ) : ?>
                            <span class="smi-status-awaiting">💳 <?php esc_html_e( 'Awaiting Payment', 'sell-my-images' ); ?></span>
                        <?php elseif ( $job->status === 'abandoned' ) : ?>
                            <span class="smi-status-abandoned">❌ <?php esc_html_e( 'Abandoned', 'sell-my-images' ); ?></span>
                        <?php else : ?>
                            <span class="smi-muted-text">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render pagination info
     * 
     * @param array $jobs_data Jobs data with pagination
     */
    private function render_pagination_info( $jobs_data ) {
        $pagination = $jobs_data['pagination'] ?? array();
        if ( empty( $pagination ) ) {
            return;
        }
        
        $start = ( ( $pagination['current_page'] - 1 ) * $pagination['per_page'] ) + 1;
        $end = min( $pagination['current_page'] * $pagination['per_page'], $pagination['total_items'] );
        
        ?>
        <div class="smi-pagination-info">
            <?php
            /* translators: 1: start number, 2: end number, 3: total number */
            printf( esc_html__( 'Showing %1$d-%2$d of %3$d jobs', 'sell-my-images' ), 
                esc_html( $start ), 
                esc_html( $end ), 
                esc_html( $pagination['total_items'] ) 
            );
            ?>
        </div>
        <?php
    }
    
    /**
     * Render pagination controls
     * 
     * @param array $jobs_data Jobs data with pagination
     */
    private function render_pagination_controls( $jobs_data ) {
        $pagination = $jobs_data['pagination'] ?? array();
        if ( empty( $pagination ) || $pagination['total_pages'] <= 1 ) {
            return;
        }
        
        $current_page = $pagination['current_page'];
        $total_pages = $pagination['total_pages'];
        $per_page = $pagination['per_page'];
        
        // Build base URL with current filters
        $base_url = remove_query_arg( array( 'paged' ) );
        
        ?>
        <div class="smi-pagination-nav">
            <?php if ( $current_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_url ) ); ?>">&laquo; <?php esc_html_e( 'First', 'sell-my-images' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>">&lsaquo; <?php esc_html_e( 'Prev', 'sell-my-images' ); ?></a>
            <?php else : ?>
                <span class="disabled">&laquo; <?php esc_html_e( 'First', 'sell-my-images' ); ?></span>
                <span class="disabled">&lsaquo; <?php esc_html_e( 'Prev', 'sell-my-images' ); ?></span>
            <?php endif; ?>
            
            <?php
            // Show page numbers (max 5 pages around current)
            $start_page = max( 1, $current_page - 2 );
            $end_page = min( $total_pages, $current_page + 2 );
            
            for ( $i = $start_page; $i <= $end_page; $i++ ) :
                if ( $i === $current_page ) :
                    ?>
                    <span class="current"><?php echo esc_html( $i ); ?></span>
                    <?php
                else :
                    ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $i, $base_url ) ); ?>"><?php echo esc_html( $i ); ?></a>
                    <?php
                endif;
            endfor;
            ?>
            
            <?php if ( $current_page < $total_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>"><?php esc_html_e( 'Next', 'sell-my-images' ); ?> &rsaquo;</a>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $total_pages, $base_url ) ); ?>"><?php esc_html_e( 'Last', 'sell-my-images' ); ?> &raquo;</a>
            <?php else : ?>
                <span class="disabled"><?php esc_html_e( 'Next', 'sell-my-images' ); ?> &rsaquo;</span>
                <span class="disabled"><?php esc_html_e( 'Last', 'sell-my-images' ); ?> &raquo;</span>
            <?php endif; ?>
        </div>
        
        <div class="smi-per-page-controls">
            <label for="smi-per-page"><?php esc_html_e( 'Jobs per page:', 'sell-my-images' ); ?></label>
            <select id="smi-per-page" class="smi-per-page-select">
                <option value="10" <?php selected( $per_page, 10 ); ?>>10</option>
                <option value="25" <?php selected( $per_page, 25 ); ?>>25</option>
                <option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
                <option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
            </select>
        </div>
        <?php
    }
    
}