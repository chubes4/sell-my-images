<?php
/**
 * Email Notification Template
 * 
 * Template for download notification emails sent to customers
 * 
 * @package SellMyImages
 * @since 1.0.0
 * 
 * Available variables:
 * @var object $job Job object with all job data
 * @var string $download_url Complete download URL with token
 * @var string $expiry_date Formatted expiry date and time
 * @var string $terms_conditions_url Terms & conditions URL (if configured)
 * @var string $site_name Site name from get_bloginfo('name')
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Build email subject
$subject = sprintf(
    __( 'Your high-resolution image is ready - %s', 'sell-my-images' ),
    $site_name
);

// Build terms section if configured
$terms_section = '';
if ( ! empty( $terms_conditions_url ) ) {
    $terms_section = sprintf( 
        __( "\n\nTerms & Conditions: %s", 'sell-my-images' ),
        $terms_conditions_url
    );
}

// Build email message
$message = sprintf(
    __( "Hi there!\n\nYour %s resolution image has been processed and is ready for download.\n\nDownload your image:\n%s\n\nThis link will expire on %s.\n\nOriginal image: %s\nResolution: %s%s\n\nThanks for using our service!\n\n%s", 'sell-my-images' ),
    $job->resolution,
    $download_url,
    $expiry_date,
    $job->image_url,
    $job->resolution,
    $terms_section,
    $site_name
);

// Return both subject and message
return array(
    'subject' => $subject,
    'message' => $message,
);