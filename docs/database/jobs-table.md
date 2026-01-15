# Jobs Table Schema

The jobs table (`wp_smi_jobs`) provides comprehensive tracking of the complete image processing workflow.

## Table Structure

**Core Identification**
- `id` - Auto-increment primary key
- `job_id` - UUID unique identifier for external references
- `post_id` - WordPress post where image appears
- `attachment_id` - WordPress media library reference

**Image Information**
- `image_url` - Original image URL for processing
- `image_width` - Original image width in pixels
- `image_height` - Original image height in pixels
- `resolution` - Target upscaling factor ('4x', '8x')

**Processing Status**
- `status` - Job processing state
  - `awaiting_payment` - Stripe checkout created
  - `pending` - Payment received, ready for processing
  - `processing` - Upsampler API processing image
  - `completed` - Ready for download
  - `failed` - Processing error occurred
  - `abandoned` - Payment not completed

## Financial Tracking

**Payment Integration**
- `stripe_payment_intent_id` - Stripe payment reference
- `stripe_checkout_session_id` - Stripe session tracking
- `payment_status` - Payment state ('pending', 'paid', 'failed')
- `paid_at` - Payment completion timestamp

**Cost Analysis**
- `amount_charged` - Customer payment amount
- `amount_cost` - Processing cost calculation
- `credits_used` - Upsampler credits consumed
- `refunded_at` - Refund processing timestamp
- `refund_amount` - Refund amount issued
- `refund_reason` - Reason for refund

## Processing Workflow

**External Service Integration**
- `upsampler_job_id` - External processing reference
- `upscaled_url` - Temporary processed image URL
- `upscaled_file_path` - Local storage path

**Download Management**
- `download_token` - Secure 64-character access token
- `download_expires_at` - Token expiration timestamp
- `email_sent` - Notification delivery confirmation

## Timestamp Tracking

**Lifecycle Timestamps**
- `created_at` - Job creation (auto-generated)
- `updated_at` - Last modification (auto-updated)
- `processing_started_at` - Upscaling initiation
- `completed_at` - Processing completion
- `failed_at` - Error occurrence

**Customer Communication**
- `email` - Customer email for notifications
- `email_sent` - Boolean delivery confirmation

## Database Indexes

**Performance Optimization**
```sql
UNIQUE KEY job_id (job_id)                    -- UUID lookups
KEY post_id (post_id)                         -- Post-based queries
KEY attachment_id (attachment_id)             -- Attachment queries
KEY post_attachment (post_id, attachment_id)  -- Analytics composite
KEY status (status)                           -- Status filtering
KEY payment_status (payment_status)          -- Financial queries
KEY download_token (download_token)           -- Secure downloads
KEY created_at (created_at)                   -- Time-based queries
```

## Data Types and Constraints

**Field Specifications**
- `job_id` - VARCHAR(255) NOT NULL UNIQUE
- `email` - VARCHAR(255) DEFAULT NULL
- `amount_*` - DECIMAL(10,2) for financial precision
- `*_at` - DATETIME for timestamp storage
- `download_token` - VARCHAR(64) for secure access

**Data Validation**
- Job ID uniqueness enforced at database level
- Foreign key relationships with WordPress tables
- Status values validated against constants
- Financial amounts stored with currency precision

## Query Patterns

**Common Queries**
```sql
-- Active jobs requiring processing
SELECT * FROM wp_smi_jobs 
WHERE status = 'pending' 
ORDER BY created_at ASC;

-- Jobs ready for cleanup
SELECT * FROM wp_smi_jobs 
WHERE download_expires_at < NOW()
AND upscaled_file_path IS NOT NULL;

-- Revenue analysis
SELECT SUM(amount_charged) as revenue,
       COUNT(*) as completed_jobs
FROM wp_smi_jobs 
WHERE status = 'completed'
AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);
```

## Maintenance Operations

**Cleanup Procedures**
- Expired download file removal
- Old job record archival
- Failed job cleanup
- Temporary file management

**Data Retention**
- Configurable retention periods
- Automatic cleanup schedules
- Manual purge capabilities
- Export before deletion