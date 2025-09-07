# Email Notification System

The email system provides professional HTML notifications for download delivery and customer communication.

## Email Types

**Download Completion**
Professional HTML email with download link:
- Template: `templates/email-notification.php`
- Format: HTML with fallback text
- Purpose: Deliver secure download link
- Timing: Immediate after processing completion

**Refund Notifications**
Plain text notifications for payment issues:
- Format: Simple text email
- Generated inline in code
- Purpose: Explain refund and reasons
- Timing: Automatic with failed processing

## Template System

**HTML Email Template**
Professional email design with:
```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your High-Resolution Image is Ready</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <!-- Professional email layout -->
</body>
</html>
```

**Template Variables**
Available in email template:
- `$job` - Complete job object
- `$download_url` - Secure download link
- `$expiry_date` - Token expiration
- `$site_name` - WordPress site name
- `$terms_conditions_url` - Legal compliance link

## Email Content

**Download Notification Content**
- Professional greeting and completion message
- Clear download button with secure link
- Expiration information (24 hours default)
- Original image reference link
- Resolution and dimension details
- Support contact information

**Responsive Design**
Email template optimized for:
- Desktop email clients
- Mobile device viewing
- Gmail rendering compatibility
- Outlook compatibility

## Delivery System

**WordPress Integration**
```php
// Send completion notification
wp_mail(
    $job->email,
    $email_data['subject'],
    $email_data['message'],
    ['Content-Type: text/html; charset=UTF-8']
);
```

**Email Validation**
- Customer email format validation
- Bounce handling considerations
- Delivery confirmation tracking
- Spam filter optimization

## Security Features

**Download Link Security**
- Secure 64-character token generation
- Time-limited access (24 hours)
- Single-use download capability
- Token validation requirements

**Email Safety**
- No sensitive data in email content
- Secure server-side token generation
- Protected download URLs
- Privacy-compliant messaging

## Customization Options

**Branding Configuration**
- Site name integration
- Custom support contact URLs
- Terms and conditions links
- Brand-consistent styling

**Content Customization**
Template hooks for modifications:
```php
// Customize email subject
add_filter('smi_email_subject', function($subject, $job) {
    return "Your {$job->resolution} image is ready!";
}, 10, 2);
```

## Error Handling

**Email Delivery Failures**
- WordPress mail function fallback
- SMTP configuration support
- Delivery retry mechanism
- Admin notification on failures

**Template Errors**
- Graceful fallback to simple text
- Error logging for debugging
- Template validation
- Default content safety net

## Multi-language Support

**Internationalization**
All text strings wrapped for translation:
```php
__('Your high-resolution image is ready!', 'sell-my-images')
```

**Template Localization**
- Language-specific email templates
- Cultural adaptation support
- RTL language compatibility
- Timezone handling

## Email Analytics

**Delivery Tracking**
- Email sent confirmation in database
- Delivery timestamp recording
- Bounce detection capability
- Open rate tracking (optional)

**Customer Engagement**
- Download link click tracking
- Email effectiveness metrics
- Customer response monitoring
- Support request correlation

## Compliance Features

**Privacy Compliance**
- GDPR-compliant data handling
- CAN-SPAM Act compliance
- Unsubscribe mechanism (if applicable)
- Data retention policies

**Legal Requirements**
- Terms and conditions inclusion
- Contact information provision
- Business identification
- Refund policy references

## Integration Points

**Job Management**
- Email triggered by job completion
- Status update integration
- Customer data synchronization
- Processing timeline coordination

**Admin Notifications**
Copy emails to administrators:
```php
// Admin notification
wp_mail(
    get_option('admin_email'),
    'Copy: ' . $email_data['subject'],
    $email_data['message']
);
```

## Testing and Development

**Email Testing**
- Template preview capability
- Test email sending
- Client compatibility testing
- Spam filter testing

**Development Tools**
- Email template validation
- Content preview functionality
- Variable substitution testing
- Delivery confirmation