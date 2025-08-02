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
            this.injectButtons();
            this.checkPaymentStatus();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Get Hi-Res button clicks
            $(document).on('click', '.smi-get-button', function(e) {
                e.preventDefault();
                var $button = $(this);
                
                // Track the button click for analytics (fire-and-forget)
                self.trackButtonClick($button);
                
                // Open the modal
                self.openModal($button);
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
            
            // Setup Terms & Conditions link if URL is provided
            if (smi_ajax.terms_conditions_url && smi_ajax.terms_conditions_url.trim() !== '') {
                var $termsLink = this.modal.find('.smi-terms-link');
                var $termsAnchor = $termsLink.find('a');
                
                $termsAnchor.attr('href', smi_ajax.terms_conditions_url);
                $termsLink.removeClass('smi-hidden');
            }
        },
        
        /**
         * Track button click for analytics
         */
        trackButtonClick: function($button) {
            var attachmentId = $button.data('attachment-id');
            var postId = $button.data('post-id');
            
            console.log('SMI: Tracking button click - Attachment ID:', attachmentId, 'Post ID:', postId);
            
            // Validate required data
            if (!attachmentId || !postId) {
                console.warn('SMI: Missing attachment-id or post-id for click tracking - Button data:', $button.data());
                return;
            }
            
            // Send tracking request (fire-and-forget - don't block user experience)
            $.ajax({
                url: wpApiSettings.root + 'smi/v1/track-button-click',
                type: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                data: {
                    attachment_id: attachmentId,
                    post_id: postId
                },
                success: function(response) {
                    console.log('SMI: Click tracked successfully for Attachment ID:', attachmentId, response);
                },
                error: function(xhr, status, error) {
                    // Log but don't interrupt user experience
                    console.warn('SMI: Click tracking failed for Attachment ID:', attachmentId, 'Error:', error);
                    if (xhr.responseJSON) {
                        console.warn('SMI: Server response:', xhr.responseJSON);
                    }
                }
            });
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
            this.modal.removeClass('smi-hidden');
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
            
            this.modal.addClass('smi-hidden');
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
            $('.smi-option').removeClass('smi-option-disabled smi-hidden');
            $('input[name="resolution"]').prop('disabled', false);
        },
        
        /**
         * Show/hide loading state
         */
        showLoading: function(show) {
            this.modal.find('.smi-loading').toggleClass('smi-hidden', !show);
        },
        
        /**
         * Show/hide main content
         */
        showMainContent: function(show) {
            this.modal.find('.smi-modal-main').toggleClass('smi-hidden', !show);
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            var $errorDiv = this.modal.find('.smi-error-message');
            
            if (message) {
                $errorDiv.find('.smi-error-text').text(message);
                $errorDiv.removeClass('smi-hidden');
                this.showLoading(false);
                this.showMainContent(false);
            } else {
                $errorDiv.addClass('smi-hidden');
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
            var resolutions = ['4x', '8x'];
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
                var retryHtml = '<div class="smi-retry-container">';
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
                    this.formatNumber(imageInfo.width) + '×' + this.formatNumber(imageInfo.height) + ' → ' +
                    this.formatNumber(outputWidth) + '×' + this.formatNumber(outputHeight) + 
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
            
            $label.removeClass('smi-hidden');
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
                    '<span class="smi-error-pricing">' + errorMsg + '</span>'
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
            
            var checkoutHtml = '<div class="smi-checkout-redirect smi-status-container">';
            checkoutHtml += '<div class="smi-status-icon info">💳</div>';
            checkoutHtml += '<h3 class="smi-status-title info">Redirecting to Payment</h3>';
            checkoutHtml += '<p>You will be redirected to Stripe to complete your payment of <strong>$' + data.amount.toFixed(2) + '</strong></p>';
            checkoutHtml += '<p><small>Job ID: ' + data.job_id + '</small></p>';
            checkoutHtml += '<div class="smi-button-container">';
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
            
            var successHtml = '<div class="smi-success-message smi-status-container">';
            successHtml += '<div class="smi-status-icon success">✓</div>';
            successHtml += '<h3 class="smi-status-title success">Processing Started!</h3>';
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
        },
        
        /**
         * Inject download buttons into WordPress image blocks
         */
        injectButtons: function() {
            var self = this;
            console.log('SMI: Injecting buttons into image blocks');
            
            // Find all WordPress image blocks
            $('.wp-block-image').each(function() {
                var $figure = $(this);
                var $img = $figure.find('img');
                
                // Skip if no image or button already exists
                if (!$img.length || $figure.find('.smi-get-button').length) {
                    return;
                }
                
                // Extract attachment ID from multiple possible locations
                var attachmentId = null;
                var attachmentMatch = null;
                var detectionSource = '';
                
                // First try: img class (wp-image-XXXX)
                var imgClasses = $img.attr('class') || '';
                attachmentMatch = imgClasses.match(/wp-image-(\d+)/);
                if (attachmentMatch) {
                    attachmentId = attachmentMatch[1];
                    detectionSource = 'img class';
                }
                
                // Second try: picture element class (for themes using picture tags)
                if (!attachmentId) {
                    var $picture = $figure.find('picture');
                    if ($picture.length) {
                        var pictureClasses = $picture.attr('class') || '';
                        attachmentMatch = pictureClasses.match(/wp-image-(\d+)/);
                        if (attachmentMatch) {
                            attachmentId = attachmentMatch[1];
                            detectionSource = 'picture class';
                        }
                    }
                }
                
                // Third try: figure element class (fallback)
                if (!attachmentId) {
                    var figureClasses = $figure.attr('class') || '';
                    attachmentMatch = figureClasses.match(/wp-image-(\d+)/);
                    if (attachmentMatch) {
                        attachmentId = attachmentMatch[1];
                        detectionSource = 'figure class';
                    }
                }
                
                if (!attachmentId) {
                    console.warn('SMI: No attachment ID found for image block');
                    console.log('SMI Debug - Figure classes:', $figure.attr('class'));
                    console.log('SMI Debug - Img classes:', imgClasses);
                    var $picture = $figure.find('picture');
                    if ($picture.length) {
                        console.log('SMI Debug - Picture classes:', $picture.attr('class'));
                    }
                    return;
                }
                
                // Log successful detection
                console.log('SMI: Found attachment ID', attachmentId, 'via', detectionSource);
                var postId = self.getPostId();
                var imgSrc = $img.attr('src');
                var imgWidth = $img.attr('width') || $img[0].naturalWidth;
                var imgHeight = $img.attr('height') || $img[0].naturalHeight;
                
                // Skip very small images (likely icons)
                if (imgWidth < 100 || imgHeight < 100) {
                    console.log('SMI: Skipping small image:', imgWidth + 'x' + imgHeight);
                    return;
                }
                
                // Create and inject button
                var buttonHtml = self.createButtonHtml(postId, attachmentId, imgSrc, imgWidth, imgHeight);
                $figure.append(buttonHtml);
                
                // Validate button was created correctly
                var $createdButton = $figure.find('.smi-get-button');
                if ($createdButton.length) {
                    var buttonAttachmentId = $createdButton.data('attachment-id');
                    var buttonPostId = $createdButton.data('post-id');
                    
                    if (buttonAttachmentId && buttonPostId) {
                        console.log('SMI: Button successfully injected - Attachment ID:', buttonAttachmentId, 'Post ID:', buttonPostId);
                    } else {
                        console.error('SMI: Button created but missing data attributes - Attachment ID:', buttonAttachmentId, 'Post ID:', buttonPostId);
                    }
                } else {
                    console.error('SMI: Button HTML created but not found in DOM');
                }
            });
        },
        
        /**
         * Create button HTML
         */
        createButtonHtml: function(postId, attachmentId, imgSrc, imgWidth, imgHeight) {
            return '<button class="smi-get-button" ' +
                   'data-post-id="' + postId + '" ' +
                   'data-attachment-id="' + attachmentId + '" ' +
                   'data-src="' + imgSrc + '" ' +
                   'data-width="' + imgWidth + '" ' +
                   'data-height="' + imgHeight + '">' +
                   '<span class="smi-button-text">Download Hi-Res</span>' +
                   '</button>';
        },
        
        /**
         * Check for payment status in URL parameters
         */
        checkPaymentStatus: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var paymentStatus = urlParams.get('smi_payment');
            var jobId = urlParams.get('job_id');
            var sessionId = urlParams.get('session_id');
            
            if (paymentStatus && jobId) {
                // Clean up URL parameters
                this.cleanupUrlParameters();
                
                if (paymentStatus === 'success') {
                    this.handlePaymentSuccess(jobId, sessionId);
                } else if (paymentStatus === 'cancelled') {
                    this.handlePaymentCancelled(jobId);
                }
            }
        },
        
        /**
         * Clean up URL parameters after handling
         */
        cleanupUrlParameters: function() {
            if (history.replaceState) {
                var url = new URL(window.location);
                url.searchParams.delete('smi_payment');
                url.searchParams.delete('job_id');
                url.searchParams.delete('session_id');
                history.replaceState(null, '', url);
            }
        },
        
        /**
         * Handle payment success
         */
        handlePaymentSuccess: function(jobId, sessionId) {
            // Show modal with success message
            this.modal.removeClass('smi-hidden');
            this.showPaymentSuccess(jobId);
            
            // Start polling for job completion
            this.startJobStatusPolling(jobId);
        },
        
        /**
         * Handle payment cancellation
         */
        handlePaymentCancelled: function(jobId) {
            // Show modal with cancellation message
            this.modal.removeClass('smi-hidden');
            this.showPaymentCancelled(jobId);
        },
        
        /**
         * Show payment success state
         */
        showPaymentSuccess: function(jobId) {
            this.showLoading(false);
            this.showMainContent(false);
            this.showError(false);
            
            var successHtml = '<div class="smi-payment-success smi-status-container">';
            successHtml += '<div class="smi-status-icon success">✓</div>';
            successHtml += '<h3 class="smi-status-title success">Payment Successful!</h3>';
            successHtml += '<p>Your image is being processed. This may take a few minutes.</p>';
            successHtml += '<p><small>Job ID: ' + jobId + '</small></p>';
            successHtml += '<div class="smi-processing-status smi-button-container">';
            successHtml += '<div class="smi-loading-spinner"></div>';
            successHtml += '<p>Processing your image...</p>';
            successHtml += '</div>';
            successHtml += '</div>';
            
            this.modal.find('.smi-modal-body').html(successHtml);
            
            // Update footer
            this.modal.find('.smi-modal-footer').html(
                '<button type="button" class="smi-btn smi-btn-secondary smi-cancel-btn">Close</button>'
            );
        },
        
        /**
         * Show payment cancelled state
         */
        showPaymentCancelled: function(jobId) {
            this.showLoading(false);
            this.showMainContent(false);
            this.showError(false);
            
            var cancelHtml = '<div class="smi-payment-cancelled smi-status-container">';
            cancelHtml += '<div class="smi-status-icon error">✗</div>';
            cancelHtml += '<h3 class="smi-status-title error">Payment Cancelled</h3>';
            cancelHtml += '<p>Your payment was cancelled. No charges were made.</p>';
            cancelHtml += '<p><small>Job ID: ' + jobId + '</small></p>';
            cancelHtml += '</div>';
            
            this.modal.find('.smi-modal-body').html(cancelHtml);
            
            // Update footer
            this.modal.find('.smi-modal-footer').html(
                '<button type="button" class="smi-btn smi-btn-primary smi-cancel-btn">Try Again</button>'
            );
        },
        
        /**
         * Start polling for job status
         */
        startJobStatusPolling: function(jobId) {
            var self = this;
            var pollCount = 0;
            var maxPolls = 60; // Poll for up to 10 minutes (10 second intervals)
            
            var poll = function() {
                pollCount++;
                
                $.ajax({
                    url: wpApiSettings.root + 'smi/v1/job-status/' + jobId,
                    type: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    },
                    success: function(response) {
                        if (response.success) {
                            self.updateJobStatus(response.data);
                            
                            if (response.data.status === 'completed') {
                                self.showJobCompleted(response.data);
                                return; // Stop polling
                            } else if (response.data.status === 'failed') {
                                self.showJobFailed(response.data);
                                return; // Stop polling
                            }
                        }
                        
                        // Continue polling if not completed/failed and under max polls
                        if (pollCount < maxPolls) {
                            setTimeout(poll, 10000); // Poll every 10 seconds
                        } else {
                            self.showJobTimeout();
                        }
                    },
                    error: function() {
                        // Continue polling on error, but with longer interval
                        if (pollCount < maxPolls) {
                            setTimeout(poll, 15000); // 15 second interval on error
                        } else {
                            self.showJobTimeout();
                        }
                    }
                });
            };
            
            // Start polling after 5 seconds (give webhook time to trigger)
            setTimeout(poll, 5000);
        },
        
        /**
         * Update job status display
         */
        updateJobStatus: function(jobData) {
            var $status = this.modal.find('.smi-processing-status p');
            
            if (jobData.status === 'processing') {
                $status.text('Processing your image... Please wait.');
            } else if (jobData.status === 'pending') {
                $status.text('Initializing processing...');
            }
        },
        
        /**
         * Show job completed state
         */
        showJobCompleted: function(jobData) {
            var completedHtml = '<div class="smi-job-completed smi-status-container">';
            completedHtml += '<div class="smi-status-icon success">🎉</div>';
            completedHtml += '<h3 class="smi-status-title success">Your Image is Ready!</h3>';
            completedHtml += '<p>Your high-resolution image has been processed and sent to your email.</p>';
            
            if (jobData.download_url) {
                completedHtml += '<div class="smi-download-container">';
                completedHtml += '<a href="' + jobData.download_url + '" class="smi-btn smi-btn-primary" target="_blank">Download Now</a>';
                completedHtml += '</div>';
            }
            
            completedHtml += '<p><small>Job ID: ' + jobData.job_id + '</small></p>';
            completedHtml += '</div>';
            
            this.modal.find('.smi-modal-body').html(completedHtml);
        },
        
        /**
         * Show job failed state
         */
        showJobFailed: function(jobData) {
            var failedHtml = '<div class="smi-job-failed smi-status-container">';
            failedHtml += '<div class="smi-status-icon error">❌</div>';
            failedHtml += '<h3 class="smi-status-title error">Processing Failed</h3>';
            failedHtml += '<p>Sorry, there was an error processing your image.</p>';
            failedHtml += '<p>Please contact support for assistance.</p>';
            failedHtml += '<p><small>Job ID: ' + jobData.job_id + '</small></p>';
            failedHtml += '</div>';
            
            this.modal.find('.smi-modal-body').html(failedHtml);
        },
        
        /**
         * Show job timeout state
         */
        showJobTimeout: function() {
            var timeoutHtml = '<div class="smi-job-timeout smi-status-container">';
            timeoutHtml += '<div class="smi-status-icon warning">⏰</div>';
            timeoutHtml += '<h3 class="smi-status-title warning">Processing Taking Longer</h3>';
            timeoutHtml += '<p>Your image is still being processed. You will receive an email when it\'s ready.</p>';
            timeoutHtml += '<p>This can take up to 15 minutes for large images.</p>';
            timeoutHtml += '</div>';
            
            this.modal.find('.smi-modal-body').html(timeoutHtml);
        },
        
        /**
         * Get current post ID
         */
        getPostId: function() {
            // Try to get from WordPress global variables
            if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
                var postId = wp.data.select('core/editor').getCurrentPostId();
                if (postId) return postId;
            }
            
            // Try to get from body class
            var bodyClasses = $('body').attr('class') || '';
            var postIdMatch = bodyClasses.match(/postid-(\d+)/);
            if (postIdMatch) {
                return postIdMatch[1];
            }
            
            // Fallback: look for WordPress post ID in script variables
            if (typeof smi_ajax !== 'undefined' && smi_ajax.post_id) {
                return smi_ajax.post_id;
            }
            
            console.warn('SMI: Could not determine post ID');
            return 0;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SMI.init();
    });
    
    // Expose SMI object globally for debugging
    window.SMI = SMI;
    
})(jQuery);