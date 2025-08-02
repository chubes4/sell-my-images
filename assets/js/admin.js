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
            // Display mode toggle for button filtering
            this.bindDisplayModeToggle();
        },
        
        /**
         * Bind display mode toggle functionality
         */
        bindDisplayModeToggle: function() {
            var $displayModeRadios = $('input[name="smi_display_mode"]');
            var $filterTable = $('#smi-filter-criteria-table');
            
            if ($displayModeRadios.length === 0 || $filterTable.length === 0) {
                return; // Not on the settings page with filtering
            }
            
            // Handle radio button changes
            $displayModeRadios.on('change', function() {
                var selectedMode = $('input[name="smi_display_mode"]:checked').val();
                
                if (selectedMode === 'all') {
                    // Hide filter criteria table
                    $filterTable.slideUp(300);
                } else {
                    // Show filter criteria table
                    $filterTable.slideDown(300);
                }
            });
            
            // Set initial state on page load
            var initialMode = $('input[name="smi_display_mode"]:checked').val();
            if (initialMode === 'all') {
                $filterTable.hide();
            } else {
                $filterTable.show();
            }
        },
        
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SMI_Admin.init();
    });
    
})(jQuery);