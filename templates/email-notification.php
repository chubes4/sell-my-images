<?php
/**
 * Download Notification Email Template
 * 
 * Professional HTML email template for successful download notifications.
 * Part of dual email system: HTML for downloads, plain text for refunds.
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
 * 
 * Note: Gmail rendering fix applied - translation wrappers removed from HTML structure
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

// Pre-format the resolution text to avoid nested sprintf issues
$resolution_text = sprintf( 
    __( 'Your <strong>%s resolution</strong> image has been processed and is ready for download.', 'sell-my-images' ), 
    $job->resolution 
);

$expiry_text = sprintf( 
    __( 'This link will expire on %s', 'sell-my-images' ), 
    $expiry_date 
);

// Build email message with proper HTML structure
$message = sprintf(
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . __( 'Your High-Resolution Image is Ready', 'sell-my-images' ) . '</title>
</head>
<body style="margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        <tr>
            <td style="padding: 40px 30px; text-align: center; border-bottom: 1px solid #eee;">
                <h1 style="margin: 0; color: #333; font-size: 24px;">' . __( 'Your High-Resolution Image is Ready!', 'sell-my-images' ) . '</h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px;">
                <p style="margin: 0 0 20px 0; color: #555; font-size: 16px; line-height: 1.5;">' . __( 'Hi there!', 'sell-my-images' ) . '</p>
                
                <p style="margin: 0 0 25px 0; color: #555; font-size: 16px; line-height: 1.5;">
                    %1$s
                </p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="%2$s" style="display: inline-block; background-color: #0066cc; color: #ffffff; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                        ' . __( 'Download Your Image', 'sell-my-images' ) . '
                    </a>
                </div>
                
                <p style="margin: 25px 0 20px 0; color: #777; font-size: 14px; text-align: center;">
                    <strong>' . __( 'Important:', 'sell-my-images' ) . '</strong> %3$s
                </p>
                
                <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;">
                    <p style="margin: 0 0 10px 0; color: #777; font-size: 14px;">
                        <strong>' . __( 'Original image:', 'sell-my-images' ) . '</strong> <a href="%4$s" style="color: #0066cc; text-decoration: none;">' . __( 'View original', 'sell-my-images' ) . '</a>
                    </p>
                    <p style="margin: 0 0 20px 0; color: #777; font-size: 14px;">
                        <strong>' . __( 'Resolution:', 'sell-my-images' ) . '</strong> %5$s%6$s
                    </p>
                </div>
                
                <p style="margin: 30px 0 0 0; color: #555; font-size: 16px; line-height: 1.5;">
                    ' . __( 'Thanks for using our service!', 'sell-my-images' ) . '
                </p>
                
                <p style="margin: 20px 0 0 0; color: #555; font-size: 16px;">
                    ' . __( 'Best regards,', 'sell-my-images' ) . '<br>
                    <strong>Sarai Chinwag</strong><br>
                    %7$s
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 30px; background-color: #f9f9f9; border-top: 1px solid #eee; text-align: center;">
                <p style="margin: 0; color: #999; font-size: 12px;">
                    ' . __( 'This email was sent because you requested an AI-enhanced high-resolution image.', 'sell-my-images' ) . '
                </p>
            </td>
        </tr>
    </table>
</body>
</html>',
    $resolution_text,
    $download_url,
    $expiry_text,
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