<?php
/**
 * CostCalculator Tests
 *
 * @package SellMyImages\Tests
 */

namespace SellMyImages\Tests\Unit\Api;

use Brain\Monkey\Functions;
use SellMyImages\Api\CostCalculator;
use SellMyImages\Config\Constants;

class CostCalculatorTest extends \SMI_TestCase {

    protected function setUp(): void {
        parent::setUp();

        // Mock get_option to return default markup percentage
        Functions\when( 'get_option' )
            ->justReturn( Constants::DEFAULT_MARKUP_PERCENTAGE );
    }

    /**
     * @test
     */
    public function calculate_cost_detailed_returns_correct_structure(): void {
        $image_data = array(
            'width'  => 1000,
            'height' => 1000,
        );

        $result = CostCalculator::calculate_cost_detailed( $image_data, '4x' );

        $this->assertArrayHasKey( 'credits', $result );
        $this->assertArrayHasKey( 'cost_usd', $result );
        $this->assertArrayHasKey( 'customer_price', $result );
        $this->assertArrayHasKey( 'markup_percentage', $result );
        $this->assertArrayHasKey( 'output_megapixels', $result );
        $this->assertArrayHasKey( 'output_dimensions', $result );
    }

    /**
     * @test
     */
    public function calculate_cost_detailed_4x_computes_correct_credits(): void {
        // 1000x1000 = 1MP input, at 4x = 4000x4000 = 16MP output
        // Credits = ceil(16 * 0.25) = 4 credits
        $image_data = array(
            'width'  => 1000,
            'height' => 1000,
        );

        $result = CostCalculator::calculate_cost_detailed( $image_data, '4x' );

        $this->assertEquals( 4, $result['credits'] );
    }

    /**
     * @test
     */
    public function calculate_cost_detailed_8x_computes_correct_credits(): void {
        // 500x500 = 0.25MP input, at 8x = 4000x4000 = 16MP output
        // Credits = ceil(16 * 0.25) = 4 credits
        $image_data = array(
            'width'  => 500,
            'height' => 500,
        );

        $result = CostCalculator::calculate_cost_detailed( $image_data, '8x' );

        $this->assertEquals( 4, $result['credits'] );
    }

    /**
     * @test
     */
    public function calculate_cost_detailed_applies_markup_correctly(): void {
        Functions\when( 'get_option' )
            ->justReturn( 200 ); // 200% markup = 3x cost

        // 2000x2000 = 4MP input, at 4x = 8000x8000 = 64MP output
        // Credits = ceil(64 * 0.25) = 16 credits
        // Cost = 16 * $0.04 = $0.64
        // Customer price = $0.64 * 3 = $1.92
        $image_data = array(
            'width'  => 2000,
            'height' => 2000,
        );

        $result = CostCalculator::calculate_cost_detailed( $image_data, '4x' );

        $this->assertEquals( 16, $result['credits'] );
        $this->assertEquals( 0.64, $result['cost_usd'] );
        $this->assertEquals( 1.92, $result['customer_price'] );
        $this->assertEquals( 200, $result['markup_percentage'] );
    }

    /**
     * @test
     */
    public function calculate_cost_detailed_enforces_stripe_minimum(): void {
        // Very small image to test Stripe minimum enforcement
        // 100x100 = 0.01MP input, at 4x = 400x400 = 0.16MP output
        // Credits = ceil(0.16 * 0.25) = 1 credit
        // Cost = 1 * $0.04 = $0.04
        // Customer price = $0.04 * 3 = $0.12
        // But Stripe minimum is $0.50, so should be $0.50
        $image_data = array(
            'width'  => 100,
            'height' => 100,
        );

        $result = CostCalculator::calculate_cost_detailed( $image_data, '4x' );

        $this->assertGreaterThanOrEqual( Constants::STRIPE_MINIMUM_PAYMENT, $result['customer_price'] );
        $this->assertEquals( 0.50, $result['customer_price'] );
    }

    /**
     * @test
     */
    public function calculate_cost_detailed_returns_zeros_for_invalid_resolution(): void {
        $image_data = array(
            'width'  => 1000,
            'height' => 1000,
        );

        $result = CostCalculator::calculate_cost_detailed( $image_data, 'invalid' );

        $this->assertEquals( 0, $result['credits'] );
        $this->assertEquals( 0, $result['cost_usd'] );
        $this->assertEquals( 0, $result['customer_price'] );
    }

    /**
     * @test
     */
    public function calculate_cost_detailed_includes_output_dimensions(): void {
        $image_data = array(
            'width'  => 500,
            'height' => 400,
        );

        $result = CostCalculator::calculate_cost_detailed( $image_data, '4x' );

        $this->assertArrayHasKey( 'output_dimensions', $result );
        $this->assertEquals( 2000, $result['output_dimensions']['width'] );
        $this->assertEquals( 1600, $result['output_dimensions']['height'] );
    }

    /**
     * @test
     */
    public function calculate_cost_detailed_calculates_output_megapixels(): void {
        // 1000x1000 at 4x = 4000x4000 = 16 megapixels
        $image_data = array(
            'width'  => 1000,
            'height' => 1000,
        );

        $result = CostCalculator::calculate_cost_detailed( $image_data, '4x' );

        $this->assertEquals( 16, $result['output_megapixels'] );
    }

    /**
     * @test
     */
    public function calculate_cost_returns_credits_only(): void {
        $image_data = array(
            'width'  => 1000,
            'height' => 1000,
        );

        $result = CostCalculator::calculate_cost( $image_data, '4x' );

        $this->assertEquals( 4, $result );
        $this->assertIsNumeric( $result );
    }

    /**
     * @test
     */
    public function get_upscale_factor_returns_correct_values(): void {
        $this->assertEquals( 4, CostCalculator::get_upscale_factor( '4x' ) );
        $this->assertEquals( 8, CostCalculator::get_upscale_factor( '8x' ) );
        $this->assertFalse( CostCalculator::get_upscale_factor( '2x' ) );
        $this->assertFalse( CostCalculator::get_upscale_factor( 'invalid' ) );
    }

    /**
     * @test
     * @dataProvider imageDataProvider
     */
    public function calculate_cost_detailed_handles_various_dimensions(
        int $width,
        int $height,
        string $resolution,
        int $expected_credits
    ): void {
        $image_data = array(
            'width'  => $width,
            'height' => $height,
        );

        $result = CostCalculator::calculate_cost_detailed( $image_data, $resolution );

        $this->assertEquals( $expected_credits, $result['credits'] );
    }

    public function imageDataProvider(): array {
        return array(
            'small_4x'  => array( 500, 500, '4x', 1 ),    // 2000x2000 = 4MP, ceil(4*0.25) = 1
            'medium_4x' => array( 1000, 1000, '4x', 4 ),  // 4000x4000 = 16MP, ceil(16*0.25) = 4
            'large_4x'  => array( 2000, 2000, '4x', 16 ), // 8000x8000 = 64MP, ceil(64*0.25) = 16
            'small_8x'  => array( 250, 250, '8x', 1 ),    // 2000x2000 = 4MP, ceil(4*0.25) = 1
            'medium_8x' => array( 500, 500, '8x', 4 ),    // 4000x4000 = 16MP, ceil(16*0.25) = 4
        );
    }
}
