/**
 * Light Views Counter - Admin JavaScript
 *
 * Handles tab switching, AJAX saving, and UI interactions.
 *
 * @package Light_Views_Counter
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    const LIGHTVCAdmin = {
        /**
         * Flags for lazy loading
         */
        statisticsLoaded: false,

        /**
         * localStorage key for last active tab
         */
        tabStorageKey: 'lightvc_last_active_tab',

        /**
         * Valid tab names whitelist for security
         */
        validTabs: ['settings', 'statistics', 'tools', 'usage'],

        /**
         * Validate tab name against whitelist
         * @param {string} tabName - Tab name to validate
         * @returns {boolean} True if valid
         */
        isValidTab: function (tabName) {
            return typeof tabName === 'string' && this.validTabs.indexOf(tabName) !== -1;
        },

        /**
         * Escape HTML special characters to prevent XSS
         * @param {string} str - String to escape
         * @returns {string} Escaped string
         */
        escapeHtml: function (str) {
            if (typeof str !== 'string') {
                return '';
            }
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        /**
         * Sanitize server-rendered HTML by removing dangerous elements
         * Defense-in-depth for HTML responses from trusted AJAX endpoints
         * @param {string} html - HTML string from server
         * @param {string} containerType - Type of container to use ('div', 'tbody', 'table')
         * @returns {string} Sanitized HTML string
         */
        sanitizeServerHtml: function (html, containerType) {
            if (typeof html !== 'string') {
                return '';
            }

            // Use appropriate container for the HTML type to preserve structure
            // <tr> elements are stripped when placed in a <div>
            var temp;
            containerType = containerType || 'div';

            if (containerType === 'tbody') {
                var table = document.createElement('table');
                temp = document.createElement('tbody');
                table.appendChild(temp);
                temp.innerHTML = html;
            } else if (containerType === 'table') {
                temp = document.createElement('table');
                temp.innerHTML = html;
            } else {
                temp = document.createElement('div');
                temp.innerHTML = html;
            }

            // Remove dangerous elements
            const dangerousTags = ['script', 'iframe', 'object', 'embed', 'form'];
            dangerousTags.forEach(function (tag) {
                const elements = temp.querySelectorAll(tag);
                elements.forEach(function (el) {
                    el.remove();
                });
            });

            // Remove event handlers from all elements
            const allElements = temp.querySelectorAll('*');
            allElements.forEach(function (el) {
                // Remove all on* attributes
                Array.from(el.attributes).forEach(function (attr) {
                    if (attr.name.toLowerCase().startsWith('on')) {
                        el.removeAttribute(attr.name);
                    }
                });
                // Remove javascript: URLs
                if (el.hasAttribute('href') && el.getAttribute('href').toLowerCase().trim().startsWith('javascript:')) {
                    el.removeAttribute('href');
                }
                if (el.hasAttribute('src') && el.getAttribute('src').toLowerCase().trim().startsWith('javascript:')) {
                    el.removeAttribute('src');
                }
            });

            return temp.innerHTML;
        },

        /**
         * Initialize admin functionality
         */
        init: function () {
            this.bindEvents();
            this.initTooltips();
            this.restoreLastTab();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            // Tab switching
            $('.lvc-tab-btn').on('click', this.handleTabClick);

            // Settings changes
            $('.lvc-setting-input').on('change', this.handleSettingChange);
            $('.lvc-toggle-switch input[type="checkbox"]').on('change', this.handleToggleChange);
            $('.lvc-post-type-checkbox').on('change', this.handlePostTypesChange);

            // Tool actions
            $('#lvc-import-pvc-btn').on('click', this.handleImportPVC);
            $('#lvc-reset-import-btn').on('click', this.handleResetImport);

            $('#lvc-clear-cache-btn').on('click', this.handleClearCache);
            $('#lvc-reset-posts-btn').on('click', this.handleResetPosts);
            $('#lvc-reset-all-views-btn').on('click', this.handleResetAllViews);

            // Statistics tab events (delegated for lazy-loaded content)
            $(document).on('click', '.lvc-time-tab', this.handleTimeTabClick);
            $(document).on('change', '#lvc-stats-post-type-filter', this.handleStatsPostTypeChange);
        },

        /**
         * Restore last active tab from localStorage
         */
        restoreLastTab: function () {
            // Check if localStorage is available
            if (typeof (Storage) === 'undefined') {
                return;
            }

            // Get last active tab from localStorage
            const lastTab = localStorage.getItem(this.tabStorageKey);

            // Validate tab name against whitelist before using in selector
            if (lastTab && this.isValidTab(lastTab)) {
                const $tabBtn = $('.lvc-tab-btn[data-tab="' + lastTab + '"]');
                if ($tabBtn.length > 0) {
                    // Remove active from all tabs
                    $('.lvc-tab-btn').removeClass('active');
                    $('.lvc-tab-content').removeClass('active');

                    // Activate the saved tab
                    $tabBtn.addClass('active');
                    $('#lvc-tab-' + lastTab).addClass('active');

                    // Lazy load statistics if needed
                    if (lastTab === 'statistics' && !this.statisticsLoaded) {
                        this.loadStatistics();
                    }
                }
            }
        },

        /**
         * Save active tab to localStorage
         */
        saveLastTab: function (tabName) {
            // Check if localStorage is available and validate tab name
            if (typeof (Storage) !== 'undefined' && this.isValidTab(tabName)) {
                localStorage.setItem(this.tabStorageKey, tabName);
            }
        },

        /**
         * Handle tab click
         */
        handleTabClick: function (e) {
            e.preventDefault();

            const $btn = $(this);
            const tabName = $btn.data('tab');

            // Validate tab name against whitelist before using in selector
            if (!LIGHTVCAdmin.isValidTab(tabName)) {
                return;
            }

            // Update active state
            $('.lvc-tab-btn').removeClass('active');
            $btn.addClass('active');

            // Show corresponding tab content
            $('.lvc-tab-content').removeClass('active');
            $('#lvc-tab-' + tabName).addClass('active');

            // Save to localStorage
            LIGHTVCAdmin.saveLastTab(tabName);

            // Lazy load statistics if needed
            if (tabName === 'statistics' && !LIGHTVCAdmin.statisticsLoaded) {
                LIGHTVCAdmin.loadStatistics();
            }
        },

        /**
         * Load statistics tab content via AJAX
         */
        loadStatistics: function () {
            $.ajax({
                url: lightvcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lightvc_load_statistics',
                    nonce: lightvcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#lvc-tab-statistics').html(LIGHTVCAdmin.sanitizeServerHtml(response.data.html));
                        LIGHTVCAdmin.statisticsLoaded = true;
                        // Load initial popular posts data
                        LIGHTVCAdmin.loadPopularPosts(0, '');
                    } else {
                        var errorMsg = LIGHTVCAdmin.escapeHtml(response.data.message || 'Failed to load statistics.');
                        $('#lvc-tab-statistics').html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                    }
                },
                error: function () {
                    $('#lvc-tab-statistics').html('<div class="notice notice-error"><p>Network error. Please try again.</p></div>');
                }
            });
        },

        /**
         * Handle time tab click in statistics
         */
        handleTimeTabClick: function (e) {
            e.preventDefault();

            const $tab = $(this);
            const dateRange = $tab.data('range');
            const postType = $('#lvc-stats-post-type-filter').val() || '';

            // Update active state
            $('.lvc-time-tab').removeClass('active');
            $tab.addClass('active');

            // Load data for selected time period
            LIGHTVCAdmin.loadPopularPosts(dateRange, postType);
        },

        /**
         * Handle post type filter change in statistics
         */
        handleStatsPostTypeChange: function () {
            const postType = $(this).val() || '';
            const dateRange = $('.lvc-time-tab.active').data('range') || 0;

            LIGHTVCAdmin.loadPopularPosts(dateRange, postType);
        },

        /**
         * Load popular posts via AJAX
         */
        loadPopularPosts: function (dateRange, postType) {
            const $tbody = $('#lvc-popular-table-body');

            // Show loading spinner
            $tbody.html('<tr><td colspan="5" style="text-align: center; padding: 20px;"><span class="spinner is-active" style="float: none; margin: 0;"></span></td></tr>');

            $.ajax({
                url: lightvcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lightvc_get_popular_posts',
                    nonce: lightvcAdmin.nonce,
                    date_range: dateRange,
                    post_type: postType
                },
                success: function (response) {
                    if (response.success) {
                        $tbody.html(LIGHTVCAdmin.sanitizeServerHtml(response.data.html, 'tbody'));
                    } else {
                        var errorMsg = LIGHTVCAdmin.escapeHtml(response.data.message || 'Failed to load data.');
                        $tbody.html('<tr><td colspan="5" style="text-align: center; padding: 20px; color: #dc2626;">' + errorMsg + '</td></tr>');
                    }
                },
                error: function () {
                    $tbody.html('<tr><td colspan="5" style="text-align: center; padding: 20px; color: #dc2626;">Network error. Please try again.</td></tr>');
                }
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function () {
            $('.lvc-info-icon').hover(
                function () {
                    const tooltip = $(this).find('.lvc-tooltip');
                    tooltip.stop(true, true).fadeIn(200);
                },
                function () {
                    const tooltip = $(this).find('.lvc-tooltip');
                    tooltip.stop(true, true).fadeOut(200);
                }
            );
        },

        /**
         * Handle setting input change
         */
        handleSettingChange: function (e) {
            const $input = $(this);
            const settingName = $input.attr('name');
            if (!settingName) {
                return;
            }
            const settingValue = $input.val();
            const $row = $input.closest('.lvc-setting-row');

            LIGHTVCAdmin.saveSetting(settingName, settingValue, $row);
        },

        /**
         * Handle toggle switch change
         */
        handleToggleChange: function (e) {
            const $checkbox = $(this);
            const settingName = $checkbox.attr('name');
            const settingValue = $checkbox.is(':checked') ? 1 : 0;
            const $row = $checkbox.closest('.lvc-setting-row');

            LIGHTVCAdmin.saveSetting(settingName, settingValue, $row);
        },

        /**
         * Handle post types checkbox change
         */
        handlePostTypesChange: function () {
            const $checkbox = $(this);
            const $label = $checkbox.closest('.lvc-post-type-tag');

            // Toggle active class
            if ($checkbox.is(':checked')) {
                $label.addClass('active');
            } else {
                $label.removeClass('active');
            }

            // Collect all selected types
            const $container = $('.lvc-post-types-container');
            const selectedTypes = [];

            $container.find('input[type="checkbox"]:checked').each(function () {
                selectedTypes.push($(this).val());
            });

            // Ensure at least 'post' is selected
            const finalTypes = selectedTypes.length > 0 ? selectedTypes : ['post'];
            const $row = $container.closest('.lvc-setting-row');

            LIGHTVCAdmin.savePostTypes(finalTypes, $row);
        },

        /**
         * Save setting via AJAX
         */
        saveSetting: function (name, value, $row) {
            // Show saving indicator
            this.showSavingIndicator($row);

            $.ajax({
                url: lightvcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lightvc_save_setting',
                    nonce: lightvcAdmin.nonce,
                    setting_name: name,
                    setting_value: value
                },
                success: function (response) {
                    if (response.success) {
                        LIGHTVCAdmin.showSuccessIndicator($row);
                    } else {
                        LIGHTVCAdmin.showErrorIndicator($row, response.data.message || 'Error saving setting');
                    }
                },
                error: function (xhr, status, error) {
                    LIGHTVCAdmin.showErrorIndicator($row, 'Network error. Please try again.');
                }
            });
        },

        /**
         * Save post types setting via AJAX
         */
        savePostTypes: function (value, $row) {
            this.showSavingIndicator($row);

            // Send array as 'lightvc_supported_post_types[]' format for proper PHP handling
            const postData = {
                action: 'lightvc_save_post_types',
                nonce: lightvcAdmin.nonce
            };

            // Add each post type as array element
            value.forEach(function (postType, index) {
                postData['post_types[' + index + ']'] = postType;
            });

            $.ajax({
                url: lightvcAdmin.ajaxUrl,
                type: 'POST',
                data: postData,
                success: function (response) {
                    if (response.success) {
                        LIGHTVCAdmin.showSuccessIndicator($row);
                    } else {
                        LIGHTVCAdmin.showErrorIndicator($row, response.data.message || 'Error saving setting');
                    }
                },
                error: function () {
                    LIGHTVCAdmin.showErrorIndicator($row, 'Network error. Please try again.');
                }
            });
        },

        /**
         * Show saving indicator
         */
        showSavingIndicator: function ($row) {
            this.showToast('saving', 'Saving...');
        },

        /**
         * Show success indicator
         */
        showSuccessIndicator: function ($row) {
            this.removeToast('saving');
            this.showToast('success', 'Setting saved successfully!');
        },

        /**
         * Show error indicator
         */
        showErrorIndicator: function ($row, message) {
            this.removeToast('saving');
            this.showToast('error', message);
        },

        /**
         * Show toast notification
         */
        showToast: function (type, message) {
            // Remove existing toasts of the same type
            $('.lvc-toast[data-type="' + type + '"]').remove();

            // Escape message to prevent XSS
            var safeMessage = this.escapeHtml(message);
            var safeType = this.escapeHtml(type);

            // Create toast element
            const iconClass = safeType === 'success' ? 'dashicons-yes' :
                safeType === 'error' ? 'dashicons-no' :
                    'dashicons-update';

            const $toast = $('<div class="lvc-toast lvc-toast-' + safeType + '" data-type="' + safeType + '">' +
                '<span class="dashicons ' + iconClass + (safeType === 'saving' ? ' spin' : '') + '"></span>' +
                '<span class="lvc-toast-message">' + safeMessage + '</span>' +
                '</div>');

            // Add to container (create if doesn't exist)
            if ($('.lvc-toast-container').length === 0) {
                $('body').append('<div class="lvc-toast-container"></div>');
            }
            $('.lvc-toast-container').append($toast);

            // Animate in
            setTimeout(function () {
                $toast.addClass('lvc-toast-show');
            }, 10);

            // Auto-remove (except for saving state)
            if (safeType !== 'saving') {
                setTimeout(function () {
                    $toast.removeClass('lvc-toast-show');
                    setTimeout(function () {
                        $toast.remove();
                    }, 300);
                }, 3000);
            }
        },

        /**
         * Remove toast notification
         */
        removeToast: function (type) {
            const $toast = $('.lvc-toast[data-type="' + type + '"]');
            $toast.removeClass('lvc-toast-show');
            setTimeout(function () {
                $toast.remove();
            }, 300);
        },


        /**
         * Handle Post Views Counter import action (batch processing)
         */
        handleImportPVC: function (e) {
            e.preventDefault();

            if (!confirm('Migrate all view counts from Post Views Counter plugin?\n\nThis will:\n• Read all view counts directly from Post Views Counter database\n• Import them into Light Views Counter\nProceed with migration?')) {
                return;
            }

            // Initialize import
            LIGHTVCAdmin.importOffset = 0;
            LIGHTVCAdmin.importInProgress = true;

            // Show progress bar
            $('#lvc-import-progress').show();
            $('#lvc-progress-fill').css('width', '0%');
            $('#lvc-progress-text').text('0%');
            $('#lvc-import-status').text('Initializing migration...');

            // Disable import button
            const $btn = $('#lvc-import-pvc-btn');
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update spin"></span> Migrating data...');

            // Start batch import
            LIGHTVCAdmin.runImportBatch(0);
        },

        /**
         * Run a single batch import
         */
        runImportBatch: function (offset) {
            $.ajax({
                url: lightvcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lightvc_import_from_pvc',
                    nonce: lightvcAdmin.nonce,
                    action_type: offset === 0 ? 'start' : 'continue',
                    offset: offset
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;

                        if (data.complete) {
                            // Import completed
                            LIGHTVCAdmin.importInProgress = false;

                            // Update progress to 100%
                            $('#lvc-progress-fill').css('width', '100%');
                            $('#lvc-progress-text').text('100%');
                            $('#lvc-import-status').html('<strong style="color: #16a34a;">✓ Migration completed!</strong> ' + LIGHTVCAdmin.escapeHtml(data.message));

                            // Show success message
                            LIGHTVCAdmin.showToast('success', data.message);

                            // Re-enable button and update text
                            $('#lvc-import-pvc-btn')
                                .prop('disabled', false)
                                .html('<span class="dashicons dashicons-download"></span> Migrate Data from Post View Counter Plugin');

                            // Show detailed results after 1 second
                            setTimeout(function () {
                                alert('Migration Complete!\n\n' +
                                    'Successfully imported: ' + data.imported + ' posts\n' +
                                    'Total posts: ' + data.total + '\n' +
                                    'Failed: ' + data.failed);
                            }, 1000);

                            // Reload page after 3 seconds to refresh UI state
                            setTimeout(function () {
                                location.reload();
                            }, 3000);

                        } else {
                            // Update progress
                            const progress = data.progress || 0;
                            $('#lvc-progress-fill').css('width', progress + '%');
                            $('#lvc-progress-text').text(progress + '%');
                            $('#lvc-import-status').text(data.message);

                            // Continue with next batch
                            if (LIGHTVCAdmin.importInProgress) {
                                setTimeout(function () {
                                    LIGHTVCAdmin.runImportBatch(data.offset);
                                }, 100); // Small delay between batches
                            }
                        }
                    } else {
                        // Error occurred
                        LIGHTVCAdmin.importInProgress = false;
                        $('#lvc-import-status').html('<strong style="color: #dc2626;">✗ Error:</strong> ' + LIGHTVCAdmin.escapeHtml(response.data.message || 'Migration failed'));
                        LIGHTVCAdmin.showToast('error', response.data.message || 'Migration failed');

                        // Re-enable button
                        $('#lvc-import-pvc-btn')
                            .prop('disabled', false)
                            .html('<span class="dashicons dashicons-download"></span> Resume Migration');
                    }
                },
                error: function (xhr, status, error) {
                    // Network error
                    LIGHTVCAdmin.importInProgress = false;
                    $('#lvc-import-status').html('<strong style="color: #dc2626;">✗ Network error.</strong> You can resume the migration by clicking the button again.');
                    LIGHTVCAdmin.showToast('error', 'Network error. Migration paused. Click the button to resume.');

                    // Re-enable button for retry
                    $('#lvc-import-pvc-btn')
                        .prop('disabled', false)
                        .html('<span class="dashicons dashicons-download"></span> Resume Migration');
                }
            });
        },

        /**
         * Handle reset import state
         */
        handleResetImport: function (e) {
            e.preventDefault();

            if (!confirm('Reset the migration state?\n\nThis will clear the migration progress and allow you to start fresh.\n\nProceed?')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update spin"></span> Resetting...');

            $.ajax({
                url: lightvcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lightvc_import_from_pvc',
                    nonce: lightvcAdmin.nonce,
                    action_type: 'reset'
                },
                success: function (response) {
                    if (response.success) {
                        LIGHTVCAdmin.showToast('success', response.data.message || 'Migration state reset successfully!');

                        // Reload page after 1 second
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        LIGHTVCAdmin.showToast('error', response.data.message || 'Failed to reset migration state.');
                        $btn.prop('disabled', false);
                        $btn.html('<span class="dashicons dashicons-update"></span> Reset Migration');
                    }
                },
                error: function () {
                    LIGHTVCAdmin.showToast('error', 'Network error. Please try again.');
                    $btn.prop('disabled', false);
                    $btn.html('<span class="dashicons dashicons-update"></span> Reset Migration');
                }
            });
        },

        /**
         * Handle clear cache action
         */
        handleClearCache: function (e) {
            e.preventDefault();

            if (!confirm('Clear all cached view counts?\n\nThis will remove cached data and force fresh database queries. Proceed?')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).addClass('loading');
            $btn.html('<span class="dashicons dashicons-update spin"></span> Clearing...');

            $.ajax({
                url: lightvcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lightvc_clear_cache',
                    nonce: lightvcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        LIGHTVCAdmin.showToast('success', response.data.message || 'Cache cleared successfully!');
                    } else {
                        LIGHTVCAdmin.showToast('error', response.data.message || 'Failed to clear cache.');
                    }
                },
                error: function () {
                    LIGHTVCAdmin.showToast('error', 'Network error. Please try again.');
                },
                complete: function () {
                    $btn.prop('disabled', false).removeClass('loading');
                    $btn.html('<span class="dashicons dashicons-trash"></span> Clear Cache');
                }
            });
        },

        /**
         * Handle reset specific posts
         */
        handleResetPosts: function (e) {
            e.preventDefault();

            const postIds = $('#lvc-reset-posts-input').val().trim();

            if (!postIds) {
                LIGHTVCAdmin.showToast('error', 'Please enter post IDs.');
                return;
            }

            if (!confirm('⚠ Are you sure you want to reset view counts for these posts? This action cannot be undone!')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).addClass('loading');
            $btn.html('<span class="dashicons dashicons-update spin"></span> Resetting...');

            $.ajax({
                url: lightvcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lightvc_reset_posts',
                    nonce: lightvcAdmin.nonce,
                    post_ids: postIds
                },
                success: function (response) {
                    if (response.success) {
                        LIGHTVCAdmin.showToast('success', response.data.message || 'Posts reset successfully!');
                        $('#lvc-reset-posts-input').val(''); // Clear input
                    } else {
                        LIGHTVCAdmin.showToast('error', response.data.message || 'Failed to reset posts.');
                    }
                },
                error: function () {
                    LIGHTVCAdmin.showToast('error', 'Network error. Please try again.');
                },
                complete: function () {
                    $btn.prop('disabled', false).removeClass('loading');
                    $btn.html('<span class="dashicons dashicons-warning"></span> Reset Selected Posts');
                }
            });
        },

        /**
         * Handle reset all views action
         */
        handleResetAllViews: function (e) {
            e.preventDefault();

            if (!confirm('⚠ Are you sure you want to reset ALL view counts? This action cannot be undone!')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).addClass('loading');
            $btn.html('<span class="dashicons dashicons-update spin"></span> Resetting...');

            $.ajax({
                url: lightvcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'lightvc_reset_all_views',
                    nonce: lightvcAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        LIGHTVCAdmin.showToast('success', response.data.message || 'All view counts have been reset.');
                        // Reload stats after 1 second
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        LIGHTVCAdmin.showToast('error', response.data.message || 'Failed to reset views.');
                    }
                },
                error: function () {
                    LIGHTVCAdmin.showToast('error', 'Network error. Please try again.');
                },
                complete: function () {
                    $btn.prop('disabled', false).removeClass('loading');
                    $btn.html('<span class="dashicons dashicons-warning"></span> Reset All Views');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        LIGHTVCAdmin.init();
    });

})(jQuery);
