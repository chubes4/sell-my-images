# Stripe Integration

The Stripe API client handles payment processing with checkout sessions, webhooks, and automatic refunds.

## Payment Flow

**Checkout Process**
1. Create Stripe checkout session
2. Redirect customer to Stripe-hosted payment
3. Process webhook on payment completion
4. Trigger image upscaling workflow

**Session Configuration**
- Payment method: Card payments only
- Success/cancel URLs with job tracking
- Automatic tax calculation disabled
- Customer email collection required

## Webhook Processing

**Supported Events**
- `checkout.session.completed` - Payment successful
- `checkout.session.expired` - Payment timeout
- `payment_intent.payment_failed` - Payment declined

**Security Verification**
- Webhook signature validation using endpoint secret
- Payload size limits (1MB maximum)
- Event deduplication handling

## Refund System

**Automatic Refunds**
Failed image processing triggers automatic refunds:
```php
$refund = $stripe->refunds->create([
    'payment_intent' => $payment_intent_id,
    'reason' => 'requested_by_customer',
    'metadata' => [
        'job_id' => $job_id,
        'failure_reason' => 'upscaling_failed'
    ]
]);
```

**Manual Refunds**
Admin-initiated refunds with reason tracking:
- Full or partial refund amounts
- Reason categorization for analytics
- Customer notification emails
- Database audit trail

## Configuration Management

**API Credentials**
- Test/Live environment switching
- Publishable key for frontend
- Secret key for server operations
- Webhook endpoint secret

**Environment Detection**
Automatic environment selection based on `smi_stripe_test_mode` setting.

## Error Handling

**Payment Failures**
- Declined cards: Customer notification
- Network errors: Retry mechanism
- API limits: Graceful degradation

**Webhook Failures**
- Invalid signatures: Security logging
- Processing errors: Dead letter queue
- Network timeouts: Retry with exponential backoff

## Metadata Tracking

**Job Association**
All Stripe objects include metadata:
```php
'metadata' => [
    'plugin' => 'sell-my-images',
    'job_id' => $job_id,
    'post_id' => $post_id,
    'resolution' => $resolution
]
```

**Audit Trail**
- Payment intent creation timestamps
- Checkout session tracking
- Refund reason documentation
- Customer communication logs