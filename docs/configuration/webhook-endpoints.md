# Webhook Endpoints

The plugin provides secure webhook endpoints for external service integration, implemented via `parse_request` (not rewrite rules) for reliability.

## Endpoint Structure

**Base Configuration**
Webhooks are handled at:
- Stripe: `/smi-webhook/stripe/`
- Upsampler: `/smi-webhook/upsampler/`

## Routing Model

Webhook requests are detected by matching the request URI against `/smi-webhook/{service}/` and then dispatching to a registered handler (see `SellMyImages\Managers\WebhookManager`). Requests for unregistered services return `404`.

## Shared Security Utilities

The `WebhookManager` provides shared helpers used by webhook handlers:
- Request method enforcement (default `POST`)
- Optional Content-Type checking
- Payload size limits (default 1MB via `SellMyImages\Config\Constants::MAX_WEBHOOK_PAYLOAD_SIZE`, filterable with `smi_max_webhook_payload_size`)

## Stripe Webhooks

**Webhook URL Configuration**
Configure in Stripe Dashboard:
```
https://yoursite.com/smi-webhook/stripe/
```

**Supported Events**
- `checkout.session.completed` - Payment successful
- `checkout.session.expired` - Session timeout
- `payment_intent.payment_failed` - Payment declined

**Event Processing**
```php
// Verify webhook signature
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);

// Process based on event type
switch ($event['type']) {
    case 'checkout.session.completed':
        $this->handle_payment_success($event['data']['object']);
        break;
}
```

## Upsampler Webhooks

**Webhook URL Configuration**
Provided to Upsampler API during job submission:
```
https://yoursite.com/smi-webhook/upsampler/
```

**Event Types**
- `job.completed` - Image processing finished
- `job.failed` - Processing error occurred
- `job.timeout` - Processing exceeded time limit

**Processing Logic**
```php
// Validate API key from headers
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($api_key !== get_option('smi_upsampler_api_key')) {
    http_response_code(401);
    exit('Unauthorized');
}

// Process completion
$job_id = $payload['job_id'];
$result_url = $payload['result_url'];
$this->process_upscale_completion($job_id, $result_url);
```

## Webhook Management

**Setup Process**
1. Configure endpoint URLs in external services
2. Set webhook secrets in plugin settings
3. Test webhook delivery
4. Monitor webhook logs for errors

**Error Handling**
- Invalid signatures: Log and reject
- Missing job IDs: Return 400 Bad Request
- Processing errors: Queue for retry
- Network timeouts: Implement exponential backoff

## Security Measures

**Signature Validation**
All webhook payloads verified:
```php
// Stripe signature verification
$expected_signature = hash_hmac('sha256', $payload, $webhook_secret);
if (!hash_equals($sig_header, 'sha256=' . $expected_signature)) {
    http_response_code(400);
    exit('Invalid signature');
}
```

**Request Validation**
- Content-Type header checking
- Payload size limits
- Rate limiting per IP
- Malformed JSON rejection

## Monitoring and Debugging

**Webhook Logging**
All webhook activity logged:
- Successful processing
- Signature validation failures  
- Processing errors
- Response times

**Debug Information**
- Request headers and payload
- Signature calculation details
- Processing step outcomes
- Error stack traces

## Reliability Features

**Idempotency**
Duplicate webhook handling:
- Event ID tracking
- Job status verification
- Safe retry logic
- Duplicate detection

**Failure Recovery**
- Dead letter queue for failed processing
- Manual retry capability
- Admin notification system
- Automatic escalation

## Testing Setup

**Local Development**
Using Stripe CLI for testing:
```bash
stripe listen --forward-to=https://yoursite.local/smi-webhook/stripe/
```

**Webhook Verification**
Test endpoints with curl:
```bash
curl -X POST https://yoursite.com/smi-webhook/stripe/ \
  -H "Stripe-Signature: t=1234,v1=signature" \
  -d '{"type": "checkout.session.completed"}'
```