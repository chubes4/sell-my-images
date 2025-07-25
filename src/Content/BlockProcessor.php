<?php
/**
 * Block Processor Class
 * 
 * Handles Gutenberg image block detection and buy button injection
 * Replaces the complex DOM manipulation approach with clean block processing
 * 
 * @package SellMyImages
 * @since 1.1.0
 */

namespace SellMyImages\Content;

use SellMyImages\Config\Constants;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BlockProcessor class
 */
class BlockProcessor {
    
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
        // Filter is used only to ensure assets load on pages with images
        add_filter( 'the_content', array( $this, 'process_content' ), 5 );
    }
    
    /**
     * Process post content to inject buy buttons into image blocks
     * 
     * @param string $content Post content
     * @return string Modified content with buttons
     */
    public function process_content( $content ) {
        // Only process single posts/pages
        if ( ! is_singular() || is_admin() ) {
            return $content;
        }
        
        // Check if plugin is enabled
        if ( ! get_option( 'smi_enabled', '1' ) ) {
            return $content;
        }
        
        // JavaScript will handle button injection - no need to modify content here
        // This filter is only used to trigger asset loading
        return $content;
    }
    
    
    /**
     * Extract image data from a Gutenberg image block
     * 
     * @param array $block The image block
     * @return array|false Image data array or false if invalid
     */
    private function extract_image_data_from_block( $block ) {
        $attrs = $block['attrs'] ?? array();
        
        // Must have an ID (attachment ID)
        if ( empty( $attrs['id'] ) ) {
            return false;
        }
        
        $attachment_id = intval( $attrs['id'] );
        $image_meta = wp_get_attachment_metadata( $attachment_id );
        $image_url = wp_get_attachment_url( $attachment_id );
        
        if ( ! $image_url || ! $image_meta ) {
            return false;
        }
        
        return array(
            'attachment_id' => $attachment_id,
            'src'           => $image_url,
            'width'         => $image_meta['width'] ?? 0,
            'height'        => $image_meta['height'] ?? 0,
            'alt'           => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
            'title'         => get_the_title( $attachment_id ),
            'is_local'      => true, // WordPress attachments are always local
            'file_path'     => get_attached_file( $attachment_id ),
        );
    }
    
    /**
     * Check if an image is valid for processing
     * 
     * @param array $image_data Image data array
     * @return bool True if valid, false otherwise
     */
    private function is_valid_image( $image_data ) {
        // Must have dimensions
        if ( empty( $image_data['width'] ) || empty( $image_data['height'] ) ) {
            return false;
        }
        
        // Skip very small images (probably icons)
        $min_size = apply_filters( 'smi_min_image_size', Constants::MIN_IMAGE_SIZE );
        if ( $image_data['width'] < $min_size || $image_data['height'] < $min_size ) {
            return false;
        }
        
        // Skip SVG images (not suitable for upscaling)
        $file_type = wp_check_filetype( $image_data['src'] );
        if ( in_array( $file_type['type'], Constants::EXCLUDED_IMAGE_TYPES, true ) ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Add buy button to an image block's HTML
     * 
     * @param array $block The image block
     * @param array $image_data Image data
     * @return string Modified block HTML with button
     */
    private function add_button_to_image_block( $block, $image_data ) {
        $original_html = $block['innerHTML'];
        
        // Create button HTML
        $button_html = $this->generate_button_html( $image_data );
        
        // Find the figure element and add button
        if ( strpos( $original_html, '<figure' ) !== false ) {
            // Add button before the closing </figure> tag
            $modified_html = str_replace( '</figure>', $button_html . '</figure>', $original_html );
        } else {
            // Fallback: append button after the content
            $modified_html = $original_html . $button_html;
        }
        
        return $modified_html;
    }
    
    /**
     * Generate buy button HTML
     * 
     * @param array $image_data Image data
     * @return string Button HTML
     */
    private function generate_button_html( $image_data ) {
        $button_text = apply_filters( 'smi_button_text', __( 'Download Hi-Res', 'sell-my-images' ) );
        $post_id = get_the_ID();
        
        $button_html = sprintf(
            '<button class="smi-get-button" data-post-id="%d" data-attachment-id="%d" data-src="%s" data-width="%d" data-height="%d">
                <span class="smi-button-text">%s</span>
                <span class="smi-button-icon">💰</span>
            </button>',
            intval( $post_id ),
            intval( $image_data['attachment_id'] ),
            esc_url( $image_data['src'] ),
            intval( $image_data['width'] ),
            intval( $image_data['height'] ),
            esc_html( $button_text )
        );
        
        error_log( 'SMI: Generated button HTML for post ' . $post_id . ', attachment ' . $image_data['attachment_id'] );
        return $button_html;
    }
    
    
    
    /**
     * Check if current post has image blocks (for asset loading optimization)
     * 
     * @param int|null $post_id Post ID (defaults to current post)
     * @return bool True if post has image blocks
     */
    public static function post_has_image_blocks( $post_id = null ) {
        if ( ! $post_id ) {
            $post_id = get_the_ID();
        }
        
        if ( ! $post_id ) {
            return false;
        }
        
        $content = get_post_field( 'post_content', $post_id );
        
        if ( ! has_blocks( $content ) ) {
            return false;
        }
        
        $blocks = parse_blocks( $content );
        return self::blocks_contain_images( $blocks );
    }
    
    /**
     * Recursively check if blocks contain image blocks
     * 
     * @param array $blocks Array of blocks to check
     * @return bool True if any block is an image block
     */
    private static function blocks_contain_images( $blocks ) {
        foreach ( $blocks as $block ) {
            if ( $block['blockName'] === 'core/image' ) {
                return true;
            }
            
            if ( ! empty( $block['innerBlocks'] ) && self::blocks_contain_images( $block['innerBlocks'] ) ) {
                return true;
            }
        }
        
        return false;
    }
}