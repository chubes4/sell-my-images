# Analytics Page

The Analytics page tracks user engagement and revenue performance for your image monetization. Access through **WordPress Admin > Sell My Images > Analytics**.

## Click Tracking System

**Button Click Analytics**
- Real-time tracking of download button interactions
- Post-level engagement metrics
- Attachment-specific performance data
- Conversion rate calculations

**Data Storage**
Click data stored in post meta as `_smi_click_analytics`:
```
{
  "attachment_123": {
    "clicks": 15,
    "last_click": "2023-10-15 14:30:00"
  }
}
```

## Revenue Metrics

**Financial Tracking**
- Total revenue from completed jobs
- Cost analysis and profit margins
- Refund tracking and reasons
- Payment status breakdowns

**Performance Indicators**
- Click-to-purchase conversion rates
- Average order values
- Popular image resolutions
- Revenue by post/attachment

## Display Features

**Data Presentation**
- Engagement-first sorting (prioritizes clicks over revenue)
- Post and attachment breakdowns
- Real-time conversion calculations
- Filterable date ranges

**Export Capabilities**
- CSV data export for external analysis
- Revenue reporting for accounting
- Click analytics for content optimization
- Performance trend analysis

## Analytics Data Collection

**Tracking Methods**
- REST API endpoint for button clicks
- Automatic revenue tracking from payments
- Post meta storage for performance data
- Job table integration for conversion metrics

**Privacy Considerations**
- No personal data stored in analytics
- IP addresses not logged
- Email addresses stored separately in job records
- GDPR-compliant data handling