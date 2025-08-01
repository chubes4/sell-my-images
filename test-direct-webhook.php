<?php
/**
 * Direct webhook test - bypass WordPress rewrite system
 */

// Check if this is a webhook request
$request_uri = $_SERVER['REQUEST_URI'] ?? '';

if (preg_match('#/smi-webhook/stripe/?#', $request_uri)) {
    // Load WordPress
    require_once('../../../wp-load.php');
    
    // Set headers
    header('Content-Type: application/json');
    
    // Log the webhook attempt
    error_log('SMI: Direct webhook test called - Request URI: ' . $request_uri);
    error_log('SMI: Request method: ' . $_SERVER['REQUEST_METHOD']);
    
    // Get webhook secret
    $webhook_secret = get_option('smi_stripe_webhook_secret', '');
    
    if (empty($webhook_secret)) {
        http_response_code(500);
        echo json_encode(['error' => 'Webhook secret not configured']);
        exit;
    }
    
    // For now, just return success to test basic functionality
    echo json_encode([
        'status' => 'received',
        'message' => 'Direct webhook handler working',
        'secret_configured' => !empty($webhook_secret),
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $request_uri
    ]);
    exit;
}

// If not a webhook request, return 404
http_response_code(404);
echo '404 Not Found';
exit;