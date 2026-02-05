/**
 * Frontend script for Image Uploader block
 */

( function() {
    'use strict';

    // Find all uploader instances on the page
    const uploaders = document.querySelectorAll( '.smi-image-uploader' );

    uploaders.forEach( function( uploader ) {
        initUploader( uploader );
    } );

    function initUploader( container ) {
        // Elements
        const dropzone = container.querySelector( '#smi-dropzone' );
        const fileInput = container.querySelector( '#smi-file-input' );
        const browseButton = container.querySelector( '#smi-browse-button' );
        const uploadZone = container.querySelector( '#smi-upload-zone' );
        const previewZone = container.querySelector( '#smi-preview-zone' );
        const previewImage = container.querySelector( '#smi-preview-image' );
        const removeButton = container.querySelector( '#smi-remove-image' );
        const dimensionsEl = container.querySelector( '#smi-image-dimensions' );
        const resolutionPicker = container.querySelector( '#smi-resolution-picker' );
        const emailSection = container.querySelector( '#smi-email-section' );
        const emailInput = container.querySelector( '#smi-email-input' );
        const checkoutSection = container.querySelector( '#smi-checkout-section' );
        const checkoutButton = container.querySelector( '#smi-checkout-button' );
        const loadingEl = container.querySelector( '#smi-loading' );
        const loadingText = container.querySelector( '#smi-loading-text' );
        const errorEl = container.querySelector( '#smi-error' );
        const errorText = container.querySelector( '#smi-error-text' );

        // State
        let uploadId = null;
        let pricing = null;
        const maxFileSize = parseInt( container.dataset.maxFileSize || 10, 10 ) * 1024 * 1024;

        // Event Listeners
        browseButton.addEventListener( 'click', function() {
            fileInput.click();
        } );

        fileInput.addEventListener( 'change', function( e ) {
            if ( e.target.files.length > 0 ) {
                handleFile( e.target.files[0] );
            }
        } );

        // Drag and drop
        dropzone.addEventListener( 'dragover', function( e ) {
            e.preventDefault();
            dropzone.classList.add( 'smi-dragover' );
        } );

        dropzone.addEventListener( 'dragleave', function( e ) {
            e.preventDefault();
            dropzone.classList.remove( 'smi-dragover' );
        } );

        dropzone.addEventListener( 'drop', function( e ) {
            e.preventDefault();
            dropzone.classList.remove( 'smi-dragover' );
            if ( e.dataTransfer.files.length > 0 ) {
                handleFile( e.dataTransfer.files[0] );
            }
        } );

        removeButton.addEventListener( 'click', resetUploader );

        checkoutButton.addEventListener( 'click', handleCheckout );

        // Resolution change
        container.querySelectorAll( 'input[name="smi-resolution"]' ).forEach( function( radio ) {
            radio.addEventListener( 'change', updateSelectedPrice );
        } );

        function handleFile( file ) {
            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if ( ! validTypes.includes( file.type ) ) {
                showError( 'Please upload a JPEG, PNG, or WebP image.' );
                return;
            }

            // Validate file size
            if ( file.size > maxFileSize ) {
                showError( 'File size exceeds ' + ( maxFileSize / 1024 / 1024 ) + 'MB limit.' );
                return;
            }

            // Show preview immediately
            const reader = new FileReader();
            reader.onload = function( e ) {
                previewImage.src = e.target.result;
            };
            reader.readAsDataURL( file );

            // Upload to server
            uploadFile( file );
        }

        function uploadFile( file ) {
            showLoading( 'Uploading...' );
            hideError();

            const formData = new FormData();
            formData.append( 'image', file );

            fetch( '/wp-json/smi/v1/upload-image', {
                method: 'POST',
                body: formData,
            } )
            .then( function( response ) {
                return response.json();
            } )
            .then( function( data ) {
                hideLoading();

                if ( data.success ) {
                    uploadId = data.upload_id;
                    pricing = data.pricing;
                    showPreview( data.image_info );
                } else {
                    showError( data.message || 'Upload failed. Please try again.' );
                    resetUploader();
                }
            } )
            .catch( function( error ) {
                hideLoading();
                showError( 'Upload failed. Please try again.' );
                resetUploader();
            } );
        }

        function showPreview( imageInfo ) {
            uploadZone.style.display = 'none';
            previewZone.style.display = 'block';
            
            dimensionsEl.textContent = imageInfo.width + ' × ' + imageInfo.height + ' px';

            // Update pricing display
            if ( pricing ) {
                updatePricingDisplay( imageInfo );
            }

            resolutionPicker.style.display = 'block';
            emailSection.style.display = 'block';
            checkoutSection.style.display = 'block';
        }

        function updatePricingDisplay( imageInfo ) {
            const output4x = container.querySelector( '#smi-output-4x' );
            const output8x = container.querySelector( '#smi-output-8x' );
            const price4x = container.querySelector( '#smi-price-4x' );
            const price8x = container.querySelector( '#smi-price-8x' );

            if ( pricing['4x'] ) {
                output4x.textContent = pricing['4x'].output_width + ' × ' + pricing['4x'].output_height + ' px';
                price4x.textContent = '$' + pricing['4x'].price.toFixed( 2 );
            }

            if ( pricing['8x'] ) {
                output8x.textContent = pricing['8x'].output_width + ' × ' + pricing['8x'].output_height + ' px';
                price8x.textContent = '$' + pricing['8x'].price.toFixed( 2 );
            }
        }

        function updateSelectedPrice() {
            const selected = container.querySelector( 'input[name="smi-resolution"]:checked' ).value;
            const price = pricing && pricing[selected] ? pricing[selected].price : 0;
            checkoutButton.textContent = 'Checkout - $' + price.toFixed( 2 );
        }

        function handleCheckout() {
            if ( ! uploadId ) {
                showError( 'Please upload an image first.' );
                return;
            }

            const resolution = container.querySelector( 'input[name="smi-resolution"]:checked' ).value;
            const email = emailInput.value.trim();

            showLoading( 'Creating checkout...' );
            hideError();

            const body = {
                upload_id: uploadId,
                resolution: resolution,
            };

            if ( email ) {
                body.email = email;
            }

            fetch( '/wp-json/smi/v1/create-checkout-upload', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify( body ),
            } )
            .then( function( response ) {
                return response.json();
            } )
            .then( function( data ) {
                hideLoading();

                if ( data.success && data.checkout_url ) {
                    // Redirect to Stripe
                    window.location.href = data.checkout_url;
                } else {
                    showError( data.message || 'Checkout failed. Please try again.' );
                }
            } )
            .catch( function( error ) {
                hideLoading();
                showError( 'Checkout failed. Please try again.' );
            } );
        }

        function resetUploader() {
            uploadId = null;
            pricing = null;
            fileInput.value = '';
            previewImage.src = '';
            emailInput.value = '';

            uploadZone.style.display = 'block';
            previewZone.style.display = 'none';
            resolutionPicker.style.display = 'none';
            emailSection.style.display = 'none';
            checkoutSection.style.display = 'none';

            // Reset to 4x
            container.querySelector( 'input[name="smi-resolution"][value="4x"]' ).checked = true;
            checkoutButton.textContent = 'Proceed to Checkout';

            hideError();
            hideLoading();
        }

        function showLoading( text ) {
            loadingText.textContent = text;
            loadingEl.style.display = 'flex';
        }

        function hideLoading() {
            loadingEl.style.display = 'none';
        }

        function showError( message ) {
            errorText.textContent = message;
            errorEl.style.display = 'block';
        }

        function hideError() {
            errorEl.style.display = 'none';
        }

        // Initialize button text
        updateSelectedPrice();
    }
} )();
