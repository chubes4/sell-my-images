=== Sell My Images ===
Contributors: chubes
Tags: images, monetization, ecommerce, ai, upscaling
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monetize website images with AI-upscaled versions via secure Stripe payments. Uses Stripe and Upsampler.com services.

== Description ==

A professional WordPress plugin that monetizes website images by adding "Download Hi-Res" buttons to content images. Users can purchase AI-upscaled versions (4x, 8x) through secure payment processing with Stripe and AI enhancement via Upsampler.com.

## 🚀 Key Features

- **Smart Image Detection**: Automatically detects Gutenberg image blocks and injects purchase buttons
- **Professional Admin Interface**: Modern tabbed interface with API Configuration, Display Control, and Download Settings
- **Granular Button Control**: Show buttons on all posts, or selectively include/exclude by post type, category, tag, or specific post IDs
- **AI-Powered Upscaling**: Professional quality image enhancement via Upsampler.com API
- **Secure Payment Processing**: Stripe integration with test/live mode support
- **Real-time Analytics**: Track clicks, conversions, and revenue per post/image
- **Webhook-Driven Workflow**: Automated payment verification and processing
- **Protected Downloads**: Secure, token-based download system with expiration
- **Mobile-Responsive Design**: Clean, professional buttons and admin interface optimized for all devices
- **Performance-Optimized**: Smart asset loading - CSS/JS only loads when buttons will appear
- **Developer-Friendly**: Clean PSR-4 architecture with comprehensive documentation

== Third Party Services ==

This plugin relies on external services to provide its core functionality. By using this plugin, you acknowledge and agree to data transmission to these services under the conditions described below.

=== Stripe Payment Processing ===

**What it is:** Secure payment processing service for handling customer purchases
**When data is sent:** When customers initiate checkout and during payment verification
**Data transmitted:**
- Customer email address
- Payment amount and currency
- Billing information (if provided by customer)
- Transaction metadata (post ID, image details)

**Service URLs:**
- Terms of Service: https://stripe.com/legal/consumer
- Privacy Policy: https://stripe.com/privacy
- Website: https://stripe.com

**Data handling:** No payment card data is stored on your server. All sensitive payment information is processed directly by Stripe's secure servers.

=== Upsampler.com AI Image Enhancement ===

**What it is:** AI-powered image upscaling service that enhances image resolution and quality
**When data is sent:** After successful payment completion, to process purchased image enhancements
**Data transmitted:**
- Original image URLs from your WordPress site
- Upscaling parameters (resolution multiplier: 4x or 8x)
- Webhook callback URLs for processing notifications
- Job tracking identifiers

**Pricing:** $0.04 per credit (1 credit per 4 megapixels of output)

**Service URLs:**
- Terms of Service: https://upsampler.com/terms-of-service
- Privacy Policy: https://upsampler.com/privacy-policy  
- Website: https://upsampler.com

**Data handling:** Images are processed temporarily and automatically deleted from Upsampler servers after completion. Enhanced images are delivered via secure download links.

=== Data Protection & Privacy ===

**GDPR Compliance:** If you serve EU customers, ensure you have appropriate data processing agreements and user consent for transmitting data to these US-based services.

**Data Retention:** 
- Stripe: Retains transaction data per their privacy policy
- Upsampler: Temporary processing only, automatic deletion after completion
- Your site: Stores job records and analytics locally (can be deleted via admin interface)

**Service Availability:** Plugin functionality depends on third-party service availability. When upscaling fails, automatic Stripe refunds are processed with customer notification via email.

**User Control:** Customers provide explicit consent during checkout. No data is transmitted without user-initiated purchase action.

## 📋 Current Status: **PRODUCTION READY** (Version 1.2.0)

### ✅ Core Features Completed
- **Payment Processing**: Full Stripe integration with webhook automation and automatic refunds
- **AI Upscaling**: Upsampler.com API integration with quality enhancement and failure handling
- **Dual Email System**: HTML download notifications and plain text refund notifications
- **Analytics System**: Click tracking, conversion rates, and revenue analytics
- **Professional Admin Interface**: Modern tabbed design with enhanced UX
- **Button Display Control**: Comprehensive filtering system with visual feedback
- **Security**: Payment verification, secure downloads, input validation
- **Mobile Support**: Responsive design with touch-friendly interactions

### 🔄 Workflow Status
1. **Image Detection & Button Injection** ✅ Working
2. **Payment Processing** ✅ Working 
3. **Webhook Automation** ✅ Working
4. **AI Upscaling** ✅ Working
5. **Download Delivery** ✅ Working
6. **Analytics Tracking** ✅ Working

## 🏗️ Architecture Overview

### Clean Layered Architecture
```
sell-my-images/
├── sell-my-images.php          # Main plugin file (singleton pattern)
├── composer.json               # PSR-4 autoloading + Stripe SDK
├── CLAUDE.md                   # Comprehensive developer documentation
├── src/                        # PSR-4 namespace: SellMyImages\
│   ├── Admin/                  # WordPress admin interface
│   ├── Api/                    # External API clients (Stripe, Upsampler)
│   ├── Content/                # Gutenberg block processing
│   ├── Managers/               # Data management & business logic
│   ├── Services/               # Workflow coordination layer
│   └── Config/                 # Configuration and constants
├── assets/                     # Frontend resources
│   ├── js/modal.js            # Modal interactions & AJAX
│   ├── css/modal.css          # Pinterest-style button design
│   └── css/admin.css          # Admin dashboard styling
├── templates/modal.php         # Modal HTML structure
└── vendor/                     # Composer dependencies
```

### Key Design Patterns
- **Services Layer**: PaymentService & UpscalingService coordinate workflows with error handling
- **Manager Pattern**: Centralized data operations (JobManager, AnalyticsTracker, DownloadManager)
- **API Abstraction**: Pure HTTP clients without business logic (StripeApi for refunds, Upsampler for processing)
- **Webhook Architecture**: Custom rewrite rules bypass WordPress routing for payment and processing notifications
- **Email Architecture**: Template-based HTML notifications for success, inline plain text for refunds

## 🚀 Quick Start

### Installation

**For Development (GitHub Clone):**
1. **Clone Repository**: `git clone https://github.com/chubes4/sell-my-images.git`
2. **Install Dependencies**: Run `composer install` in plugin directory (required for Stripe SDK)
3. **Upload & Activate**: Install plugin in `/wp-content/plugins/` and activate

**For Production Use:**
1. **Download Release**: Download from plugin marketplace (includes dependencies)
2. **Upload & Activate**: Install in `/wp-content/plugins/` and activate

**Configuration:**
3. **Configure APIs**: Use the new tabbed interface - API Configuration tab for Stripe and Upsampler setup
4. **Control Button Display**: Use the Display Control tab to choose where download buttons appear
5. **Configure Downloads**: Use the Download Settings tab for expiry and pricing settings
6. **Test Webhooks**: Use Stripe CLI for local development testing

### Production Setup
1. **SSL Certificate**: Required for Stripe payments and webhooks
2. **Stripe Configuration**: 
   - Use API Configuration tab to add live/test API keys
   - Configure webhook endpoint: `https://yoursite.com/smi-webhook/stripe/`
3. **Upsampler Setup**: Add API key in API Configuration tab
4. **Button Display Setup**: Use Display Control tab for comprehensive filtering system
5. **Download Settings**: Configure expiry and pricing in Download Settings tab
6. **Analytics**: Enable click tracking for conversion optimization

## 📋 Requirements

- **WordPress**: 5.0+ (Gutenberg support required)
- **PHP**: 7.4+ with cURL extension
- **SSL**: HTTPS required for payment processing
- **APIs**: Stripe account + Upsampler.com account
- **Server**: Public webhook endpoint accessibility

## 🔧 Development

### Local Development Setup
```bash
# Install dependencies
composer install

# Start Stripe webhook listener (keep running)
stripe listen --forward-to=https://yoursite.local/smi-webhook/stripe/

# Copy webhook secret to WordPress settings
# Admin → Sell My Images → Stripe Webhook Secret
```

### Testing Workflow
1. **Create Test Job**: Click "Download Hi-Res" on any image
2. **Complete Payment**: Use Stripe test cards (4242 4242 4242 4242)
3. **Verify Processing**: Check logs for webhook events and upscaling
4. **Test Success Path**: Verify HTML email delivery and secure download
5. **Test Failure Path**: Simulate upscaling failure to verify automatic refund and plain text notification
6. **Monitor Email System**: Check both customer and admin email delivery

### Database Schema
- **wp_smi_jobs**: Complete job tracking with analytics support and refund audit trail
- **Post Meta**: Click analytics stored in `_smi_click_analytics`
- **Indexes**: Optimized for post/attachment cross-reference queries
- **Refund Fields**: `refunded_at`, `refund_reason`, `refund_amount` for complete transaction history

## 📊 Analytics Dashboard

Track your image monetization performance with comprehensive analytics:
- **Revenue by Post**: Identify top-performing content with refund impact analysis
- **Conversion Rates**: Click-to-purchase optimization accounting for refunded transactions
- **Image Performance**: Most profitable individual images with failure rate tracking
- **Profit Margins**: Real-time cost analysis with Upsampler pricing and refund adjustments
- **Customer Satisfaction**: Email delivery tracking and refund rate monitoring

## 🎨 Professional Admin Interface (New in v1.2.0)

Experience a modern, organized settings interface with three dedicated tabs for comprehensive plugin management:

### Tabbed Organization

**API Configuration Tab**
- Upsampler API key setup with direct signup links
- Complete Stripe configuration (test/live modes) 
- Webhook endpoint documentation and guidance
- Service integration instructions

**Display Control Tab**
- Button display mode selection with visual indicators
- Comprehensive filter criteria table (always visible for clarity)
- Real-time validation and contextual help
- Professional responsive design

**Download Settings Tab**
- Download link expiry configuration (1-168 hours)
- Markup percentage control with live preview
- Terms & Conditions integration
- Business logic transparency

### UX Improvements
- **Progressive Enhancement**: Works with and without JavaScript
- **Mobile Responsive**: Adapts seamlessly to all screen sizes  
- **WordPress Integration**: Professional styling that matches WordPress admin
- **Contextual Feedback**: Disabled states and visual cues for better understanding
- **Single Form Submission**: Efficient processing while maintaining organization

## 🎯 Button Display Control

Control exactly where download buttons appear on your site with three flexible display modes:

### Display Modes

**All Posts** (Default)
- Shows buttons on all eligible posts with images
- Zero configuration required
- Best for sites where all content is monetizable

**Exclude Selected**
- Hide buttons on posts matching your criteria
- Perfect for excluding specific categories (e.g., "Free Resources")
- Useful for bloggers who want to exclude personal content

**Include Only Selected**
- Show buttons only on posts matching your criteria  
- Ideal for photographers focusing on specific portfolios
- Great for e-commerce sites with dedicated product categories

### Filter Criteria

Combine any of these filtering options:

- **Post Types**: Target specific content types (posts, pages, custom post types)
- **Categories**: Include/exclude entire content categories
- **Tags**: Fine-tune targeting with post tags
- **Specific Post IDs**: Granular control over individual posts

### Use Cases

**Professional Photographers**
- Include only "Portfolio" and "Gallery" categories
- Exclude "Behind the Scenes" or "Blog" content

**Content Creators**
- Exclude "Free Downloads" category
- Include only "Premium Content" posts

**E-commerce Sites**
- Include only "Products" post type
- Exclude "News" and "Support" categories

**Multi-Author Blogs**
- Include only posts tagged "Monetizable"
- Exclude guest author content

## ⚖️ Legal Disclaimer

**Important**: You are responsible for ensuring you have proper rights to sell all images processed through this plugin. This includes copyright ownership, model releases, and licensing compliance. Use at your own legal risk.

== Privacy & External Services ==

**Important Privacy Notice:** This plugin transmits data to external services (Stripe for payments, Upsampler.com for image processing). Review the third-party services section above and ensure compliance with your privacy policy and applicable data protection laws.

**Recommended Actions:**
1. Update your site's privacy policy to disclose data transmission to Stripe and Upsampler.com
2. Ensure appropriate user consent mechanisms for EU visitors (GDPR compliance)
3. Review service terms and privacy policies linked above
4. Consider data processing agreements if required by your jurisdiction

== Installation ==

=== Basic Setup ===

**For Development (GitHub Clone):**
1. **Clone Repository**: `git clone https://github.com/chubes4/sell-my-images.git`
2. **Install Dependencies**: Run `composer install` in plugin directory (required for Stripe SDK)
3. **Upload & Activate**: Install plugin in WordPress admin or upload to `/wp-content/plugins/`

**For Production Use:**
1. **Download Release**: Download from plugin marketplace (includes dependencies)
2. **Upload & Activate**: Install plugin in WordPress admin

**Configuration:**
3. **Review Service Terms**: Read and agree to Stripe and Upsampler.com terms of service
4. **Configure Button Display**: Set up where download buttons should appear on your site
5. **Update Privacy Policy**: Add disclosure of external service usage to your site's privacy policy

=== Service Configuration ===
Before using this plugin:

1. **Create Stripe Account:** Sign up at stripe.com and obtain API keys
2. **Create Upsampler Account:** Sign up at upsampler.com and obtain API key  
3. **Configure APIs**: Add API keys in WordPress Admin → Sell My Images
4. **Configure Button Display**: Choose where download buttons appear using the filtering system
5. **Set Up Webhooks**: Configure Stripe webhook endpoint as documented
6. **Test Configuration**: Use test mode to verify functionality

=== Production Requirements ===
- **SSL Certificate**: Required for Stripe payments and webhooks
- **HTTPS**: All payment processing requires secure connections
- **Webhook Endpoint**: Must be publicly accessible for payment processing
- **PHP 7.4+**: Required for Stripe SDK compatibility

## 📄 License & Author

**License**: GPL v2 or later  
**Author**: Chris Huber - [chubes.net](https://chubes.net)  
**Documentation**: See `CLAUDE.md` for comprehensive developer guide