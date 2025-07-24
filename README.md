# Sell My Images - WordPress Plugin

A WordPress plugin that allows content creators to monetize their images by selling high-resolution versions with AI upscaling and secure payment processing.

## Features

- **Automatic Image Detection**: Scans post content and adds "Buy Hi-Res" buttons to all images
- **AI Upscaling**: Enhances images to higher resolutions (2x, 4x, 8x)  
- **Secure Payments**: Stripe integration for safe transactions
- **Protected Downloads**: Time-limited, secure download links
- **Universal Compatibility**: Works with any WordPress theme

## Development Phases

### Phase 1: Foundation ✅ **COMPLETED**
- [x] Plugin structure and initialization
- [x] Directory structure creation (migrated to PSR-4 with Composer autoloading)
- [x] Image detection and button injection (DOM-based with multiple positioning modes)
- [x] Modal system (complete with HTML templates, JavaScript, and CSS)
- [x] Basic upscaling functionality (Upsampler.com API integration)
- [x] Admin settings page (WordPress Settings API integration)
- [x] REST API endpoints (`/wp-json/smi/v1/`)
- [x] Frontend completion (responsive modal, JavaScript interactions, CSS styling)

### Phase 2: Payment Integration ✅ **COMPLETED**
- [x] Stripe payment processing with test/live mode support
- [x] Purchase tracking and job management
- [x] Email confirmations with file attachments
- [x] Dynamic pricing based on Upsampler API costs
- [x] Webhook handling for payment and processing events
- [x] Secure checkout sessions and payment verification

### Phase 3: Advanced Features
- [x] AI upscaling API integration (Upsampler.com Precise Upscale)
- [x] Enhanced security (payment verification, file protection)
- [ ] Analytics and reporting dashboard
- [ ] Multi-resolution bundle pricing
- [ ] Batch processing capabilities

## Directory Structure

```
sell-my-images/
├── sell-my-images.php          # Main plugin file
├── composer.json               # PSR-4 autoloading configuration
├── src/                        # PSR-4 source code
│   ├── Admin/                  # Admin interface classes
│   ├── Api/                    # Image processing and external APIs
│   └── Content/                # Content detection and manipulation
├── assets/
│   ├── js/                     # Frontend JavaScript
│   ├── css/                    # Stylesheets
│   └── images/                 # Plugin icons and assets
├── templates/                  # HTML templates
├── vendor/                     # Composer autoloader
└── languages/                  # Internationalization files
```

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Configure settings under Settings > Sell My Images

## Requirements

- WordPress 5.0+
- PHP 7.4+
- SSL certificate (required for payments)

## License

GPL v2 or later

## Author

Chris Huber - [chubes.net](https://chubes.net)