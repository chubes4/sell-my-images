# Purchase Workflow

The complete customer purchase workflow from button click to download delivery.

## Workflow Overview

**Complete Process Flow**
1. Customer clicks download button on image
2. Modal opens with pricing and resolution options
3. Customer selects resolution (email optional)
4. Stripe checkout session created and customer redirected
5. Payment processed and webhook triggers upscaling (email backfilled from Stripe if not provided)
6. Image processed by Upsampler AI service
7. Download token generated and email sent
8. Customer downloads high-resolution image

## Phase 1: Button Interaction

**Click Detection**
```javascript
// Button click triggers modal
$(document).on('click', '.smi-get-button', function(e) {
    var $button = $(this);
    
    // Track analytics
    trackButtonClick($button);
    
    // Open pricing modal
    openModal($button);
});
```

**Data Collection**
Button data attributes provide context:
- `data-attachment-id` - WordPress media ID
- `data-post-id` - Source post identifier
- `data-src` - Original image URL
- `data-width`/`data-height` - Dimensions

## Phase 2: Pricing and Selection

**Dynamic Price Calculation**
REST API call for all resolution pricing:
```javascript
$.ajax({
    url: wpApiSettings.root + 'smi/v1/calculate-all-prices',
    data: {
        attachment_id: attachmentId,
        post_id: postId
    },
    success: function(response) {
        updateResolutionPricing(response.pricing);
    }
});
```

**Customer Selection**
Modal presents options:
- Standard Quality (4x): Web and print ready
- Premium Quality (8x): Higher resolution for large prints
- Optional email input for delivery notification (obtained from Stripe if not provided)
- Terms and conditions acknowledgment

## Phase 3: Payment Processing

**Checkout Session Creation**
```javascript
$.ajax({
    url: wpApiSettings.root + 'smi/v1/create-checkout',
    data: {
        attachment_id: attachmentId,
        post_id: postId,
        resolution: selectedResolution,
        email: customerEmail
    },
    success: function(response) {
        // Redirect to Stripe checkout
        window.location.href = response.checkout_url;
    }
});
```

**Stripe Processing**
- Secure payment through Stripe-hosted checkout
- Card validation and processing
- Success/cancel URL handling
- Payment intent creation and tracking

## Phase 4: Webhook Processing

**Payment Completion Webhook**
```php
// Stripe webhook: checkout.session.completed
public function handle_payment_success($session) {
    $job_id = $session['metadata']['job_id'];
    
    // Update job status
    JobManager::update_status($job_id, 'pending');
    
    // Trigger upscaling workflow
    do_action('smi_payment_completed', $job_id);
}
```

**Upscaling Initiation**
Payment completion triggers image processing:
1. Job status updated to 'processing'
2. Image submitted to Upsampler API
3. Processing monitoring begins
4. Customer status polling enabled

## Phase 5: Image Processing

**Upsampler Submission**
```php
$response = Upsampler::submit_upscale_job([
    'image_url' => $job->image_url,
    'scale_factor' => $scale_factor,
    'webhook_url' => home_url('/smi-webhook/upsampler/'),
    'format' => 'auto'
]);
```

**Status Monitoring**
- Real-time status polling via JavaScript
- Webhook notifications for completion
- Progress indication to customer
- Timeout handling and error recovery

## Phase 6: Completion and Delivery

**Processing Completion**
Upsampler webhook triggers final steps:
1. Download processed image from CDN
2. Store securely in WordPress uploads
3. Generate 64-character download token
4. Send notification email to customer
5. Update job status to 'completed'

**Email Notification**
HTML email with:
- Download link with secure token
- Expiration information (24 hours)
- Processing details and resolution
- Support contact information

## Phase 7: Download and Cleanup

**Secure Download**
Token-based file access:
```php
// REST API endpoint: /download/{token}
public function download_image($request) {
    $token = $request->get_param('token');
    DownloadManager::serve_download($token);
    // Streams file with appropriate headers
}
```

**Automatic Cleanup**
- Download token expiration (24 hours)
- Physical file removal after expiration
- Job record retention for analytics
- Temporary processing file cleanup

## Error Handling

**Failure Points and Recovery**
- Payment failures: Customer notification and retry
- Processing errors: Automatic refund and explanation
- Network issues: Retry logic with exponential backoff
- Timeout scenarios: Manual admin intervention

**Customer Communication**
All failure scenarios include:
- Clear explanation of the issue
- Automatic refund processing
- Support contact information
- Alternative resolution options

## Analytics Integration

**Tracking Points**
- Button click analytics (engagement)
- Conversion rate calculation (clicks to purchases)
- Revenue tracking per post/attachment
- Popular resolution analysis

**Data Collection**
- Click timestamps and post context
- Purchase completion rates
- Average order values
- Customer behavior patterns