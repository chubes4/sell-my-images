# Analytics Storage

Analytics data storage uses WordPress post meta for efficient click tracking and engagement metrics.

## Storage Method

**Post Meta Integration**
Analytics stored using `_smi_click_analytics` meta key:
- Single meta entry per post
- JSON-encoded structured data
- Atomic update operations
- WordPress cache integration

**Data Structure**
```json
{
    "attachment_123": {
        "clicks": 15,
        "last_click": "2023-10-15 14:30:00",
        "first_click": "2023-10-10 09:15:00"
    },
    "attachment_456": {
        "clicks": 8,
        "last_click": "2023-10-15 12:45:00",
        "first_click": "2023-10-12 16:20:00"
    }
}
```

## Click Tracking

**Data Collection**
Real-time button click recording:
- Attachment-specific metrics
- Timestamp precision
- Incremental counting
- First/last click tracking

**Update Process**
```php
// Atomic click tracking
$analytics = get_post_meta($post_id, '_smi_click_analytics', true) ?: [];
$analytics["attachment_{$attachment_id}"] = [
    'clicks' => ($analytics["attachment_{$attachment_id}"]['clicks'] ?? 0) + 1,
    'last_click' => current_time('mysql'),
    'first_click' => $analytics["attachment_{$attachment_id}"]['first_click'] ?? current_time('mysql')
];
update_post_meta($post_id, '_smi_click_analytics', $analytics);
```

## Performance Characteristics

**Efficient Storage**
- Single meta entry per post minimizes database overhead
- JSON encoding provides structured access
- WordPress object cache integration
- Minimal impact on site performance

**Scalability**
- Suitable for high-volume sites
- Efficient bulk data retrieval
- Aggregation-friendly structure
- Export capabilities

## Analytics Calculations

**Engagement Metrics**
Post-level engagement analysis:
- Total clicks per post
- Most popular attachments
- Click distribution patterns
- Engagement trends over time

**Conversion Analysis**
Integration with job data for conversion tracking:
```php
// Calculate conversion rates
$clicks = array_sum(array_column($analytics, 'clicks'));
$conversions = JobManager::get_count(['post_id' => $post_id, 'status' => 'completed']);
$conversion_rate = $clicks > 0 ? ($conversions / $clicks) * 100 : 0;
```

## Data Retrieval

**Single Post Analytics**
```php
$analytics = AnalyticsTracker::get_post_analytics($post_id);
// Returns: Array of click data by attachment ID
```

**Bulk Analytics**
```php
$bulk_data = AnalyticsTracker::get_bulk_analytics($post_ids);
// Returns: Associative array indexed by post ID
```

## Privacy Considerations

**Data Minimization**
- No personal information stored
- IP addresses not tracked
- Anonymous engagement metrics only
- GDPR-compliant data handling

**Data Retention**
- Configurable retention periods
- Bulk deletion capabilities
- Export functionality
- Right to erasure compliance

## Integration Points

**Admin Interface**
- Analytics page data source
- Performance report generation
- Export functionality
- Trend analysis

**REST API**
- Click tracking endpoint integration
- Real-time data collection
- Frontend JavaScript compatibility
- Error handling

## Database Optimization

**Index Utilization**
WordPress post meta indexes support efficient queries:
- Meta key index for `_smi_click_analytics`
- Post ID index for bulk operations
- Combined indexes for complex queries

**Query Patterns**
```sql
-- Get analytics for specific posts
SELECT post_id, meta_value 
FROM wp_postmeta 
WHERE meta_key = '_smi_click_analytics'
AND post_id IN (123, 456, 789);

-- Find posts with high engagement
SELECT post_id, meta_value
FROM wp_postmeta 
WHERE meta_key = '_smi_click_analytics'
AND JSON_EXTRACT(meta_value, '$.*.clicks') > 10;
```

## Backup and Recovery

**Data Export**
- CSV export functionality
- JSON data format preservation
- Post association maintenance
- Time range filtering

**Import Capabilities**
- Analytics data restoration
- Migration between sites
- Bulk data processing
- Validation and cleanup