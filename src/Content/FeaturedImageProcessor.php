<?php
/**
 * Featured Image Processor Class
 *
 * Handles automatic download button injection for featured images
 * Provides smart conflict detection to avoid double buttons with block-styled themes
 *
 * @package SellMyImages
 * @since 1.2.0
 */

namespace SellMyImages\Content;

use SellMyImages\Content\FilterManager;
use SellMyImages\Content\BlockProcessor;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FeaturedImageProcessor class
 */
class FeaturedImageProcessor {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Hook into featured image HTML generation
        add_filter( 'post_thumbnail_html', array( $this, 'add_button_to_featured_image' ), 10, 2 );
    }

    /**
     * Add download button to featured images (when not already styled as blocks)
     *
     * @param string $html The post thumbnail HTML
     * @param int $post_id The post ID
     * @return string Modified HTML with button (if applicable)
     */
    public function add_button_to_featured_image( $html, $post_id ) {
        // Respect admin setting - default to enabled
        if ( ! get_option( 'smi_include_featured_images', true ) ) {
            return $html;
        }

        // Use existing filtering logic
        if ( ! FilterManager::should_show_buttons( $post_id ) ) {
            return $html;
        }

        // CRITICAL: Skip if already styled as block to prevent double buttons
        // This ensures compatibility with themes like Sarai Chinwag that style featured images as blocks
        if ( strpos( $html, 'wp-block-image' ) !== false ) {
            return $html; // Let JavaScript handle block-styled images
        }

        // Get attachment ID
        $attachment_id = get_post_thumbnail_id( $post_id );
        if ( ! $attachment_id ) {
            return $html;
        }

        // Verify this is a valid image for processing
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return $html;
        }

        // Generate download button using existing method
        $button = BlockProcessor::generate_external_button_html( $attachment_id, $post_id );

        if ( empty( $button ) ) {
            return $html;
        }

        // Add button after the image HTML
        return $html . $button;
    }

    /**
     * Check if featured image support is enabled
     *
     * @return bool True if enabled, false otherwise
     */
    public static function is_enabled() {
        return (bool) get_option( 'smi_include_featured_images', true );
    }

    /**
     * Check if a featured image is styled as a block
     *
     * @param string $html Featured image HTML
     * @return bool True if styled as block, false otherwise
     */
    public static function is_styled_as_block( $html ) {
        return strpos( $html, 'wp-block-image' ) !== false;
    }
}