# Payment Service

The Payment Service coordinates the complete payment workflow using Stripe integration and job management.

## Payment Workflow

**Checkout Creation**
1. Validate Stripe configuration
2. Calculate pricing with markup
3. Create job record in database
4. Generate Stripe checkout session
5. Return checkout URL for redirect

**Payment Completion**
1. Receive Stripe webhook notification
2. Validate payment intent
3. Update job status to 'pending'
4. Trigger upscaling workflow
5. Send confirmation to customer

## Stripe Configuration

**Environment Management**
Automatic selection based on settings:
```php
$config = PaymentService::get_stripe_config();
// Returns appropriate keys for test/live mode
```

**Configuration Validation**
```php
$result = PaymentService::validate_configuration();
// Returns WP_Error for issues or true for valid setup
```

## Checkout Session Creation

**Session Configuration**
```php
$session = $stripe->checkout->sessions->create([
    'payment_method_types' => ['card'],
    'line_items' => [{
        'price_data' => [
            'currency' => 'usd',
            'product_data' => [
                'name' => 'High-Resolution Image (4x)',
                'description' => '2000x2000 → 8000x8000 pixels'
            ],
            'unit_amount' => $amount_cents
        ],
        'quantity' => 1
    }],
    'mode' => 'payment',
    'success_url' => $success_url,
    'cancel_url' => $cancel_url,
    'metadata' => [
        'job_id' => $job_id,
        'plugin' => 'sell-my-images'
    ]
]);
```

## Webhook Processing

**Supported Events**
- `checkout.session.completed` - Payment successful
- `payment_intent.payment_failed` - Payment declined
- `checkout.session.expired` - Session timeout

**Security Validation**
```php
// Verify webhook signature
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
```

## Refund Management

**Automatic Refunds**
Failed processing triggers automatic refunds:
```php
public function process_refund($job_id, $reason) {
    $job = JobManager::get_job($job_id);
    $refund = $this->stripe->refunds->create([
        'payment_intent' => $job->stripe_payment_intent_id,
        'reason' => 'requested_by_customer',
        'metadata' => [
            'job_id' => $job_id,
            'failure_reason' => $reason
        ]
    ]);
}
```

**Refund Tracking**
- Complete audit trail in database
- Customer notification emails
- Admin reporting integration
- Financial reconciliation support

## Error Handling

**Payment Failures**
- Card declined: Customer notification
- Insufficient funds: Retry suggestion
- Network errors: Graceful degradation
- API limits: Queuing system

**Recovery Mechanisms**
- Automatic retry for transient errors
- Manual admin intervention support
- Customer support integration
- Detailed error logging

## Success/Cancel URLs

**URL Generation**
```php
$success_url = add_query_arg([
    'smi_payment' => 'success',
    'job_id' => $job_id,
    'session_id' => '{CHECKOUT_SESSION_ID}'
], $return_url);
```

**Status Handling**
- JavaScript detection of URL parameters
- Modal display of payment status
- Automatic job status polling
- User experience optimization

## Integration Points

**Job Management**
- Create job before payment
- Update status on completion
- Link payment intent to job
- Coordinate with upscaling service

**Customer Communication**
- Email confirmation on payment
- Processing status updates
- Completion notifications
- Support contact information