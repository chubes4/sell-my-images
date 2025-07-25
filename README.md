# Sell My Images - WordPress Plugin

A professional WordPress plugin that monetizes website images by adding "Download Hi-Res" buttons to content images. Users can purchase AI-upscaled versions (4x, 8x) through secure payment processing.

## 🚀 Key Features

- **Smart Image Detection**: Automatically detects Gutenberg image blocks and injects purchase buttons
- **AI-Powered Upscaling**: Professional quality image enhancement via Upsampler.com API
- **Secure Payment Processing**: Stripe integration with test/live mode support
- **Real-time Analytics**: Track clicks, conversions, and revenue per post/image
- **Webhook-Driven Workflow**: Automated payment verification and processing
- **Protected Downloads**: Secure, token-based download system with expiration
- **Mobile-Responsive Design**: Pinterest-style hover buttons with mobile optimization
- **Developer-Friendly**: Clean PSR-4 architecture with comprehensive documentation

## 📋 Current Status: **PRODUCTION READY**

### ✅ Core Features Completed
- **Payment Processing**: Full Stripe integration with webhook automation
- **AI Upscaling**: Upsampler.com API integration with quality enhancement
- **Analytics System**: Click tracking, conversion rates, and revenue analytics
- **Admin Interface**: Comprehensive settings and analytics dashboard
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
- **Services Layer**: PaymentService & UpscalingService coordinate workflows
- **Manager Pattern**: Centralized data operations (JobManager, AnalyticsTracker)
- **API Abstraction**: Pure HTTP clients without business logic
- **Webhook Architecture**: Custom rewrite rules bypass WordPress routing

## 🚀 Quick Start

### Installation
1. **Upload & Activate**: Install plugin in `/wp-content/plugins/` and activate
2. **Install Dependencies**: Run `composer install` in plugin directory
3. **Configure APIs**: Add Stripe and Upsampler API keys in Admin → Sell My Images
4. **Test Webhooks**: Use Stripe CLI for local development testing

### Production Setup
1. **SSL Certificate**: Required for Stripe payments and webhooks
2. **Stripe Configuration**: 
   - Add live/test API keys in settings
   - Configure webhook endpoint: `https://yoursite.com/smi-webhook/stripe/`
3. **Upsampler Setup**: Get API key from upsampler.com
4. **Analytics**: Enable click tracking for conversion optimization

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
4. **Download Test**: Verify secure download delivery

### Database Schema
- **wp_smi_jobs**: Complete job tracking with analytics support
- **Post Meta**: Click analytics stored in `_smi_click_analytics`
- **Indexes**: Optimized for post/attachment cross-reference queries

## 📊 Analytics Dashboard

Track your image monetization performance:
- **Revenue by Post**: Identify top-performing content
- **Conversion Rates**: Click-to-purchase optimization
- **Image Performance**: Most profitable individual images
- **Profit Margins**: Real-time cost analysis with Upsampler pricing

## ⚖️ Legal Disclaimer

**Important**: You are responsible for ensuring you have proper rights to sell all images processed through this plugin. This includes copyright ownership, model releases, and licensing compliance. Use at your own legal risk.

## 📄 License & Author

**License**: GPL v2 or later  
**Author**: Chris Huber - [chubes.net](https://chubes.net)  
**Documentation**: See `CLAUDE.md` for comprehensive developer guide