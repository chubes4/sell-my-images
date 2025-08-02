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

use SellMyImages\Content\FilterManager;

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
        
        register_setting( 'smi_settings', 'smi_upsampler_api_key', array(
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
        
        register_setting( 'smi_settings', 'smi_terms_conditions_url', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw'
        ) );
        
        // Button Display Filter Settings
        register_setting( 'smi_settings', 'smi_display_mode', array(
            'type' => 'string',
            'default' => 'all',
            'sanitize_callback' => array( $this, 'sanitize_display_mode' )
        ) );
        
        register_setting( 'smi_settings', 'smi_filter_post_types', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array( $this, 'sanitize_post_types' )
        ) );
        
        register_setting( 'smi_settings', 'smi_filter_categories', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array( $this, 'sanitize_categories' )
        ) );
        
        register_setting( 'smi_settings', 'smi_filter_tags', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array( $this, 'sanitize_tags' )
        ) );
        
        register_setting( 'smi_settings', 'smi_filter_post_ids', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array( $this, 'sanitize_post_ids' )
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
        
        // Button Display Section
        add_settings_section(
            'smi_display_section',
            __( 'Button Display Control', 'sell-my-images' ),
            array( $this, 'display_section_callback' ),
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
        // General Settings (removed enabled field - use plugin activation/deactivation instead)
        
        // API Settings
        add_settings_field(
            'smi_upsampler_api_key',
            __( 'Upsampler API Key', 'sell-my-images' ),
            array( $this, 'api_key_field_callback' ),
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
        
        add_settings_field(
            'smi_terms_conditions_url',
            __( 'Terms & Conditions URL', 'sell-my-images' ),
            array( $this, 'terms_conditions_url_field_callback' ),
            'smi_settings',
            'smi_download_section'
        );
        
        // Button Display Fields
        add_settings_field(
            'smi_display_mode',
            __( 'Display Mode', 'sell-my-images' ),
            array( $this, 'display_mode_field_callback' ),
            'smi_settings',
            'smi_display_section'
        );
        
        add_settings_field(
            'smi_filter_criteria',
            __( 'Filter Criteria', 'sell-my-images' ),
            array( $this, 'filter_criteria_field_callback' ),
            'smi_settings',
            'smi_display_section'
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
        echo '<p class="description">';
        echo '<strong>' . esc_html__( 'Webhook URL:', 'sell-my-images' ) . '</strong> ';
        echo '<code>' . esc_url( home_url( '/smi-webhook/upsampler/' ) ) . '</code>';
        echo '<br>' . esc_html__( 'Use this URL in your Upsampler webhook configuration. No authentication required.', 'sell-my-images' );
        echo '</p>';
    }
    
    public function stripe_section_callback() {
        echo '<p>' . esc_html__( 'Configure your Stripe API keys for payment processing. Use test mode for development.', 'sell-my-images' ) . '</p>';
        echo '<p><a href="https://dashboard.stripe.com/apikeys" target="_blank">' . esc_html__( 'Get your API keys from the Stripe Dashboard', 'sell-my-images' ) . '</a></p>';
    }
    
    public function display_section_callback() {
        echo '<p>' . esc_html__( 'Control where download buttons appear on your site. Choose to show buttons on all posts, or selectively include/exclude specific content.', 'sell-my-images' ) . '</p>';
    }
    
    public function download_section_callback() {
        echo '<p>' . esc_html__( 'Configure how long download links remain valid after purchase.', 'sell-my-images' ) . '</p>';
    }
    
    
    /**
     * Field callbacks
     */
    
    public function api_key_field_callback() {
        $value = get_option( 'smi_upsampler_api_key', '' );
        ?>
        <input type="password" id="smi_upsampler_api_key" name="smi_upsampler_api_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Your Upsampler API key for image upscaling. Keep this secure and never share it publicly.', 'sell-my-images' ); ?>
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
            <?php 
            /* translators: %s: webhook URL */
            printf( esc_html__( 'Webhook URL: %s', 'sell-my-images' ), '<code>' . esc_url( home_url( 'smi-webhook/stripe/' ) ) . '</code>' ); ?>
        </p>
        <?php
    }
    
    public function markup_percentage_field_callback() {
        $value = get_option( 'smi_markup_percentage', '500' );
        ?>
        <input type="number" id="smi_markup_percentage" name="smi_markup_percentage" value="<?php echo esc_attr( $value ); ?>" class="small-text" step="1" min="50" max="1200" />
        <span>%</span>
        <p class="description">
            <?php esc_html_e( 'Markup percentage applied to Upsampler costs. Upsampler charges $0.04/credit (hardcoded).', 'sell-my-images' ); ?><br>
            <?php 
            /* translators: %s: markup percentage value */
            printf( esc_html__( 'Current setting: Customer pays %s%% more than our cost. Default: 500%% (6x total price). Minimum $0.50 enforced for Stripe.', 'sell-my-images' ), '<span id="markup-display">' . esc_html( $value ) . '</span>' ); ?>
        </p>
        <script>
        document.getElementById('smi_markup_percentage').addEventListener('input', function() {
            document.getElementById('markup-display').textContent = this.value;
        });
        </script>
        <?php
    }
    
    public function terms_conditions_url_field_callback() {
        $value = get_option( 'smi_terms_conditions_url', '' );
        ?>
        <input type="url" id="smi_terms_conditions_url" name="smi_terms_conditions_url" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="https://yoursite.com/terms" />
        <p class="description">
            <?php esc_html_e( 'Optional: Link to your Terms & Conditions page. If provided, a link will appear in the purchase modal and download emails.', 'sell-my-images' ); ?>
        </p>
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
        if ( $value < 50 || $value > 1200 ) {
            $value = 500;
        }
        return $value;
    }
    
    /**
     * Display mode field callback
     */
    public function display_mode_field_callback() {
        $value = get_option( 'smi_display_mode', 'all' );
        ?>
        <fieldset>
            <legend class="screen-reader-text"><?php esc_html_e( 'Display Mode', 'sell-my-images' ); ?></legend>
            <p>
                <label>
                    <input type="radio" name="smi_display_mode" value="all" <?php checked( $value, 'all' ); ?> />
                    <?php esc_html_e( 'All Posts', 'sell-my-images' ); ?>
                    <span class="description"><?php esc_html_e( ' - Show buttons on all eligible posts (current behavior)', 'sell-my-images' ); ?></span>
                </label>
            </p>
            <p>
                <label>
                    <input type="radio" name="smi_display_mode" value="exclude" <?php checked( $value, 'exclude' ); ?> />
                    <?php esc_html_e( 'Exclude Selected', 'sell-my-images' ); ?>
                    <span class="description"><?php esc_html_e( ' - Hide buttons on posts matching the criteria below', 'sell-my-images' ); ?></span>
                </label>
            </p>
            <p>
                <label>
                    <input type="radio" name="smi_display_mode" value="include" <?php checked( $value, 'include' ); ?> />
                    <?php esc_html_e( 'Include Only Selected', 'sell-my-images' ); ?>
                    <span class="description"><?php esc_html_e( ' - Show buttons only on posts matching the criteria below', 'sell-my-images' ); ?></span>
                </label>
            </p>
        </fieldset>
        <?php
    }
    
    /**
     * Filter criteria field callback (table layout)
     */
    public function filter_criteria_field_callback() {
        $display_mode = get_option( 'smi_display_mode', 'all' );
        $post_types = get_option( 'smi_filter_post_types', array() );
        $categories = get_option( 'smi_filter_categories', array() );
        $tags = get_option( 'smi_filter_tags', array() );
        $post_ids = get_option( 'smi_filter_post_ids', '' );
        
        $is_hidden = ( $display_mode === 'all' ) ? 'style="display: none;"' : '';
        ?>
        <div id="smi-filter-criteria-table" <?php echo $is_hidden; ?>>
            <table class="smi-filter-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Post Types', 'sell-my-images' ); ?></th>
                        <th><?php esc_html_e( 'Categories', 'sell-my-images' ); ?></th>
                        <th><?php esc_html_e( 'Tags', 'sell-my-images' ); ?></th>
                        <th><?php esc_html_e( 'Specific Post IDs', 'sell-my-images' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td data-label="<?php esc_attr_e( 'Post Types', 'sell-my-images' ); ?>">
                            <?php
                            $available_post_types = FilterManager::get_available_post_types();
                            foreach ( $available_post_types as $post_type => $post_type_obj ) {
                                $checked = in_array( $post_type, $post_types, true ) ? 'checked' : '';
                                printf(
                                    '<label><input type="checkbox" name="smi_filter_post_types[]" value="%s" %s /> %s</label><br>',
                                    esc_attr( $post_type ),
                                    $checked,
                                    esc_html( $post_type_obj->labels->name )
                                );
                            }
                            ?>
                        </td>
                        <td data-label="<?php esc_attr_e( 'Categories', 'sell-my-images' ); ?>">
                            <div class="smi-filter-scrollable">
                                <?php
                                $available_categories = FilterManager::get_available_categories();
                                foreach ( $available_categories as $category ) {
                                    $checked = in_array( $category->term_id, array_map( 'intval', $categories ), true ) ? 'checked' : '';
                                    printf(
                                        '<label><input type="checkbox" name="smi_filter_categories[]" value="%s" %s /> %s</label><br>',
                                        esc_attr( $category->term_id ),
                                        $checked,
                                        esc_html( $category->name )
                                    );
                                }
                                if ( empty( $available_categories ) ) {
                                    echo '<em>' . esc_html__( 'No categories found', 'sell-my-images' ) . '</em>';
                                }
                                ?>
                            </div>
                        </td>
                        <td data-label="<?php esc_attr_e( 'Tags', 'sell-my-images' ); ?>">
                            <div class="smi-filter-scrollable">
                                <?php
                                $available_tags = FilterManager::get_available_tags();
                                foreach ( $available_tags as $tag ) {
                                    $checked = in_array( $tag->term_id, array_map( 'intval', $tags ), true ) ? 'checked' : '';
                                    printf(
                                        '<label><input type="checkbox" name="smi_filter_tags[]" value="%s" %s /> %s</label><br>',
                                        esc_attr( $tag->term_id ),
                                        $checked,
                                        esc_html( $tag->name )
                                    );
                                }
                                if ( empty( $available_tags ) ) {
                                    echo '<em>' . esc_html__( 'No tags found', 'sell-my-images' ) . '</em>';
                                }
                                ?>
                            </div>
                        </td>
                        <td data-label="<?php esc_attr_e( 'Specific Post IDs', 'sell-my-images' ); ?>">
                            <textarea 
                                name="smi_filter_post_ids" 
                                id="smi_filter_post_ids" 
                                rows="6" 
                                cols="20" 
                                placeholder="123, 456, 789"
                            ><?php echo esc_textarea( $post_ids ); ?></textarea>
                            <p class="description">
                                <?php esc_html_e( 'Enter post IDs separated by commas. Example: 123, 456, 789', 'sell-my-images' ); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="description">
                <?php esc_html_e( 'Select any combination of criteria. Posts matching ANY of the selected criteria will be included/excluded based on the display mode above.', 'sell-my-images' ); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Sanitize display mode
     * 
     * @param mixed $value
     * @return string
     */
    public function sanitize_display_mode( $value ) {
        $valid_modes = array( 'all', 'exclude', 'include' );
        return in_array( $value, $valid_modes, true ) ? $value : 'all';
    }
    
    /**
     * Sanitize post types
     * 
     * @param mixed $value
     * @return array
     */
    public function sanitize_post_types( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }
        
        $available_post_types = array_keys( FilterManager::get_available_post_types() );
        $sanitized = array();
        
        foreach ( $value as $post_type ) {
            if ( in_array( $post_type, $available_post_types, true ) ) {
                $sanitized[] = sanitize_text_field( $post_type );
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize categories
     * 
     * @param mixed $value
     * @return array
     */
    public function sanitize_categories( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }
        
        $sanitized = array();
        foreach ( $value as $category_id ) {
            $category_id = intval( $category_id );
            if ( $category_id > 0 && term_exists( $category_id, 'category' ) ) {
                $sanitized[] = $category_id;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize tags
     * 
     * @param mixed $value
     * @return array
     */
    public function sanitize_tags( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }
        
        $sanitized = array();
        foreach ( $value as $tag_id ) {
            $tag_id = intval( $tag_id );
            if ( $tag_id > 0 && term_exists( $tag_id, 'post_tag' ) ) {
                $sanitized[] = $tag_id;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize post IDs
     * 
     * @param mixed $value
     * @return string
     */
    public function sanitize_post_ids( $value ) {
        if ( empty( $value ) ) {
            return '';
        }
        
        $post_ids = array_map( 'trim', explode( ',', $value ) );
        $valid_post_ids = array();
        
        foreach ( $post_ids as $post_id ) {
            $post_id = intval( $post_id );
            if ( $post_id > 0 && get_post( $post_id ) ) {
                $valid_post_ids[] = $post_id;
            }
        }
        
        return implode( ', ', $valid_post_ids );
    }
}