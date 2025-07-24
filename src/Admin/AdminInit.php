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
        
        // Initialize settings page immediately
        $this->init_settings_page();
        
        // Initialize analytics page
        $this->init_analytics_page();
        
        // Add plugin action links
        add_filter( 'plugin_action_links_' . SMI_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
        
        // Add admin notices
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
        
        // Handle AJAX requests for admin
        add_action( 'wp_ajax_smi_test_api_key', array( $this, 'ajax_test_api_key' ) );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Sell My Images Settings', 'sell-my-images' ),
            __( 'Sell My Images', 'sell-my-images' ),
            'manage_options',
            'sell-my-images',
            array( $this, 'render_settings_page' ),
            'dashicons-images-alt2',
            30
        );
        
        // Add analytics submenu
        add_submenu_page(
            'sell-my-images',
            __( 'Analytics', 'sell-my-images' ),
            __( 'Analytics', 'sell-my-images' ),
            'manage_options',
            'sell-my-images-analytics',
            array( $this, 'render_analytics_page' )
        );
    }
    
    /**
     * Initialize settings page
     */
    public function init_settings_page() {
        if ( ! class_exists( 'SellMyImages\Admin\SettingsPage' ) ) {
            return;
        }
        
        new SettingsPage();
    }
    
    /**
     * Initialize analytics page
     */
    public function init_analytics_page() {
        // Analytics page is initialized on demand when accessed
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'sell-my-images' ) );
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php $this->show_page_notices(); ?>
            
            <form action="options.php" method="post">
                <?php
                settings_fields( 'smi_settings' );
                do_settings_sections( 'smi_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'sell-my-images' ) );
        }
        
        if ( ! class_exists( 'SellMyImages\Admin\AnalyticsPage' ) ) {
            wp_die( __( 'Analytics page class not found.', 'sell-my-images' ) );
        }
        
        $analytics_page = new AnalyticsPage();
        $analytics_page->render_page();
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
        
        // Check if plugin is enabled
        if ( ! get_option( 'smi_enabled', '1' ) ) {
            ?>
            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e( 'Plugin Disabled', 'sell-my-images' ); ?></strong><br>
                    <?php esc_html_e( 'The plugin is currently disabled. Enable it below to start showing buy buttons on images.', 'sell-my-images' ); ?>
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
     * AJAX handler to test API key
     */
    public function ajax_test_api_key() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'smi_admin_nonce' ) ) {
            wp_send_json_error( __( 'Security check failed', 'sell-my-images' ) );
        }
        
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions', 'sell-my-images' ) );
        }
        
        $api_key = sanitize_text_field( $_POST['api_key'] );
        
        if ( empty( $api_key ) ) {
            wp_send_json_error( __( 'API key is required', 'sell-my-images' ) );
        }
        
        // Test the API key using a flexible approach
        $result = $this->validate_upscaler_api_key( $api_key );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        
        wp_send_json_success( __( 'API key is valid!', 'sell-my-images' ) );
    }
    
    /**
     * Validate Upscaler API key (dependency injection ready)
     * 
     * @param string $api_key API key to validate
     * @return mixed Result from validation or WP_Error on failure
     */
    private function validate_upscaler_api_key( $api_key ) {
        // Use new Upsampler API client
        return \SellMyImages\Api\Upsampler::validate_api_key( $api_key );
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
}