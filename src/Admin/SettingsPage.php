<?php
/**
 * Settings Page Class
 * 
 * Handles plugin settings configuration and display
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
 * SettingsPage class
 */
class SettingsPage {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_settings();
    }
    
    /**
     * Initialize settings
     */
    private function init_settings() {
        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register individual settings
        register_setting( 'smi_settings', 'smi_enabled', array(
            'type' => 'string',
            'default' => '1',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'smi_settings', 'smi_upsampler_api_key', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'smi_settings', 'smi_upsampler_webhook_secret', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'smi_settings', 'smi_stripe_test_mode', array(
            'type' => 'string',
            'default' => '1',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'smi_settings', 'smi_stripe_test_publishable_key', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'smi_settings', 'smi_stripe_test_secret_key', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'smi_settings', 'smi_stripe_live_publishable_key', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'smi_settings', 'smi_stripe_live_secret_key', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'smi_settings', 'smi_stripe_webhook_secret', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'smi_settings', 'smi_download_expiry_hours', array(
            'type' => 'integer',
            'default' => 24,
            'sanitize_callback' => array( $this, 'sanitize_download_expiry' )
        ) );
        
        register_setting( 'smi_settings', 'smi_markup_percentage', array(
            'type' => 'number',
            'default' => 200,
            'sanitize_callback' => array( $this, 'sanitize_markup_percentage' )
        ) );
        
        // General Settings Section
        add_settings_section(
            'smi_general_section',
            __( 'General Settings', 'sell-my-images' ),
            array( $this, 'general_section_callback' ),
            'smi_settings'
        );
        
        // Upsampler API Section
        add_settings_section(
            'smi_api_section',
            __( 'Upsampler API Configuration', 'sell-my-images' ),
            array( $this, 'api_section_callback' ),
            'smi_settings'
        );
        
        // Stripe Payment Section
        add_settings_section(
            'smi_stripe_section',
            __( 'Stripe Payment Configuration', 'sell-my-images' ),
            array( $this, 'stripe_section_callback' ),
            'smi_settings'
        );
        
        // Download Settings Section
        add_settings_section(
            'smi_download_section',
            __( 'Download Settings', 'sell-my-images' ),
            array( $this, 'download_section_callback' ),
            'smi_settings'
        );
        
        
        // Add individual settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add all settings fields
     */
    private function add_settings_fields() {
        // General Settings
        add_settings_field(
            'smi_enabled',
            __( 'Enable Plugin', 'sell-my-images' ),
            array( $this, 'enabled_field_callback' ),
            'smi_settings',
            'smi_general_section'
        );
        
        // API Settings
        add_settings_field(
            'smi_upsampler_api_key',
            __( 'Upsampler API Key', 'sell-my-images' ),
            array( $this, 'api_key_field_callback' ),
            'smi_settings',
            'smi_api_section'
        );
        
        add_settings_field(
            'smi_upsampler_webhook_secret',
            __( 'Upsampler Webhook Secret', 'sell-my-images' ),
            array( $this, 'upsampler_webhook_secret_field_callback' ),
            'smi_settings',
            'smi_api_section'
        );
        
        // Stripe Settings
        add_settings_field(
            'smi_stripe_test_mode',
            __( 'Test Mode', 'sell-my-images' ),
            array( $this, 'stripe_test_mode_field_callback' ),
            'smi_settings',
            'smi_stripe_section'
        );
        
        add_settings_field(
            'smi_stripe_test_publishable_key',
            __( 'Test Publishable Key', 'sell-my-images' ),
            array( $this, 'stripe_test_publishable_key_field_callback' ),
            'smi_settings',
            'smi_stripe_section'
        );
        
        add_settings_field(
            'smi_stripe_test_secret_key',
            __( 'Test Secret Key', 'sell-my-images' ),
            array( $this, 'stripe_test_secret_key_field_callback' ),
            'smi_settings',
            'smi_stripe_section'
        );
        
        add_settings_field(
            'smi_stripe_live_publishable_key',
            __( 'Live Publishable Key', 'sell-my-images' ),
            array( $this, 'stripe_live_publishable_key_field_callback' ),
            'smi_settings',
            'smi_stripe_section'
        );
        
        add_settings_field(
            'smi_stripe_live_secret_key',
            __( 'Live Secret Key', 'sell-my-images' ),
            array( $this, 'stripe_live_secret_key_field_callback' ),
            'smi_settings',
            'smi_stripe_section'
        );
        
        // Download Settings
        add_settings_field(
            'smi_download_expiry_hours',
            __( 'Download Link Expiry (Hours)', 'sell-my-images' ),
            array( $this, 'download_expiry_field_callback' ),
            'smi_settings',
            'smi_download_section'
        );
        
        // Pricing Settings
        add_settings_field(
            'smi_markup_percentage',
            __( 'Markup Percentage', 'sell-my-images' ),
            array( $this, 'markup_percentage_field_callback' ),
            'smi_settings',
            'smi_download_section'
        );
        
        add_settings_field(
            'smi_stripe_webhook_secret',
            __( 'Stripe Webhook Secret', 'sell-my-images' ),
            array( $this, 'stripe_webhook_secret_field_callback' ),
            'smi_settings',
            'smi_stripe_section'
        );
    }
    
    /**
     * Section callbacks
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__( 'Configure basic plugin settings and functionality.', 'sell-my-images' ) . '</p>';
    }
    
    public function api_section_callback() {
        echo '<p>' . esc_html__( 'Configure your Upsampler API key for image upscaling functionality.', 'sell-my-images' ) . '</p>';
        echo '<p><a href="https://upsampler.com" target="_blank">' . esc_html__( 'Get your free API key from Upsampler.com', 'sell-my-images' ) . '</a></p>';
    }
    
    public function stripe_section_callback() {
        echo '<p>' . esc_html__( 'Configure your Stripe API keys for payment processing. Use test mode for development.', 'sell-my-images' ) . '</p>';
        echo '<p><a href="https://dashboard.stripe.com/apikeys" target="_blank">' . esc_html__( 'Get your API keys from the Stripe Dashboard', 'sell-my-images' ) . '</a></p>';
    }
    
    public function download_section_callback() {
        echo '<p>' . esc_html__( 'Configure how long download links remain valid after purchase.', 'sell-my-images' ) . '</p>';
    }
    
    
    /**
     * Field callbacks
     */
    public function enabled_field_callback() {
        $value = get_option( 'smi_enabled', '1' );
        ?>
        <input type="checkbox" id="smi_enabled" name="smi_enabled" value="1" <?php checked( $value, '1' ); ?>>
        <label for="smi_enabled"><?php esc_html_e( 'Enable "Download Hi-Res" buttons on images', 'sell-my-images' ); ?></label>
        <?php
    }
    
    public function api_key_field_callback() {
        $value = get_option( 'smi_upsampler_api_key', '' );
        ?>
        <input type="password" id="smi_upsampler_api_key" name="smi_upsampler_api_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <button type="button" id="smi-test-api-key" class="button button-secondary" style="margin-left: 10px;">
            <?php esc_html_e( 'Test API Key', 'sell-my-images' ); ?>
        </button>
        <div id="smi-api-test-result" style="margin-top: 10px;"></div>
        <p class="description">
            <?php esc_html_e( 'Your Upsampler API key for image upscaling. Keep this secure and never share it publicly.', 'sell-my-images' ); ?>
        </p>
        <?php
    }
    
    /**
     * Upsampler webhook secret field callback
     */
    public function upsampler_webhook_secret_field_callback() {
        $value = get_option( 'smi_upsampler_webhook_secret', '' );
        ?>
        <input type="password" id="smi_upsampler_webhook_secret" name="smi_upsampler_webhook_secret" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="your-secret-key" />
        <p class="description">
            <?php esc_html_e( 'Secret key for authenticating Upsampler webhooks. Use this in your Upsampler webhook configuration as Authorization header or X-Webhook-Secret header.', 'sell-my-images' ); ?>
        </p>
        <p class="description">
            <strong><?php esc_html_e( 'Webhook URL:', 'sell-my-images' ); ?></strong> 
            <code><?php echo esc_url( home_url( '/smi-webhook/upsampler/' ) ); ?></code>
        </p>
        <?php
    }
    
    public function stripe_test_mode_field_callback() {
        $value = get_option( 'smi_stripe_test_mode', '1' );
        ?>
        <input type="checkbox" id="smi_stripe_test_mode" name="smi_stripe_test_mode" value="1" <?php checked( $value, '1' ); ?>>
        <label for="smi_stripe_test_mode"><?php esc_html_e( 'Enable test mode (recommended for development)', 'sell-my-images' ); ?></label>
        <p class="description">
            <?php esc_html_e( 'When enabled, all payments will be processed in test mode using your test API keys.', 'sell-my-images' ); ?>
        </p>
        <?php
    }
    
    public function stripe_test_publishable_key_field_callback() {
        $value = get_option( 'smi_stripe_test_publishable_key', '' );
        ?>
        <input type="text" id="smi_stripe_test_publishable_key" name="smi_stripe_test_publishable_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="pk_test_..." />
        <p class="description">
            <?php esc_html_e( 'Your Stripe test publishable key (starts with pk_test_)', 'sell-my-images' ); ?>
        </p>
        <?php
    }
    
    public function stripe_test_secret_key_field_callback() {
        $value = get_option( 'smi_stripe_test_secret_key', '' );
        ?>
        <input type="password" id="smi_stripe_test_secret_key" name="smi_stripe_test_secret_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="sk_test_..." />
        <p class="description">
            <?php esc_html_e( 'Your Stripe test secret key (starts with sk_test_)', 'sell-my-images' ); ?>
        </p>
        <?php
    }
    
    public function stripe_live_publishable_key_field_callback() {
        $value = get_option( 'smi_stripe_live_publishable_key', '' );
        ?>
        <input type="text" id="smi_stripe_live_publishable_key" name="smi_stripe_live_publishable_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="pk_live_..." />
        <p class="description">
            <?php esc_html_e( 'Your Stripe live publishable key (starts with pk_live_)', 'sell-my-images' ); ?>
        </p>
        <?php
    }
    
    public function stripe_live_secret_key_field_callback() {
        $value = get_option( 'smi_stripe_live_secret_key', '' );
        ?>
        <input type="password" id="smi_stripe_live_secret_key" name="smi_stripe_live_secret_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="sk_live_..." />
        <p class="description">
            <?php esc_html_e( 'Your Stripe live secret key (starts with sk_live_)', 'sell-my-images' ); ?>
        </p>
        <?php
    }
    
    public function download_expiry_field_callback() {
        $value = get_option( 'smi_download_expiry_hours', '24' );
        ?>
        <input type="number" id="smi_download_expiry_hours" name="smi_download_expiry_hours" value="<?php echo esc_attr( $value ); ?>" min="1" max="168" class="small-text" />
        <span><?php esc_html_e( 'hours', 'sell-my-images' ); ?></span>
        <p class="description">
            <?php esc_html_e( 'How long download links remain valid (1-168 hours / 1 week max).', 'sell-my-images' ); ?>
        </p>
        <?php
    }
    
    public function stripe_webhook_secret_field_callback() {
        $value = get_option( 'smi_stripe_webhook_secret', '' );
        ?>
        <input type="password" id="smi_stripe_webhook_secret" name="smi_stripe_webhook_secret" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="whsec_..." />
        <p class="description">
            <?php esc_html_e( 'Webhook endpoint secret from Stripe dashboard for secure webhook verification (optional but recommended).', 'sell-my-images' ); ?><br>
            <?php printf( __( 'Webhook URL: %s', 'sell-my-images' ), '<code>' . home_url( 'smi-webhook/stripe/' ) . '</code>' ); ?>
        </p>
        <?php
    }
    
    public function markup_percentage_field_callback() {
        $value = get_option( 'smi_markup_percentage', '200' );
        ?>
        <input type="number" id="smi_markup_percentage" name="smi_markup_percentage" value="<?php echo esc_attr( $value ); ?>" class="small-text" step="1" min="50" max="500" />
        <span>%</span>
        <p class="description">
            <?php esc_html_e( 'Markup percentage applied to Upsampler costs. Upsampler charges $0.04/credit (hardcoded).', 'sell-my-images' ); ?><br>
            <?php printf( esc_html__( 'Current setting: Customer pays %s%% more than our cost. Default: 200%% (3x total price)', 'sell-my-images' ), '<span id="markup-display">' . esc_html( $value ) . '</span>' ); ?>
        </p>
        <script>
        document.getElementById('smi_markup_percentage').addEventListener('input', function() {
            document.getElementById('markup-display').textContent = this.value;
        });
        </script>
        <?php
    }
    
    
    /**
     * Sanitize download expiry hours
     * 
     * @param mixed $value
     * @return int
     */
    public function sanitize_download_expiry( $value ) {
        $value = intval( $value );
        if ( $value < 1 || $value > 168 ) {
            $value = 24;
        }
        return $value;
    }
    
    /**
     * Sanitize markup percentage
     * 
     * @param mixed $value
     * @return int
     */
    public function sanitize_markup_percentage( $value ) {
        $value = intval( $value );
        if ( $value < 50 || $value > 500 ) {
            $value = 200;
        }
        return $value;
    }
}