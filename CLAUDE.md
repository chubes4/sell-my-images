# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

**Sell My Images** is a WordPress plugin that monetizes website images by adding "Download Hi-Res" buttons to content images. When clicked, users can purchase upscaled versions (4x, 8x) of images via AI upscaling and secure payment processing. Features comprehensive button display control system with tabbed admin interface for precise targeting of monetizable content.

## KNOWN ISSUES

- All recent modal system conversion optimization improvements have been successfully implemented
- Critical mobile z-index fix prevents Journey by Mediavine ads from covering modal on mobile devices
- Modal system now uses consistent `.smi-spinner` class across all templates, JavaScript, and CSS
- AI-enhanced messaging strategy improves user understanding and conversion rates
- Email delivery transparency messaging implemented for better user expectations

## FUTURE PLANS

- Continue monitoring conversion rate improvements from recent modal enhancements
- Analyze impact of AI messaging strategy on user engagement and purchase completion
- Consider additional transparency improvements based on user feedback
- Monitor mobile conversion rates post z-index fix implementation

## Development Notes

- No need for backwards compatibility, this is a brand new system in development
- **Version 1.2.0**: Major UX upgrade with tabbed admin interface and enhanced button display control
- **Post ID Tracking**: The system now tracks both `attachment_id` and `post_id` for comprehensive analytics
- **Analytics Philosophy**: The system prioritizes engagement metrics (clicks) over financial metrics (revenue) by default to promote user-focused optimization
- **Tabbed Interface**: Professional WordPress admin design replaces traditional Settings API for improved organization
- **Modal System Optimization**: Recent improvements focus on conversion optimization through AI messaging and mobile compatibility fixes

## Architecture

### Core Components

- **Main Plugin File**: `sell-my-images.php` - Singleton pattern with Composer autoloader and WordPress hooks initialization
- **Admin Interface**: `src/Admin/` - Professional tabbed settings interface with comprehensive button display control
- **Content Processing**: `src/Content/` - Gutenberg block-based image detection and button injection
- **Managers Layer**: `src/Managers/` - Data management, file operations, and system coordination
- **API Layer**: `src/Api/` - REST endpoints, external API integration, and payment processing

### Class Structure & Responsibilities

All classes follow PSR-4 autoloading under the `SellMyImages\` namespace:

**Content Layer:**
- `SellMyImages\Content\BlockProcessor` - Processes Gutenberg blocks using `parse_blocks()` and `serialize_blocks()`, injects buy buttons with `data-attachment-id` and `data-post-id` attributes
- `SellMyImages\Content\FilterManager` - Centralized filtering logic for granular button display control with three modes: all posts, exclude selected, include only selected

**Managers Layer:**
- `SellMyImages\Managers\DatabaseManager` - Centralized database operations, schema management, and CRUD with auto-formatting
- `SellMyImages\Managers\JobManager` - Business logic for job lifecycle and status management
- `SellMyImages\Managers\DownloadManager` - Secure token-based downloads using `wp_generate_password()`
- `SellMyImages\Managers\FileManager` - File operations with WordPress standards
- `SellMyImages\Managers\WebhookManager` - Shared webhook utilities and security
- `SellMyImages\Managers\AnalyticsTracker` - Post meta-based click tracking and conversion rate analytics

**Services Layer:**
- `SellMyImages\Services\PaymentService` - Payment workflow coordination and business logic, uses StripeApi for HTTP calls
- `SellMyImages\Services\UpscalingService` - Upscaling workflow coordination and business logic, uses Upsampler for HTTP calls

**API Layer:**
- `SellMyImages\Api\RestApi` - WordPress REST API endpoints under `/wp-json/smi/v1/` namespace, delegates to Services for business logic
- `SellMyImages\Api\StripeApi` - Pure Stripe HTTP client without business logic
- `SellMyImages\Api\CostCalculator` - Dynamic pricing calculations using Constants for Upsampler rates with configurable markup
- `SellMyImages\Api\Upsampler` - Pure Upsampler.com HTTP client without business logic

**Admin Layer:**
- `SellMyImages\Admin\AdminInit` - Top-level admin menu and tabbed settings initialization
- `SellMyImages\Admin\SettingsPage` - Tabbed interface with API Configuration, Display Control, and Download Settings tabs
- `SellMyImages\Admin\AnalyticsPage` - Engagement-first analytics with clickable navigation and enhanced click tracking
- `SellMyImages\Admin\JobsPage` - Complete job management interface with pagination and filtering

### System Architecture & Data Flow

**Content Processing Pipeline:**
1. WordPress `the_content` filter → `BlockProcessor::process_content()`
2. `FilterManager::should_show_buttons()` → evaluate display criteria and mode
3. JavaScript injection handles button placement (no server-side content modification)
4. Smart asset loading - CSS/JS only enqueued when buttons will appear

**Click Tracking & Analytics Pipeline:**
1. Button click → JavaScript `trackButtonClick()` → AJAX call to `/track-button-click`
2. `AnalyticsTracker::track_button_click()` → increment post meta click counts
3. First click on post initializes analytics table with creation timestamp
4. Click data stored in `_smi_click_analytics` post meta as serialized array

**Payment & Processing Workflow:**
1. Frontend captures attachment_id and post_id directly from button data attributes
2. **Click Tracking**: Fire-and-forget AJAX call to track button interaction
3. **Price Calculation**: `/calculate-all-prices` with both attachment_id and post_id
4. **Checkout Creation**: `/create-checkout` → RestApi → PaymentService → StripeApi → creates job with post_id tracking
5. **Payment Verification**: Stripe webhook → PaymentService → job status 'paid' → triggers `smi_payment_completed` action
6. **Upscaling Processing**: UpscalingService handles `smi_payment_completed` → Upsampler API → external job_id linking
7. **Fulfillment**: DownloadManager → secure download tokens via `wp_generate_password()`

**Webhook System:**
- Custom rewrite rules bypass WordPress routing: `/smi-webhook/stripe/` and `/smi-webhook/upsampler/`
- Dual job ID system: internal UUIDs + external Upsampler job IDs
- Token-based security model with admin override capabilities for operational flexibility

## Database Architecture

### DatabaseManager Pattern
All database operations use centralized `DatabaseManager` class:
- **Schema Management**: `DatabaseManager::create_tables()` handles table creation/migration
- **Type-Safe Operations**: Automatic format detection (%s, %d, %f) for all CRUD operations
- **Standardized Methods**: `insert()`, `update()`, `delete()`, `get_row()`, `get_results()`
- **File Management**: Automated cleanup of expired download files to prevent server bloat while preserving customer data

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
- **Analytics Testing**: View Admin → Analytics to verify click tracking, default sorting, and navigation features
- **Filter Testing**: Test button display control by configuring different display modes and criteria combinations
- **Modal CSS Validation**: Verify modal.css syntax and z-index effectiveness on mobile devices with ads present
- **Spinner Class Testing**: Confirm all loading states use consistent `.smi-spinner` class across templates and JavaScript
- **Mobile Conversion Testing**: Test complete purchase flow on mobile devices to verify ad overlay fix effectiveness

### Local Development Setup
- **WordPress Environment**: Requires WordPress 5.0+ with Gutenberg support
- **PHP Requirements**: PHP 7.4+ with curl and json extensions
- **SSL Certificate**: Required for Stripe integration (use local SSL or ngrok for testing)
- **Database**: MySQL/MariaDB with InnoDB support for proper indexing
- **Modal Testing**: Test modal display on mobile devices with ad blockers disabled to verify z-index fix effectiveness
- **Conversion Testing**: Verify modal displays above third-party ads (especially Journey by Mediavine) on mobile devices

### Database Operations for Analytics
- **Most clicked posts**: `SELECT post_id, SUM(meta_value) as clicks FROM wp_postmeta WHERE meta_key='_smi_click_analytics' GROUP BY post_id ORDER BY clicks DESC;`
- **Most profitable posts**: `SELECT post_id, COUNT(*) as sales, SUM(amount_charged) as revenue FROM wp_smi_jobs WHERE payment_status='paid' GROUP BY post_id ORDER BY revenue DESC;`
- **Most profitable images**: `SELECT attachment_id, COUNT(*) as sales, SUM(amount_charged) as revenue FROM wp_smi_jobs WHERE payment_status='paid' GROUP BY attachment_id ORDER BY revenue DESC;`
- **Profit analysis**: `SELECT post_id, SUM(amount_charged - COALESCE(amount_cost, 0)) as profit, (SUM(amount_charged - COALESCE(amount_cost, 0)) / SUM(amount_charged)) * 100 as margin FROM wp_smi_jobs WHERE payment_status='paid' GROUP BY post_id;`
- **Cross-reference analysis**: `SELECT p.post_title, j.attachment_id, COUNT(*) as sales, SUM(j.amount_charged) as revenue FROM wp_smi_jobs j JOIN wp_posts p ON j.post_id = p.ID WHERE j.payment_status='paid' GROUP BY j.post_id, j.attachment_id ORDER BY revenue DESC;`
- **Conversion analysis**: Combines click data from post meta with sales data for comprehensive conversion rate calculations

### Data Retention Policy
- **Job Records**: All job records are preserved indefinitely for customer analytics and business intelligence
- **Customer Data**: Email addresses and payment information are retained for re-engagement and analytics
- **File Management**: Physical upscaled files are automatically deleted after download expiration to prevent server bloat
- **Download Tokens**: Expired download tokens are cleared from database while preserving job history
- **Pagination**: Jobs management interface supports pagination (10-100 jobs per page) to handle large datasets efficiently

### Download Security Model
- **Token-Based Authorization**: Download access controlled by secure 64-character tokens generated with `wp_generate_password()`
- **Natural Expiration**: Time-limited access provides security boundaries without overengineering
- **Admin Override System**: Administrators can retry failed operations with bypass capabilities and audit logging
- **Simplified Verification**: Possession of valid, non-expired token is sufficient proof of purchase authorization

### Admin Operations
- **Analytics Navigation**: Post titles in Analytics page are clickable links that open posts in new tabs for easy content review
- **Default Sorting**: Analytics page defaults to 'clicks' sorting to prioritize engagement insights over revenue metrics
- **Enhanced UX**: Improved error handling prevents undefined property warnings in analytics display
- **Batch Operations**: Efficient click data processing reduces database queries for better performance
- **Button Display Control**: Professional admin interface for managing where download buttons appear
- **Responsive Design**: Filter table adapts to mobile devices with stacked layout and data labels
- **Real-time Filtering**: JavaScript-powered dynamic interface with smooth transitions
- **Retry System**: Administrators can retry any job regardless of payment status mismatches via admin override context
- **Email Transparency**: Admin receives identical HTML emails as customers (not plaintext) with "Copy:" subject prefix
- **Comprehensive Logging**: All admin overrides logged with job details and payment status for audit trail
- **Jobs Management**: Full pagination, filtering, and bulk operations interface at Admin → Jobs

### Analytics Architecture
- **Engagement-First Display**: `AnalyticsPage` defaults to sorting by clicks (not revenue) to prioritize engagement metrics
- **Post-Centric Organization**: Data organized by post_id at top level with expandable attachment details and clickable post titles
- **Batch Click Processing**: Enhanced performance with `add_click_data_to_results()` and `get_click_data_for_posts()` methods for efficient bulk operations
- **Navigation Enhancement**: Post titles are clickable links using `get_permalink()` that open in new tabs for easy content review
- **Profit Calculations**: Real-time profit margins calculated from `amount_charged - amount_cost`
- **Click Tracking**: Post meta-based system using `_smi_click_analytics` for button interaction tracking with robust error handling
- **Conversion Analytics**: Click-to-purchase rates calculated per post and per attachment with proper null checking
- **Summary Statistics**: Total revenue, profit, sales count, profit margins, click counts, and conversion rates
- **Database Optimization**: Uses composite indexes (`post_id`, `attachment_id`) for efficient queries with intelligent caching

### Click Tracking System
- **Storage Method**: WordPress post meta (`_smi_click_analytics`) with serialized array structure
- **Data Structure**: `array('attachment_123' => count, 'total_clicks' => total, 'first_click_date' => timestamp)`
- **Batch Processing**: New `get_click_data_for_posts()` method fetches click data for multiple posts efficiently using single query with IN clause
- **Enhanced Integration**: `add_click_data_to_results()` method seamlessly merges click data with sales results for comprehensive analytics
- **Error Prevention**: Proper `isset()` checks prevent undefined property warnings when accessing `total_clicks`
- **Automatic Initialization**: First click on post creates analytics baseline
- **Conversion Metrics**: Calculates click-to-purchase ratios for optimization insights with safe division handling
- **Performance**: No database schema changes, uses WordPress native post meta system with optimized bulk retrieval

### Payment & Webhook Testing
- **Stripe CLI**: `stripe listen --forward-to=https://yoursite.com/smi-webhook/stripe/`
- **Critical**: Stripe CLI must run in persistent terminal (not tool calls) to forward webhook events
- **Webhook Secret**: Copy signing secret from CLI output and add to WordPress settings at Admin → Sell My Images → Stripe Webhook Secret
- **Test Flow**: Create real job via "Download Hi-Res" button → complete Stripe checkout → webhook processes payment → triggers upscaling
- **Webhook Endpoints**: `/smi-webhook/stripe/` and `/smi-webhook/upsampler/` routes bypass WordPress and go directly to service handlers

## Key WordPress Integrations & Standards

- **Content Filtering**: Uses `the_content` filter with Gutenberg block processing
- **REST API**: Custom `/wp-json/smi/v1/` namespace with proper authentication
- **WordPress Native Functions**: `wp_generate_password()`, `wp_remote_get()`, `wp_mail()`, `wp_generate_uuid4()`, `get_permalink()`
- **Asset Loading**: Conditional loading based on content analysis
- **Settings API**: Individual option registration (not group-based) for proper field display
- **Post Meta Integration**: Uses WordPress post meta system for click tracking with bulk retrieval optimization

## External Dependencies

- **Stripe PHP SDK**: `stripe/stripe-php` ^10.0 for payment processing
- **Upsampler API**: AI upscaling via `https://upsampler.com/api/v1/` (Precise Upscale endpoint)
- **WordPress Requirements**: 5.0+ (Gutenberg), PHP 7.4+, SSL for payments

## Plugin Configuration & Constants

### Core Constants (defined in `sell-my-images.php`)
- `SMI_VERSION` (1.2.0), `SMI_PLUGIN_DIR`, `SMI_PLUGIN_URL`, `SMI_PLUGIN_BASENAME`

### Asset Management
- **Performance Strategy**: Smart asset loading - CSS/JS only loads when buttons will appear on current post
- **Filter Integration**: `FilterManager::should_show_buttons()` determines asset loading in main plugin file
- **Detection Method**: Combined filtering logic and content analysis for optimal performance
- **Version Control**: `SMI_VERSION` constant for cache invalidation (current: 1.2.0)
- **Frontend Assets**: `assets/css/modal.css` and `assets/js/modal.js` with jQuery dependency
- **Admin Assets**: Enhanced admin.css with tabbed interface design and admin.js for tab navigation and dynamic filtering

### Pricing Configuration
- **Upsampler Costs**: Hardcoded at $0.04/credit (defined in `Constants::UPSAMPLER_COST_PER_CREDIT`)
- **Markup Control**: Configurable via `smi_markup_percentage` setting (default 500%)
- **Credit Formula**: 1 credit per 4 megapixels of output for Precise Upscale
- **Shared Utilities**: `Constants::get_upscale_factor()` method used by CostCalculator and Upsampler classes
- **Price Updates**: When Upsampler changes pricing, update the constant in Constants class

## Version 1.2.0 Major Updates

### Tabbed Admin Interface Architecture

Version 1.2.0 introduces a complete restructure of the admin interface from WordPress Settings API to a modern, professional tabbed design for improved user experience and logical organization.

**Key Improvements:**
- **Three Organized Tabs**: API Configuration, Display Control, Download Settings
- **Enhanced UX**: Professional WordPress admin styling with responsive design
- **Single Form Submission**: Maintains efficient form processing while improving organization
- **Progressive Enhancement**: JavaScript-powered tab navigation with accessibility support
- **Mobile Responsive**: Adapts seamlessly to all device sizes
- **Disabled State Handling**: Filter criteria table remains visible but disabled when not applicable

**Tab Organization:**

**API Configuration Tab:**
- Upsampler API key configuration
- Complete Stripe payment setup (test/live modes)
- Webhook endpoint documentation
- Service integration guidance

**Display Control Tab:**
- Button display mode selection (All Posts, Exclude Selected, Include Only Selected)
- Comprehensive filter criteria table (always visible, contextually disabled)
- Multi-criteria filtering: Post types, categories, tags, specific post IDs
- Real-time mode validation and sanitization

**Download Settings Tab:**
- Download link expiry configuration (1-168 hours)
- Markup percentage control with real-time preview
- Terms & Conditions URL integration
- Pricing transparency and business logic settings

**Technical Implementation:**
- `AdminInit::render_settings_page()` → `SettingsPage::render_tabbed_page()`
- Individual tab rendering methods: `render_api_tab()`, `render_display_tab()`, `render_downloads_tab()`
- Enhanced CSS: Tabbed interface styles, disabled state handling, responsive design
- Enhanced JavaScript: Tab navigation, active state management, progressive enhancement
- Maintains backward compatibility with existing settings registration

## Button Display Control System (FilterManager)

### Overview
The FilterManager class provides granular control over where download buttons appear, supporting three display modes with multi-criteria filtering for precise content targeting. Now accessible through the dedicated Display Control tab in the new admin interface.

### FilterManager Architecture

**Core Method**: `FilterManager::should_show_buttons($post_id)`
- Main decision point for button visibility
- Zero overhead for default 'all' mode (fast path)
- Returns boolean for current or specified post

**Display Modes**:
- `all` - Show buttons on all eligible posts (default, zero config)
- `exclude` - Hide buttons on posts matching filter criteria 
- `include` - Show buttons only on posts matching filter criteria

**Filter Criteria** (combinable):
- **Post Types**: Target specific content types using `get_post_types()`
- **Categories**: Include/exclude by category using `has_category()`
- **Tags**: Filter by post tags using `has_tag()`
- **Post IDs**: Granular control with comma-separated ID list

### Performance Optimization

**Smart Asset Loading Integration**:
```php
// In main plugin file enqueue_frontend_assets()
if ( ! \SellMyImages\Content\FilterManager::should_show_buttons() ) {
    return; // Skip asset loading entirely
}
```

**Zero-Overhead Default Mode**:
- Fast path for 'all' mode returns true immediately
- No database queries or processing when using default settings
- Maintains performance for sites not using filtering

**Efficient Filtering Logic**:
- Short-circuit evaluation - returns true on first match
- WordPress native functions for optimal performance
- Proper type casting and validation throughout

### Admin Interface Integration

**Tabbed Professional Interface**:
- Three organized tabs: API Configuration, Display Control, Download Settings
- Responsive design with mobile-friendly layout throughout
- Professional WordPress admin styling with seamless integration
- Enhanced UX with tab navigation and smooth transitions

**Professional Filter Table Layout**:
- Responsive design with mobile-friendly stacked layout
- Scrollable sections for categories/tags with many items
- Disabled state display when "All Posts" mode is selected (table remains visible but grayed out)
- Data validation and sanitization for all filter criteria

**JavaScript Enhancement**:
- Tab navigation with active state management
- Dynamic show/hide of filter criteria table via admin.js
- Smooth transitions and user experience improvements
- Progressive enhancement pattern for accessibility

### Settings Integration

**New Settings Options**:
- `smi_display_mode` - Controls filtering behavior ('all', 'exclude', 'include')
- `smi_filter_post_types` - Array of selected post types
- `smi_filter_categories` - Array of category IDs
- `smi_filter_tags` - Array of tag IDs  
- `smi_filter_post_ids` - Comma-separated string of post IDs

**Validation & Sanitization**:
- `FilterManager::sanitize_filter_settings()` - Comprehensive validation
- WordPress native functions: `term_exists()`, `get_post()`
- Type safety with `intval()` and `sanitize_text_field()`
- Invalid options filtered out automatically

### Use Case Examples

**Professional Photography Portfolio**:
```php
// Include only 'portfolio' and 'gallery' categories
Mode: include
Categories: [5, 12] // Portfolio, Gallery category IDs
```

**Content Creator Blog**:
```php  
// Exclude 'free-resources' category
Mode: exclude
Categories: [8] // Free Resources category ID
```

**E-commerce Product Showcase**:
```php
// Include only 'product' post type with 'featured' tag
Mode: include
Post Types: ['product']
Tags: [15] // Featured tag ID
```

**Multi-Author Platform**:
```php
// Include only specific high-value posts
Mode: include  
Post IDs: "123, 456, 789, 1011"
```

### Technical Implementation Details

**Method Flow**:
1. `should_show_buttons()` - Entry point with post ID resolution
2. `evaluate_filters()` - Mode-based decision logic
3. `post_matches_criteria()` - Multi-criteria evaluation with OR logic
4. Individual match methods for each criteria type

**Error Handling**:
- Graceful fallback to showing buttons on invalid configurations
- Proper null checking and type validation throughout
- WordPress function return value verification

**Integration Points**:
- Main plugin asset loading logic
- BlockProcessor content processing decisions  
- Settings page display and validation
- Future extensibility for additional criteria types

## Conversion Optimization & User Experience

### Modal System Enhancements (January 2025)
- **Critical Mobile Fix**: Maximum z-index (2147483647) on `.smi-modal` prevents third-party ads from covering modal on mobile devices
- **AI Messaging Strategy**: Modal title "AI-Enhanced High-Resolution Image" and process overview section focus on quality benefits
- **Email Delivery**: "Automatically delivered to your email" messaging provides clear delivery expectations
- **Process Overview**: Dedicated "AI-Powered Image Upscaling" section explains upscaling workflow
- **Unified Spinner Class**: Consistent `.smi-spinner` implementation across templates, JavaScript, and CSS
- **Enhanced Options**: Quality-focused descriptions ("Vivid details and sharpness", "Professional-grade detail enhancement") improve user understanding

### Technical Fixes for Conversion
- **Mobile Ad Prevention**: Maximum z-index on `.smi-modal` prevents Journey by Mediavine ads from blocking purchases
- **Spinner Class Consistency**: Unified `.smi-spinner` class across all components
- **Quality-Focused Descriptions**: Options emphasize benefits ("Vivid details and sharpness", "Professional-grade detail enhancement")
- **Process Overview Styling**: Clear "AI-Powered Image Upscaling" section with professional typography

### Conversion Psychology Implementation
- **Trust Building**: Transparent communication about AI process, timing, and delivery method reduces purchase anxiety
- **Premium Positioning**: "AI-Enhanced" terminology elevates perceived service value vs generic "upscaling"
- **Expectation Management**: Clear process overview prevents user confusion and abandonment
- **Mobile Optimization**: Critical for conversion as mobile users were unable to complete purchases due to ad overlays
- **Professional Design**: Clean, informative interface builds confidence in service quality

## Recent Major Enhancements

### Version 1.2.0 - Tabbed Admin Interface (December 2024)
- **Complete Admin Restructure**: Professional tabbed interface replacing WordPress Settings API
- **Enhanced Organization**: Three logical tabs for API Configuration, Display Control, and Download Settings
- **Improved UX**: Professional WordPress admin styling with responsive design and accessibility features
- **Filter Table Enhancement**: Always-visible filter table with contextual disabled state for better user understanding
- **Performance Maintained**: Zero-overhead filtering logic and smart asset loading preserved
- **Mobile Responsive**: Seamless experience across all device sizes with progressive enhancement

### AnalyticsPage Class Improvements
- **New Methods**:
  - `add_click_data_to_results()` - Efficiently merges click data with sales results using batch processing
  - `get_click_data_for_posts()` - Fetches click data for multiple posts in single database query using IN clause
  - Enhanced `get_sort_value()` with proper null checking for `total_clicks` property
- **UX Improvements**:
  - Default sorting changed from 'revenue' to 'clicks' for engagement-first analytics approach
  - Post titles are now clickable links using `get_permalink()` that open in new tabs
  - Robust error handling prevents undefined property warnings
- **Performance Optimizations**:
  - Batch click data processing reduces N+1 query problems
  - Single query retrieval for multiple posts' click analytics
  - Proper `isset()` checks throughout to prevent PHP notices

## Architecture Patterns & Implementation Guidelines

### WordPress Native Compliance
- **PSR-4 Autoloading**: `SellMyImages\` namespace with Composer
- **Individual Settings**: Each option registered separately (not as groups)
- **Top-Level Menu**: `add_menu_page()` with `dashicons-images-alt2`
- **Security**: Nonce verification, capability checks, input sanitization throughout

### Clean Architecture Principles
- **Database Layer**: DatabaseManager provides type-safe CRUD operations with automatic format detection
- **Business Logic**: Services coordinate workflows, Managers handle specialized data operations
- **API Layer**: Pure HTTP clients (StripeApi, Upsampler) without embedded business logic
- **Data Access**: Direct button attribute access via `data-attachment-id`
- **WordPress Integration**: WordPress native functions for all external operations
- **Security Model**: Payment confirmation required before processing

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
The Services layer coordinates complex workflows between API integrations and data management:

- **PaymentService**: Orchestrates payment workflow using StripeApi for HTTP operations
- **UpscalingService**: Orchestrates upscaling workflow using Upsampler for HTTP operations
- **RestApi**: Routes requests to Services for business logic execution

### Architectural Flow
**RestApi** → **Services** → **API Classes/Managers** maintains strict separation of concerns with Services providing essential workflow coordination.

### Payment Data Structure
PaymentService expects specific CostCalculator output format:
- `$cost_data['customer_price']` (not `total_price`)
- `$cost_data['output_dimensions']['width']` and `['height']` (not direct width/height keys)
- Original dimensions come from `$image_data['width']` and `$image_data['height']`

## Troubleshooting Common Issues

### Modal System Issues
- **Mobile Ad Overlay**: If modal is hidden by ads on mobile, verify `.smi-modal` z-index is set to maximum value (2147483647) in modal.css
- **Spinner Display**: All loading states should use `.smi-spinner` class - check templates/modal.php, assets/js/modal.js, and assets/css/modal.css for consistency
- **CSS Syntax Errors**: Verify no extra closing braces in modal.css that could break modal styling
- **Process Overview**: Blue-tinted info box should display properly with AI enhancement messaging
- **Mobile Compatibility**: Test modal display and interaction on various mobile devices, especially with Journey by Mediavine ads present
- **Conversion Blocking**: If users can't complete purchases on mobile, check for ad overlays covering modal elements

### Settings Page
- **Tabbed Interface**: Professional three-tab design replacing traditional WordPress Settings API
- **Individual Registration**: Settings must be registered individually, not as groups
- **Top-Level Menu**: Hook condition is `toplevel_page_sell-my-images`
- **Asset Loading**: Admin assets load only on plugin settings page
- **Tab Organization**: API Configuration, Display Control, Download Settings for logical organization
- **Button Display Filtering**: Filter criteria table always visible but disabled state when "All Posts" mode selected

### Analytics & Job Tracking
- **Default Sorting**: Analytics page defaults to 'clicks' (engagement-first) instead of 'revenue' for better user experience
- **Enhanced Click Processing**: New batch processing methods improve performance and prevent undefined property errors
- **Navigation Integration**: Post titles are clickable with `get_permalink()` integration for seamless content access
- **Required Fields**: Both post_id and attachment_id are required (NOT NULL)
- **Index Usage**: Use composite indexes for cross-reference queries
- **Job Lifecycle**: `pending` → `paid` → `processing` → `completed`/`failed`
- **Error Prevention**: Proper null checking prevents warnings when accessing click data properties

### Button Display Control
- **Tabbed Interface**: Dedicated Display Control tab for comprehensive button targeting
- **Filter Logic**: Default 'all' mode provides zero-overhead performance with immediate true return
- **Asset Loading**: Smart integration prevents CSS/JS loading when buttons won't appear
- **Mode Validation**: FilterManager gracefully handles invalid configurations by defaulting to showing buttons
- **Performance**: OR logic in criteria evaluation - returns true on first match for efficiency
- **UX Enhancement**: Filter table shows in disabled state rather than hiding when "All Posts" selected

### Payment Integration
- **SSL Requirements**: Stripe requires HTTPS for live payments and webhooks
- **Webhook Security**: Both Stripe and Upsampler webhooks must be publicly accessible
- **Job Linking**: Dual ID system links internal jobs to external Upsampler job IDs

## Common Development Issues & Solutions

### Namespace Import Requirements
**Critical**: All cross-namespace class references must include proper `use` statements:
- RestApi requires: `use SellMyImages\Managers\JobManager;` and `use SellMyImages\Managers\DownloadManager;`
- Services classes have proper imports but verify when adding new dependencies
- Main plugin file references: `\SellMyImages\Managers\DatabaseManager::create_tables()` (not Api namespace)

### Method Signature Compatibility
**PaymentService Integration**: When calling JobManager from PaymentService:
- Correct: `JobManager::update_payment_status($job_id, $payment_status, $payment_data)`
- Incorrect: `JobManager::update_job_payment_status($job_id, $update_data)` (method doesn't exist)

### Security Token Validation
**Download Tokens**: Generated with `wp_generate_password(64, false, false)` (alphanumeric only)
- Validation regex: `/^[a-zA-Z0-9]{64}$/` (not hex-only `/^[a-f0-9]{64}$/i`)
- Token length: exactly 64 characters

### Database Field Consistency
**Stripe Integration**: Use standardized field names across all classes:
- Database field: `stripe_checkout_session_id` (not `stripe_session_id`)
- PaymentService must use correct field names when building update arrays

### Error Handling Philosophy
**Simplified Error Management**: The system uses streamlined error handling without overengineering:
- **Job Status**: Simple status tracking ('pending', 'processing', 'completed', 'failed') without detailed failure reasons
- **Error Logging**: Detailed errors logged via `error_log()` for debugging purposes
- **Database**: No `failure_reason` column - status tracking is sufficient for user-facing functionality
- **User Experience**: Failed jobs show generic failure message; detailed errors are server-side only
- **Analytics Error Prevention**: Proper `isset()` checks prevent undefined property warnings when accessing click data
- **Null Safety**: All analytics methods include robust null checking for undefined or missing data properties
- **Button Display Filtering**: Clean error handling in FilterManager with graceful fallback to showing buttons
- **Modal System Reliability**: Recent fixes ensure modal displays correctly and loading states work consistently across all devices
- **Conversion Protection**: Error handling designed to never block user purchase flow, with graceful fallbacks for pricing and checkout

### Architecture Enforcement
**RestApi Boundaries**: Maintain strict separation of concerns:
- RestApi handles routing, validation, and response formatting only
- Business logic must reside in Services layer
- API classes (StripeApi, Upsampler) are pure HTTP clients without business logic

## Frontend Implementation Details

### JavaScript Architecture
- **Modal System**: jQuery-based modal with AJAX integration for price calculations and checkout
- **Event Handling**: Click tracking with fire-and-forget AJAX calls to `/wp-json/smi/v1/track-button-click`
- **Payment Integration**: Stripe Checkout redirection with status polling on return
- **Error Handling**: User-friendly error messages with detailed server-side logging
- **Status Updates**: Real-time job status polling with exponential backoff and timeout handling
- **Spinner Class Consistency**: All loading states use unified `.smi-spinner` class across templates, JavaScript, and CSS for maintainable code architecture
- **Conversion Optimization**: Enhanced user experience through AI messaging and transparent delivery expectations

### CSS Structure  
- **Modal Styling**: Responsive design with mobile-friendly breakpoints
- **Critical Z-Index Fix**: Modal uses maximum z-index value (2147483647) to ensure it covers all third-party ads, especially Journey by Mediavine on mobile devices - critical for preventing conversion loss
- **Button Design**: Clean, professional appearance without promotional symbols for better user experience
- **Button Integration**: Seamless integration with theme styles via CSS custom properties
- **Loading States**: Visual feedback for processing, payment, and download states with consistent `.smi-spinner` implementation
- **Process Overview Styling**: Professional blue-tinted info box with clear typography for AI enhancement explanation
- **Mobile Optimization**: Enhanced mobile experience with proper ad overlay prevention and responsive design
- **Admin Interface**: Comprehensive admin styles with tabbed interface design and WordPress admin color scheme compliance
- **Tabbed Navigation**: Professional tab interface with active states, transitions, and responsive behavior
- **Filter Table Styling**: Professional table layout with scrollable sections, mobile responsiveness, disabled state handling, and high contrast support
- **Progressive Enhancement**: CSS handles graceful degradation when JavaScript is disabled

### Modal Template Architecture
- **AI-Enhanced Messaging**: Modal title changed to "AI-Enhanced High-Resolution Image" for better user understanding
- **Process Overview Section**: Dedicated section explaining "AI-Powered Image Enhancement" with timing expectations (2-5 minutes)
- **Enhanced Option Descriptions**: Focus on AI enhancement benefits rather than print-size specifications
- **Email Delivery Transparency**: Specific "automatically delivered in 2-5 minutes" messaging replaces vague "via email" description
- **User Experience Psychology**: Content improvements designed to increase trust and conversion rates through transparency

### Mobile Conversion Optimization
- **Ad Overlay Prevention**: The z-index fix is critical for mobile conversions - third-party ads (especially Journey by Mediavine) were covering the modal and blocking purchases
- **Touch-Friendly Interface**: Mobile-optimized button positioning and sizing for better touch interaction
- **Responsive Modal**: Full mobile compatibility with proper viewport handling and touch event support
- **Performance**: Smart asset loading prevents unnecessary CSS/JS when buttons won't appear

### Conversion Psychology Strategy
- **AI Messaging Benefits**: Emphasizes advanced technology and quality enhancement rather than technical specifications
- **Transparency Builds Trust**: Specific delivery timeframes and process explanations reduce user uncertainty
- **Professional Positioning**: "AI-Enhanced" terminology positions the service as premium and cutting-edge
- **Clear Expectations**: Process overview section manages user expectations about timing and automation

### Security Implementation
- **CSRF Protection**: WordPress nonces for all AJAX requests
- **Input Validation**: Client-side validation with server-side sanitization
- **Download Security**: Time-limited tokens generated with `wp_generate_password(64, false, false)`
- **Webhook Verification**: Signature validation for both Stripe and Upsampler webhooks using stored secrets
- **Modal Security**: Maximum z-index prevents malicious ads from overlaying modal elements and blocking legitimate user interactions
- **Code Consistency**: Unified class naming prevents JavaScript injection via inconsistent DOM manipulation

## Modal System Implementation Files

### Core Files Modified for Conversion Optimization
- **`templates/modal.php`**: Enhanced modal content with AI messaging, process overview section, and transparent delivery expectations
- **`assets/css/modal.css`**: Critical z-index fix for `.smi-modal` selector, process overview styling, and spinner class consistency
- **`assets/js/modal.js`**: Unified spinner class usage and enhanced user experience handling

### Key Implementation Details
1. **Critical Z-Index Fix**: `.smi-modal` uses maximum z-index (2147483647) for Journey by Mediavine ad coverage
2. **Unified Spinner Class**: All loading states use `.smi-spinner` class across templates, JS, and CSS
3. **AI Process Overview**: Dedicated "AI-Powered Image Upscaling" section with workflow explanation
4. **Modal Title**: "AI-Enhanced High-Resolution Image" for premium positioning
5. **Quality-Focused Options**: Emphasis on enhancement benefits over technical specifications
6. **Email Integration**: "Automatically delivered to your email" for clear delivery expectations

### Technical Architecture Notes
- **Maximum Z-Index Strategy**: Using maximum possible z-index value ensures modal displays above all third-party content
- **Class Consistency**: Unified `.smi-spinner` class prevents DOM manipulation issues and maintains code quality
- **Mobile-First Design**: All changes prioritize mobile user experience where conversion blocking was most severe
- **Professional Messaging**: AI-focused content positions service as premium technology solution
- **Trust Building**: Transparent communication about process, timing, and delivery method reduces purchase anxiety