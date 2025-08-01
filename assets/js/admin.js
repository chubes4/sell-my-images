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
            // No admin events to bind currently
        },
        
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SMI_Admin.init();
    });
    
})(jQuery);