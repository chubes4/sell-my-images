<?php
/**
 * Debug Webhook Script
 * 
 * This script helps debug webhook issues and test the image retrieval process
 * Place this file in your WordPress root directory and access it via browser
 */

// Load WordPress
require_once( dirname( __FILE__ ) . '/wp-load.php' );

// Security check
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized access' );
}

// Prevent direct access without proper authentication
if ( ! isset( $_GET['debug_key'] ) || $_GET['debug_key'] !== 'smi_debug_2024' ) {
    wp_die( 'Invalid debug key' );
}

echo '<h1>Sell My Images - Webhook Debug</h1>';

// Test webhook URL generation
echo '<h2>Webhook URLs</h2>';
echo '<p><strong>Upsampler Webhook:</strong> ' . home_url( '/smi-webhook/upsampler/' ) . '</p>';
echo '<p><strong>Stripe Webhook:</strong> ' . home_url( '/smi-webhook/stripe/' ) . '</p>';

// Test file upload directory
echo '<h2>File Storage</h2>';
$upload_dir = wp_upload_dir();
$smi_dir = $upload_dir['basedir'] . '/sell-my-images';
echo '<p><strong>Upload Directory:</strong> ' . $smi_dir . '</p>';
echo '<p><strong>Directory Exists:</strong> ' . ( file_exists( $smi_dir ) ? 'Yes' : 'No' ) . '</p>';
echo '<p><strong>Directory Writable:</strong> ' . ( is_writable( $smi_dir ) ? 'Yes' : 'No' ) . '</p>';

// Test recent jobs
echo '<h2>Recent Jobs</h2>';
global $wpdb;
$recent_jobs = $wpdb->get_results( "
    SELECT job_id, status, payment_status, upscaled_url, completed_at, upscaled_file_path, download_token
    FROM {$wpdb->prefix}smi_jobs 
    ORDER BY created_at DESC 
    LIMIT 5
" );

if ( $recent_jobs ) {
    echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><th>Job ID</th><th>Status</th><th>Payment</th><th>Upscaled URL</th><th>File Path</th><th>Download Token</th><th>Completed</th></tr>';
    
    foreach ( $recent_jobs as $job ) {
        echo '<tr>';
        echo '<td>' . esc_html( substr( $job->job_id, 0, 8 ) ) . '...</td>';
        echo '<td>' . esc_html( $job->status ) . '</td>';
        echo '<td>' . esc_html( $job->payment_status ) . '</td>';
        echo '<td>' . ( $job->upscaled_url ? esc_html( substr( $job->upscaled_url, 0, 50 ) ) . '...' : 'None' ) . '</td>';
        echo '<td>' . ( $job->upscaled_file_path ? esc_html( $job->upscaled_file_path ) : 'None' ) . '</td>';
        echo '<td>' . ( $job->download_token ? esc_html( substr( $job->download_token, 0, 8 ) ) . '...' : 'None' ) . '</td>';
        echo '<td>' . esc_html( $job->completed_at ?? 'Not completed' ) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p>No jobs found in database.</p>';
}

// Test webhook handler registration
echo '<h2>Webhook Handlers</h2>';
$registered_handlers = \SellMyImages\Managers\WebhookManager::get_registered_services();
echo '<p><strong>Registered Services:</strong> ' . implode( ', ', $registered_handlers ) . '</p>';

// Test file download functionality
echo '<h2>File Download Test</h2>';
if ( isset( $_GET['test_download'] ) && $_GET['test_download'] === '1' ) {
    echo '<p>Testing file download functionality...</p>';
    
    // Create a test job
    $test_job_id = wp_generate_uuid4();
    $test_url = 'https://via.placeholder.com/100x100.png';
    
    echo '<p>Test Job ID: ' . $test_job_id . '</p>';
    echo '<p>Test URL: ' . $test_url . '</p>';
    
    // Test the download
    $result = \SellMyImages\Managers\FileManager::download_from_upsampler( $test_url, $test_job_id );
    
    if ( $result ) {
        echo '<p style="color: green;">✓ File downloaded successfully: ' . $result . '</p>';
        echo '<p>File size: ' . filesize( $result ) . ' bytes</p>';
        
        // Clean up test file
        unlink( $result );
        echo '<p>Test file cleaned up.</p>';
    } else {
        echo '<p style="color: red;">✗ File download failed</p>';
    }
} else {
    echo '<p><a href="?debug_key=smi_debug_2024&test_download=1">Test File Download</a></p>';
}

// Test webhook simulation
echo '<h2>Webhook Simulation</h2>';
if ( isset( $_GET['simulate_webhook'] ) && $_GET['simulate_webhook'] === '1' ) {
    echo '<p>Simulating Upsampler webhook...</p>';
    
    // Find a completed job to simulate
    $completed_job = $wpdb->get_row( "
        SELECT job_id, upscaled_url 
        FROM {$wpdb->prefix}smi_jobs 
        WHERE status = 'completed' AND upscaled_url IS NOT NULL 
        LIMIT 1
    " );
    
    if ( $completed_job ) {
        echo '<p>Simulating webhook for job: ' . $completed_job->job_id . '</p>';
        
        // Simulate webhook data
        $webhook_data = array(
            'id' => $completed_job->job_id,
            'status' => 'SUCCESS',
            'imageUrl' => $completed_job->upscaled_url,
            'creditCost' => 1
        );
        
        // Call the webhook handler directly
        $upscaling_service = new \SellMyImages\Services\UpscalingService();
        
        // Use reflection to call private method
        $reflection = new ReflectionClass( $upscaling_service );
        $method = $reflection->getMethod( 'handle_upsampler_webhook' );
        $method->setAccessible( true );
        
        try {
            $method->invoke( $upscaling_service, $webhook_data );
            echo '<p style="color: green;">✓ Webhook simulation completed</p>';
        } catch ( Exception $e ) {
            echo '<p style="color: red;">✗ Webhook simulation failed: ' . $e->getMessage() . '</p>';
        }
    } else {
        echo '<p>No completed jobs found to simulate webhook.</p>';
    }
} else {
    echo '<p><a href="?debug_key=smi_debug_2024&simulate_webhook=1">Simulate Upsampler Webhook</a></p>';
}

echo '<hr>';
echo '<p><strong>Debug Key:</strong> smi_debug_2024</p>';
echo '<p><strong>Usage:</strong> Add ?debug_key=smi_debug_2024 to URL to access this debug page.</p>'; 