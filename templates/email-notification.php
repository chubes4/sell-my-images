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
    /* translators: %s is the site name */
    __( 'Your high-resolution image is ready - %s', 'sell-my-images' ),
    $site_name
);

// Build terms section if configured
$terms_section = '';
if ( ! empty( $terms_conditions_url ) ) {
    $terms_section = sprintf( 
        /* translators: %s is the terms and conditions URL */
        __( "\n\nTerms & Conditions: %s", 'sell-my-images' ),
        $terms_conditions_url
    );
}

// Build email message
$message = sprintf(
    /* translators: 1: resolution, 2: download URL, 3: expiry date, 4: original image URL, 5: resolution, 6: terms section, 7: site name */
    __( "Hi there!\n\nYour %1\$s resolution image has been processed and is ready for download.\n\nDownload your image:\n%2\$s\n\nThis link will expire on %3\$s.\n\nOriginal image: %4\$s\nResolution: %5\$s%6\$s\n\nThanks for using our service!\n\nBest regards,\nSarai Chinwag\n%7\$s", 'sell-my-images' ),
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