<?php
/**
 * Modal Template
 * 
 * HTML structure for the image upscaling modal
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="smi-modal" class="smi-modal smi-hidden">
    <div class="smi-modal-overlay"></div>
    <div class="smi-modal-container">
        <div class="smi-modal-content">
            <div class="smi-modal-header">
                <h2 class="smi-modal-title">Upscale High-Resolution Image</h2>
                <button type="button" class="smi-modal-close">&times;</button>
            </div>
            
            <div class="smi-modal-body">
                <!-- Loading state -->
                <div class="smi-loading smi-hidden">
                    <div class="smi-loading-spinner"></div>
                    <p>Loading image information...</p>
                </div>
                
                <!-- Error message -->
                <div class="smi-error-message smi-hidden">
                    <div class="smi-error-icon">⚠️</div>
                    <div class="smi-error-text"></div>
                </div>
                
                <!-- Main content -->
                <div class="smi-modal-main smi-hidden">
                    <div class="smi-image-preview">
                        <img class="smi-preview-image" src="" alt="" />
                    </div>
                    
                    <div class="smi-upscale-options">
                        <h4>Choose Quality & Pricing</h4>
                        <div class="smi-resolution-options">
                            
                            <label class="smi-option" for="smi-resolution-4x">
                                <input type="radio" id="smi-resolution-4x" name="resolution" value="4x" />
                                <div class="smi-option-label">
                                    <strong>Standard Quality (4x)</strong>
                                    <div class="smi-option-details">Perfect for photo prints up to 11×14 inches</div>
                                </div>
                                <div class="smi-option-price">Calculating...</div>
                            </label>
                            
                            <label class="smi-option" for="smi-resolution-8x">
                                <input type="radio" id="smi-resolution-8x" name="resolution" value="8x" />
                                <div class="smi-option-label">
                                    <strong>Premium Quality (8x)</strong>
                                    <div class="smi-option-details">Professional quality for large prints up to 20×30 inches</div>
                                </div>
                                <div class="smi-option-price">Calculating...</div>
                            </label>
                            
                        </div>
                    </div>
                    
                    <div class="smi-email-field">
                        <label for="smi-email">Email Address:</label>
                        <input type="email" id="smi-email" name="email" placeholder="Enter your email address" required />
                        <p class="description">You'll receive the high-resolution image via email</p>
                    </div>
                </div>
            </div>
            
            <div class="smi-modal-footer">
                <div class="smi-terms-link smi-hidden">
                    <a href="" target="_blank" rel="noopener noreferrer">Terms & Conditions</a>
                </div>
                <div class="smi-footer-buttons">
                    <button type="button" class="smi-btn smi-btn-secondary smi-cancel-btn">Cancel</button>
                    <button type="button" class="smi-btn smi-btn-primary smi-process-btn" disabled>Pay & Process</button>
                </div>
            </div>
        </div>
    </div>
</div>