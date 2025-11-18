# REST API Endpoints

The plugin provides REST API endpoints under the `/wp-json/smi/v1/` namespace for frontend interactions and external integrations.

## Pricing Calculation

### `POST /wp-json/smi/v1/calculate-all-prices`
Calculate pricing for all available resolutions.

**Parameters**
- `attachment_id` (required) - WordPress attachment ID
- `post_id` (required) - Source post ID for analytics

**Response**
```json
{
  "success": true,
  "pricing": {
    "4x": {
      "customer_price": 2.50,
      "cost": 0.42,
      "credits_required": 10.5,
      "output_megapixels": "16.0MP"
    },
    "8x": {
      "customer_price": 8.75,
      "cost": 1.46,
      "credits_required": 36.5,
      "output_megapixels": "64.0MP"
    }
  },
  "image_info": {
    "width": 2000,
    "height": 2000
  }
}
```

## Checkout Creation

### `POST /wp-json/smi/v1/create-checkout`
Create Stripe checkout session for payment processing.

**Parameters**
- `attachment_id` (required) - WordPress attachment ID
- `post_id` (required) - Source post ID
- `resolution` (required) - Resolution multiplier (4x, 8x)
- `email` (optional) - Customer email for notifications (backfilled from Stripe if not provided)

**Response**
```json
{
  "success": true,
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "checkout_url": "https://checkout.stripe.com/pay/cs_...",
  "amount": 2.50,
  "message": "Redirecting to payment..."
}
```

## Analytics Tracking

### `POST /wp-json/smi/v1/track-button-click`
Track download button clicks for analytics.

**Parameters**
- `attachment_id` (required) - Clicked button's attachment ID
- `post_id` (required) - Source post ID

**Response**
```json
{
  "success": true,
  "message": "Click tracked successfully"
}
```

## File Downloads

### `GET /wp-json/smi/v1/download/{token}`
Secure file download using token-based authentication.

**Parameters**
- `token` (required) - 64-character download token

**Response**
- Binary file stream with appropriate headers
- Automatic file cleanup after expiration
- Secure token validation

## Job Status Polling

### `GET /wp-json/smi/v1/job-status/{job_id}`
Check processing status for real-time updates.

**Parameters**
- `job_id` (required) - UUID job identifier

**Response**
```json
{
  "success": true,
  "data": {
    "job_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "processing",
    "payment_status": "paid",
    "resolution": "4x",
    "created_at": "2023-10-15 10:30:00",
    "download_url": "https://example.com/wp-json/smi/v1/download/abc123..."
  }
}
```

## Authentication

**Public Endpoints**
All endpoints are public but include validation:
- WordPress nonce verification
- Rate limiting through WordPress
- Input sanitization and validation

**Security Headers**
- X-WP-Nonce header for authenticated requests
- CORS handling for frontend interactions
- SSL requirement for payment operations