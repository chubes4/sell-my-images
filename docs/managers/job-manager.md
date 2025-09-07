# Job Manager

The Job Manager handles the complete lifecycle of image processing requests from creation to completion.

## Job Lifecycle

**Status Progression**
1. `awaiting_payment` - Stripe checkout session created
2. `pending` - Payment confirmed, ready for processing
3. `processing` - Upsampler API processing image
4. `completed` - High-resolution image ready for download
5. `failed` - Processing error, refund initiated
6. `abandoned` - Payment not completed within timeout

## Job Creation

**Initial Job Setup**
```php
$job_data = JobManager::create_job([
    'image_url' => $attachment_url,
    'resolution' => '4x',
    'email' => $customer_email,
    'post_id' => $post_id,
    'attachment_id' => $attachment_id,
    'image_width' => $width,
    'image_height' => $height
]);
```

**UUID Generation**
- WordPress `wp_generate_uuid4()` for unique identifiers
- Database unique constraint enforcement
- Cross-system compatibility
- Audit trail support

## Status Management

**Status Updates**
```php
// Update job status with timestamp
JobManager::update_status($job_id, 'processing');

// Update with additional data
JobManager::update_job($job_id, [
    'upsampler_job_id' => $external_id,
    'processing_started_at' => current_time('mysql')
]);
```

**Payment Integration**
```php
// Link Stripe checkout session
JobManager::update_checkout_session($job_id, $session_id);

// Record payment completion
JobManager::mark_payment_completed($job_id, $payment_intent_id);
```

## Cost Tracking

**Financial Data**
- Customer charge amount
- Processing cost calculation
- Profit margin tracking
- Refund amount recording

**Cost Data Structure**
```php
JobManager::update_cost_data($job_id, [
    'upsampler_cost' => 0.64,
    'customer_price' => 3.84,
    'credits_required' => 16.0,
    'markup_percentage' => 500
]);
```

## Download Management

**Token Generation**
```php
// Generate secure download token
$token = JobManager::generate_download_token($job_id);
// Returns: 64-character random string via wp_generate_password()
```

**Expiration Handling**
- Default 24-hour expiration
- Configurable through settings
- Automatic cleanup of expired files
- Email notification before expiration

## Job Retrieval

**Single Job Lookup**
```php
$job = JobManager::get_job($job_id);
// Returns: stdClass with all job data or WP_Error
```

**Job Queries**
```php
$recent_jobs = JobManager::get_jobs([
    'status' => ['completed', 'processing'],
    'limit' => 20,
    'order_by' => 'created_at'
]);
```

## Error Handling

**Job Failures**
```php
// Mark job as failed with reason
JobManager::mark_failed($job_id, 'upsampler_timeout');

// Trigger automatic refund process
do_action('smi_job_failed', $job_id, $failure_reason);
```

**Recovery Operations**
- Admin retry functionality
- Automatic retry for transient errors
- Manual intervention support
- Customer notification system

## Integration Points

**WordPress Hooks**
- `smi_job_created` - New job created
- `smi_payment_completed` - Payment successful
- `smi_job_completed` - Processing finished
- `smi_job_failed` - Processing error

**External Services**
- Stripe payment intent linking
- Upsampler job ID tracking
- Email notification triggers
- Download security management