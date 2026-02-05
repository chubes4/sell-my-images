<?php
/**
 * Admin Initialization Class
 * 
 * Handles admin-specific functionality and initialization
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Admin;

use SellMyImages\Config\Constants;
use SellMyImages\Api\Upsampler;
use StripeIntegration\StripeClient;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AdminInit class
 */
class AdminInit {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        $this->init_settings_page();
        
        // Register AJAX handlers for jobs page
        add_action( 'wp_ajax_smi_retry_upscale', array( $this, 'handle_ajax_retry_upscale' ) );
        add_action( 'wp_ajax_smi_resend_email', array( $this, 'handle_ajax_resend_email' ) );
        add_action( 'wp_ajax_smi_fix_broken_job', array( $this, 'handle_ajax_fix_broken_job' ) );

        // Add plugin action links
        add_filter( 'plugin_action_links_' . SMI_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
        
        // Add admin notices
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
        
        // Add startup health check
        add_action( 'admin_init', array( $this, 'run_health_check' ) );
        
    }
    
    /**
     * Add admin menu
     */
    private $hooks = array();

    public function add_admin_menu() {
        $this->hooks = array();

    $settings_hook = add_menu_page(
            __( 'Sell My Images Settings', 'sell-my-images' ),
            __( 'Sell My Images', 'sell-my-images' ),
            'manage_options',
            'sell-my-images',
            array( $this, 'render_settings_page' ),
            'dashicons-images-alt2',
            30
        );
    if ( $settings_hook ) { $this->hooks[] = $settings_hook; }
        
        // Add analytics submenu
    $analytics_hook = add_submenu_page(
            'sell-my-images',
            __( 'Analytics', 'sell-my-images' ),
            __( 'Analytics', 'sell-my-images' ),
            'manage_options',
            'sell-my-images-analytics',
            array( $this, 'render_analytics_page' )
        );
    if ( $analytics_hook ) { $this->hooks[] = $analytics_hook; }
        
        // Add jobs submenu
    $jobs_hook = add_submenu_page(
            'sell-my-images',
            __( 'Jobs', 'sell-my-images' ),
            __( 'Jobs', 'sell-my-images' ),
            'manage_options',
            'sell-my-images-jobs',
            array( $this, 'render_jobs_page' )
        );
    if ( $jobs_hook ) { $this->hooks[] = $jobs_hook; }

        // Enqueue admin assets only on our pages
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /** Enqueue SMI admin assets on our registered screens only */
    public function enqueue_admin_assets( $hook ) {
    if ( empty( $this->hooks ) || ! in_array( $hook, $this->hooks, true ) ) {
            return;
        }
    $css_path = SMI_PLUGIN_DIR . 'assets/css/admin.css';
    $js_path  = SMI_PLUGIN_DIR . 'assets/js/admin.js';
    $css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : SMI_VERSION;
    $js_ver   = file_exists( $js_path ) ? filemtime( $js_path ) : SMI_VERSION;

    wp_enqueue_style( 'smi-admin', SMI_PLUGIN_URL . 'assets/css/admin.css', array(), $css_ver );
    wp_enqueue_script( 'smi-admin', SMI_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), $js_ver, true );
    }
    
    private function init_settings_page() {
        if ( class_exists( 'SellMyImages\Admin\SettingsPage' ) ) {
            new SettingsPage();
        }
    }
    
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'sell-my-images' ) );
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php $this->show_page_notices(); ?>
            
            <form action="options.php" method="post">
                <?php settings_fields( 'smi_settings' ); ?>
                
                <?php
                // Render tabbed interface
                if ( class_exists( 'SellMyImages\\Admin\\SettingsPage' ) ) {
                    $settings_page = new SettingsPage();
                    $settings_page->render_tabbed_page();
                }
                ?>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'sell-my-images' ) );
        }
        
        if ( ! class_exists( 'SellMyImages\Admin\AnalyticsPage' ) ) {
            wp_die( esc_html__( 'Analytics page class not found.', 'sell-my-images' ) );
        }
        
        $analytics_page = new AnalyticsPage();
        $analytics_page->render_page();
    }
    
    /**
     * Render jobs page
     */
    public function render_jobs_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'sell-my-images' ) );
        }
        
        if ( ! class_exists( 'SellMyImages\Admin\JobsPage' ) ) {
            wp_die( esc_html__( 'Jobs page class not found.', 'sell-my-images' ) );
        }
        
        $jobs_page = new JobsPage();
        $jobs_page->render();
    }
    
    /**
     * Handle AJAX retry upscale request
     */
    public function handle_ajax_retry_upscale() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'smi_retry_upscale' ) ) {
            wp_die( 'Security check failed' );
        }
        
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions', 'sell-my-images' ) );
        }
        
        $job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );
        
        if ( empty( $job_id ) ) {
            wp_send_json_error( __( 'Invalid job ID', 'sell-my-images' ) );
        }
        
        // Get job data to verify it exists
        $job = \SellMyImages\Managers\DatabaseManager::get_row( array( 'job_id' => $job_id ) );
        
        if ( ! $job ) {
            wp_send_json_error( __( 'Job not found', 'sell-my-images' ) );
        }
        
        // Admin can retry ANY job - no restrictions
        // This provides maximum safety net for delivery issues
        
        
        // Trigger the payment completed action to start upscaling with admin override context
        do_action( 'smi_payment_completed', $job_id, array( 'admin_override' => true ) );

        wp_send_json_success( __( 'Upscaling process started successfully', 'sell-my-images' ) );
    }

    /**
     * Handle AJAX resend email request
     */
    public function handle_ajax_resend_email() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'smi_resend_email' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions', 'sell-my-images' ) );
        }

        $job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );

        if ( empty( $job_id ) ) {
            wp_send_json_error( __( 'Invalid job ID', 'sell-my-images' ) );
        }

        // Get job data to verify it exists and has download_token
        $job = \SellMyImages\Managers\JobManager::get_job( $job_id );

        if ( is_wp_error( $job ) ) {
            wp_send_json_error( __( 'Job not found', 'sell-my-images' ) );
        }

        if ( $job->status !== 'completed' ) {
            wp_send_json_error( __( 'Job is not completed yet', 'sell-my-images' ) );
        }

        if ( empty( $job->download_token ) ) {
            wp_send_json_error( __( 'Job is missing download token. Use "Fix Broken Job" instead.', 'sell-my-images' ) );
        }

        if ( empty( $job->upscaled_file_path ) || ! file_exists( $job->upscaled_file_path ) ) {
            wp_send_json_error( __( 'Upscaled file not found on server', 'sell-my-images' ) );
        }

        // Regenerate expiry if expired
        $expiry_hours = get_option( 'smi_download_expiry_hours', \SellMyImages\Config\Constants::DEFAULT_DOWNLOAD_EXPIRY_HOURS );
        $new_expiry = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_hours * 3600 ) );

        \SellMyImages\Managers\DatabaseManager::update(
            array( 'download_expires_at' => $new_expiry ),
            array( 'job_id' => $job_id )
        );

        // Resend email notification
        $email_result = \SellMyImages\Managers\DownloadManager::send_download_notification( $job_id );

        if ( $email_result ) {
            wp_send_json_success( __( 'Email resent successfully', 'sell-my-images' ) );
        } else {
            wp_send_json_error( __( 'Failed to send email. Check error logs.', 'sell-my-images' ) );
        }
    }

    /**
     * Handle AJAX fix broken job request
     */
    public function handle_ajax_fix_broken_job() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'smi_fix_broken_job' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions', 'sell-my-images' ) );
        }

        $job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );

        if ( empty( $job_id ) ) {
            wp_send_json_error( __( 'Invalid job ID', 'sell-my-images' ) );
        }

        // Get job data
        $job = \SellMyImages\Managers\JobManager::get_job( $job_id );

        if ( is_wp_error( $job ) ) {
            wp_send_json_error( __( 'Job not found', 'sell-my-images' ) );
        }

        if ( $job->status !== 'completed' ) {
            wp_send_json_error( __( 'Job must be completed first', 'sell-my-images' ) );
        }

        if ( empty( $job->upscaled_file_path ) || ! file_exists( $job->upscaled_file_path ) ) {
            wp_send_json_error( __( 'Upscaled file not found. Cannot fix this job.', 'sell-my-images' ) );
        }

        // Generate new download token and expiry
        $download_token = \SellMyImages\Managers\DownloadManager::generate_download_token();
        $expiry_hours = get_option( 'smi_download_expiry_hours', \SellMyImages\Config\Constants::DEFAULT_DOWNLOAD_EXPIRY_HOURS );
        $expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_hours * 3600 ) );

        // Update job with download data
        $update_result = \SellMyImages\Managers\DatabaseManager::update(
            array(
                'download_token' => $download_token,
                'download_expires_at' => $expires_at,
            ),
            array( 'job_id' => $job_id )
        );

        if ( is_wp_error( $update_result ) ) {
            wp_send_json_error( __( 'Failed to update job record', 'sell-my-images' ) );
        }

        // Send email notification
        $email_result = \SellMyImages\Managers\DownloadManager::send_download_notification( $job_id );

        if ( $email_result ) {
            wp_send_json_success( __( 'Job fixed and email sent successfully', 'sell-my-images' ) );
        } else {
            wp_send_json_error( __( 'Job fixed but email send failed. Check error logs.', 'sell-my-images' ) );
        }
    }

    /**
     * Show page-specific notices
     */
    private function show_page_notices() {
        // Check if API key is configured
        $api_key = get_option( 'smi_upsampler_api_key', '' );
        
        if ( empty( $api_key ) ) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e( 'Upsampler API Key Required', 'sell-my-images' ); ?></strong><br>
                    <?php esc_html_e( 'Please configure your Upsampler API key below to enable image upscaling functionality.', 'sell-my-images' ); ?>
                    <a href="https://upsampler.com" target="_blank"><?php esc_html_e( 'Get your API key here', 'sell-my-images' ); ?></a>
                </p>
            </div>
            <?php
        }
        
    }
    
    
    /**
     * Add plugin action links
     * 
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=sell-my-images' ),
            __( 'Settings', 'sell-my-images' )
        );
        
        array_unshift( $links, $settings_link );
        
        return $links;
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Only show on our admin pages
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'sell-my-images' ) === false ) {
            return;
        }
        
        // Show any stored admin notices
        $notices = get_transient( 'smi_admin_notices' );
        
        if ( $notices && is_array( $notices ) ) {
            foreach ( $notices as $notice ) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr( $notice['type'] ),
                    esc_html( $notice['message'] )
                );
            }
            
            delete_transient( 'smi_admin_notices' );
        }
    }
    
    
    
    /**
     * Add admin notice
     * 
     * @param string $message Notice message
     * @param string $type Notice type (success, error, warning, info)
     */
    public static function add_admin_notice( $message, $type = 'info' ) {
        $notices = get_transient( 'smi_admin_notices' ) ?: array();
        
        $notices[] = array(
            'message' => $message,
            'type' => $type,
        );
        
        set_transient( 'smi_admin_notices', $notices, 300 ); // 5 minutes
    }
    
    /**
     * Run configuration health check
     */
    public function run_health_check() {
        // Only run health check once per day
        $last_check = get_transient( 'smi_last_health_check' );
        if ( $last_check ) {
            return;
        }
        
        // Set flag to prevent frequent checks
        set_transient( 'smi_last_health_check', time(), DAY_IN_SECONDS );
        
        $issues = $this->validate_configuration();
        
        if ( ! empty( $issues ) ) {
            foreach ( $issues as $issue ) {
                self::add_admin_notice( $issue['message'], $issue['type'] );
            }
        }
    }
    
    /**
     * Validate complete plugin configuration
     * 
     * @return array Array of configuration issues
     */
    public function validate_configuration() {
        $issues = array();
        
        
        // Validate Upsampler configuration
        $upsampler_issues = $this->validate_upsampler_config();
        $issues = array_merge( $issues, $upsampler_issues );
        
        // Validate Stripe configuration
        $stripe_issues = $this->validate_stripe_config();
        $issues = array_merge( $issues, $stripe_issues );
        
        // Validate WordPress environment
        $wp_issues = $this->validate_wordpress_environment();
        $issues = array_merge( $issues, $wp_issues );
        
        // Validate database tables
        $db_issues = $this->validate_database_tables();
        $issues = array_merge( $issues, $db_issues );
        
        return $issues;
    }
    
    /**
     * Validate Upsampler configuration
     * 
     * @return array Issues found
     */
    private function validate_upsampler_config() {
        $issues = array();
        
        $api_key = get_option( 'smi_upsampler_api_key', '' );
        
        if ( empty( $api_key ) ) {
            $issues[] = array(
                'type' => 'error',
                'message' => __( 'Upsampler API key is not configured. Image upscaling will not work.', 'sell-my-images' ),
            );
        }
        
        return $issues;
    }
    
    /**
     * Validate Stripe configuration
     *
     * Uses the shared stripe-integration plugin for configuration.
     *
     * @return array Issues found
     */
    private function validate_stripe_config() {
        $issues = array();

        // Check if shared stripe-integration plugin is configured.
        if ( ! stripe_integration_is_configured() ) {
            $mode_name = stripe_integration_is_test_mode() ? 'test' : 'live';
            $issues[]  = array(
                'type'    => 'error',
                /* translators: %s: mode name (test or live) */
                'message' => sprintf( __( 'Stripe %s mode keys are not configured. Go to Settings > Stripe Integration to configure.', 'sell-my-images' ), $mode_name ),
            );
        } else {
            // Test Stripe configuration using shared plugin.
            $result = StripeClient::validate_configuration();
            if ( is_wp_error( $result ) ) {
                $issues[] = array(
                    'type'    => 'warning',
                    /* translators: %s: error message */
                    'message' => sprintf( __( 'Stripe configuration validation failed: %s', 'sell-my-images' ), $result->get_error_message() ),
                );
            }
        }

        return $issues;
    }
    
    /**
     * Validate WordPress environment
     * 
     * @return array Issues found
     */
    private function validate_wordpress_environment() {
        $issues = array();
        
        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            $issues[] = array(
                'type' => 'error',
                /* translators: %s: PHP version number */
                'message' => sprintf( __( 'PHP version %s is not supported. Please upgrade to PHP 7.4 or higher.', 'sell-my-images' ), PHP_VERSION ),
            );
        }
        
        // Check WordPress version
        global $wp_version;
        if ( version_compare( $wp_version, '5.0', '<' ) ) {
            $issues[] = array(
                'type' => 'error',
                /* translators: %s: WordPress version number */
                'message' => sprintf( __( 'WordPress version %s is not supported. Please upgrade to WordPress 5.0 or higher.', 'sell-my-images' ), $wp_version ),
            );
        }
        
        // Check if SSL is enabled for live mode.
        if ( ! stripe_integration_is_test_mode() && ! is_ssl() ) {
            $issues[] = array(
                'type' => 'error',
                'message' => __( 'SSL is required for live payment processing. Please enable HTTPS.', 'sell-my-images' ),
            );
        }
        
        // Check upload directory writable
        $upload_dir = wp_upload_dir();
        if ( ! wp_is_writable( $upload_dir['basedir'] ) ) {
            $issues[] = array(
                'type' => 'error',
                'message' => __( 'Upload directory is not writable. File downloads will not work.', 'sell-my-images' ),
            );
        }
        
        return $issues;
    }
    
    /**
     * Validate database tables
     * 
     * @return array Issues found
     */
    private function validate_database_tables() {
        $issues = array();
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smi_jobs';
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        
        if ( ! $table_exists ) {
            $issues[] = array(
                'type' => 'error',
                'message' => __( 'Database table is missing. Please deactivate and reactivate the plugin.', 'sell-my-images' ),
            );
        }
        
        return $issues;
    }
}