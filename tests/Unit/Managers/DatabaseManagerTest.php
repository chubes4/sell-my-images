<?php
/**
 * DatabaseManager Tests
 *
 * @package SellMyImages\Tests
 */

namespace SellMyImages\Tests\Unit\Managers;

use Brain\Monkey\Functions;
use SellMyImages\Managers\DatabaseManager;
use Mockery;

class DatabaseManagerTest extends \SMI_TestCase {

    private $wpdb_mock;

    protected function setUp(): void {
        parent::setUp();

        // Create wpdb mock
        $this->wpdb_mock              = Mockery::mock( 'wpdb' );
        $this->wpdb_mock->prefix      = 'wp_';
        $this->wpdb_mock->last_error  = '';
        $this->wpdb_mock->insert_id   = 0;

        // Set global wpdb
        $GLOBALS['wpdb'] = $this->wpdb_mock;
    }

    protected function tearDown(): void {
        unset( $GLOBALS['wpdb'] );
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function get_jobs_table_returns_prefixed_name(): void {
        $result = DatabaseManager::get_jobs_table();

        $this->assertEquals( 'wp_smi_jobs', $result );
    }

    /**
     * @test
     */
    public function insert_returns_false_for_empty_data(): void {
        $result = DatabaseManager::insert( array() );

        $this->assertFalse( $result );
    }

    /**
     * @test
     */
    public function insert_returns_id_on_success(): void {
        $this->wpdb_mock->insert_id = 42;

        $this->wpdb_mock
            ->shouldReceive( 'insert' )
            ->once()
            ->andReturn( 1 );

        $result = DatabaseManager::insert(
            array(
                'job_id'    => 'test-uuid',
                'image_url' => 'https://example.com/image.jpg',
            )
        );

        $this->assertIsArray( $result );
        $this->assertEquals( 42, $result['id'] );
        $this->assertEquals( 1, $result['rows_affected'] );
    }

    /**
     * @test
     */
    public function insert_returns_false_on_database_failure(): void {
        $this->wpdb_mock
            ->shouldReceive( 'insert' )
            ->once()
            ->andReturn( false );

        $result = DatabaseManager::insert(
            array(
                'job_id' => 'test-uuid',
            )
        );

        $this->assertFalse( $result );
    }

    /**
     * @test
     */
    public function update_returns_false_for_empty_data(): void {
        $result = DatabaseManager::update( array(), array( 'job_id' => 'test' ) );

        $this->assertFalse( $result );
    }

    /**
     * @test
     */
    public function update_returns_false_for_empty_where(): void {
        $result = DatabaseManager::update( array( 'status' => 'completed' ), array() );

        $this->assertFalse( $result );
    }

    /**
     * @test
     */
    public function update_returns_true_on_success(): void {
        $this->wpdb_mock
            ->shouldReceive( 'update' )
            ->once()
            ->andReturn( 1 );

        $result = DatabaseManager::update(
            array( 'status' => 'completed' ),
            array( 'job_id' => 'test-uuid' )
        );

        $this->assertTrue( $result );
    }

    /**
     * @test
     */
    public function update_returns_true_even_when_no_rows_affected(): void {
        // wpdb::update returns 0 when no rows matched, but this is still successful
        $this->wpdb_mock
            ->shouldReceive( 'update' )
            ->once()
            ->andReturn( 0 );

        $result = DatabaseManager::update(
            array( 'status' => 'completed' ),
            array( 'job_id' => 'nonexistent' )
        );

        // 0 is not false, so should return true
        $this->assertTrue( $result );
    }

    /**
     * @test
     */
    public function delete_returns_false_for_empty_where(): void {
        $result = DatabaseManager::delete( array() );

        $this->assertFalse( $result );
    }

    /**
     * @test
     */
    public function delete_returns_count_on_success(): void {
        $this->wpdb_mock
            ->shouldReceive( 'delete' )
            ->once()
            ->andReturn( 1 );

        $result = DatabaseManager::delete( array( 'job_id' => 'test-uuid' ) );

        $this->assertEquals( 1, $result );
    }

    /**
     * @test
     */
    public function get_row_returns_null_for_empty_where(): void {
        $result = DatabaseManager::get_row( array() );

        $this->assertNull( $result );
    }

    /**
     * @test
     */
    public function get_row_returns_job_object(): void {
        $expected_job = (object) array(
            'id'     => 1,
            'job_id' => 'test-uuid',
            'status' => 'pending',
        );

        $this->wpdb_mock
            ->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'SELECT * FROM wp_smi_jobs WHERE job_id = "test-uuid"' );

        $this->wpdb_mock
            ->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( $expected_job );

        $result = DatabaseManager::get_row( array( 'job_id' => 'test-uuid' ) );

        $this->assertEquals( $expected_job, $result );
    }

    /**
     * @test
     */
    public function get_results_returns_empty_array_when_no_results(): void {
        Functions\when( 'sanitize_sql_orderby' )
            ->justReturn( 'created_at DESC' );

        $this->wpdb_mock
            ->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'SELECT * FROM wp_smi_jobs WHERE status = "completed" ORDER BY created_at DESC' );

        $this->wpdb_mock
            ->shouldReceive( 'get_results' )
            ->once()
            ->andReturn( null );

        $result = DatabaseManager::get_results( array( 'where' => array( 'status' => 'completed' ) ) );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    /**
     * @test
     */
    public function get_count_returns_integer(): void {
        $this->wpdb_mock
            ->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'SELECT COUNT(*) FROM wp_smi_jobs WHERE status = "pending"' );

        $this->wpdb_mock
            ->shouldReceive( 'get_var' )
            ->once()
            ->andReturn( '5' );

        $result = DatabaseManager::get_count( array( 'status' => 'pending' ) );

        $this->assertIsInt( $result );
        $this->assertEquals( 5, $result );
    }

    /**
     * @test
     */
    public function get_count_returns_zero_for_empty_table(): void {
        $this->wpdb_mock
            ->shouldReceive( 'get_var' )
            ->once()
            ->andReturn( '0' );

        $result = DatabaseManager::get_count();

        $this->assertEquals( 0, $result );
    }

    /**
     * @test
     */
    public function jobs_table_constant_is_defined(): void {
        $this->assertEquals( 'smi_jobs', DatabaseManager::JOBS_TABLE );
    }
}
