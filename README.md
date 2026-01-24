=== Sell My Images ===
Contributors: chubes
Tags: images, monetization, ecommerce, ai, upscaling
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monetize website images with AI-upscaled versions via secure Stripe payments. Uses Stripe and Upsampler.com services.

== Description ==

Monetize WordPress photography, art, and visual content with AI-powered image downloads. This plugin automatically adds "Download Hi-Res" buttons to your images, allowing visitors to purchase enhanced 4x or 8x resolution versions through secure Stripe payments. Perfect for photographers, artists, and content creators looking to generate revenue from their visual content.

## Features

- Automatically detects Gutenberg image blocks and injects purchase buttons
- Admin interface with API Configuration, Display Control, and Download Settings
- Granular button control: show on all posts, or selectively include/exclude by post type, category, tag, or specific post IDs
- AI-powered image upscaling via Upsampler.com API
- Secure Stripe payment processing with test/live mode support
- Real-time analytics tracking clicks, conversions, and revenue per post/image
- Webhook-driven automated payment verification and processing
- Secure token-based download system with expiration
- Mobile-responsive design
- Smart asset loading - CSS/JS only loads when buttons will appear
- Button detection compatible with diverse WordPress themes
- Handles long-running AI processing jobs
- PSR-4 architecture with comprehensive documentation

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

## Architecture Overview

WordPress plugin with PSR-4 architecture. Technical details in `CLAUDE.md`.

## Quick Start

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

## Requirements

- **WordPress**: 5.0+ (Gutenberg support required)
- **PHP**: 7.4+ with cURL extension
- **SSL**: HTTPS required for payment processing
- **APIs**: Stripe account + Upsampler.com account
- **Server**: Public webhook endpoint accessibility

## Development

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
3. **Monitor Job Status**: Test real-time polling system
4. **Verify Processing**: Check logs for webhook events and upscaling
5. **Test Success Path**: Verify email delivery and secure download
6. **Test Failure Path**: Simulate upscaling failure to verify automatic refund

### Code Examples

See `CLAUDE.md` for comprehensive technical documentation.

## Analytics Dashboard

Track image monetization performance with comprehensive analytics:
- Revenue by Post to identify top-performing content
- Conversion rates for click-to-purchase optimization  
- Image performance metrics showing most profitable individual images
- Real-time profit margin analysis with Upsampler pricing integration

## Admin Interface (v1.2.0+)

Settings interface with three tabs for plugin management:

### Tabs

**API Configuration Tab**
- Upsampler API key setup
- Stripe configuration (test/live modes) 
- Webhook endpoint documentation
- Service integration instructions

**Display Control Tab**
- Button display mode selection
- Filter criteria table
- Real-time validation and help
- Responsive design

**Download Settings Tab**
- Download link expiry configuration (1-168 hours)
- Markup percentage control
- Terms & Conditions integration

### UX Features
- Works with and without JavaScript
- Mobile responsive design
- WordPress admin styling integration
- Visual feedback for disabled states
- Single form submission

## Button Display Control

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

**Photographers**
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

## Legal Disclaimer

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

## License & Author

**License**: GPL v2 or later  
**Author**: Chris Huber - [chubes.net](https://chubes.net)  
**Documentation**: See `CLAUDE.md` for comprehensive developer guide  
**Live Demo**: [Sarai Chinwag](https://saraichinwag.com)