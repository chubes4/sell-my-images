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
            // Tab navigation
            this.bindTabNavigation();
            
            // Display mode toggle for button filtering
            this.bindDisplayModeToggle();
        },
        
        /**
         * Bind tab navigation functionality
         */
        bindTabNavigation: function() {
            var $tabLinks = $('.smi-tab-link');
            var $tabPanels = $('.smi-tab-panel');
            
            if ($tabLinks.length === 0) {
                return; // Not on a tabbed settings page
            }
            
            // Handle tab clicks
            $tabLinks.on('click', function(e) {
                e.preventDefault();
                
                var targetTab = $(this).data('tab');
                var $targetPanel = $('#smi-tab-' + targetTab);
                
                if ($targetPanel.length === 0) {
                    return;
                }
                
                // Remove active state from all tabs and panels
                $tabLinks.removeClass('smi-tab-active');
                $tabPanels.removeClass('smi-tab-active');
                
                // Add active state to clicked tab and corresponding panel
                $(this).addClass('smi-tab-active');
                $targetPanel.addClass('smi-tab-active');
                
                // Update URL hash without scrolling
                if (history.pushState) {
                    history.pushState(null, null, '#' + targetTab);
                } else {
                    window.location.hash = targetTab;
                }
            });
            
            // Handle initial tab state from URL hash
            var hash = window.location.hash.substring(1);
            if (hash && $('#smi-tab-' + hash).length > 0) {
                $tabLinks.removeClass('smi-tab-active');
                $tabPanels.removeClass('smi-tab-active');
                
                $('[data-tab="' + hash + '"]').addClass('smi-tab-active');
                $('#smi-tab-' + hash).addClass('smi-tab-active');
            }
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
                    // Disable filter criteria table
                    $filterTable.addClass('smi-disabled');
                } else {
                    // Enable filter criteria table
                    $filterTable.removeClass('smi-disabled');
                }
            });
            
            // Set initial state on page load is handled by PHP class assignment
        },
        
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SMI_Admin.init();
    });
    
})(jQuery);