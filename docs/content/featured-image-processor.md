# Featured Image Processor

The Featured Image Processor injects the **Sell My Images** “Download Hi-Res” button for **featured images**.

Unlike Gutenberg block images (handled via the Block Processor + JS button injection), featured images are generated via `the_post_thumbnail()` and are filtered at render time.

## What It Does

- Hooks into `post_thumbnail_html` to optionally append a purchase button after the featured image markup.
- Uses the same display filtering rules as the rest of the plugin (via `FilterManager::should_show_buttons()`), so featured images respect your include/exclude logic.
- Avoids **double-injection** when the theme styles featured images as Gutenberg blocks.

## Hook Integration

- Filter: `post_thumbnail_html`
- Callback: `SellMyImages\Content\FeaturedImageProcessor::add_button_to_featured_image( $html, $post_id )`

## Settings

Featured image injection is controlled by the option:

- `smi_include_featured_images`

If disabled, the featured image HTML is returned unchanged.

## Conflict Detection (Block-Styled Featured Images)

Some themes render featured images with block markup/classes (notably `wp-block-image`). In that case, the processor skips injection so the frontend JS can handle it consistently.

Detection:

- If the featured image HTML contains `wp-block-image`, the processor returns the original HTML.

## Button Generation

When injection is allowed, the button HTML is generated via:

- `SellMyImages\Content\BlockProcessor::generate_external_button_html( $attachment_id, $post_id )`

This ensures featured-image buttons are compatible with the same modal workflow, REST endpoints, and tracking as block-based buttons.

## Related Code

- `sell-my-images/src/Content/FeaturedImageProcessor.php`
- `sell-my-images/src/Content/FilterManager.php`
- `sell-my-images/src/Content/BlockProcessor.php`
