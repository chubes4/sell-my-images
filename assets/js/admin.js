/**
 * Admin Settings Page JavaScript
 * 
 * Handles admin functionality for the Sell My Images plugin
 */
(function($) {
    'use strict';
    
    var SMI_Admin = {
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $('#smi-test-api-key').on('click', this.testApiKey);
        },
        
        /**
         * Test API key functionality
         */
        testApiKey: function(e) {
            e.preventDefault();
            
            var button = $(this);
            var apiKey = $('#smi_upsampler_api_key').val();
            var resultDiv = $('#smi-api-test-result');
            
            if (!apiKey) {
                resultDiv.html('<span style="color: #d63638;">Please enter an API key first.</span>');
                return;
            }
            
            button.prop('disabled', true).text('Testing...');
            resultDiv.html('');
            
            $.post(ajaxurl, {
                action: 'smi_test_api_key',
                api_key: apiKey,
                nonce: smi_admin.nonce
            }, function(response) {
                if (response.success) {
                    resultDiv.html('<span style="color: #00a32a;">✓ ' + response.data + '</span>');
                } else {
                    resultDiv.html('<span style="color: #d63638;">✗ ' + response.data + '</span>');
                }
            }).fail(function() {
                resultDiv.html('<span style="color: #d63638;">✗ Connection failed</span>');
            }).always(function() {
                button.prop('disabled', false).text('Test API Key');
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SMI_Admin.init();
    });
    
})(jQuery);