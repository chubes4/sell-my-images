# Block Processor

The Block Processor handles Gutenberg image block detection and download button injection for WordPress content.

## Content Processing

**Hook Integration**
- Filters `the_content` at priority 5
- Processes only singular posts and pages
- Respects display filter settings
- JavaScript-based button injection

**Block Detection**
Identifies WordPress image blocks:
- `wp-block-image` class detection
- Gutenberg block structure parsing
- Attachment ID extraction from classes
- Image metadata validation

## Button Injection System

**JavaScript Integration**
Frontend JavaScript handles dynamic button placement:
- `wp-image-{ID}` class detection
- MutationObserver for dynamic content
- Theme compatibility through multiple detection methods
- Automatic retry for AJAX-loaded content

**Button HTML Structure**
```html
<button class="smi-get-button" 
        data-post-id="123"
        data-attachment-id="456"
        data-src="image-url.jpg"
        data-width="2000"
        data-height="1500">
    <span class="smi-button-text">Download Hi-Res</span>
</button>
```

## Image Validation

**Quality Filters**
- Minimum size: 100x100 pixels (configurable)
- Excluded types: SVG images
- Local images only (WordPress attachments)
- Valid attachment metadata required

**Data Extraction**
From WordPress attachment system:
- `wp_get_attachment_metadata()` for dimensions
- `wp_get_attachment_url()` for source URL
- `wp_attachment_is_image()` for type validation
- Post meta for alt text and titles

## External Integration

**Theme/Plugin Support**
```php
// Generate button HTML for custom contexts
$button_html = \SellMyImages\Content\BlockProcessor::generate_external_button_html(
    $attachment_id,
    $post_id
);
```

**Filter Support**
- `smi_button_text` - Customize button label
- `smi_min_image_size` - Adjust size threshold
- `smi_load_assets` - Control asset loading

## Dynamic Content Support

**Real-time Detection**
- jQuery event system: `$(document).trigger('smi:refreshButtons')`
- Native events: `document.dispatchEvent(new CustomEvent('smi:refreshButtons'))`
- MutationObserver for automatic detection
- Gallery and slider compatibility

**Performance Optimization**
- Asset loading only when buttons appear
- Debounced mutation detection
- Efficient DOM querying
- Cache-friendly button detection

## Attachment ID Detection

**Multiple Detection Methods**
1. Image class: `wp-image-123`
2. Picture element class (theme compatibility)
3. Figure element class (fallback)
4. Data attributes (custom implementations)

**Theme Compatibility**
Handles various theme implementations:
- Responsive image techniques
- Picture element usage
- Custom image wrappers
- Lazy loading systems