<?php
/**
 * Plugin Name: Sell My Images
 * Plugin URI: https://chubes.net/
 * Description: Monetize your website images by selling high-resolution versions with AI upscaling and secure payment processing. Perfect for photographers, bloggers, and content creators.
 * Version: 1.2.0
 * Author: Chris Huber
 * Author URI: https://chubes.net/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sell-my-images
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SMI_VERSION', '1.2.0' );
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
        // Initialize admin functionality (for admin pages and admin AJAX requests)
        if ( is_admin() ) {
            $this->init_admin();
        }
        
        // Initialize frontend functionality (for frontend AND AJAX requests)
        if ( ! is_admin() || wp_doing_ajax() ) {
            $this->init_frontend();
        }
    }
    
    private function init_admin() {
        new \SellMyImages\Admin\AdminInit();
    }
    
    private function init_frontend() {
        \SellMyImages\Managers\WebhookManager::init();
        new \SellMyImages\Content\BlockProcessor();
        new \SellMyImages\Api\RestApi();
        new \SellMyImages\Services\PaymentService();
        new \SellMyImages\Services\UpscalingService();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load Composer autoloader
        require_once SMI_PLUGIN_DIR . 'vendor/autoload.php';
    }
    
    public function enqueue_frontend_assets() {
        $should_load = false;
        
        // Load on singular posts where buttons should show
        if ( is_singular() && \SellMyImages\Content\FilterManager::should_show_buttons() ) {
            $should_load = true;
        }
        
        // Allow external themes/plugins to trigger asset loading
        $should_load = apply_filters( 'smi_load_assets', $should_load );
        
        if ( ! $should_load ) {
            return;
        }
        
        wp_enqueue_script( 'smi-modal', SMI_PLUGIN_URL . 'assets/js/modal.js', array( 'jquery' ), SMI_VERSION, true );
        wp_enqueue_style( 'smi-modal', SMI_PLUGIN_URL . 'assets/css/modal.css', array(), SMI_VERSION );
        
        // Localize script for AJAX
        wp_localize_script( 'smi-modal', 'smi_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'smi_nonce' ),
            'post_id'  => get_the_ID(), // Add current post ID for JavaScript
            'strings'  => array(
                'processing' => __( 'Processing...', 'sell-my-images' ),
                'error'      => __( 'An error occurred. Please try again.', 'sell-my-images' ),
                'download'   => __( 'Download', 'sell-my-images' ),
            ),
            'terms_conditions_url' => get_option( 'smi_terms_conditions_url', '' ),
        ) );
        
        // Add REST API settings for modal JavaScript
        wp_localize_script( 'smi-modal', 'wpApiSettings', array(
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ) );
        
        // Output modal HTML to footer
        add_action( 'wp_footer', array( $this, 'output_modal_html' ) );
    }
    
    public function output_modal_html() {
        $template_path = SMI_PLUGIN_DIR . 'templates/modal.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }
    
    // Admin assets are enqueued by AdminInit on specific SMI pages.
    
    /*
     * Plugin activation
     */
    public function activate() {
        // Create database tables using DatabaseManager
        if ( ! \SellMyImages\Managers\DatabaseManager::create_tables() ) {
            // Database table creation failed during activation
            wp_die( esc_html__( 'Failed to create database tables during plugin activation.', 'sell-my-images' ) );
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
        $default_options = \SellMyImages\Config\Constants::DEFAULT_OPTIONS;
        
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