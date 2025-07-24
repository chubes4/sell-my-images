<?php
/**
 * Plugin Name: Sell My Images
 * Plugin URI: https://chubes.net/
 * Description: Monetize your website images by selling high-resolution versions with AI upscaling and secure payment processing. Perfect for photographers, bloggers, and content creators.
 * Version: 1.0.0
 * Author: Chris Huber
 * Author URI: https://chubes.net/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sell-my-images
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SMI_VERSION', '1.0.0' );
define( 'SMI_PLUGIN_FILE', __FILE__ );
define( 'SMI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Sell My Images Plugin Class
 */
class SellMyImages {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Plugin initialization
     */
    public function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        
        // Plugin activation/deactivation hooks
        register_activation_hook( SMI_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( SMI_PLUGIN_FILE, array( $this, 'deactivate' ) );
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'sell-my-images', false, dirname( SMI_PLUGIN_BASENAME ) . '/languages' );
    }
    
    /**
     * Plugin initialization
     */
    public function init() {
        // Initialize admin functionality
        if ( is_admin() ) {
            $this->init_admin();
        }
        
        // Initialize frontend functionality
        if ( ! is_admin() ) {
            $this->init_frontend();
        }
    }
    
    /**
     * Initialize admin functionality
     */
    private function init_admin() {
        new \SellMyImages\Admin\AdminInit();
    }
    
    /**
     * Initialize frontend functionality
     */
    private function init_frontend() {
        // Initialize components
        new \SellMyImages\Content\BlockProcessor();
        new \SellMyImages\Api\RestApi();
        new \SellMyImages\Api\StripePayment();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load Composer autoloader
        require_once SMI_PLUGIN_DIR . 'vendor/autoload.php';
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on single posts/pages with content
        if ( ! is_singular() ) {
            return;
        }
        
        // Only load assets if the current post has image blocks
        if ( ! \SellMyImages\Content\BlockProcessor::post_has_image_blocks() ) {
            return;
        }
        
        wp_enqueue_script(
            'smi-modal',
            SMI_PLUGIN_URL . 'assets/js/modal.js',
            array( 'jquery' ),
            SMI_VERSION,
            true
        );
        
        wp_enqueue_style(
            'smi-modal',
            SMI_PLUGIN_URL . 'assets/css/modal.css',
            array(),
            SMI_VERSION
        );
        
        // Localize script for AJAX
        wp_localize_script( 'smi-modal', 'smi_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'smi_nonce' ),
            'strings'  => array(
                'processing' => __( 'Processing...', 'sell-my-images' ),
                'error'      => __( 'An error occurred. Please try again.', 'sell-my-images' ),
                'download'   => __( 'Download', 'sell-my-images' ),
            ),
        ) );
        
        // Add REST API settings for modal JavaScript
        wp_localize_script( 'smi-modal', 'wpApiSettings', array(
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ) );
        
        // Output modal HTML to footer
        add_action( 'wp_footer', array( $this, 'output_modal_html' ) );
    }
    
    /**
     * Output modal HTML to footer
     */
    public function output_modal_html() {
        $template_path = SMI_PLUGIN_DIR . 'templates/modal.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our admin pages - updated for top-level menu and analytics submenu
        $allowed_hooks = array(
            'toplevel_page_sell-my-images',
            'sell-my-images_page_sell-my-images-analytics'
        );
        
        if ( ! in_array( $hook, $allowed_hooks, true ) ) {
            return;
        }
        
        wp_enqueue_style(
            'smi-admin',
            SMI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SMI_VERSION
        );
        
        wp_enqueue_script(
            'smi-admin',
            SMI_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            SMI_VERSION,
            true
        );
        
        // Localize script for admin functionality
        wp_localize_script( 'smi-admin', 'smi_admin', array(
            'nonce' => wp_create_nonce( 'smi_admin_nonce' ),
        ) );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables using DatabaseManager
        if ( ! \SellMyImages\Api\DatabaseManager::create_tables() ) {
            error_log( 'SMI: Failed to create database tables during activation' );
        }
        
        // Set default options
        $this->set_default_options();
        
        // Clear rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear rewrite rules
        flush_rewrite_rules();
    }
    
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'smi_enabled'                      => '1',
            'smi_upsampler_api_key'            => '',
            'smi_upsampler_webhook_secret'     => '',
            'smi_stripe_test_mode'             => '1',
            'smi_stripe_test_publishable_key'  => '',
            'smi_stripe_test_secret_key'       => '',
            'smi_stripe_live_publishable_key'  => '',
            'smi_stripe_live_secret_key'       => '',
            'smi_stripe_webhook_secret'        => '',
            'smi_download_expiry_hours'        => '24',
            'smi_markup_percentage'            => '200',
        );
        
        foreach ( $default_options as $option_name => $option_value ) {
            if ( ! get_option( $option_name ) ) {
                add_option( $option_name, $option_value );
            }
        }
    }
}

// Initialize the plugin
function sell_my_images() {
    return SellMyImages::get_instance();
}

// Start the plugin
sell_my_images();