# Jobs Management Page

The Jobs page provides comprehensive management of image processing requests. Access through **WordPress Admin > Sell My Images > Jobs**.

## Job Overview

**Job Lifecycle**
1. `awaiting_payment` - Stripe checkout created
2. `pending` - Payment received, awaiting processing
3. `processing` - Image being upscaled by Upsampler
4. `completed` - Download ready and email sent
5. `failed` - Processing error, automatic refund issued

## Management Features

**Job Listing**
- Paginated job display with filtering
- Status-based sorting and filtering
- Search by job ID, email, or post
- Batch operations for multiple jobs

**Job Details**
- Complete payment information
- Processing timestamps and duration
- Download token and expiration status
- Refund history and reasons

## Admin Actions

**Manual Interventions**
- Retry failed upscaling processes
- Regenerate expired download tokens
- Issue manual refunds with reasons
- Update job status for edge cases

**Retry Upscaling**
Admin override system allows retrying any job:
```php
do_action( 'smi_payment_completed', $job_id, array( 'admin_override' => true ) );
```

## Job Data Structure

**Database Fields**
- `job_id` - UUID identifier
- `payment_status` - Stripe payment state
- `upsampler_job_id` - External processing reference
- `download_token` - Secure access token
- `amount_charged` - Customer payment
- `amount_cost` - Processing cost
- `refund_amount` - Refund tracking

**Timestamps**
- `created_at` - Job creation
- `paid_at` - Payment completion
- `processing_started_at` - Upscaling began
- `completed_at` - Processing finished
- `failed_at` - Error occurrence

## Filtering and Search

**Available Filters**
- Job status (all statuses supported)
- Payment status (pending, paid, failed)
- Date range selection
- Email address search
- Post and attachment IDs

**Pagination**
- Configurable results per page
- Efficient database queries with indexes
- Large dataset handling optimization
- Export capabilities for reporting