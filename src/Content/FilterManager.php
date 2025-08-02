<?php
/**
 * Filter Manager Class
 * 
 * Centralized filtering logic for determining where download buttons should appear
 * Supports three modes: all posts, exclude selected, include only selected
 * 
 * @package SellMyImages
 * @since 1.1.0
 */

namespace SellMyImages\Content;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FilterManager class
 */
class FilterManager {
    
    /**
     * Main decision point - should buttons appear on this post?
     * 
     * @param int|null $post_id Post ID (defaults to current post)
     * @return bool True if buttons should appear
     */
    public static function should_show_buttons( $post_id = null ) {
        // Get current post ID if not provided
        if ( ! $post_id ) {
            $post_id = get_the_ID();
        }
        
        // Must have a valid post ID
        if ( ! $post_id ) {
            return false;
        }
        
        // Get display mode setting
        $mode = get_option( 'smi_display_mode', 'all' );
        
        // Fast path for default mode - zero overhead
        if ( $mode === 'all' ) {
            return true;
        }
        
        // Evaluate filters based on mode
        return self::evaluate_filters( $post_id, $mode );
    }
    
    /**
     * Evaluate filtering rules based on mode
     * 
     * @param int $post_id Post ID to check
     * @param string $mode Display mode ('exclude' or 'include')
     * @return bool True if buttons should appear
     */
    private static function evaluate_filters( $post_id, $mode ) {
        $matches_criteria = self::post_matches_criteria( $post_id );
        
        if ( $mode === 'exclude' ) {
            // Exclude mode: show buttons UNLESS post matches criteria
            return ! $matches_criteria;
        } elseif ( $mode === 'include' ) {
            // Include mode: show buttons ONLY IF post matches criteria
            return $matches_criteria;
        }
        
        // Fallback to showing buttons (safe default)
        return true;
    }
    
    /**
     * Check if post matches any of the configured filter criteria
     * 
     * @param int $post_id Post ID to check
     * @return bool True if post matches any criteria
     */
    private static function post_matches_criteria( $post_id ) {
        // Check post type filter
        if ( self::matches_post_type_filter( $post_id ) ) {
            return true;
        }
        
        // Check category filter
        if ( self::matches_category_filter( $post_id ) ) {
            return true;
        }
        
        // Check tag filter
        if ( self::matches_tag_filter( $post_id ) ) {
            return true;
        }
        
        // Check specific post IDs filter
        if ( self::matches_post_ids_filter( $post_id ) ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if post matches post type filter
     * 
     * @param int $post_id Post ID to check
     * @return bool True if matches
     */
    private static function matches_post_type_filter( $post_id ) {
        $filtered_post_types = get_option( 'smi_filter_post_types', array() );
        
        if ( empty( $filtered_post_types ) || ! is_array( $filtered_post_types ) ) {
            return false;
        }
        
        $post_type = get_post_type( $post_id );
        return in_array( $post_type, $filtered_post_types, true );
    }
    
    /**
     * Check if post matches category filter
     * 
     * @param int $post_id Post ID to check
     * @return bool True if matches
     */
    private static function matches_category_filter( $post_id ) {
        $filtered_categories = get_option( 'smi_filter_categories', array() );
        
        if ( empty( $filtered_categories ) || ! is_array( $filtered_categories ) ) {
            return false;
        }
        
        // Convert string IDs to integers for proper comparison
        $filtered_categories = array_map( 'intval', $filtered_categories );
        
        foreach ( $filtered_categories as $category_id ) {
            if ( has_category( $category_id, $post_id ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if post matches tag filter
     * 
     * @param int $post_id Post ID to check
     * @return bool True if matches
     */
    private static function matches_tag_filter( $post_id ) {
        $filtered_tags = get_option( 'smi_filter_tags', array() );
        
        if ( empty( $filtered_tags ) || ! is_array( $filtered_tags ) ) {
            return false;
        }
        
        // Convert string IDs to integers for proper comparison
        $filtered_tags = array_map( 'intval', $filtered_tags );
        
        foreach ( $filtered_tags as $tag_id ) {
            if ( has_tag( $tag_id, $post_id ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if post matches specific post IDs filter
     * 
     * @param int $post_id Post ID to check
     * @return bool True if matches
     */
    private static function matches_post_ids_filter( $post_id ) {
        $filtered_post_ids = get_option( 'smi_filter_post_ids', '' );
        
        if ( empty( $filtered_post_ids ) ) {
            return false;
        }
        
        // Parse comma-separated post IDs
        $post_ids = array_map( 'trim', explode( ',', $filtered_post_ids ) );
        $post_ids = array_map( 'intval', $post_ids );
        $post_ids = array_filter( $post_ids ); // Remove empty values
        
        return in_array( $post_id, $post_ids, true );
    }
    
    /**
     * Get all available post types for admin UI
     * 
     * @return array Array of post type objects
     */
    public static function get_available_post_types() {
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        
        // Filter out attachment post type (not relevant for content display)
        unset( $post_types['attachment'] );
        
        return $post_types;
    }
    
    /**
     * Get all available categories for admin UI
     * 
     * @return array Array of category objects
     */
    public static function get_available_categories() {
        return get_categories( array(
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );
    }
    
    /**
     * Get all available tags for admin UI
     * 
     * @return array Array of tag objects
     */
    public static function get_available_tags() {
        return get_tags( array(
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );
    }
    
    /**
     * Validate and sanitize filter settings
     * 
     * @param array $settings Raw settings array
     * @return array Sanitized settings
     */
    public static function sanitize_filter_settings( $settings ) {
        $sanitized = array();
        
        // Sanitize display mode
        $sanitized['smi_display_mode'] = in_array( $settings['smi_display_mode'] ?? '', array( 'all', 'exclude', 'include' ), true ) 
            ? $settings['smi_display_mode'] 
            : 'all';
        
        // Sanitize post types
        $sanitized['smi_filter_post_types'] = array();
        if ( ! empty( $settings['smi_filter_post_types'] ) && is_array( $settings['smi_filter_post_types'] ) ) {
            $available_post_types = array_keys( self::get_available_post_types() );
            foreach ( $settings['smi_filter_post_types'] as $post_type ) {
                if ( in_array( $post_type, $available_post_types, true ) ) {
                    $sanitized['smi_filter_post_types'][] = sanitize_text_field( $post_type );
                }
            }
        }
        
        // Sanitize categories
        $sanitized['smi_filter_categories'] = array();
        if ( ! empty( $settings['smi_filter_categories'] ) && is_array( $settings['smi_filter_categories'] ) ) {
            foreach ( $settings['smi_filter_categories'] as $category_id ) {
                $category_id = intval( $category_id );
                if ( $category_id > 0 && term_exists( $category_id, 'category' ) ) {
                    $sanitized['smi_filter_categories'][] = $category_id;
                }
            }
        }
        
        // Sanitize tags
        $sanitized['smi_filter_tags'] = array();
        if ( ! empty( $settings['smi_filter_tags'] ) && is_array( $settings['smi_filter_tags'] ) ) {
            foreach ( $settings['smi_filter_tags'] as $tag_id ) {
                $tag_id = intval( $tag_id );
                if ( $tag_id > 0 && term_exists( $tag_id, 'post_tag' ) ) {
                    $sanitized['smi_filter_tags'][] = $tag_id;
                }
            }
        }
        
        // Sanitize post IDs
        $sanitized['smi_filter_post_ids'] = '';
        if ( ! empty( $settings['smi_filter_post_ids'] ) ) {
            $post_ids = array_map( 'trim', explode( ',', $settings['smi_filter_post_ids'] ) );
            $valid_post_ids = array();
            
            foreach ( $post_ids as $post_id ) {
                $post_id = intval( $post_id );
                if ( $post_id > 0 && get_post( $post_id ) ) {
                    $valid_post_ids[] = $post_id;
                }
            }
            
            $sanitized['smi_filter_post_ids'] = implode( ', ', $valid_post_ids );
        }
        
        return $sanitized;
    }
}