# Settings Page

The Settings page provides a three-tab interface for configuring the Sell My Images plugin. Access through **WordPress Admin > Sell My Images**.

## Tab Structure

### API Configuration
Configure payment processing and image upscaling services.

**Stripe Settings**
- Test/Live mode toggle
- Publishable and Secret keys for both environments
- Webhook secret for payment verification
- SSL requirement for live mode

**Upsampler Configuration**  
- API key for AI image upscaling
- Cost calculation settings
- Markup percentage configuration (default: 500%)

### Display Control
Control where download buttons appear on your site.

**Button Display Modes**
- **All Posts** (default): Show buttons on all posts and pages
- **Exclude Selected**: Hide buttons on specific content
- **Include Only Selected**: Show buttons only on specific content

**Filter Options**
- Post types selection
- Category and tag filtering  
- Specific post ID targeting
- Custom filter criteria

### Downloads Management
Configure download behavior and security settings.

**Download Settings**
- Token expiration (default: 24 hours)
- Download chunk size for large files
- Email notification templates
- Terms and conditions URL

## Required Configuration

**Minimum Setup**
1. Upsampler API key (required for upscaling)
2. Stripe keys (required for payments)
3. SSL certificate (required for live payments)

**Health Check System**
Automatic validation runs daily to identify configuration issues:
- Missing API keys
- Invalid credentials
- WordPress environment compatibility
- Database table integrity

## Settings Storage

All settings stored as individual WordPress options:
- `smi_enabled` - Plugin status
- `smi_upsampler_api_key` - AI upscaling service
- `smi_stripe_test_mode` - Payment environment
- `smi_stripe_*_key` - Payment credentials
- `smi_markup_percentage` - Pricing configuration
- `smi_download_expiry_hours` - Security settings