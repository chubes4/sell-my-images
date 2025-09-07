# Upscaling Workflow

The AI-powered image upscaling workflow using Upsampler API integration.

## Workflow Initiation

**Payment Trigger**
Upscaling begins when payment is confirmed:
```php
// Triggered by Stripe webhook
do_action('smi_payment_completed', $job_id);

// Upscaling service responds to action
add_action('smi_payment_completed', [UpscalingService::class, 'process_upscale_request']);
```

**Job Preparation**
1. Validate job exists and payment confirmed
2. Extract image URL and resolution parameters
3. Update job status to 'processing'
4. Calculate expected processing time
5. Initialize progress tracking

## Upsampler API Integration

**Job Submission**
```php
$response = Upsampler::submit_upscale_job([
    'image_url' => $job->image_url,
    'scale_factor' => $scale_factor,
    'format' => 'auto',
    'webhook_url' => home_url('/smi-webhook/upsampler/'),
    'metadata' => [
        'job_id' => $job_id,
        'plugin' => 'sell-my-images'
    ]
]);
```

**Response Processing**
Successful submission returns:
```json
{
    "success": true,
    "job_id": "upsampler_job_12345",
    "status": "queued",
    "estimated_completion": "2023-10-15T10:45:00Z"
}
```

## Status Monitoring

**Webhook Integration**
Primary status updates via webhook:
```php
// Endpoint: /smi-webhook/upsampler/
public function handle_upsampler_webhook($payload) {
    $job_id = $payload['job_id'];
    $status = $payload['status'];
    
    switch ($status) {
        case 'completed':
            $this->process_completion($job_id, $payload);
            break;
        case 'failed':
            $this->handle_processing_failure($job_id, $payload);
            break;
    }
}
```

**Polling Fallback**
Backup monitoring for reliability:
- Periodic status checks every 30 seconds
- Maximum processing time: 15 minutes
- Exponential backoff on errors
- Manual admin override capability

## Image Processing States

**Processing Stages**
1. `queued` - Waiting for processing slot
2. `processing` - AI upscaling in progress
3. `completed` - High-resolution image ready
4. `failed` - Processing error occurred

**Progress Communication**
Real-time updates to customer:
```javascript
// Frontend status polling
setInterval(() => {
    $.get(wpApiSettings.root + 'smi/v1/job-status/' + jobId)
        .done(response => {
            updateProcessingStatus(response.data.status);
        });
}, 10000);
```

## Completion Processing

**File Download and Storage**
Upon successful completion:
1. Download processed image from Upsampler CDN
2. Validate file integrity and dimensions
3. Store in secure WordPress upload directory
4. Generate unique download token
5. Set expiration timestamp (24 hours)

**Download Token Generation**
```php
// Generate secure 64-character token
$token = wp_generate_password(64, false, false);
$expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Update job record
JobManager::update_job($job_id, [
    'download_token' => $token,
    'download_expires_at' => $expiry,
    'upscaled_file_path' => $file_path,
    'status' => 'completed',
    'completed_at' => current_time('mysql')
]);
```

## Email Notification

**Completion Email**
Professional HTML notification includes:
- Download link with secure token
- Processing details (resolution, dimensions)
- Expiration information
- Support contact details

**Email Template**
```php
// Generate email from template
$email_data = include SMI_PLUGIN_DIR . 'templates/email-notification.php';
wp_mail($job->email, $email_data['subject'], $email_data['message'], [
    'Content-Type: text/html; charset=UTF-8'
]);
```

## Error Handling

**Processing Failures**
Common failure scenarios:
- Unsupported image format
- File size exceeds limits
- API timeout or network errors
- Invalid image data

**Automatic Recovery**
```php
// Handle processing failure
public function handle_processing_failure($job_id, $error_reason) {
    // Mark job as failed
    JobManager::update_status($job_id, 'failed');
    
    // Initiate automatic refund
    $payment_service = new PaymentService();
    $payment_service->process_refund($job_id, $error_reason);
    
    // Notify customer
    $this->send_failure_notification($job_id, $error_reason);
}
```

## Quality Assurance

**Output Validation**
Post-processing quality checks:
- Verify output dimensions match expected scale
- Validate file format and integrity
- Check file size appropriateness
- Confirm no processing artifacts

**Success Metrics**
- Processing completion rate
- Average processing time
- Customer satisfaction scores
- Error rate monitoring

## Admin Management

**Manual Intervention**
Admin override capabilities:
```php
// Retry failed processing
do_action('smi_payment_completed', $job_id, [
    'admin_override' => true
]);
```

**Monitoring Dashboard**
- Processing queue status
- Error rate tracking
- Performance metrics
- Cost analysis reporting

## Performance Optimization

**Concurrent Processing**
- Multiple simultaneous upscaling jobs
- Queue management for high volume
- Resource utilization monitoring
- Load balancing considerations

**Cost Management**
- Credit usage tracking
- Processing cost calculation
- Profit margin monitoring
- Budget alerting system