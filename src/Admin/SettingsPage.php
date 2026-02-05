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
        // Register individual settings - simplified for tabbed interface
        
        register_setting( 'smi_settings', 'smi_upsampler_api_key', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        // Note: Stripe settings are now managed by the stripe-integration plugin.
        // Go to Settings > Stripe Integration to configure payment processing.

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
        
        register_setting( 'smi_settings', 'smi_button_text', array(
            'type' => 'string',
            'default' => __( 'Download Hi-Res', 'sell-my-images' ),
            'sanitize_callback' => array( $this, 'sanitize_button_text' )
        ) );

        register_setting( 'smi_settings', 'smi_include_featured_images', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => array( $this, 'sanitize_boolean' )
        ) );
    }
    
    /**
     * Render tabbed settings page interface
     * 
     * Professional three-tab interface introduced in v1.2.0 for enhanced organization:
     * 
     * - API Configuration Tab: Upsampler & Stripe setup with webhook documentation
     * - Display Control Tab: Button filtering with always-visible criteria table
     * - Download Settings Tab: Expiry, pricing, and terms configuration
     * 
     * Features responsive design, progressive enhancement, and contextual disabled states.
     * Maintains single form submission for efficient processing.
     */
    public function render_tabbed_page() {
        ?>
        <div class="smi-tabbed-settings">
            <!-- Tab Navigation -->
            <nav class="smi-tab-nav">
                <a href="#api" class="smi-tab-link smi-tab-active" data-tab="api">
                    <span class="dashicons dashicons-cloud"></span>
                    <?php esc_html_e( 'API Configuration', 'sell-my-images' ); ?>
                </a>
                <a href="#display" class="smi-tab-link" data-tab="display">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php esc_html_e( 'Display Control', 'sell-my-images' ); ?>
                </a>
                <a href="#downloads" class="smi-tab-link" data-tab="downloads">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Download Settings', 'sell-my-images' ); ?>
                </a>
            </nav>

            <!-- Tab Content -->
            <div class="smi-tab-content">
                <!-- API Configuration Tab -->
                <div id="smi-tab-api" class="smi-tab-panel smi-tab-active">
                    <?php $this->render_api_tab(); ?>
                </div>

                <!-- Display Control Tab -->
                <div id="smi-tab-display" class="smi-tab-panel">
                    <?php $this->render_display_tab(); ?>
                </div>

                <!-- Download Settings Tab -->
                <div id="smi-tab-downloads" class="smi-tab-panel">
                    <?php $this->render_downloads_tab(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render API Configuration Tab
     */
    public function render_api_tab() {
        ?>
        <div class="smi-tab-section">
            <h3><?php esc_html_e( 'Upsampler API Configuration', 'sell-my-images' ); ?></h3>
            <p><?php esc_html_e( 'Configure your Upsampler API key for image upscaling functionality.', 'sell-my-images' ); ?></p>
            <p><a href="https://upsampler.com" target="_blank"><?php esc_html_e( 'Get your free API key from Upsampler.com', 'sell-my-images' ); ?></a></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="smi_upsampler_api_key"><?php esc_html_e( 'Upsampler API Key', 'sell-my-images' ); ?></label>
                    </th>
                    <td>
                        <?php $value = get_option( 'smi_upsampler_api_key', '' ); ?>
                        <input type="password" id="smi_upsampler_api_key" name="smi_upsampler_api_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                        <p class="description">
                            <?php esc_html_e( 'Your Upsampler API key for image upscaling. Keep this secure and never share it publicly.', 'sell-my-images' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <div class="smi-webhook-info">
                <h4><?php esc_html_e( 'Webhook Configuration', 'sell-my-images' ); ?></h4>
                <p class="description">
                    <strong><?php esc_html_e( 'Webhook URL:', 'sell-my-images' ); ?></strong> 
                    <code><?php echo esc_url( home_url( '/smi-webhook/upsampler/' ) ); ?></code><br>
                    <?php esc_html_e( 'Use this URL in your Upsampler webhook configuration. No authentication required.', 'sell-my-images' ); ?>
                </p>
            </div>
        </div>

        <div class="smi-tab-section">
            <h3><?php esc_html_e( 'Stripe Payment Configuration', 'sell-my-images' ); ?></h3>
            <p><?php esc_html_e( 'Stripe payment processing is managed by the Stripe Integration plugin.', 'sell-my-images' ); ?></p>

            <?php
            $stripe_configured = stripe_integration_is_configured();
            $is_test_mode      = stripe_integration_is_test_mode();
            ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Status', 'sell-my-images' ); ?></th>
                    <td>
                        <?php if ( $stripe_configured ) : ?>
                            <span style="color: green;">✓ <?php esc_html_e( 'Configured', 'sell-my-images' ); ?></span>
                            <?php if ( $is_test_mode ) : ?>
                                <span class="description"> (<?php esc_html_e( 'Test Mode', 'sell-my-images' ); ?>)</span>
                            <?php else : ?>
                                <span class="description"> (<?php esc_html_e( 'Live Mode', 'sell-my-images' ); ?>)</span>
                            <?php endif; ?>
                        <?php else : ?>
                            <span style="color: red;">✗ <?php esc_html_e( 'Not Configured', 'sell-my-images' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Webhook URL', 'sell-my-images' ); ?></th>
                    <td>
                        <code><?php echo esc_url( stripe_integration_get_webhook_url() ); ?></code>
                        <p class="description">
                            <?php esc_html_e( 'Use this URL in your Stripe Dashboard under Developers > Webhooks.', 'sell-my-images' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p>
                <a href="<?php echo esc_url( admin_url( 'options-general.php?page=stripe-integration' ) ); ?>" class="button">
                    <?php esc_html_e( 'Configure Stripe Settings', 'sell-my-images' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Render Display Control Tab
     */
    public function render_display_tab() {
        $display_mode = get_option( 'smi_display_mode', 'all' );
        $post_types = get_option( 'smi_filter_post_types', array() );
        $categories = get_option( 'smi_filter_categories', array() );
        $tags = get_option( 'smi_filter_tags', array() );
        $post_ids = get_option( 'smi_filter_post_ids', '' );
        
        $is_disabled = ( $display_mode === 'all' ) ? 'smi-disabled' : '';
        ?>
        <div class="smi-tab-section">
            <h3><?php esc_html_e( 'Button Text Configuration', 'sell-my-images' ); ?></h3>
            <p><?php esc_html_e( 'Customize the text displayed on download buttons.', 'sell-my-images' ); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="smi_button_text"><?php esc_html_e( 'Button Text', 'sell-my-images' ); ?></label>
                    </th>
                    <td>
                        <?php $button_text = get_option( 'smi_button_text', __( 'Download Hi-Res', 'sell-my-images' ) ); ?>
                        <input type="text" id="smi_button_text" name="smi_button_text" value="<?php echo esc_attr( $button_text ); ?>" class="regular-text" />
                        <p class="description">
                            <?php esc_html_e( 'The text displayed on download buttons. Keep it short and descriptive.', 'sell-my-images' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="smi-tab-section">
            <h3><?php esc_html_e( 'Featured Image Support', 'sell-my-images' ); ?></h3>
            <p><?php esc_html_e( 'Control whether download buttons appear on featured images in addition to content images.', 'sell-my-images' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="smi_include_featured_images"><?php esc_html_e( 'Featured Image Buttons', 'sell-my-images' ); ?></label>
                    </th>
                    <td>
                        <?php $include_featured = get_option( 'smi_include_featured_images', true ); ?>
                        <input type="checkbox" id="smi_include_featured_images" name="smi_include_featured_images" value="1" <?php checked( $include_featured ); ?> />
                        <label for="smi_include_featured_images"><?php esc_html_e( 'Add download buttons to featured images', 'sell-my-images' ); ?></label>
                        <p class="description">
                            <?php esc_html_e( 'When enabled, download buttons will appear on featured images that are not already styled as content blocks. Disable if you experience conflicts with your theme.', 'sell-my-images' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="smi-tab-section">
            <h3><?php esc_html_e( 'Button Display Control', 'sell-my-images' ); ?></h3>
            <p><?php esc_html_e( 'Control where download buttons appear on your site. Choose to show buttons on all posts, or selectively include/exclude specific content.', 'sell-my-images' ); ?></p>
            
            <fieldset class="smi-display-modes">
                <legend class="screen-reader-text"><?php esc_html_e( 'Display Mode', 'sell-my-images' ); ?></legend>
                <p>
                    <label>
                        <input type="radio" name="smi_display_mode" value="all" <?php checked( $display_mode, 'all' ); ?> />
                        <strong><?php esc_html_e( 'All Posts', 'sell-my-images' ); ?></strong>
                        <span class="description"><?php esc_html_e( ' - Show buttons on all eligible posts (filtering disabled)', 'sell-my-images' ); ?></span>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="radio" name="smi_display_mode" value="exclude" <?php checked( $display_mode, 'exclude' ); ?> />
                        <strong><?php esc_html_e( 'Exclude Selected', 'sell-my-images' ); ?></strong>
                        <span class="description"><?php esc_html_e( ' - Hide buttons on posts matching the criteria below', 'sell-my-images' ); ?></span>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="radio" name="smi_display_mode" value="include" <?php checked( $display_mode, 'include' ); ?> />
                        <strong><?php esc_html_e( 'Include Only Selected', 'sell-my-images' ); ?></strong>
                        <span class="description"><?php esc_html_e( ' - Show buttons only on posts matching the criteria below', 'sell-my-images' ); ?></span>
                    </label>
                </p>
            </fieldset>

            <!-- Filter Criteria Table - Always Visible, Disabled When "All Posts" -->
            <div id="smi-filter-criteria-table" class="<?php echo $is_disabled; ?>">
                <h4><?php esc_html_e( 'Filter Criteria', 'sell-my-images' ); ?></h4>
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
        </div>
        <?php
    }

    /**
     * Render Download Settings Tab
     */
    public function render_downloads_tab() {
        ?>
        <div class="smi-tab-section">
            <h3><?php esc_html_e( 'Download Settings', 'sell-my-images' ); ?></h3>
            <p><?php esc_html_e( 'Configure how long download links remain valid after purchase and pricing settings.', 'sell-my-images' ); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="smi_download_expiry_hours"><?php esc_html_e( 'Download Link Expiry', 'sell-my-images' ); ?></label>
                    </th>
                    <td>
                        <?php $value = get_option( 'smi_download_expiry_hours', '24' ); ?>
                        <input type="number" id="smi_download_expiry_hours" name="smi_download_expiry_hours" value="<?php echo esc_attr( $value ); ?>" min="1" max="168" class="small-text" />
                        <span><?php esc_html_e( 'hours', 'sell-my-images' ); ?></span>
                        <p class="description">
                            <?php esc_html_e( 'How long download links remain valid (1-168 hours / 1 week max).', 'sell-my-images' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="smi_markup_percentage"><?php esc_html_e( 'Markup Percentage', 'sell-my-images' ); ?></label>
                    </th>
                    <td>
                        <?php $value = get_option( 'smi_markup_percentage', '500' ); ?>
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
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="smi_terms_conditions_url"><?php esc_html_e( 'Terms & Conditions URL', 'sell-my-images' ); ?></label>
                    </th>
                    <td>
                        <?php $value = get_option( 'smi_terms_conditions_url', '' ); ?>
                        <input type="url" id="smi_terms_conditions_url" name="smi_terms_conditions_url" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="https://yoursite.com/terms" />
                        <p class="description">
                            <?php esc_html_e( 'Optional: Link to your Terms & Conditions page. If provided, a link will appear in the purchase modal and download emails.', 'sell-my-images' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
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
    
    /**
     * Sanitize button text
     * 
     * @param mixed $value
     * @return string
     */
    public function sanitize_button_text( $value ) {
        $sanitized = sanitize_text_field( $value );
        
        // Limit to 50 characters for reasonable display
        if ( strlen( $sanitized ) > 50 ) {
            $sanitized = substr( $sanitized, 0, 50 );
        }
        
        // Return default if empty
        if ( empty( $sanitized ) ) {
            $sanitized = __( 'Download Hi-Res', 'sell-my-images' );
        }
        
        return $sanitized;
    }

    /**
     * Sanitize boolean settings
     *
     * @param mixed $value Input value
     * @return bool Sanitized boolean value
     */
    public function sanitize_boolean( $value ) {
        return (bool) $value;
    }
}