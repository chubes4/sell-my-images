<?php
/**
 * JobManager Tests
 *
 * @package SellMyImages\Tests
 */

namespace SellMyImages\Tests\Unit\Managers;

use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use SellMyImages\Managers\JobManager;
use SellMyImages\Config\Constants;

class JobManagerTest extends \SMI_TestCase {

    protected function setUp(): void {
        parent::setUp();

        // Mock WordPress functions
        Functions\when( 'wp_generate_uuid4' )
            ->justReturn( 'test-uuid-1234-5678-abcd' );

        Functions\when( 'current_time' )
            ->justReturn( '2024-01-15 12:00:00' );

        Functions\when( 'is_email' )
            ->alias(
                function ( $email ) {
                    return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
                }
            );

        Functions\when( 'do_action' )
            ->justReturn( null );
    }

    /**
     * @test
     */
    public function create_job_requires_image_url(): void {
        $job_data = array(
            'resolution' => '4x',
            'email'      => 'test@example.com',
            'post_id'    => 123,
        );

        $result = JobManager::create_job( $job_data );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'missing_required_field', $result->get_error_code() );
    }

    /**
     * @test
     */
    public function create_job_requires_resolution(): void {
        $job_data = array(
            'image_url' => 'https://example.com/image.jpg',
            'email'     => 'test@example.com',
            'post_id'   => 123,
        );

        $result = JobManager::create_job( $job_data );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'missing_required_field', $result->get_error_code() );
    }

    /**
     * @test
     */
    public function create_job_requires_post_id(): void {
        $job_data = array(
            'image_url'  => 'https://example.com/image.jpg',
            'resolution' => '4x',
            'email'      => 'test@example.com',
        );

        $result = JobManager::create_job( $job_data );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'missing_required_field', $result->get_error_code() );
    }

    /**
     * @test
     */
    public function create_job_requires_valid_resolution(): void {
        $job_data = array(
            'image_url'  => 'https://example.com/image.jpg',
            'resolution' => 'invalid',
            'email'      => 'test@example.com',
            'post_id'    => 123,
        );

        $result = JobManager::create_job( $job_data );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'invalid_resolution', $result->get_error_code() );
    }

    /**
     * @test
     */
    public function create_job_validates_email_when_provided(): void {
        $job_data = array(
            'image_url'  => 'https://example.com/image.jpg',
            'resolution' => '4x',
            'email'      => 'invalid-email',
            'post_id'    => 123,
        );

        $result = JobManager::create_job( $job_data );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'invalid_email', $result->get_error_code() );
    }

    /**
     * @test
     */
    public function create_job_requires_valid_image_url(): void {
        $job_data = array(
            'image_url'  => 'not-a-url',
            'resolution' => '4x',
            'email'      => 'test@example.com',
            'post_id'    => 123,
        );

        $result = JobManager::create_job( $job_data );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'invalid_image_url', $result->get_error_code() );
    }

    /**
     * @test
     */
    public function create_job_requires_positive_post_id(): void {
        $job_data = array(
            'image_url'  => 'https://example.com/image.jpg',
            'resolution' => '4x',
            'email'      => 'test@example.com',
            'post_id'    => 0, // Empty/zero is treated as missing
        );

        $result = JobManager::create_job( $job_data );

        $this->assertInstanceOf( \WP_Error::class, $result );
        // post_id=0 is treated as empty, triggering missing_required_field
        $this->assertEquals( 'missing_required_field', $result->get_error_code() );
    }

    /**
     * @test
     */
    public function get_job_returns_error_for_empty_id(): void {
        $result = JobManager::get_job( '' );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'invalid_job_id', $result->get_error_code() );
    }

    /**
     * @test
     */
    public function get_job_by_token_returns_error_for_empty_token(): void {
        $result = JobManager::get_job_by_token( '' );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'invalid_token', $result->get_error_code() );
    }

    /**
     * @test
     */
    public function get_job_by_checkout_session_returns_error_for_empty_session(): void {
        $result = JobManager::get_job_by_checkout_session( '' );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'invalid_session_id', $result->get_error_code() );
    }

    /**
     * @test
     */
    public function get_job_by_upsampler_id_returns_error_for_empty_id(): void {
        $result = JobManager::get_job_by_upsampler_id( '' );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'invalid_upsampler_job_id', $result->get_error_code() );
    }

    /**
     * @test
     * @dataProvider statusTransitionProvider
     */
    public function status_transitions_are_validated(
        string $current_status,
        string $new_status,
        bool $should_be_valid
    ): void {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass( JobManager::class );
        $method     = $reflection->getMethod( 'is_valid_status_transition' );

        $result = $method->invoke( null, $current_status, $new_status );

        $this->assertEquals( $should_be_valid, $result );
    }

    public function statusTransitionProvider(): array {
        return array(
            // awaiting_payment transitions
            'awaiting_payment to pending'    => array( 'awaiting_payment', 'pending', true ),
            'awaiting_payment to processing' => array( 'awaiting_payment', 'processing', true ),
            'awaiting_payment to failed'     => array( 'awaiting_payment', 'failed', true ),
            'awaiting_payment to abandoned'  => array( 'awaiting_payment', 'abandoned', true ),
            'awaiting_payment to completed'  => array( 'awaiting_payment', 'completed', false ),

            // pending transitions
            'pending to processing'          => array( 'pending', 'processing', true ),
            'pending to failed'              => array( 'pending', 'failed', true ),
            'pending to completed'           => array( 'pending', 'completed', false ),
            'pending to abandoned'           => array( 'pending', 'abandoned', false ),

            // processing transitions
            'processing to completed'        => array( 'processing', 'completed', true ),
            'processing to failed'           => array( 'processing', 'failed', true ),
            'processing to pending'          => array( 'processing', 'pending', false ),

            // Terminal states
            'completed to anything'          => array( 'completed', 'failed', false ),
            'failed to anything'             => array( 'failed', 'processing', false ),
            'abandoned to anything'          => array( 'abandoned', 'pending', false ),

            // Idempotent (same status)
            'pending to pending'             => array( 'pending', 'pending', true ),
            'processing to processing'       => array( 'processing', 'processing', true ),
            'completed to completed'         => array( 'completed', 'completed', true ),
        );
    }

    /**
     * @test
     */
    public function update_upsampler_job_id_requires_both_parameters(): void {
        $result = JobManager::update_upsampler_job_id( '', 'upsampler-123' );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'invalid_parameters', $result->get_error_code() );

        $result2 = JobManager::update_upsampler_job_id( 'job-123', '' );

        $this->assertInstanceOf( \WP_Error::class, $result2 );
        $this->assertEquals( 'invalid_parameters', $result2->get_error_code() );
    }

    /**
     * @test
     */
    public function get_jobs_by_status_validates_status(): void {
        $result = JobManager::get_jobs_by_status( 'invalid_status' );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'invalid_status', $result->get_error_code() );
    }

    /**
     * @test
     */
    public function status_transitions_constant_defines_valid_statuses(): void {
        $transitions = JobManager::STATUS_TRANSITIONS;

        $this->assertArrayHasKey( 'awaiting_payment', $transitions );
        $this->assertArrayHasKey( 'pending', $transitions );
        $this->assertArrayHasKey( 'processing', $transitions );
        $this->assertArrayHasKey( 'completed', $transitions );
        $this->assertArrayHasKey( 'failed', $transitions );
        $this->assertArrayHasKey( 'abandoned', $transitions );

        // Terminal states have no valid transitions
        $this->assertEmpty( $transitions['completed'] );
        $this->assertEmpty( $transitions['failed'] );
        $this->assertEmpty( $transitions['abandoned'] );
    }
}
