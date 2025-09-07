# Upsampler Integration

The Upsampler API client handles AI-powered image upscaling through the Precise Upscale endpoint.

## Upscaling Process

**API Workflow**
1. Submit image URL to Upsampler API
2. Receive job ID for processing tracking
3. Poll status until completion
4. Download processed high-resolution image
5. Store locally for secure delivery

**Endpoint Configuration**
- Base URL: `https://upsampler.com/api/v1`
- Endpoint: `/precise-upscale`
- Authentication: API key in headers
- Content-Type: `application/json`

## Request Format

**Upscale Submission**
```php
POST /api/v1/precise-upscale
{
    "image_url": "https://example.com/image.jpg",
    "scale_factor": 4,
    "format": "auto",
    "webhook_url": "https://example.com/smi-webhook/upsampler/"
}
```

**Response Structure**
```json
{
    "success": true,
    "job_id": "upsampler_job_12345",
    "status": "processing",
    "estimated_completion": "2023-10-15T10:45:00Z"
}
```

## Status Monitoring

**Polling System**
- Initial 30-second delay before first check
- Exponential backoff for subsequent polls
- Maximum 15-minute processing timeout
- Webhook callback for immediate notification

**Status Values**
- `queued` - Waiting for processing slot
- `processing` - Image being upscaled
- `completed` - High-resolution image ready
- `failed` - Processing error occurred

## File Management

**Download Process**
1. Receive completion webhook or poll success
2. Download processed image from Upsampler CDN
3. Store in WordPress uploads directory
4. Generate secure download token
5. Clean up temporary processing files

**Storage Structure**
```
/wp-content/uploads/smi-images/
├── {job_id}/
│   ├── original.jpg
│   └── upscaled_{resolution}.jpg
```

## Error Handling

**Processing Failures**
- Image format not supported
- File size exceeds limits
- Network connectivity issues
- API rate limiting

**Recovery Actions**
- Automatic retry for transient errors
- Customer refund for permanent failures
- Admin notification for manual review
- Detailed error logging for debugging

## Cost Management

**Credit Calculation**
- 0.25 credits per output megapixel
- $0.04 per credit consumed
- Automatic cost tracking per job
- Profit margin calculation for pricing

**Usage Monitoring**
- API quota tracking
- Daily/monthly usage reports
- Cost analysis and optimization
- Budget alerting system

## Security Considerations

**API Key Protection**
- Stored in WordPress options (encrypted in database)
- Never exposed to frontend
- Rotated periodically for security
- Environment-specific keys for test/live

**Image Privacy**
- Temporary processing URLs
- Automatic cleanup after download
- No permanent storage on Upsampler servers
- GDPR-compliant data handling