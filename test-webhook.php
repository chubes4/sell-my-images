<?php
/**
 * Test Upsampler Webhook Endpoint
 * 
 * This script tests the webhook endpoint with Upsampler's actual payload format
 * Run this from the plugin directory to test webhook handling
 */

// Test payload matching Upsampler's documentation
$test_payloads = array(
    'success' => array(
        'id' => '12345678-1234-1234-1234-123456789012',
        'status' => 'SUCCESS',
        'compressedImageUrl' => 'https://upsampler.com/temp/compressed.jpg',
        'imageUrl' => 'https://upsampler.com/temp/full-quality.png',
        'creditCost' => 2
    ),
    'failure' => array(
        'id' => '12345678-1234-1234-1234-123456789012',
        'status' => 'FAILED',
        'error' => 'Failed to process image due to an internal error. Your credits have been refunded.'
    )
);

// Get webhook URL
$webhook_url = 'http://localhost/smi-webhook/upsampler/'; // Adjust as needed

echo "Testing Upsampler webhook endpoint...\n\n";

foreach ( $test_payloads as $test_name => $payload ) {
    echo "Testing {$test_name} payload:\n";
    echo json_encode( $payload, JSON_PRETTY_PRINT ) . "\n";
    
    $ch = curl_init();
    curl_setopt_array( $ch, array(
        CURLOPT_URL => $webhook_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode( $payload ),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen( json_encode( $payload ) )
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_VERBOSE => true
    ) );
    
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $error = curl_error( $ch );
    curl_close( $ch );
    
    echo "HTTP Code: {$http_code}\n";
    echo "Response: {$response}\n";
    if ( $error ) {
        echo "Error: {$error}\n";
    }
    echo "\n" . str_repeat('-', 50) . "\n\n";
}

echo "Test completed. Check your WordPress error logs for webhook processing details.\n";