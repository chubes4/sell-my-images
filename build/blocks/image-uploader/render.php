<?php
/**
 * Server-side rendering of the Image Uploader block
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$title = isset( $attributes['title'] ) ? esc_html( $attributes['title'] ) : __( 'Upscale Your Image', 'sell-my-images' );
$description = isset( $attributes['description'] ) ? esc_html( $attributes['description'] ) : '';
$max_file_size = isset( $attributes['maxFileSize'] ) ? intval( $attributes['maxFileSize'] ) : 10;
$show_terms_link = isset( $attributes['showTermsLink'] ) ? $attributes['showTermsLink'] : true;

$terms_url = get_option( 'smi_terms_conditions_url', '' );
$wrapper_attributes = get_block_wrapper_attributes( array(
    'class' => 'smi-image-uploader',
    'data-max-file-size' => $max_file_size,
) );
?>

<div <?php echo $wrapper_attributes; ?>>
    <div class="smi-uploader-container">
        <!-- Header -->
        <div class="smi-uploader-header">
            <h3 class="smi-uploader-title"><?php echo $title; ?></h3>
            <?php if ( $description ) : ?>
                <p class="smi-uploader-description"><?php echo $description; ?></p>
            <?php endif; ?>
        </div>

        <!-- Upload Zone (initial state) -->
        <div class="smi-upload-zone" id="smi-upload-zone">
            <div class="smi-dropzone" id="smi-dropzone">
                <div class="smi-dropzone-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="17 8 12 3 7 8" />
                        <line x1="12" y1="3" x2="12" y2="15" />
                    </svg>
                </div>
                <p class="smi-dropzone-text"><?php esc_html_e( 'Drag & drop your image here', 'sell-my-images' ); ?></p>
                <p class="smi-dropzone-or"><?php esc_html_e( 'or', 'sell-my-images' ); ?></p>
                <button type="button" class="smi-browse-button" id="smi-browse-button">
                    <?php esc_html_e( 'Browse Files', 'sell-my-images' ); ?>
                </button>
                <input type="file" id="smi-file-input" accept="image/jpeg,image/png,image/webp" hidden />
                <p class="smi-dropzone-hint">
                    <?php printf( 
                        /* translators: %d: maximum file size in MB */
                        esc_html__( 'Supports: JPEG, PNG, WebP (max %dMB)', 'sell-my-images' ), 
                        $max_file_size 
                    ); ?>
                </p>
            </div>
        </div>

        <!-- Preview Zone (after upload) -->
        <div class="smi-preview-zone" id="smi-preview-zone" style="display: none;">
            <div class="smi-preview-image-container">
                <img id="smi-preview-image" src="" alt="<?php esc_attr_e( 'Preview', 'sell-my-images' ); ?>" />
                <button type="button" class="smi-remove-image" id="smi-remove-image" title="<?php esc_attr_e( 'Remove image', 'sell-my-images' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="smi-image-info" id="smi-image-info">
                <p class="smi-image-dimensions"><span id="smi-image-dimensions"></span></p>
            </div>
        </div>

        <!-- Resolution Picker (after upload) -->
        <div class="smi-resolution-picker" id="smi-resolution-picker" style="display: none;">
            <h4><?php esc_html_e( 'Choose Resolution', 'sell-my-images' ); ?></h4>
            <div class="smi-resolution-options">
                <label class="smi-resolution-option">
                    <input type="radio" name="smi-resolution" value="4x" checked />
                    <div class="smi-resolution-card">
                        <span class="smi-resolution-label">4x</span>
                        <span class="smi-resolution-output" id="smi-output-4x"></span>
                        <span class="smi-resolution-price" id="smi-price-4x"></span>
                    </div>
                </label>
                <label class="smi-resolution-option">
                    <input type="radio" name="smi-resolution" value="8x" />
                    <div class="smi-resolution-card">
                        <span class="smi-resolution-label">8x</span>
                        <span class="smi-resolution-output" id="smi-output-8x"></span>
                        <span class="smi-resolution-price" id="smi-price-8x"></span>
                    </div>
                </label>
            </div>
        </div>

        <!-- Email Input -->
        <div class="smi-email-section" id="smi-email-section" style="display: none;">
            <label for="smi-email-input"><?php esc_html_e( 'Email for download link', 'sell-my-images' ); ?></label>
            <input type="email" id="smi-email-input" placeholder="<?php esc_attr_e( 'your@email.com', 'sell-my-images' ); ?>" />
        </div>

        <!-- Checkout Button -->
        <div class="smi-checkout-section" id="smi-checkout-section" style="display: none;">
            <button type="button" class="smi-checkout-button" id="smi-checkout-button">
                <?php esc_html_e( 'Proceed to Checkout', 'sell-my-images' ); ?>
            </button>
            <?php if ( $show_terms_link && $terms_url ) : ?>
                <p class="smi-terms-notice">
                    <?php printf(
                        /* translators: %s: terms and conditions link */
                        esc_html__( 'By proceeding, you agree to our %s', 'sell-my-images' ),
                        '<a href="' . esc_url( $terms_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Terms & Conditions', 'sell-my-images' ) . '</a>'
                    ); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Loading State -->
        <div class="smi-loading" id="smi-loading" style="display: none;">
            <div class="smi-spinner"></div>
            <p id="smi-loading-text"><?php esc_html_e( 'Uploading...', 'sell-my-images' ); ?></p>
        </div>

        <!-- Error Message -->
        <div class="smi-error" id="smi-error" style="display: none;">
            <p id="smi-error-text"></p>
        </div>
    </div>
</div>
