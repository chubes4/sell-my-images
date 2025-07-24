# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

**Sell My Images** is a WordPress plugin that monetizes website images by adding "Buy Hi-Res" buttons to content images. When clicked, users can purchase upscaled versions (2x, 4x, 8x) of images via AI upscaling and secure payment processing.

## Development Notes

- No need for backwards compatibility, this is a brand new system in development
- **Post ID Tracking**: The system now tracks both `attachment_id` and `post_id` for comprehensive analytics

## Architecture

### Core Components

- **Main Plugin File**: `sell-my-images.php` - Singleton pattern with Composer autoloader and WordPress hooks initialization
- **Admin Interface**: `src/Admin/` - Settings page with top-level menu and WordPress Settings API
- **Content Processing**: `src/Content/` - Gutenberg block-based image detection and button injection
- **Managers Layer**: `src/Managers/` - Data management, file operations, and system coordination
- **API Layer**: `src/Api/` - REST endpoints, external API integration, and payment processing

### Class Structure & Responsibilities

All classes follow PSR-4 autoloading under the `SellMyImages\` namespace:

**Content Layer:**
- `SellMyImages\Content\BlockProcessor` - Processes Gutenberg blocks using `parse_blocks()` and `serialize_blocks()`, injects buy buttons with `data-attachment-id` and `data-post-id` attributes

**Managers Layer:**
- `SellMyImages\Managers\DatabaseManager` - Centralized database operations, schema management, and CRUD with auto-formatting
- `SellMyImages\Managers\JobManager` - Business logic for job lifecycle and status management
- `SellMyImages\Managers\DownloadManager` - Secure token-based downloads using `wp_generate_password()`
- `SellMyImages\Managers\FileManager` - File operations with WordPress standards
- `SellMyImages\Managers\WebhookManager` - Shared webhook utilities and security

**Services Layer:**
- `SellMyImages\Services\PaymentService` - Payment workflow coordination and business logic, uses StripeApi for HTTP calls
- `SellMyImages\Services\UpscalingService` - Upscaling workflow coordination and business logic, uses Upsampler for HTTP calls

**API Layer:**
- `SellMyImages\Api\RestApi` - WordPress REST API endpoints under `/wp-json/smi/v1/` namespace, delegates to Services for business logic
- `SellMyImages\Api\StripeApi` - Pure Stripe HTTP client without business logic
- `SellMyImages\Api\CostCalculator` - Hardcoded Upsampler pricing ($0.04/credit, 1 credit per 4 megapixels output) with configurable markup and shared upscale factor utilities
- `SellMyImages\Api\Upsampler` - Pure Upsampler.com HTTP client without business logic

**Admin Layer:**
- `SellMyImages\Admin\AdminInit` - Top-level admin menu and settings initialization
- `SellMyImages\Admin\SettingsPage` - Individual option registration (not group-based)
- `SellMyImages\Admin\AnalyticsPage` - Post-centric analytics with profit margin calculations

### System Architecture & Data Flow

**Content Processing Pipeline:**
1. WordPress `the_content` filter → `BlockProcessor::process_content()`
2. `parse_blocks()` → identify `core/image` blocks → extract attachment metadata
3. Inject buttons with `data-attachment-id="{id}"` and `data-post-id="{post_id}"`
4. `serialize_blocks()` → return modified content

**Payment & Processing Workflow:**
1. Frontend captures attachment_id and post_id directly from button data attributes
2. **Price Calculation**: `/calculate-all-prices` with both attachment_id and post_id
3. **Checkout Creation**: `/create-checkout` → RestApi → PaymentService → StripeApi → creates job with post_id tracking
4. **Payment Verification**: Stripe webhook → PaymentService → job status 'paid' → triggers `smi_payment_completed` action
5. **Upscaling Processing**: UpscalingService handles `smi_payment_completed` → Upsampler API → external job_id linking
6. **Fulfillment**: DownloadManager → secure download tokens via `wp_generate_password()`

**Webhook System:**
- Custom rewrite rules bypass WordPress routing: `/smi-webhook/stripe/` and `/smi-webhook/upsampler/`
- Dual job ID system: internal UUIDs + external Upsampler job IDs
- Pay-first security model with payment verification before processing

## Database Architecture

### DatabaseManager Pattern
All database operations use centralized `DatabaseManager` class:
- **Schema Management**: `DatabaseManager::create_tables()` handles table creation/migration
- **Type-Safe Operations**: Automatic format detection (%s, %d, %f) for all CRUD operations
- **Standardized Methods**: `insert()`, `update()`, `delete()`, `get_row()`, `get_results()`
- **Cleanup Utilities**: Built-in cleanup for failed jobs, abandoned payments, expired downloads

### `wp_smi_jobs` Table
Complete job tracking with payment, processing status, and **analytics support**:

**Core Fields:**
- `job_id` (UUID), `image_url`, `resolution`, `email`
- `post_id` (NOT NULL) - Required for analytics tracking
- `attachment_id` - Links to WordPress media
- `status`, `payment_status` - Job lifecycle tracking
- `amount_charged`, `amount_cost` - Revenue and cost tracking for profit analysis

**Analytics Indexes:**
- `KEY post_id (post_id)` - Most profitable posts queries
- `KEY post_attachment (post_id, attachment_id)` - Cross-reference analysis
- `KEY attachment_id (attachment_id)` - Most profitable images queries
- `KEY email (email)` - Customer analysis

## Development Commands

### Dependency Management
- **Install dependencies**: `composer install`
- **Regenerate autoloader**: `composer dump-autoload`
- **Update dependencies**: `composer update`

### Code Quality & Debugging
- **Syntax check**: `php -l sell-my-images.php` or `php -l src/Api/RestApi.php`
- **WordPress debug logging**: Enable `WP_DEBUG_LOG` in wp-config.php
- **Plugin activation test**: Activate/deactivate to test database table creation

### Database Operations for Analytics
- **Most profitable posts**: `SELECT post_id, COUNT(*) as sales, SUM(amount_charged) as revenue FROM wp_smi_jobs WHERE payment_status='paid' GROUP BY post_id ORDER BY revenue DESC;`
- **Most profitable images**: `SELECT attachment_id, COUNT(*) as sales, SUM(amount_charged) as revenue FROM wp_smi_jobs WHERE payment_status='paid' GROUP BY attachment_id ORDER BY revenue DESC;`
- **Profit analysis**: `SELECT post_id, SUM(amount_charged - COALESCE(amount_cost, 0)) as profit, (SUM(amount_charged - COALESCE(amount_cost, 0)) / SUM(amount_charged)) * 100 as margin FROM wp_smi_jobs WHERE payment_status='paid' GROUP BY post_id;`
- **Cross-reference analysis**: `SELECT p.post_title, j.attachment_id, COUNT(*) as sales, SUM(j.amount_charged) as revenue FROM wp_smi_jobs j JOIN wp_posts p ON j.post_id = p.ID WHERE j.payment_status='paid' GROUP BY j.post_id, j.attachment_id ORDER BY revenue DESC;`

### Analytics Architecture
- **Post-Centric Display**: `AnalyticsPage` organizes data by post_id at top level with expandable attachment details
- **Profit Calculations**: Real-time profit margins calculated from `amount_charged - amount_cost`
- **Summary Statistics**: Total revenue, profit, sales count, profit margins, and customer metrics
- **Database Optimization**: Uses composite indexes (`post_id`, `attachment_id`) for efficient queries

### Payment & Webhook Testing
- **Stripe CLI**: `stripe listen --forward-to=https://yoursite.com/smi-webhook/stripe/`
- **Test webhooks directly**: Stripe and Upsampler endpoints at `/smi-webhook/` paths
- **Payment flow testing**: Success/cancel URLs with session_id/job_id parameters

## Key WordPress Integrations & Standards

- **Content Filtering**: Uses `the_content` filter with Gutenberg block processing
- **REST API**: Custom `/wp-json/smi/v1/` namespace with proper authentication
- **WordPress Native Functions**: `wp_generate_password()`, `wp_remote_get()`, `wp_mail()`, `wp_generate_uuid4()`
- **Asset Loading**: Conditional loading based on content analysis
- **Settings API**: Individual option registration (not group-based) for proper field display

## External Dependencies

- **Stripe PHP SDK**: `stripe/stripe-php` ^10.0 for payment processing
- **Upsampler API**: AI upscaling via `https://upsampler.com/api/v1/` (Precise Upscale endpoint)
- **WordPress Requirements**: 5.0+ (Gutenberg), PHP 7.4+, SSL for payments

## Plugin Configuration & Constants

### Core Constants (defined in `sell-my-images.php`)
- `SMI_VERSION` (1.0.0), `SMI_PLUGIN_DIR`, `SMI_PLUGIN_URL`, `SMI_PLUGIN_BASENAME`

### Asset Management
- **Performance Strategy**: Assets load only on posts with image blocks
- **Detection Method**: `BlockProcessor::post_has_image_blocks()` content scanning
- **Version Control**: `SMI_VERSION` constant for cache invalidation

### Pricing Configuration
- **Upsampler Costs**: Hardcoded at $0.04/credit (updated in `CostCalculator::UPSAMPLER_COST_PER_CREDIT`)
- **Markup Control**: Configurable via `smi_markup_percentage` setting (default 200%)
- **Credit Formula**: 1 credit per 4 megapixels of output for Precise Upscale
- **Shared Utilities**: `CostCalculator::get_upscale_factor()` public method used by both CostCalculator and Upscaler
- **Price Updates**: When Upsampler changes pricing, update the constant in CostCalculator class

## Architecture Patterns & Implementation Guidelines

### WordPress Native Compliance
- **PSR-4 Autoloading**: `SellMyImages\` namespace with Composer
- **Individual Settings**: Each option registered separately (not as groups)
- **Top-Level Menu**: `add_menu_page()` with `dashicons-images-alt2`
- **Security**: Nonce verification, capability checks, input sanitization throughout

### Clean Architecture Principles
- **Database Layer Separation**: DatabaseManager handles all database operations with type-safe auto-formatting
- **Business Logic Isolation**: Services layer coordinates workflows, Managers handle data operations
- **API Layer Separation**: API classes are pure HTTP clients without business logic (StripeApi, Upsampler)
- **Service Coordination**: PaymentService and UpscalingService handle business workflows and use API classes for external calls
- **Direct Data Access**: Buttons use `data-attachment-id` directly (no complex extraction)
- **WordPress Standards**: All external operations use WordPress functions
- **Pay-First Security**: All processing occurs only after payment confirmation

### Data Flow Architecture
- **Button → JavaScript**: Direct `$button.data('attachment-id')` access
- **Frontend → RestApi → Services → API/Managers**: Clean separation with RestApi routing to Services for business logic
- **Services → API Classes**: PaymentService uses StripeApi, UpscalingService uses Upsampler (pure HTTP clients)
- **Services → Managers**: Business logic delegates data operations to specialized Managers
- **Database Operations**: All operations go through DatabaseManager with automatic format detection (%s, %d, %f)
- **Database**: Single jobs table with comprehensive analytics indexes managed by DatabaseManager
- **Token Generation**: WordPress native `wp_generate_password(64, false, false)`

## Critical Architecture Notes

### Services Layer Pattern
The Services layer is **essential for separation of concerns** and should never be removed:

- **PaymentService**: Coordinates payment workflow, uses StripeApi for HTTP calls, handles Stripe webhooks
- **UpscalingService**: Coordinates upscaling workflow, uses Upsampler for HTTP calls, handles Upsampler webhooks  
- **API Classes**: Pure HTTP clients (StripeApi, Upsampler) with no business logic
- **RestApi**: Routes requests to appropriate Services, does not contain business logic

### Architectural Boundaries
- **RestApi** → **Services** → **API Classes/Managers** (correct flow)
- **Never**: RestApi directly calling API classes or containing business logic
- **Never**: Removing Services layer - it provides essential workflow coordination

### Payment Data Structure
PaymentService expects specific CostCalculator output format:
- `$cost_data['customer_price']` (not `total_price`)
- `$cost_data['output_dimensions']['width']` and `['height']` (not direct width/height keys)
- Original dimensions come from `$image_data['width']` and `$image_data['height']`

## Troubleshooting Common Issues

### Settings Page
- **Individual Registration**: Settings must be registered individually, not as groups
- **Top-Level Menu**: Hook condition is `toplevel_page_sell-my-images`
- **Asset Loading**: Admin assets load only on plugin settings page

### Analytics & Job Tracking
- **Required Fields**: Both post_id and attachment_id are required (NOT NULL)
- **Index Usage**: Use composite indexes for cross-reference queries
- **Job Lifecycle**: `pending` → `paid` → `processing` → `completed`/`failed`

### Payment Integration
- **SSL Requirements**: Stripe requires HTTPS for live payments and webhooks
- **Webhook Security**: Both Stripe and Upsampler webhooks must be publicly accessible
- **Job Linking**: Dual ID system links internal jobs to external Upsampler job IDs