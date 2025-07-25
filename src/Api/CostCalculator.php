<?php
/**
 * Cost Calculator Class
 * 
 * Handles dynamic pricing calculations based on Upsampler API costs
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

namespace SellMyImages\Api;

use SellMyImages\Config\Constants;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CostCalculator class
 */
class CostCalculator {
    
    /**
     * Calculate processing cost in credits
     * 
     * @param array $image_data Image data
     * @param string $resolution Resolution multiplier
     * @return float Cost in credits
     */
    public static function calculate_cost( $image_data, $resolution ) {
        $cost_data = self::calculate_cost_detailed( $image_data, $resolution );
        return $cost_data['credits'];
    }
    
    /**
     * Calculate detailed processing cost with credits and pricing
     * 
     * @param array $image_data Image data
     * @param string $resolution Resolution multiplier
     * @return array Cost breakdown with credits, cost, and customer price
     */
    public static function calculate_cost_detailed( $image_data, $resolution ) {
        $upscale_factor = Constants::get_upscale_factor( $resolution );
        
        if ( ! $upscale_factor ) {
            return array(
                'credits' => 0,
                'cost_usd' => 0,
                'customer_price' => 0,
                'markup_percentage' => 0,
            );
        }
        
        $original_width = intval( $image_data['width'] );
        $original_height = intval( $image_data['height'] );
        
        // Calculate output dimensions
        $output_width = $original_width * $upscale_factor;
        $output_height = $original_height * $upscale_factor;
        
        // Calculate megapixels
        $output_megapixels = ( $output_width * $output_height ) / 1000000;
        
        // Precise upscale: 1 credit per 4 megapixels of output
        $credits_needed = ceil( $output_megapixels * Constants::UPSAMPLER_CREDITS_PER_MEGAPIXEL );
        
        // Use constants for pricing and configurable markup
        $cost_per_credit = Constants::UPSAMPLER_COST_PER_CREDIT;
        $markup_percentage = floatval( get_option( 'smi_markup_percentage', Constants::DEFAULT_MARKUP_PERCENTAGE ) );
        
        // Calculate costs
        $our_cost = $credits_needed * $cost_per_credit;
        $customer_price = $our_cost * ( 1 + ( $markup_percentage / 100 ) );
        
        // Enforce Stripe minimum payment requirement
        if ( $customer_price < Constants::STRIPE_MINIMUM_PAYMENT ) {
            $customer_price = Constants::STRIPE_MINIMUM_PAYMENT;
        }
        
        return array(
            'credits' => $credits_needed,
            'cost_usd' => round( $our_cost, 2 ),
            'customer_price' => round( $customer_price, 2 ),
            'markup_percentage' => $markup_percentage,
            'output_megapixels' => round( $output_megapixels, 2 ),
            'output_dimensions' => array(
                'width' => $output_width,
                'height' => $output_height,
            ),
        );
    }
    
    /**
     * Get upscale factor from resolution string
     * Shared utility method used by both CostCalculator and Upscaler
     * 
     * @param string $resolution Resolution (2x, 4x, 8x)
     * @return float|false Upscale factor or false if invalid
     */
    public static function get_upscale_factor( $resolution ) {
        return Constants::get_upscale_factor( $resolution );
    }
}