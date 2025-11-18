# Database Manager

The Database Manager provides centralized database operations with standardized CRUD methods and automated schema management.

## Table Schema

**Jobs Table (`wp_smi_jobs`)**
Complete job lifecycle tracking with financial and processing data:

```sql
CREATE TABLE wp_smi_jobs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    job_id varchar(255) NOT NULL,
    image_url text NOT NULL,
    resolution varchar(10) NOT NULL,
    email varchar(255) DEFAULT NULL,
    post_id bigint(20) unsigned NOT NULL,
    attachment_id bigint(20) unsigned DEFAULT NULL,
    image_width int unsigned DEFAULT NULL,
    image_height int unsigned DEFAULT NULL,
    status varchar(20) DEFAULT 'pending',
    upsampler_job_id varchar(255) DEFAULT NULL,
    upscaled_url text DEFAULT NULL,
    upscaled_file_path text DEFAULT NULL,
    stripe_payment_intent_id varchar(255) DEFAULT NULL,
    stripe_checkout_session_id varchar(255) DEFAULT NULL,
    payment_status varchar(20) DEFAULT 'pending',
    amount_charged decimal(10,2) DEFAULT NULL,
    amount_cost decimal(10,2) DEFAULT NULL,
    credits_used int DEFAULT NULL,
    download_token varchar(64) DEFAULT NULL,
    download_expires_at datetime DEFAULT NULL,
    email_sent tinyint(1) DEFAULT 0,
    paid_at datetime DEFAULT NULL,
    processing_started_at datetime DEFAULT NULL,
    completed_at datetime DEFAULT NULL,
    failed_at datetime DEFAULT NULL,
    refunded_at datetime DEFAULT NULL,
    refund_reason text DEFAULT NULL,
    refund_amount decimal(10,2) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY job_id (job_id),
    KEY post_id (post_id),
    KEY attachment_id (attachment_id),
    KEY post_attachment (post_id, attachment_id),
    KEY status (status),
    KEY payment_status (payment_status),
    KEY download_token (download_token),
    KEY stripe_checkout_session_id (stripe_checkout_session_id),
    KEY upsampler_job_id (upsampler_job_id),
    KEY email (email),
    KEY created_at (created_at)
) {$wpdb->get_charset_collate()};
```

## CRUD Operations

**Insert Records**
```php
$result = DatabaseManager::insert([
    'job_id' => wp_generate_uuid4(),
    'image_url' => $url,
    'email' => $email,
    'post_id' => $post_id
]);
// Returns: ['id' => 123, 'rows_affected' => 1]
```

**Update Records**
```php
DatabaseManager::update(
    ['status' => 'completed'],
    ['job_id' => $job_id]
);
```

**Query Records**
```php
$jobs = DatabaseManager::get_results([
    'where' => ['status' => 'pending'],
    'order_by' => 'created_at',
    'order' => 'DESC',
    'limit' => 50
]);
```

**Delete Records**
```php
$deleted = DatabaseManager::delete(['job_id' => $job_id]);
// Returns: number of deleted rows
```

## Advanced Querying

**Custom Queries**
```php
$results = DatabaseManager::query(
    "SELECT * FROM {$table} WHERE created_at > %s",
    [date('Y-m-d H:i:s', strtotime('-7 days'))]
);
```

**Count Operations**
```php
$pending_count = DatabaseManager::get_count(['status' => 'pending']);
```

## Data Management

**Automatic Formatting**
- Format detection based on data types
- Prepared statement generation
- SQL injection prevention
- Type-safe operations

**Cleanup Operations**
```php
// Remove expired downloads while preserving job records
$cleaned = DatabaseManager::cleanup_expired_downloads();
```

## Index Optimization

**Performance Indexes**
- `job_id` - Unique identifier lookups
- `post_attachment` - Composite for analytics
- `status` - Job state filtering
- `payment_status` - Financial queries
- `download_token` - Secure access
- `created_at` - Time-based queries

**Query Optimization**
- Proper WHERE clause construction
- Efficient JOIN operations
- Pagination support
- Large dataset handling

## Error Handling

**Database Errors**
- Connection failure recovery
- Query error logging
- Transaction support
- Deadlock handling

**Validation**
- Data type checking
- Required field validation
- Constraint enforcement
- Foreign key relationships

## Schema Management

**Table Creation**
- `dbDelta()` integration for WordPress compatibility
- Charset and collation handling
- Index creation and management
- Upgrade path support

**Migration Support**
- Version-aware schema updates
- Backward compatibility maintenance
- Data preservation during upgrades
- Rollback capability