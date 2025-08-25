# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

**Sell My Images** monetizes website images by adding "Download Hi-Res" buttons to content images. Users purchase AI-upscaled versions (4x, 8x) via secure Stripe payments and Upsampler.com processing.

## Current Status

**Version 1.2.0** - Production ready.

## Architecture

### Core Components

- **Main Plugin File**: `sell-my-images.php` - Singleton pattern with Composer autoloader
- **Admin Interface**: `src/Admin/` - Professional tabbed settings interface
- **Content Processing**: `src/Content/` - Gutenberg block processing and button injection
- **Managers Layer**: `src/Managers/` - Data management and business logic
- **Services Layer**: `src/Services/` - Workflow coordination (Payment/Upscaling)
- **API Layer**: `src/Api/` - REST endpoints and external API clients

### Class Structure

All classes follow PSR-4 autoloading under the `SellMyImages\` namespace:

**Content Layer:**
- `BlockProcessor` - Processes Gutenberg blocks, injects buttons with `data-attachment-id` and `data-post-id`
- `FilterManager` - Button display control (all posts, exclude selected, include only selected)

**Managers Layer:**
- `DatabaseManager` - Centralized CRUD with auto-formatting
- `JobManager` - Job lifecycle and status management
- `DownloadManager` - Secure token-based downloads using `wp_generate_password()`
- `AnalyticsTracker` - Post meta-based click tracking

**Services Layer:**
- `PaymentService` - Payment workflow coordination using StripeApi
- `UpscalingService` - Upscaling workflow coordination using Upsampler

**API Layer:**
- `RestApi` - WordPress REST endpoints under `/wp-json/smi/v1/`
- `StripeApi` - Pure HTTP client for Stripe operations
- `Upsampler` - Pure HTTP client for Upsampler.com
- `CostCalculator` - Dynamic pricing calculations

**Admin Layer:**
- `AdminInit` - Admin menu and settings initialization
- `SettingsPage` - Three-tab interface (API Config, Display Control, Downloads)
- `AnalyticsPage` - Click tracking and revenue analytics
- `JobsPage` - Job management with pagination and filtering

## System Workflow

### Payment & Processing Flow
1. Button click → Price calculation → Stripe checkout
2. Stripe webhook → Job status: `awaiting_payment` → `pending`
3. UpscalingService → Upsampler API → Job status: `processing` → `completed`
4. DownloadManager → Secure token generation → Email notification
5. Failed jobs → Automatic Stripe refunds + customer notification

### Click Tracking & Analytics
- Button clicks stored in `_smi_click_analytics` post meta
- Analytics page defaults to engagement-first sorting (clicks over revenue)
- Real-time conversion rate calculations with post/attachment breakdowns

### Button Display Control
- Three modes: All Posts (default), Exclude Selected, Include Only Selected
- Filter criteria: Post types, categories, tags, specific post IDs
- Smart asset loading - CSS/JS only loads when buttons will appear

## Database Schema

### `wp_smi_jobs` Table
Complete job tracking with analytics support:
- `job_id` (UUID), `post_id` (required), `attachment_id`, `email`
- `status` ('awaiting_payment', 'pending', 'processing', 'completed', 'failed')
- `payment_status` ('pending', 'paid', 'failed')
- `amount_charged`, `amount_cost` - Revenue/cost tracking
- `refunded_at`, `refund_reason`, `refund_amount` - Refund audit trail

## Development

### Dependencies
- **Install**: `composer install` (required for Stripe SDK)
- **Stripe SDK**: `stripe/stripe-php` ^10.0
- **WordPress**: 5.0+ (Gutenberg required)
- **PHP**: 7.4+ with cURL

### Local Testing
```bash
# Install dependencies
composer install

# Start Stripe webhook listener
stripe listen --forward-to=https://yoursite.local/smi-webhook/stripe/

# Add webhook secret to WordPress settings
```

### Webhook Endpoints
- Stripe: `/smi-webhook/stripe/` (bypasses WordPress routing)
- Upsampler: `/smi-webhook/upsampler/` (bypasses WordPress routing)

## Configuration Constants

### Core Constants (`sell-my-images.php`)
- `SMI_VERSION` (1.2.0), `SMI_PLUGIN_DIR`, `SMI_PLUGIN_URL`, `SMI_PLUGIN_BASENAME`

### Constants Class (`src/Config/Constants.php`)
- `VALID_JOB_STATUSES` - ('awaiting_payment', 'pending', 'processing', 'completed', 'failed')
- `VALID_PAYMENT_STATUSES` - ('pending', 'paid', 'failed')
- `UPSAMPLER_COST_PER_CREDIT` - 0.04 ($0.04/credit)
- `DOWNLOAD_TOKEN_LENGTH` - 64 characters
- `DEFAULT_MARKUP_PERCENTAGE` - 500 (6x cost markup)
- `RESOLUTION_MULTIPLIERS` - ('4x' => 4, '8x' => 8)

## Frontend Implementation

### JavaScript (`assets/js/modal.js`)
- Modal system with AJAX integration
- Job status polling (10-second intervals, 60 poll max = 10 minutes)
- Payment status detection via URL parameters
- Dynamic button injection with MutationObserver support
- Enhanced button detection with small image filtering and theme compatibility

### CSS (`assets/css/modal.css`)
- Maximum z-index (2147483647) prevents ad overlays on mobile
- Unified `.smi-spinner` class across all loading states
- Professional responsive modal design

### Modal Template (`templates/modal.php`)
- Quality-focused messaging: "Upscale High-Resolution Image"
- Two options: Standard (4x) and Premium (8x) with benefit descriptions
- Clear delivery expectations via email

## Key WordPress Integrations

- **Content Filter**: `the_content` with Gutenberg block processing
- **REST API**: Custom `/wp-json/smi/v1/` namespace with proper authentication
- **WordPress Functions**: `wp_generate_password()`, `wp_remote_get()`, `wp_mail()`, `wp_generate_uuid4()`
- **Post Meta**: Click tracking via `_smi_click_analytics`
- **Settings**: Individual option registration (not grouped)

## External Dependencies

- **Stripe**: Payment processing with automatic refunds
- **Upsampler.com**: AI upscaling via Precise Upscale endpoint
- **SSL**: Required for payments and webhooks

## Common Issues & Solutions

### Modal System
- **Mobile Ad Overlay**: Ensure `.smi-modal` has maximum z-index (2147483647)
- **Spinner Consistency**: All loading states use `.smi-spinner` class

### Button Display
- **Filter Logic**: Default 'all' mode returns true immediately (zero overhead)
- **Asset Loading**: Smart integration prevents CSS/JS loading when unnecessary

### Architecture
- **RestApi Boundaries**: Routes to Services for business logic, maintains separation
- **Services Pattern**: PaymentService/UpscalingService coordinate workflows
- **Error Handling**: Failed jobs trigger automatic refunds with customer notification

### Database Operations
- **Analytics Queries**: Use composite indexes for post/attachment cross-reference
- **Job Lifecycle**: `awaiting_payment` → `pending` → `processing` → `completed`/`failed`

## Email System

- **Download Notifications**: HTML template (`templates/email-notification.php`)
- **Refund Notifications**: Plain text generated inline
- **Admin Copies**: Sent with "Copy:" prefix to admin email

## Security Model

- **Download Tokens**: 64-character tokens via `wp_generate_password(64, false, false)`
- **Webhook Verification**: Signature validation for Stripe and Upsampler
- **CSRF Protection**: WordPress nonces for AJAX requests
- **Input Sanitization**: Server-side validation with escaping

## Development Commands

- **Install Dependencies**: `composer install` (required)
- **Test Webhooks**: `stripe listen --forward-to=https://yoursite.local/smi-webhook/stripe/`