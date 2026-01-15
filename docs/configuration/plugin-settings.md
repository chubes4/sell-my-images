# Plugin Settings

Comprehensive configuration options control all aspects of the image monetization system.

## Core Settings

**Plugin Status**
- `smi_enabled` (default: '1') - Master enable/disable toggle
- Global plugin functionality control
- Emergency disable capability
- Maintenance mode support

**Display Control**
- `smi_display_mode` (default: 'all') - Button display mode
- Options: 'all', 'exclude', 'include'
- Granular content targeting
- Performance optimization

## Payment Configuration

**Stripe Integration**
- `smi_stripe_test_mode` (default: '1') - Environment selection
- `smi_stripe_test_publishable_key` - Frontend test key
- `smi_stripe_test_secret_key` - Backend test key  
- `smi_stripe_live_publishable_key` - Production frontend key
- `smi_stripe_live_secret_key` - Production backend key
- `smi_stripe_webhook_secret` - Webhook signature validation

**Environment Management**
Automatic key selection based on test mode:
```php
$test_mode = get_option('smi_stripe_test_mode', '1');
$secret_key = $test_mode ? 
    get_option('smi_stripe_test_secret_key') : 
    get_option('smi_stripe_live_secret_key');
```

## Service Integration

**Upsampler API**
- `smi_upsampler_api_key` - AI upscaling service authentication
- Required for image processing functionality
- Secure storage in WordPress options
- Health check validation

**Cost Management**
- `smi_markup_percentage` (default: '200') - Profit margin control
- Pricing calculation multiplier
- Revenue optimization setting
- Dynamic pricing support

## Download Settings

**Security Configuration**
- `smi_download_expiry_hours` (default: '24') - Token lifetime
- Balances security with user convenience
- Configurable per business needs
- Automatic cleanup trigger

**Cleanup Configuration**
- `smi_failed_job_cleanup_days` (default: '7') - Failed job retention period
- `smi_abandoned_job_cleanup_hours` (default: '24') - Abandoned job retention period
- Daily automated cleanup removes expired resources
- Configurable retention policies for maintenance

**File Management**
- Download chunk size: 8192 bytes (constant)
- Secure token length: 64 characters (constant)
- Cleanup schedule configuration
- Storage path customization

## Display Filtering

**Content Targeting**
- `smi_display_mode` - Display mode ('all', 'exclude', 'include')
- `smi_filter_post_types` - Post type filtering array
- `smi_filter_categories` - Category filtering array
- `smi_filter_tags` - Tag filtering array
- `smi_filter_post_ids` - Specific post IDs (comma-separated)

## Email Configuration

**Notification Settings**
- `smi_terms_conditions_url` - Legal compliance link
- `smi_contact_url` (default: home_url('/contact')) - Support link
- Email template customization
- Branding configuration

## Constants

**System Constants**
Defined in `Constants.php`:
- `UPSAMPLER_COST_PER_CREDIT` (0.04) - Base processing cost
- `DEFAULT_MARKUP_PERCENTAGE` (200) - Default profit margin
- `STRIPE_MINIMUM_PAYMENT` (0.50) - Payment processor minimum
- `DOWNLOAD_TOKEN_LENGTH` (64) - Security token size

**Resolution Configuration**
- `VALID_RESOLUTIONS` - ['4x', '8x'] supported upscaling
- `RESOLUTION_MULTIPLIERS` - Scaling factors
- Extensible for additional resolutions

## Default Values

**Initial Configuration**
All settings have sensible defaults:
```php
const DEFAULT_OPTIONS = [
    'smi_enabled' => '1',
    'smi_stripe_test_mode' => '1',
    'smi_download_expiry_hours' => '24',
    'smi_markup_percentage' => '200',
    'smi_terms_conditions_url' => '',
    // Additional options...
];
```

## Health Validation

**Configuration Checking**
Daily health checks validate:
- API key presence and validity
- Stripe configuration completeness
- WordPress environment compatibility
- Database table integrity
- SSL requirement for live payments