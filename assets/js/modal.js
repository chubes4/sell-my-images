/**
 * Sell My Images - Modal JavaScript
 * 
 * Handles modal interactions, AJAX calls, and user interface functionality
 * 
 * @package SellMyImages
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    // Plugin object
    var SMI = {
        modal: null,
        currentImageData: null,
        processing: false,
        
        /**
         * Initialize the plugin
         */
        init: function() {
            this.modal = $('#smi-modal');
            this.bindEvents();
            this.setupModal();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Get Hi-Res button clicks
            $(document).on('click', '.smi-get-button', function(e) {
                e.preventDefault();
                self.openModal($(this));
            });
            
            // Modal close events
            $(document).on('click', '.smi-modal-close, .smi-modal-overlay, .smi-cancel-btn', function(e) {
                e.preventDefault();
                self.closeModal();
            });
            
            // Prevent modal content clicks from closing modal
            $(document).on('click', '.smi-modal-content', function(e) {
                e.stopPropagation();
            });
            
            // Resolution option changes
            $(document).on('change', 'input[name="resolution"]', function() {
                self.updateProcessButton();
            });
            
            // Email input changes
            $(document).on('input', '#smi-email', function() {
                self.updateProcessButton();
            });
            
            // Process button click
            $(document).on('click', '.smi-process-btn', function(e) {
                e.preventDefault();
                self.processUpscaling();
            });
            
            // Escape key to close modal
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && self.modal.is(':visible')) {
                    self.closeModal();
                }
            });
        },
        
        /**
         * Setup modal structure
         */
        setupModal: function() {
            // Ensure modal exists
            if (this.modal.length === 0) {
                console.warn('SMI: Modal element not found');
                return;
            }
            
            // Add any additional setup here
        },
        
        /**
         * Open modal for specific image
         */
        openModal: function($button) {
            if (this.processing) {
                return;
            }
            
            var attachmentId = $button.data('attachment-id');
            var postId = $button.data('post-id');
            var imageSrc = $button.data('src');
            var imageWidth = $button.data('width');
            var imageHeight = $button.data('height');
            
            if (!attachmentId) {
                this.showError('Attachment ID is required');
                return;
            }
            
            if (!postId) {
                this.showError('Post ID is required');
                return;
            }
            
            // Show modal with loading state
            this.modal.show();
            this.showLoading(true);
            this.resetModal();
            
            // Store basic data and get pricing directly
            this.currentImageData = {
                image_data: {
                    src: imageSrc,
                    attachment_id: attachmentId,
                    post_id: postId,
                    width: imageWidth,
                    height: imageHeight
                }
            };
            
            this.populateModalBasic();
            this.loadPricingForResolutions();
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            if (this.processing) {
                return;
            }
            
            this.modal.hide();
            this.resetModal();
            this.currentImageData = null;
        },
        
        /**
         * Reset modal to initial state
         */
        resetModal: function() {
            this.showLoading(false);
            this.showMainContent(false);
            this.showError(false);
            this.toggleProcessButton(false);
            $('input[name="resolution"]').prop('checked', false);
            $('#smi-email').val('');
            
            // Remove any retry buttons
            $('.smi-retry-container').remove();
            
            // Reset option states
            $('.smi-option').removeClass('smi-option-disabled').show();
            $('input[name="resolution"]').prop('disabled', false);
        },
        
        /**
         * Show/hide loading state
         */
        showLoading: function(show) {
            this.modal.find('.smi-loading').toggle(show);
        },
        
        /**
         * Show/hide main content
         */
        showMainContent: function(show) {
            this.modal.find('.smi-modal-main').toggle(show);
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            var $errorDiv = this.modal.find('.smi-error-message');
            
            if (message) {
                $errorDiv.find('.smi-error-text').text(message);
                $errorDiv.show();
                this.showLoading(false);
                this.showMainContent(false);
            } else {
                $errorDiv.hide();
            }
        },
        
        
        /**
         * Populate modal with basic image data
         */
        populateModalBasic: function() {
            // Update modal title
            this.modal.find('.smi-modal-title').text('Upscale High-Resolution Image');
            
            // Update image preview
            var $preview = this.modal.find('.smi-preview-image');
            $preview.attr('src', this.currentImageData.image_data.src);
            $preview.attr('alt', 'Image to upscale');
            
            // Show main content
            this.showLoading(false);
            this.showMainContent(true);
        },
        
        /**
         * Build pricing request data with attachment ID when available
         */
        buildPricingRequestData: function() {
            var attachmentId = this.currentImageData.image_data.attachment_id;
            if (!attachmentId) {
                throw new Error('Attachment ID not available');
            }
            
            return {
                attachment_id: attachmentId,
                post_id: this.currentImageData.image_data.post_id
            };
        },
        
        
        /**
         * Load pricing for all resolutions in one request
         */
        loadPricingForResolutions: function() {
            var self = this;
            
            // Show loading state for pricing
            $('.smi-option').each(function() {
                $(this).find('.smi-option-price').html('<span class="smi-loading-price">Calculating...</span>');
            });
            
            // Build request data and handle errors
            var requestData;
            try {
                requestData = this.buildPricingRequestData();
            } catch (error) {
                this.updateAllResolutionsError('Only Gutenberg image blocks are supported');
                return;
            }
            
            // Load pricing for all resolutions at once
            $.ajax({
                url: wpApiSettings.root + 'smi/v1/calculate-all-prices',
                type: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                data: requestData,
                success: function(response) {
                    if (response.success && response.pricing) {
                        // Update each resolution with its pricing
                        Object.keys(response.pricing).forEach(function(resolution) {
                            self.updateResolutionPricing(resolution, response.pricing[resolution], response.image_info);
                        });
                    } else {
                        self.updateAllResolutionsError('Unable to calculate pricing');
                    }
                },
                error: function(xhr) {
                    var errorMsg = 'Pricing unavailable';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    self.updateAllResolutionsError(errorMsg);
                }
            });
        },
        
        /**
         * Update all resolutions with error message
         */
        updateAllResolutionsError: function(errorMsg) {
            var resolutions = ['2x', '4x', '8x'];
            var self = this;
            
            resolutions.forEach(function(resolution) {
                self.updateResolutionError(resolution, errorMsg);
            });
            
            // Add retry button for better user experience
            this.addRetryButton();
        },
        
        /**
         * Add retry button when pricing fails
         */
        addRetryButton: function() {
            var $retryContainer = this.modal.find('.smi-retry-container');
            if ($retryContainer.length === 0) {
                var retryHtml = '<div class="smi-retry-container" style="text-align: center; margin-top: 15px;">';
                retryHtml += '<button type="button" class="smi-btn smi-btn-secondary smi-retry-pricing">Retry Pricing</button>';
                retryHtml += '</div>';
                
                this.modal.find('.smi-upscale-options').append(retryHtml);
                
                // Add retry event handler
                var self = this;
                $(document).on('click', '.smi-retry-pricing', function() {
                    $('.smi-retry-container').remove();
                    self.loadPricingForResolutions();
                });
            }
        },
        
        /**
         * Update resolution option with pricing
         */
        updateResolutionPricing: function(resolution, pricing, imageInfo) {
            var $option = $('input[name="resolution"][value="' + resolution + '"]');
            var $label = $option.closest('.smi-option');
            
            // Check if elements exist
            if ($option.length === 0 || $label.length === 0) {
                console.warn('SMI: Resolution option elements not found for ' + resolution);
                return;
            }
            
            // Update dimensions info
            var multiplier = parseInt(resolution);
            var outputWidth = imageInfo.width * multiplier;
            var outputHeight = imageInfo.height * multiplier;
            
            var $details = $label.find('.smi-option-details');
            if ($details.length > 0) {
                $details.html(
                    'Output: ' + this.formatNumber(outputWidth) + 'x' + this.formatNumber(outputHeight) + 
                    ' (' + pricing.output_megapixels + 'MP)'
                );
            }
            
            // Update pricing
            var $price = $label.find('.smi-option-price');
            if ($price.length > 0) {
                $price.html(
                    '<strong>$' + pricing.customer_price.toFixed(2) + '</strong>'
                );
            }
            
            // Store pricing data for later use
            $option.data('pricing', pricing);
            $option.data('imageInfo', imageInfo);
            
            $label.show();
        },
        
        /**
         * Update resolution option with error
         */
        updateResolutionError: function(resolution, errorMsg) {
            var $option = $('input[name="resolution"][value="' + resolution + '"]');
            var $label = $option.closest('.smi-option');
            
            // Check if elements exist
            if ($option.length === 0 || $label.length === 0) {
                console.warn('SMI: Resolution option elements not found for error display: ' + resolution);
                return;
            }
            
            var $price = $label.find('.smi-option-price');
            if ($price.length > 0) {
                $price.html(
                    '<span style="color: #d63638; font-size: 12px;">' + errorMsg + '</span>'
                );
            }
            
            // Disable the option
            $option.prop('disabled', true);
            $label.addClass('smi-option-disabled');
        },
        
        
        /**
         * Toggle process button state
         */
        toggleProcessButton: function(enabled) {
            this.modal.find('.smi-process-btn').prop('disabled', !enabled);
        },
        
        /**
         * Update process button based on form state
         */
        updateProcessButton: function() {
            var hasResolution = $('input[name="resolution"]:checked').length > 0;
            var hasEmail = $('#smi-email').val().trim().length > 0;
            
            this.toggleProcessButton(hasResolution && hasEmail);
        },
        
        /**
         * Process upscaling via checkout
         */
        processUpscaling: function() {
            if (this.processing) {
                return;
            }
            
            var $selected = $('input[name="resolution"]:checked');
            var email = $('#smi-email').val().trim();
            
            if ($selected.length === 0) {
                this.showError('Please select a resolution option.');
                return;
            }
            
            if (!email) {
                this.showError('Please enter your email address.');
                return;
            }
            
            if (!this.currentImageData) {
                this.showError('Image data not available.');
                return;
            }
            
            // Get pricing data from selected option
            var pricingData = $selected.data('pricing');
            if (!pricingData) {
                this.showError('Pricing information not available. Please try again.');
                return;
            }
            
            // Validate attachment ID is available
            var attachmentId = this.currentImageData.image_data.attachment_id;
            if (!attachmentId) {
                this.showError('Attachment ID not available.');
                return;
            }
            
            this.processing = true;
            
            // Update UI to show processing
            this.modal.find('.smi-process-btn').prop('disabled', true).text('Creating checkout...');
            
            // Make REST API request to create checkout session
            var self = this;
            
            $.ajax({
                url: wpApiSettings.root + 'smi/v1/create-checkout',
                type: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                data: {
                    attachment_id: attachmentId,
                    post_id: this.currentImageData.image_data.post_id,
                    resolution: $selected.val(),
                    email: email
                },
                success: function(response) {
                    if (response.success && response.checkout_url) {
                        self.handleCheckoutSuccess(response);
                    } else {
                        self.showError(response.message || 'Checkout creation failed.');
                        self.resetProcessButton();
                    }
                },
                error: function(xhr) {
                    var errorMsg = 'Checkout creation failed.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    self.showError(errorMsg);
                    self.resetProcessButton();
                },
                complete: function() {
                    self.processing = false;
                }
            });
        },
        
        /**
         * Handle successful checkout creation
         */
        handleCheckoutSuccess: function(data) {
            // Hide main content and show checkout redirect message
            this.showMainContent(false);
            
            var checkoutHtml = '<div class="smi-checkout-redirect" style="text-align: center; padding: 20px;">';
            checkoutHtml += '<div style="font-size: 48px; color: #0073aa; margin-bottom: 15px;">💳</div>';
            checkoutHtml += '<h3 style="color: #0073aa; margin-bottom: 15px;">Redirecting to Payment</h3>';
            checkoutHtml += '<p>You will be redirected to Stripe to complete your payment of <strong>$' + data.amount.toFixed(2) + '</strong></p>';
            checkoutHtml += '<p><small>Job ID: ' + data.job_id + '</small></p>';
            checkoutHtml += '<div style="margin-top: 20px;">';
            checkoutHtml += '<button type="button" class="smi-btn smi-btn-primary" onclick="window.location.href=\'' + data.checkout_url + '\'">Continue to Payment</button>';
            checkoutHtml += '</div>';
            checkoutHtml += '</div>';
            
            // Insert checkout message
            this.modal.find('.smi-modal-body').append(checkoutHtml);
            
            // Hide footer buttons
            this.modal.find('.smi-modal-footer').html(
                '<button type="button" class="smi-btn smi-btn-secondary smi-cancel-btn">Cancel</button>'
            );
            
            // Auto-redirect after short delay
            setTimeout(function() {
                window.location.href = data.checkout_url;
            }, 2000);
        },
        
        /**
         * Handle successful upscaling start
         */
        handleUpscalingSuccess: function(data) {
            // Hide main content and show success message
            this.showMainContent(false);
            
            var successHtml = '<div class="smi-success-message" style="text-align: center; padding: 20px;">';
            successHtml += '<div style="font-size: 48px; color: #00a32a; margin-bottom: 15px;">✓</div>';
            successHtml += '<h3 style="color: #00a32a; margin-bottom: 15px;">Processing Started!</h3>';
            successHtml += '<p>' + (data.message || 'Your image is being processed. You will receive an email when ready.') + '</p>';
            successHtml += '<p><small>Job ID: ' + data.job_id + '</small></p>';
            successHtml += '</div>';
            
            // Insert success message
            this.modal.find('.smi-modal-body').append(successHtml);
            
            // Hide footer buttons or change them
            this.modal.find('.smi-modal-footer').html(
                '<button type="button" class="smi-btn smi-btn-primary smi-close-success-btn">Close</button>'
            );
            
            // Auto-close after delay
            setTimeout(function() {
                this.closeModal();
            }.bind(this), 3000);
        },
        
        /**
         * Reset process button to original state
         */
        resetProcessButton: function() {
            this.modal.find('.smi-process-btn')
                .prop('disabled', false)
                .text('Pay & Process');
        },
        
        
        /**
         * Utility function to format currency
         */
        formatCurrency: function(amount, currency) {
            currency = currency || '$';
            return currency + parseFloat(amount).toFixed(2);
        },
        
        /**
         * Utility function to format numbers with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SMI.init();
    });
    
    // Expose SMI object globally for debugging
    window.SMI = SMI;
    
})(jQuery);