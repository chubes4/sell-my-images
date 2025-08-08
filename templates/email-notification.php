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
    __( '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your High-Resolution Image is Ready</title>
</head>
<body style="margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        <tr>
            <td style="padding: 40px 30px; text-align: center; border-bottom: 1px solid #eee;">
                <h1 style="margin: 0; color: #333; font-size: 24px;">Your High-Resolution Image is Ready!</h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px;">
                <p style="margin: 0 0 20px 0; color: #555; font-size: 16px; line-height: 1.5;">Hi there!</p>
                
                <p style="margin: 0 0 25px 0; color: #555; font-size: 16px; line-height: 1.5;">
                    Your <strong>%1$s resolution</strong> image has been processed and is ready for download.
                </p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="%2$s" style="display: inline-block; background-color: #0066cc; color: #ffffff; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                        Download Your Image
                    </a>
                </div>
                
                <p style="margin: 25px 0 20px 0; color: #777; font-size: 14px; text-align: center;">
                    <strong>Important:</strong> This link will expire on %3$s
                </p>
                
                <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;">
                    <p style="margin: 0 0 10px 0; color: #777; font-size: 14px;">
                        <strong>Original image:</strong> <a href="%4$s" style="color: #0066cc; text-decoration: none;">View original</a>
                    </p>
                    <p style="margin: 0 0 20px 0; color: #777; font-size: 14px;">
                        <strong>Resolution:</strong> %5$s%6$s
                    </p>
                </div>
                
                <p style="margin: 30px 0 0 0; color: #555; font-size: 16px; line-height: 1.5;">
                    Thanks for using our service!
                </p>
                
                <p style="margin: 20px 0 0 0; color: #555; font-size: 16px;">
                    Best regards,<br>
                    <strong>Sarai Chinwag</strong><br>
                    %7$s
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 30px; background-color: #f9f9f9; border-top: 1px solid #eee; text-align: center;">
                <p style="margin: 0; color: #999; font-size: 12px;">
                    This email was sent because you requested an AI-enhanced high-resolution image.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>', 'sell-my-images' ),
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