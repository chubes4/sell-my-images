# Analytics Tracker

The Analytics Tracker records user engagement with download buttons using WordPress post meta storage.

## Click Tracking System

**Data Collection**
Button click events tracked in real-time:
- Post-level engagement metrics
- Attachment-specific click counts
- Timestamp recording for trends
- Conversion rate calculation support

**Storage Method**
Post meta key: `_smi_click_analytics`
```json
{
    "attachment_456": {
        "clicks": 15,
        "last_click": "2023-10-15 14:30:00",
        "first_click": "2023-10-10 09:15:00"
    },
    "attachment_789": {
        "clicks": 8,
        "last_click": "2023-10-15 12:45:00",
        "first_click": "2023-10-12 16:20:00"
    }
}
```

## Tracking Methods

**Click Recording**
```php
// Track button interaction
$success = AnalyticsTracker::track_button_click($post_id, $attachment_id);
// Returns: boolean success status
```

**Data Retrieval**
```php
// Get post analytics
$analytics = AnalyticsTracker::get_post_analytics($post_id);
// Returns: array of click data by attachment
```

## Performance Optimization

**Efficient Storage**
- Single post meta entry per post
- JSON encoding for structured data
- Atomic update operations
- Minimal database overhead

**Bulk Operations**
```php
// Get analytics for multiple posts
$bulk_data = AnalyticsTracker::get_bulk_analytics($post_ids);
// Returns: associative array indexed by post ID
```

## Analytics Calculations

**Engagement Metrics**
- Total clicks per post/attachment
- Click-through rates
- Popular content identification
- Trend analysis over time

**Conversion Tracking**
```php
// Calculate conversion rates
$conversion_data = AnalyticsTracker::calculate_conversions($post_id);
// Returns: clicks vs. completed purchases
```

## Data Structure

**Click Data Format**
Each tracked interaction includes:
- `clicks` - Total interaction count
- `last_click` - Most recent timestamp
- `first_click` - Initial interaction timestamp
- Automatic increment handling

**Aggregation Support**
- Post-level summaries
- Attachment-level details
- Time-range filtering
- Export capabilities

## Integration Points

**REST API**
- `/track-button-click` endpoint
- Real-time data collection
- JavaScript integration
- Error handling

**Admin Interface**
- Analytics page display
- Engagement reports
- Performance insights
- Data export tools

## Privacy Considerations

**Data Minimization**
- No personal information stored
- IP addresses not tracked
- Anonymous engagement metrics
- GDPR compliance

**Data Retention**
- Configurable retention periods
- Automatic cleanup options
- Bulk deletion capabilities
- Export before deletion

## Reporting Features

**Engagement Analysis**
- Click-to-conversion ratios
- Popular image identification
- Content performance ranking
- Revenue per click calculations

**Export Capabilities**
- CSV data export
- Date range filtering
- Post/attachment grouping
- Integration with analytics tools